<?php

use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Internal\Pasak\Models\PasakTrustedIp;
use Internal\Pasak\Models\TrustedIp;
use Internal\Pasak\Models\UserLogin;
use Internal\Pasak\Services\PasakService;
use Internal\Pasak\Services\ServerSecurityService;
use Internal\Pasak\Services\UserLoginService;

test('login event records a user login session', function () {
    $user = $this->createRegularUser();

    $before = UserLogin::query()->count();

    event(new Login('web', $user, false));

    expect(UserLogin::query()->count())->toBe($before + 1);
});

test('logoutSession marks the matching session as logged out', function () {
    $user = $this->createRegularUser();

    $login = UserLogin::create([
        'user_id' => $user->id,
        'session_id' => 'session-abc-123',
        'ip_address' => '127.0.0.1',
        'device_type' => 'desktop',
        'operating_system' => 'Linux',
        'browser' => 'Pest',
        'login_at' => now(),
        'last_activity_at' => now(),
    ]);

    expect($login->logout_at)->toBeNull();

    $affected = app(UserLoginService::class)->logoutSession('session-abc-123');

    expect($affected)
        ->toBe(1)
        ->and($login->fresh()->logout_at)
        ->not->toBeNull();
});

test('trustIp stores a trusted ip for the user', function () {
    $user = $this->createRegularUser();

    $trusted = app(UserLoginService::class)->trustIp(
        $user,
        '127.0.0.1',
        'Perangkat Uji',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    );

    expect($trusted)
        ->toBeInstanceOf(TrustedIp::class)
        ->and($trusted->ip_address)
        ->toBe('127.0.0.1')
        ->and((int) $trusted->user_id)
        ->toBe((int) $user->id);
});

test('trustIp handles long user-agent and stores parsed OS and browser safely', function () {
    $user = $this->createRegularUser();
    $rawUserAgent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36';

    $trusted = app(UserLoginService::class)->trustIp(
        user: $user,
        ip: '158.140.185.154',
        deviceName: null,
        userAgent: $rawUserAgent,
        localIp: '192.168.1.100',
        deviceId: 'dev_2f925986fc4979a2',
    );

    expect($trusted)
        ->toBeInstanceOf(TrustedIp::class)
        ->and($trusted->ip_address)->toBe('158.140.185.154')
        ->and($trusted->operating_system)->toBe('Linux')
        ->and($trusted->browser)->toContain('Google Chrome');

    // Mutator protection when raw user agent is assigned directly to operating_system attribute
    $trustedDirect = PasakTrustedIp::query()->create([
        'user_id' => $user->id,
        'ip_address' => '158.140.185.155',
        'operating_system' => $rawUserAgent,
    ]);

    expect($trustedDirect->operating_system)->toBe('Linux');
});

test('admin login automatically lifts an ip block', function () {
    $this->app->instance(
        'request',
        Request::create(
            '/',
            'GET',
            [],
            [],
            [],
            ['REMOTE_ADDR' => '203.0.113.99'],
        ),
    );

    $admin = $this->createAdminUser();
    $security = app(PasakService::class);

    $security->block('203.0.113.99', [
        'reason' => 'uji regresi',
        'duration_hours' => 1,
    ]);
    expect($security->activeBlock('203.0.113.99'))->not->toBeNull();

    event(new Login('web', $admin, false));

    expect($security->activeBlock('203.0.113.99'))->toBeNull();
});

test('server scan runs when admin accounts exist', function () {
    $this->createAdminUser();

    $scan = app(ServerSecurityService::class)->scan(force: true);

    expect($scan)
        ->toBeArray()
        ->toHaveKeys(['summary', 'categories']);
});

test('application audit marks a strong public api key and trusted proxies as ok', function () {
    config()->set('pasak.server_scan.public_api_key', 'long-random-secret-value');
    config()->set('pasak.server_scan.trusted_proxies', '10.0.0.1');

    $checks = collect(app(ServerSecurityService::class)->scan(force: true)['categories'])
        ->flatMap(fn (array $category): array => $category['checks'])
        ->keyBy('id');

    expect($checks['public_api_key']['status'])->toBe('ok')
        ->and($checks['trusted_proxies']['status'])->toBe('ok')
        ->and($checks['public_api_key']['value'])->toBe('long-random-secret-value');
});

test('application audit warns when public api key and trusted proxies are empty', function () {
    config()->set('pasak.server_scan.public_api_key', '');
    config()->set('pasak.server_scan.trusted_proxies', '');

    $checks = collect(app(ServerSecurityService::class)->scan(force: true)['categories'])
        ->flatMap(fn (array $category): array => $category['checks'])
        ->keyBy('id');

    expect($checks['public_api_key']['status'])->toBe('warning')
        ->and($checks['trusted_proxies']['status'])->toBe('warning');
});
