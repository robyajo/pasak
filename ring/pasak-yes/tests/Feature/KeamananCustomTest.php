<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Internal\Pasak\Facades\Pasak;
use Internal\Pasak\Models\PasakBlockedIp;
use Internal\Pasak\Models\PasakLog;

uses(RefreshDatabase::class);

test('keamanan page requires authentication', function () {
    $response = $this->get('/keamanan');

    $response->assertRedirect('/login');
});

test('authenticated user can view custom keamanan dashboard with pasak stats', function () {
    $user = User::factory()->create();

    // Buat dummy log via Pasak
    PasakLog::create([
        'ip_address' => '203.0.113.10',
        'event_type' => 'sqli',
        'threat_level' => 'critical',
        'method' => 'GET',
        'path' => '/posts?id=1%20UNION%20SELECT',
        'rule_label' => 'SQL Injection Probe',
        'was_blocked' => true,
    ]);

    $response = $this->actingAs($user)->get('/keamanan');

    $response->assertOk();
    $response->assertSee('Pusat Keamanan Kustom');
    $response->assertSee('WAF Engine: Aktif');
    $response->assertSee('203.0.113.10');
    $response->assertSee('SQL Injection Probe');
    $response->assertSee('403 Blocked');
});

test('user can block an ip manually via custom keamanan controller', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/keamanan/block', [
        'ip' => '198.51.100.99',
        'reason' => 'Percobaan serangan brute force kustom',
        'hours' => 24,
    ]);

    $response->assertRedirect('/keamanan');
    $response->assertSessionHas('status', 'IP 198.51.100.99 berhasil diblokir.');

    expect(Pasak::isBlocked('198.51.100.99'))->toBeTrue();

    $blocked = PasakBlockedIp::where('ip_address', '198.51.100.99')->first();
    expect($blocked)->not->toBeNull()
        ->and($blocked->reason)->toBe('Percobaan serangan brute force kustom')
        ->and($blocked->is_active)->toBeTrue();
});

test('user can unblock an ip via custom keamanan controller', function () {
    $user = User::factory()->create();

    Pasak::blockIp('198.51.100.88', 'Untuk diuji buka blokir');
    expect(Pasak::isBlocked('198.51.100.88'))->toBeTrue();

    $response = $this->actingAs($user)->post('/keamanan/unblock/198.51.100.88');

    $response->assertRedirect('/keamanan');
    $response->assertSessionHas('status', 'Blokir untuk IP 198.51.100.88 berhasil dibuka.');

    expect(Pasak::isBlocked('198.51.100.88'))->toBeFalse();
});

test('user can clear logs via custom keamanan controller', function () {
    $user = User::factory()->create();

    PasakLog::create([
        'ip_address' => '10.0.0.1',
        'event_type' => 'test',
        'threat_level' => 'low',
        'path' => '/test',
    ]);

    expect(PasakLog::count())->toBe(1);

    $response = $this->actingAs($user)->delete('/keamanan/logs');

    $response->assertRedirect('/keamanan');
    expect(PasakLog::count())->toBe(0);
});
