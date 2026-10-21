<?php

use Internal\Pasak\Database\Seeders\PasakDefaultSettingsSeeder;
use Internal\Pasak\Models\BlockedIp;
use Internal\Pasak\Models\PasakSetting;
use Internal\Pasak\Models\SecurityLog;
use Internal\Pasak\Services\PasakLoginThrottleService;
use Internal\Pasak\Services\PasakService;

beforeEach(function () {
    config()->set('pasak.enabled', true);
    config()->set('pasak.block_enforcement', true);
    config()->set('pasak.auto_block.enabled', true);
    config()->set('pasak.auto_block.scope', 'ip');
    config()->set('pasak.auto_block.window_minutes', 10);
    config()->set('pasak.auto_block.combined_threshold', 3);
    config()->set('pasak.auto_block.level_thresholds', ['critical' => 1, 'high' => 2, 'medium' => 3, 'low' => 5]);
});

function autoBlockLog(string $ip, string $level, ?string $deviceId = null, ?string $localIp = null): void
{
    SecurityLog::create([
        'ip_address' => $ip,
        'device_id' => $deviceId,
        'local_ip' => $localIp,
        'event_type' => 'threat_detected',
        'threat_level' => $level,
        'path' => '/?q=suspicious-'.uniqid(),
    ]);
}

test('critical single event trips the automatic block at its own per-level threshold', function () {
    $service = app(PasakService::class);
    $ip = '203.0.113.41';

    autoBlockLog($ip, 'high');

    expect($service->autoBlockIfNeeded($ip))->toBeNull()
        ->and(BlockedIp::query()->where('ip_address', $ip)->exists())->toBeFalse();

    autoBlockLog($ip, 'critical');

    expect($service->autoBlockIfNeeded($ip))->not->toBeNull();

    $block = BlockedIp::query()->where('ip_address', $ip)->first();

    expect($block->block_scope)->toBe('ip')
        ->and($block->source)->toBe('automatic');
});

test('mixed levels below per-level caps trip the combined fallback threshold', function () {
    $service = app(PasakService::class);
    $ip = '203.0.113.42';

    $service->updateSettings(['auto_block_levels' => ['high', 'medium']]);

    autoBlockLog($ip, 'high');
    autoBlockLog($ip, 'medium');

    expect($service->autoBlockIfNeeded($ip))->toBeNull();

    autoBlockLog($ip, 'medium');

    // 1x high + 2x medium = 3 campuran, tapi masing-masing level belum
    // mencapai ambangnya sendiri (high < 2, medium < 3).
    expect($service->autoBlockIfNeeded($ip))->not->toBeNull();
});

test('thresholds above a level guard against blocking until satisfied', function () {
    $service = app(PasakService::class);
    $ip = '203.0.113.43';

    $service->updateSettings(['auto_block_levels' => ['low']]);

    foreach (range(1, 4) as $i) {
        autoBlockLog($ip, 'low');
    }

    expect($service->autoBlockIfNeeded($ip))->toBeNull();

    autoBlockLog($ip, 'low');

    expect($service->autoBlockIfNeeded($ip))->not->toBeNull();
});

test('levels outside the monitored set never trip the block', function () {
    $service = app(PasakService::class);
    $ip = '203.0.113.44';

    $service->updateSettings(['auto_block_levels' => ['critical']]);

    foreach (range(1, 6) as $i) {
        autoBlockLog($ip, 'low');
    }

    expect($service->autoBlockIfNeeded($ip))->toBeNull();
});

test('adaptive scope falls back to whole ip block when no device fingerprint exists', function () {
    $service = app(PasakService::class);
    $ip = '203.0.113.45';

    $service->updateSettings(['auto_block_scope' => 'auto']);
    expect($service->getSetting('auto_block.scope'))->toBe('auto');

    autoBlockLog($ip, 'critical');

    $block = $service->autoBlockIfNeeded($ip);

    expect($block)->not->toBeNull()
        ->and($block->block_scope)->toBe('ip');
});

test('adaptive scope keeps device isolation when a fingerprint exists', function () {
    $service = app(PasakService::class);
    $ip = '203.0.113.46';
    $deviceId = 'dev-suspect-01';
    $localIp = '192.168.50.9';

    $service->updateSettings(['auto_block_scope' => 'auto']);

    autoBlockLog($ip, 'critical', $deviceId, $localIp);

    $block = $service->autoBlockIfNeeded($ip, $deviceId, $localIp);

    expect($block)->not->toBeNull()
        ->and($block->block_scope)->toBe('device')
        ->and($block->device_id)->toBe($deviceId)
        ->and($block->local_ip)->toBe($localIp);
});

test('level_thresholds round-trips through updateSettings and getSettings', function () {
    $service = app(PasakService::class);

    $service->updateSettings([
        'auto_block_level_thresholds' => ['critical' => 1, 'high' => 4, 'medium' => 6, 'low' => 9],
        'auto_block_combined_threshold' => 4,
        'log_retention_days' => 45,
        'login_lockout_threshold' => 5,
        'login_lockout_base_minutes' => 2,
    ]);

    expect($service->getSettings()['auto_block_level_thresholds'])->toBe([
        'critical' => 1, 'high' => 4, 'medium' => 6, 'low' => 9,
    ])
        ->and($service->getSettings()['auto_block_combined_threshold'])->toBe(4)
        ->and($service->getSettings()['log_retention_days'])->toBe(45)
        ->and($service->getSettings()['login_lockout_threshold'])->toBe(5)
        ->and($service->getSetting('login_lockout.base_minutes'))->toBe(2);

    // Nilai di luar 1..100 dipotong, level tak dikenal diabaikan.
    $service->updateSettings([
        'auto_block_level_thresholds' => ['critical' => 500, 'bogus' => 7],
    ]);

    expect($service->autoBlockLevelThresholds()['critical'])->toBe(100);
});

test('default settings seeder inserts all built-in keys without overwriting custom values', function () {
    $service = app(PasakService::class);

    $service->updateSettings(['log_retention_days' => 33, 'auto_block_scope' => 'auto']);

    $created = app(PasakDefaultSettingsSeeder::class)->run();

    // Pola bawaan konsisten: kunci pengaturan "nama-kunci" rapi & tanpa dobel.
    $allKeys = PasakSetting::query()->whereIn('key', $created)->count();
    expect($allKeys)->toBe(count($created));

    // Kustomisasi tidak ditimpa.
    expect($service->getSettings()['log_retention_days'])->toBe(33)
        ->and($service->getSettings()['auto_block_scope'])->toBe('auto');

    // Menjalankan lagi bersifat idempotent.
    expect(app(PasakDefaultSettingsSeeder::class)->run())->toBe([]);
});

test('lockout tuning is read from the database-backed settings', function () {
    $service = app(PasakService::class);

    $service->updateSettings(['login_lockout_base_minutes' => 7, 'login_lockout_max_minutes' => 45]);

    $throttle = app(PasakLoginThrottleService::class);
    expect($throttle->minutesForLevel(1))->toBe(7);
    expect($throttle->minutesForLevel(99))->toBe(45);
});
