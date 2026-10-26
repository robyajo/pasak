<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Internal\Pasak\Facades\Pasak;
use Internal\Pasak\Models\PasakLog;

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
