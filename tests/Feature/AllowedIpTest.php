<?php

use Internal\Pasak\Models\AllowedIp;
use Internal\Pasak\Services\PasakService;
use Internal\Pasak\Tests\TestUser;

beforeEach(function () {
    config()->set('pasak.enabled', true);
    config()->set('pasak.block_enforcement', true);
});

test('pattern validation accepts ip wildcard and cidr', function () {
    expect(AllowedIp::isValidPattern('203.0.113.10'))->toBeTrue()
        ->and(AllowedIp::isValidPattern('192.168.1.*'))->toBeTrue()
        ->and(AllowedIp::isValidPattern('10.0.0.0/8'))->toBeTrue()
        ->and(AllowedIp::isValidPattern('::1'))->toBeTrue()
        ->and(AllowedIp::isValidPattern('not-an-ip'))->toBeFalse()
        ->and(AllowedIp::isValidPattern(''))->toBeFalse()
        ->and(AllowedIp::isValidPattern('10.0.0.0/99'))->toBeFalse();
});

test('allowed entries bypass threat inspection', function () {
    $service = app(PasakService::class);
    $ip = '198.51.100.77';

    expect($service->isWhitelisted($ip))->toBeFalse();

    AllowedIp::create([
        'ip_address' => '198.51.100.0/24',
        'label' => 'Office range',
        'is_active' => true,
    ]);

    expect($service->isWhitelisted($ip))->toBeTrue();
    expect($service->isWhitelisted('203.0.113.9'))->toBeFalse();
});

test('disabled or expired entries do not whitelist', function () {
    $service = app(PasakService::class);

    AllowedIp::create(['ip_address' => '198.51.100.78', 'is_active' => false]);
    AllowedIp::create([
        'ip_address' => '198.51.100.79',
        'is_active' => true,
        'expires_at' => now()->subHour(),
    ]);

    expect($service->isWhitelisted('198.51.100.78'))->toBeFalse()
        ->and($service->isWhitelisted('198.51.100.79'))->toBeFalse();
});

test('admin can manage allowed ips via api', function () {
    $admin = TestUser::create([
        'name' => 'Admin User',
        'email' => 'admin-allowed@example.com',
        'password' => bcrypt('password'),
        'role' => 'admin',
    ]);

    // Invalid pattern rejected.
    $this->actingAs($admin)->postJson('/api/pasak/allowed-ips', [
        'ip_address' => 'not-an-ip',
    ])->assertStatus(422);

    // Store CIDR + wildcard.
    $this->actingAs($admin)->postJson('/api/pasak/allowed-ips', [
        'ip_address' => '10.20.0.0/16',
        'label' => 'Branch office',
    ])->assertCreated()->assertJsonPath('success', true);

    $this->actingAs($admin)->postJson('/api/pasak/allowed-ips', [
        'ip_address' => '192.168.5.*',
    ])->assertCreated();

    expect(AllowedIp::count())->toBe(2);

    // Index + show.
    $this->actingAs($admin)->getJson('/api/pasak/allowed-ips')
        ->assertOk()->assertJsonPath('success', true);

    $entry = AllowedIp::where('ip_address', '10.20.0.0/16')->firstOrFail();
    $this->actingAs($admin)->getJson("/api/pasak/allowed-ips/{$entry->id}")
        ->assertOk()->assertJsonPath('data.ip_address', '10.20.0.0/16');

    // Toggle off → IP no longer whitelisted.
    $this->actingAs($admin)->patchJson("/api/pasak/allowed-ips/{$entry->id}/toggle")
        ->assertOk()->assertJsonPath('data.is_active', false);
    expect(app(PasakService::class)->isWhitelisted('10.20.99.1'))->toBeFalse();

    // Toggle on → whitelisted again.
    $this->actingAs($admin)->patchJson("/api/pasak/allowed-ips/{$entry->id}/toggle")
        ->assertOk()->assertJsonPath('data.is_active', true);
    expect(app(PasakService::class)->isWhitelisted('10.20.99.1'))->toBeTrue();

    // Destroy.
    $this->actingAs($admin)->deleteJson("/api/pasak/allowed-ips/{$entry->id}")->assertOk();
    expect(AllowedIp::where('ip_address', '10.20.0.0/16')->exists())->toBeFalse();

    // Missing id → 404.
    $this->actingAs($admin)->getJson('/api/pasak/allowed-ips/999999')->assertNotFound();
});

test('regular users cannot access allowed ips api', function () {
    $user = TestUser::create([
        'name' => 'Regular User',
        'email' => 'user-allowed@example.com',
        'password' => bcrypt('password'),
        'role' => 'user',
    ]);

    $this->actingAs($user)->getJson('/api/pasak/allowed-ips')->assertForbidden();
    $this->actingAs($user)->postJson('/api/pasak/allowed-ips', [
        'ip_address' => '203.0.113.1',
    ])->assertForbidden();
});
