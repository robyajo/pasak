<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Internal\Pasak\Concerns\HasPasakSecurityRelations;
use Internal\Pasak\Concerns\HasSecurityRelations;
use Internal\Pasak\Models\BlockedIp;
use Internal\Pasak\Models\PasakBlockedIp;
use Internal\Pasak\Models\PasakLog;
use Internal\Pasak\Models\PasakSetting;
use Internal\Pasak\Models\SecurityLog;

uses(RefreshDatabase::class);

test('user model uses HasPasakSecurityRelations and works with relationships', function () {
    $traits = class_uses_recursive(User::class);

    expect(in_array(HasPasakSecurityRelations::class, $traits, true))->toBeTrue();

    $user = User::factory()->create([
        'name' => 'Security Admin',
        'email' => 'admin@example.com',
    ]);

    expect($user->logins())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class)
        ->and($user->trustedIps())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class)
        ->and($user->pasakLogs())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class)
        ->and($user->securityLogs())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class)
        ->and($user->blockedIps())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('models resolve to pasak_ prefixed tables and legacy models alias transparently', function () {
    $blocked = PasakBlockedIp::create([
        'ip_address' => '203.0.113.99',
        'reason' => 'Suspicious test attack',
        'is_active' => true,
    ]);

    expect($blocked->getTable())->toBe('pasak_blocked_ips')
        ->and(PasakBlockedIp::where('ip_address', '203.0.113.99')->exists())->toBeTrue()
        ->and(BlockedIp::where('ip_address', '203.0.113.99')->exists())->toBeTrue();

    $log = PasakLog::create([
        'ip_address' => '203.0.113.99',
        'event_type' => 'threat_detected',
        'threat_level' => 'high',
        'rule_label' => 'SSTI probe',
    ]);

    expect($log->getTable())->toBe('pasak_logs')
        ->and(PasakLog::count())->toBe(1)
        ->and(SecurityLog::count())->toBe(1);
});

test('blocked ip receives 403 response through global WAF middleware', function () {
    PasakBlockedIp::create([
        'ip_address' => '198.51.100.77',
        'reason' => 'Test block',
        'is_active' => true,
    ]);

    $response = $this->withServerVariables([
        'REMOTE_ADDR' => '198.51.100.77',
    ])->get('/');

    $response->assertStatus(403);
});
