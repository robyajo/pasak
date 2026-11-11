<?php

use Illuminate\Support\Facades\Http;
use Internal\Pasak\Services\PasakCloudService;

beforeEach(function () {
    config()->set('app.name', 'Laravel');
    config()->set('app.url', 'http://localhost:8000');
    config()->set('pasak.cloud.enabled', true);
    config()->set('pasak.cloud.key', 'psk_live_test1234567890abcdef');
    config()->set('pasak.cloud.url', 'https://mock-cloud.pasak.test');
});

test('cloud service extracts metadata from APP_NAME and APP_URL', function () {
    $cloud = app(PasakCloudService::class);
    $meta = $cloud->getAppMetadata();

    expect($meta['name'])->toBe('Laravel')
        ->and($meta['url'])->toBe('http://localhost:8000')
        ->and($meta['host'])->toBe('localhost')
        ->and($meta['is_https'])->toBeFalse();
});

test('cloud service validates isConfigured based on key format', function () {
    $cloud = app(PasakCloudService::class);
    expect($cloud->isConfigured())->toBeTrue();
    expect($cloud->getMaskedKey())->toContain('psk_live');

    config()->set('pasak.cloud.key', null);
    $emptyCloud = new PasakCloudService;
    expect($emptyCloud->isConfigured())->toBeFalse();
});

test('cloud service can send heartbeat and report threat event', function () {
    Http::fake([
        'https://mock-cloud.pasak.test/api/v1/nodes/telemetry' => Http::response([
            'status' => 'acknowledged',
            'node_id' => 'nod_mock_123',
        ], 200),
        'https://mock-cloud.pasak.test/api/v1/events/threat' => Http::response([
            'status' => 'success',
            'event_id' => 'evt_123',
        ], 200),
        'https://mock-cloud.pasak.test/api/v1/threats/global-blocklist' => Http::response([
            'ips' => ['198.51.100.99', '203.0.113.88'],
        ], 200),
    ]);

    $cloud = app(PasakCloudService::class);

    $hb = $cloud->sendHeartbeat();
    expect($hb['success'])->toBeTrue()
        ->and($hb['app_name'])->toBe('Laravel')
        ->and($hb['app_url'])->toBe('http://localhost:8000');

    $reported = $cloud->reportThreat([
        'event_type' => 'sql_injection',
        'ip_address' => '198.51.100.42',
        'threat_level' => 'critical',
    ]);
    expect($reported)->toBeTrue();

    $blocklist = $cloud->fetchGlobalBlocklist();
    expect($blocklist)->toHaveCount(2)
        ->and($blocklist)->toContain('198.51.100.99');
});
