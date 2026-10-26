<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Internal\Pasak\Facades\Pasak;
use Internal\Pasak\Models\PasakAllowedIp;
use Internal\Pasak\Models\PasakIpUnblockRequest;
use Internal\Pasak\Models\PasakLog;
use Internal\Pasak\Models\PasakTrustedIp;

uses(RefreshDatabase::class);

test('api keamanan index returns stats and waf status in json', function () {
    $response = $this->getJson('/api/keamanan');

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'waf' => [
                    'enabled',
                    'block_enforcement',
                ],
                'stats' => [
                    'total_logs',
                    'logs_today',
                    'active_blocks',
                ],
                'level_breakdown',
                'top_attackers',
            ],
        ])
        ->assertJson([
            'success' => true,
            'data' => [
                'waf' => [
                    'enabled' => true,
                ],
            ],
        ]);
});

test('api keamanan logs returns paginated logs with filter', function () {
    PasakLog::create([
        'ip_address' => '1.2.3.4',
        'event_type' => 'xss',
        'threat_level' => 'high',
        'path' => '/search?q=<script>alert(1)</script>',
        'rule_label' => 'Cross-Site Scripting Probe',
    ]);

    PasakLog::create([
        'ip_address' => '5.6.7.8',
        'event_type' => 'sqli',
        'threat_level' => 'critical',
        'path' => '/login?user=admin\'--',
        'rule_label' => 'SQLi Probe',
    ]);

    $response = $this->getJson('/api/keamanan/logs?level=critical');

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $data = $response->json('data.data');
    expect(count($data))->toBe(1)
        ->and($data[0]['threat_level'])->toBe('critical')
        ->and($data[0]['ip_address'])->toBe('5.6.7.8');
});

test('api keamanan can block and unblock ip via json', function () {
    $blockResponse = $this->postJson('/api/keamanan/block', [
        'ip' => '203.0.113.55',
        'reason' => 'Suspicious bot activity detected by API client',
        'hours' => 12,
    ]);

    $blockResponse->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'IP 203.0.113.55 berhasil diblokir.',
        ]);

    expect(Pasak::isBlocked('203.0.113.55'))->toBeTrue();

    // Check IP status endpoint
    $checkResponse = $this->getJson('/api/keamanan/check/203.0.113.55');
    $checkResponse->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'ip_address' => '203.0.113.55',
                'is_blocked' => true,
            ],
        ]);

    // List blocked IPs
    $listResponse = $this->getJson('/api/keamanan/blocked-ips');
    $listResponse->assertOk()
        ->assertJsonPath('count', 1);

    // Unblock endpoint
    $unblockResponse = $this->postJson('/api/keamanan/unblock/203.0.113.55');
    $unblockResponse->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Blokir untuk IP 203.0.113.55 berhasil dibuka.',
            'data' => [
                'is_blocked' => false,
            ],
        ]);

    expect(Pasak::isBlocked('203.0.113.55'))->toBeFalse();
});

test('api keamanan can clear all logs', function () {
    PasakLog::create([
        'ip_address' => '10.0.0.99',
        'event_type' => 'probe',
        'threat_level' => 'low',
        'path' => '/robots.txt',
    ]);

    expect(PasakLog::count())->toBe(1);

    $response = $this->deleteJson('/api/keamanan/logs');

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Semua riwayat log ancaman WAF berhasil dibersihkan.',
        ]);

    expect(PasakLog::count())->toBe(0);
});

test('api keamanan handles allowed ips crud', function () {
    // Add allowed IP
    $postResponse = $this->postJson('/api/keamanan/allowed-ips', [
        'ip_address' => '172.16.0.10',
        'label' => 'Server Webhook API',
    ]);
    $postResponse->assertStatus(201)
        ->assertJson(['success' => true]);

    $item = PasakAllowedIp::where('ip_address', '172.16.0.10')->first();
    expect($item)->not->toBeNull();

    // List allowed IPs
    $listResponse = $this->getJson('/api/keamanan/allowed-ips');
    $listResponse->assertOk()
        ->assertJsonPath('count', 1);

    // Delete allowed IP
    $delResponse = $this->deleteJson("/api/keamanan/allowed-ips/{$item->id}");
    $delResponse->assertOk()
        ->assertJson(['success' => true]);
    expect(PasakAllowedIp::where('ip_address', '172.16.0.10')->exists())->toBeFalse();
});

test('api keamanan returns server audit report and manages baseline', function () {
    $reportResponse = $this->getJson('/api/keamanan/server');
    $reportResponse->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'report' => ['summary', 'categories'],
                'baseline',
            ],
        ]);

    $baselineResponse = $this->postJson('/api/keamanan/server/baseline');
    $baselineResponse->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'data' => ['total_files'],
        ]);
});

test('api keamanan handles sessions and trusted ips', function () {
    $user = User::factory()->create();

    $sessionsResponse = $this->getJson('/api/keamanan/sessions');
    $sessionsResponse->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => ['sessions', 'trusted_ips'],
        ]);

    $trustedResponse = $this->actingAs($user)->postJson('/api/keamanan/trusted-ips/my-ip');
    $trustedResponse->assertStatus(201)
        ->assertJson(['success' => true]);

    $trusted = PasakTrustedIp::latest('id')->first();
    expect($trusted)->not->toBeNull();

    $delResponse = $this->actingAs($user)->deleteJson("/api/keamanan/trusted-ips/{$trusted->id}");
    $delResponse->assertOk();
});

test('api keamanan handles tickets and resolves them', function () {
    Pasak::blockIp('198.51.100.33', 'Blokir untuk tiket api');
    expect(Pasak::isBlocked('198.51.100.33'))->toBeTrue();

    $ticket = PasakIpUnblockRequest::create([
        'ticket_number' => 'TCK-API-001',
        'ip_address' => '198.51.100.33',
        'name' => 'API Client',
        'email' => 'api@example.com',
        'reason' => 'Permohonan via API',
        'status' => 'pending',
    ]);

    $listResponse = $this->getJson('/api/keamanan/tickets');
    $listResponse->assertOk()
        ->assertJsonPath('data.total', 1);

    $respondResponse = $this->postJson("/api/keamanan/tickets/{$ticket->id}/respond", [
        'status' => 'approved',
        'admin_notes' => 'Disetujui dari API REST kustom',
    ]);
    $respondResponse->assertOk()
        ->assertJson(['success' => true]);

    expect(Pasak::isBlocked('198.51.100.33'))->toBeFalse();
});

test('api keamanan gets and updates runtime settings', function () {
    $getResponse = $this->getJson('/api/keamanan/settings');
    $getResponse->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'auto_block_threshold',
                'auto_block_duration',
                'block_enforcement',
            ],
        ]);

    $putResponse = $this->putJson('/api/keamanan/settings', [
        'auto_block_threshold' => 9,
        'auto_block_duration' => 72,
    ]);
    $putResponse->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $settings = Pasak::getSettings();
    expect($settings['auto_block_threshold'])->toBe(9)
        ->and($settings['auto_block_duration'])->toBe(72);
});
