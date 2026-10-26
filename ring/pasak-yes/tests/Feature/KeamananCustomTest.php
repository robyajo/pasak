<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Internal\Pasak\Facades\Pasak;
use Internal\Pasak\Models\PasakAllowedIp;
use Internal\Pasak\Models\PasakBlockedIp;
use Internal\Pasak\Models\PasakIpUnblockRequest;
use Internal\Pasak\Models\PasakLog;
use Internal\Pasak\Models\PasakTrustedIp;

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
    $response->assertSee('Ringkasan Keamanan Sistem');
    $response->assertSee('WAF Engine: Aktif');
    $response->assertSee('203.0.113.10');
    $response->assertSee('SQL Injection Probe');
});

test('authenticated user can access all 8 modular custom views', function () {
    $user = User::factory()->create();

    $routes = [
        '/keamanan',
        '/keamanan/logs',
        '/keamanan/blocked-ips',
        '/keamanan/allowed-ips',
        '/keamanan/server',
        '/keamanan/sessions',
        '/keamanan/tickets',
        '/keamanan/settings',
    ];

    foreach ($routes as $route) {
        $response = $this->actingAs($user)->get($route);
        $response->assertOk();
    }
});

test('user can block an ip manually via custom keamanan controller', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/keamanan/block', [
        'ip' => '198.51.100.99',
        'reason' => 'Percobaan serangan brute force kustom',
        'hours' => 24,
    ]);

    $response->assertRedirect();
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

    $response->assertRedirect();
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

    $response->assertRedirect();
    expect(PasakLog::count())->toBe(0);
});

test('user can add, toggle, and delete allowed ip', function () {
    $user = User::factory()->create();

    // Tambah Allowed IP
    $postResponse = $this->actingAs($user)->post('/keamanan/allowed-ips', [
        'ip_address' => '192.168.1.100',
        'label' => 'Kantor Pengembang',
        'notes' => 'IP jaringan internal',
    ]);
    $postResponse->assertRedirect();

    $item = PasakAllowedIp::where('ip_address', '192.168.1.100')->first();
    expect($item)->not->toBeNull()
        ->and($item->is_active)->toBeTrue();

    // Toggle status
    $patchResponse = $this->actingAs($user)->patch("/keamanan/allowed-ips/{$item->id}/toggle");
    $patchResponse->assertRedirect();
    expect($item->fresh()->is_active)->toBeFalse();

    // Hapus
    $deleteResponse = $this->actingAs($user)->delete("/keamanan/allowed-ips/{$item->id}");
    $deleteResponse->assertRedirect();
    expect(PasakAllowedIp::where('ip_address', '192.168.1.100')->exists())->toBeFalse();
});

test('user can add and delete trusted ip', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/keamanan/trusted-ips/my-ip');
    $response->assertRedirect();

    $trusted = PasakTrustedIp::where('user_id', $user->id)->first();
    expect($trusted)->not->toBeNull();

    $delResponse = $this->actingAs($user)->delete("/keamanan/trusted-ips/{$trusted->id}");
    $delResponse->assertRedirect();
    expect(PasakTrustedIp::where('id', $trusted->id)->exists())->toBeFalse();
});

test('user can respond to appeal ticket and unblock requester automatically', function () {
    $user = User::factory()->create();

    Pasak::blockIp('198.51.100.77', 'Blokir untuk tiket');
    expect(Pasak::isBlocked('198.51.100.77'))->toBeTrue();

    $ticket = PasakIpUnblockRequest::create([
        'ticket_number' => 'TCK-TEST-001',
        'ip_address' => '198.51.100.77',
        'name' => 'Budi Pengembang',
        'email' => 'budi@example.com',
        'reason' => 'Saya tidak sengaja menekan refresh berulang kali',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user)->post("/keamanan/tickets/{$ticket->id}/respond", [
        'status' => 'approved',
        'admin_notes' => 'Disetujui via pengujian custom controller',
    ]);

    $response->assertRedirect();
    expect($ticket->fresh()->status)->toBe('approved');
    expect(Pasak::isBlocked('198.51.100.77'))->toBeFalse();
});

test('user can update runtime settings via custom controller', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/keamanan/settings', [
        'auto_block_scope' => 'ip',
        'auto_block_enabled' => 1,
        'auto_block_threshold' => 7,
        'auto_block_duration' => 48,
        'instant_block_enabled' => 1,
        'block_enforcement' => 1,
    ]);

    $response->assertRedirect();

    $settings = Pasak::getSettings();
    expect($settings['auto_block_threshold'])->toBe(7)
        ->and($settings['auto_block_duration'])->toBe(48);
});
