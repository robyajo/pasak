<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Internal\Pasak\Concerns\ResolvesPasakTable;
use Internal\Pasak\Models\AllowedIp;
use Internal\Pasak\Models\BlockedIp;
use Internal\Pasak\Models\IpUnblockRequest;
use Internal\Pasak\Models\LoginAttempt;
use Internal\Pasak\Models\PasakAllowedIp;
use Internal\Pasak\Models\PasakBlockedIp;
use Internal\Pasak\Models\PasakIpUnblockRequest;
use Internal\Pasak\Models\PasakLog;
use Internal\Pasak\Models\PasakLoginAttempt;
use Internal\Pasak\Models\PasakSetting;
use Internal\Pasak\Models\PasakTrustedIp;
use Internal\Pasak\Models\PasakUserLogin;
use Internal\Pasak\Models\SecurityLog;
use Internal\Pasak\Models\SecuritySetting;
use Internal\Pasak\Models\TrustedIp;
use Internal\Pasak\Models\UserLogin;

afterEach(function () {
    ResolvesPasakTable::clearResolvedTableCache();
});

test('all pasak models have pasak_ prefix by default', function () {
    ResolvesPasakTable::clearResolvedTableCache();

    expect((new PasakBlockedIp)->getTable())->toBe('pasak_blocked_ips')
        ->and((new PasakLog)->getTable())->toBe('pasak_logs')
        ->and((new PasakLoginAttempt)->getTable())->toBe('pasak_login_attempts')
        ->and((new PasakIpUnblockRequest)->getTable())->toBe('pasak_ip_unblock_requests')
        ->and((new PasakUserLogin)->getTable())->toBe('pasak_user_logins')
        ->and((new PasakTrustedIp)->getTable())->toBe('pasak_trusted_ips')
        ->and((new PasakAllowedIp)->getTable())->toBe('pasak_allowed_ips')
        ->and((new PasakSetting)->getTable())->toBe('pasak_settings');
});

test('legacy model classes are transparent aliases of the prefixed models', function () {
    expect(class_exists(BlockedIp::class))->toBeTrue()
        ->and(class_exists(SecurityLog::class))->toBeTrue()
        ->and(class_exists(LoginAttempt::class))->toBeTrue()
        ->and(class_exists(IpUnblockRequest::class))->toBeTrue()
        ->and(class_exists(UserLogin::class))->toBeTrue()
        ->and(class_exists(TrustedIp::class))->toBeTrue()
        ->and(class_exists(AllowedIp::class))->toBeTrue()
        ->and(class_exists(SecuritySetting::class))->toBeTrue();

    $blocked = new BlockedIp(['ip_address' => '1.2.3.4']);
    expect($blocked)->toBeInstanceOf(PasakBlockedIp::class)
        ->and($blocked)->toBeInstanceOf(BlockedIp::class)
        ->and($blocked->getTable())->toBe('pasak_blocked_ips');

    $log = new SecurityLog(['ip_address' => '1.2.3.4', 'threat_level' => 'high']);
    expect($log)->toBeInstanceOf(PasakLog::class)
        ->and($log)->toBeInstanceOf(SecurityLog::class)
        ->and($log->getTable())->toBe('pasak_logs');
});

test('user relations support both legacy and new relation names', function () {
    $user = $this->createRegularUser();

    expect($user->securityLogs())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class)
        ->and($user->pasakLogs())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class)
        ->and($user->blockedIps())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class)
        ->and($user->logins())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class)
        ->and($user->trustedIps())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class)
        ->and($user->allowedIps())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class)
        ->and($user->resolvedTickets())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('resolvesPasakTable transparently falls back to legacy table when prefixed table is not yet migrated', function () {
    ResolvesPasakTable::clearResolvedTableCache();

    // Temporarily simulate pre-migration state where only legacy 'blocked_ips' exists
    Schema::rename('pasak_blocked_ips', 'blocked_ips');

    try {
        ResolvesPasakTable::clearResolvedTableCache();
        $model = new PasakBlockedIp;
        expect($model->getTable())->toBe('blocked_ips');

        // Querying should work on legacy table without throwing table not found error
        BlockedIp::create(['ip_address' => '10.0.0.99', 'source' => 'manual', 'is_active' => true]);
        expect(DB::table('blocked_ips')->where('ip_address', '10.0.0.99')->exists())->toBeTrue()
            ->and(BlockedIp::where('ip_address', '10.0.0.99')->exists())->toBeTrue();
    } finally {
        if (Schema::hasTable('blocked_ips') && ! Schema::hasTable('pasak_blocked_ips')) {
            Schema::rename('blocked_ips', 'pasak_blocked_ips');
        }
        ResolvesPasakTable::clearResolvedTableCache();
    }
});

test('resolvesPasakTable resolves to prefixed table even if user config still has legacy table name', function () {
    ResolvesPasakTable::clearResolvedTableCache();
    config()->set('pasak.table_names.blocked_ips', 'blocked_ips');

    // DB has pasak_blocked_ips, but config says blocked_ips
    $model = new PasakBlockedIp;
    expect($model->getTable())->toBe('pasak_blocked_ips');
});

test('resolvesPasakTable strictly respects custom user-defined table names', function () {
    ResolvesPasakTable::clearResolvedTableCache();
    config()->set('pasak.table_names.blocked_ips', 'my_custom_firewall_table');

    $model = new PasakBlockedIp;
    expect($model->getTable())->toBe('my_custom_firewall_table');
});

test('prefix migration safely migrates existing legacy tables preserving data', function () {
    // 1. Setup legacy tables with dummy records
    Schema::disableForeignKeyConstraints();
    foreach ([
        'pasak_blocked_ips' => 'blocked_ips',
        'pasak_login_attempts' => 'login_attempts',
        'pasak_ip_unblock_requests' => 'ip_unblock_requests',
        'pasak_user_logins' => 'user_logins',
        'pasak_trusted_ips' => 'trusted_ips',
        'pasak_allowed_ips' => 'allowed_ips',
    ] as $new => $old) {
        if (Schema::hasTable($new) && ! Schema::hasTable($old)) {
            Schema::rename($new, $old);
        }
    }
    Schema::enableForeignKeyConstraints();

    DB::table('blocked_ips')->insert([
        'ip_address' => '192.168.100.50',
        'source' => 'manual',
        'is_active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 2. Run the prefix migration
    $migration = require __DIR__.'/../../database/migrations/2026_10_21_000001_prefix_pasak_tables.php';
    $migration->up();

    // 3. Verify tables were renamed to pasak_* and data is preserved
    expect(Schema::hasTable('pasak_blocked_ips'))->toBeTrue()
        ->and(Schema::hasTable('blocked_ips'))->toBeFalse()
        ->and(Schema::hasTable('pasak_login_attempts'))->toBeTrue()
        ->and(Schema::hasTable('login_attempts'))->toBeFalse()
        ->and(Schema::hasTable('pasak_ip_unblock_requests'))->toBeTrue()
        ->and(Schema::hasTable('ip_unblock_requests'))->toBeFalse()
        ->and(Schema::hasTable('pasak_user_logins'))->toBeTrue()
        ->and(Schema::hasTable('user_logins'))->toBeFalse()
        ->and(Schema::hasTable('pasak_trusted_ips'))->toBeTrue()
        ->and(Schema::hasTable('trusted_ips'))->toBeFalse()
        ->and(Schema::hasTable('pasak_allowed_ips'))->toBeTrue()
        ->and(Schema::hasTable('allowed_ips'))->toBeFalse();

    $preserved = DB::table('pasak_blocked_ips')->where('ip_address', '192.168.100.50')->first();
    expect($preserved)->not->toBeNull()
        ->and($preserved->ip_address)->toBe('192.168.100.50');

    // 4. Test rollback (down)
    $migration->down();
    expect(Schema::hasTable('blocked_ips'))->toBeTrue()
        ->and(Schema::hasTable('pasak_blocked_ips'))->toBeFalse();

    // Clean up back to normal state
    $migration->up();
    expect(Schema::hasTable('pasak_blocked_ips'))->toBeTrue();
});

test('upgrade command migrates legacy table_names in config/pasak.php', function () {
    $tempDir = sys_get_temp_dir().'/pasak_test_upgrade_'.uniqid();
    File::makeDirectory($tempDir.'/config', 0755, true);
    $this->app->setBasePath($tempDir);

    $oldConfig = <<<'PHP'
<?php

return [
    'table_names' => [
        'users' => env('PASAK_USERS_TABLE', env('SECURITY_USERS_TABLE', 'users')),
        'blocked_ips' => env('PASAK_BLOCKED_IPS_TABLE', env('SECURITY_BLOCKED_IPS_TABLE', 'blocked_ips')),
        'pasak_logs' => env('PASAK_LOGS_TABLE', env('SECURITY_LOGS_TABLE', 'pasak_logs')),
        'login_attempts' => env('PASAK_LOGIN_ATTEMPTS_TABLE', env('SECURITY_LOGIN_ATTEMPTS_TABLE', 'login_attempts')),
        'ip_unblock_requests' => env('PASAK_IP_UNBLOCK_REQUESTS_TABLE', env('SECURITY_IP_UNBLOCK_REQUESTS_TABLE', 'ip_unblock_requests')),
        'user_logins' => env('PASAK_USER_LOGINS_TABLE', env('SECURITY_USER_LOGINS_TABLE', 'user_logins')),
        'trusted_ips' => env('PASAK_TRUSTED_IPS_TABLE', env('SECURITY_TRUSTED_IPS_TABLE', 'trusted_ips')),
        'allowed_ips' => env('PASAK_ALLOWED_IPS_TABLE', 'allowed_ips'),
        'settings' => env('PASAK_SETTINGS_TABLE', 'pasak_settings'),
    ],
];
PHP;
    File::put($tempDir.'/config/pasak.php', $oldConfig);

    $this->artisan('pasak:upgrade', [
        '--force' => true,
        '--no-composer' => true,
        '--no-migrate' => true,
    ])->assertSuccessful();

    $migrated = File::get($tempDir.'/config/pasak.php');
    expect($migrated)->toContain("'blocked_ips' => env('PASAK_BLOCKED_IPS_TABLE', env('SECURITY_BLOCKED_IPS_TABLE', 'pasak_blocked_ips'))")
        ->and($migrated)->toContain("'login_attempts' => env('PASAK_LOGIN_ATTEMPTS_TABLE', env('SECURITY_LOGIN_ATTEMPTS_TABLE', 'pasak_login_attempts'))")
        ->and($migrated)->toContain("'ip_unblock_requests' => env('PASAK_IP_UNBLOCK_REQUESTS_TABLE', env('SECURITY_IP_UNBLOCK_REQUESTS_TABLE', 'pasak_ip_unblock_requests'))")
        ->and($migrated)->toContain("'user_logins' => env('PASAK_USER_LOGINS_TABLE', env('SECURITY_USER_LOGINS_TABLE', 'pasak_user_logins'))")
        ->and($migrated)->toContain("'trusted_ips' => env('PASAK_TRUSTED_IPS_TABLE', env('SECURITY_TRUSTED_IPS_TABLE', 'pasak_trusted_ips'))")
        ->and($migrated)->toContain("'allowed_ips' => env('PASAK_ALLOWED_IPS_TABLE', 'pasak_allowed_ips')");

    File::deleteDirectory($tempDir);
});

test('all pasak middleware, listeners, rules, services, commands, controllers are prefixed with Pasak', function () {
    // Middleware
    expect(class_exists(\Internal\Pasak\Http\Middleware\PasakBlockIpAddress::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Http\Middleware\PasakDetectSecurityThreats::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Http\Middleware\PasakEnsureSecurityAdmin::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Http\Middleware\PasakInjectAnnouncementBanner::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Http\Middleware\PasakTrackUserActivity::class))->toBeTrue();

    // Middleware legacy aliases
    expect(class_exists(\Internal\Pasak\Http\Middleware\BlockIpAddress::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Http\Middleware\DetectSecurityThreats::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Http\Middleware\EnsureSecurityAdmin::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Http\Middleware\InjectAnnouncementBanner::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Http\Middleware\TrackUserActivity::class))->toBeTrue();

    // Listeners
    expect(class_exists(\Internal\Pasak\Listeners\PasakLogFailedLoginAttempt::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Listeners\PasakRecordUserLogin::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Listeners\PasakResetLoginAttempts::class))->toBeTrue();

    // Listeners legacy aliases
    expect(class_exists(\Internal\Pasak\Listeners\LogFailedLoginAttempt::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Listeners\RecordUserLogin::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Listeners\ResetLoginAttempts::class))->toBeTrue();

    // Rules
    expect(class_exists(\Internal\Pasak\Rules\PasakSafeAssetPath::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Rules\PasakSafeImageFile::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Rules\SafeAssetPath::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Rules\SafeImageFile::class))->toBeTrue();

    // Services
    expect(class_exists(\Internal\Pasak\Services\PasakService::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Services\PasakAccessLogScannerService::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Services\PasakLoginThrottleService::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Services\PasakServerSecurityService::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Services\PasakUserLoginService::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Services\PasakVersionCheckService::class))->toBeTrue();

    // Services legacy aliases
    expect(class_exists(\Internal\Pasak\Services\AccessLogScannerService::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Services\LoginThrottleService::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Services\ServerSecurityService::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Services\UserLoginService::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Services\VersionCheckService::class))->toBeTrue();

    // Support
    expect(class_exists(\Internal\Pasak\Support\PasakDashboardView::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Support\PasakTableResolver::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Support\DashboardView::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Support\TableResolver::class))->toBeTrue();

    // Concerns / Traits
    expect(trait_exists(\Internal\Pasak\Concerns\HasPasakSecurityRelations::class))->toBeTrue()
        ->and(trait_exists(\Internal\Pasak\Concerns\HasSecurityRelations::class))->toBeTrue()
        ->and(trait_exists(\Internal\Pasak\Http\Controllers\Dashboard\RespondsWithPasakDashboard::class))->toBeTrue()
        ->and(trait_exists(\Internal\Pasak\Http\Controllers\Dashboard\RespondsWithDashboard::class))->toBeTrue();

    // Commands
    expect(class_exists(\Internal\Pasak\Console\Commands\PasakInstallCommand::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Console\Commands\PasakUpgradeCommand::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Console\Commands\PasakBaselineCommand::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Console\Commands\PasakPruneSecurityLogsCommand::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Console\Commands\PasakPurgeInjectedDataCommand::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Console\Commands\PasakScanAccessLogsCommand::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Console\Commands\PasakUnblockIpCommand::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Console\Commands\PasakUninstallCommand::class))->toBeTrue();

    // Commands legacy aliases
    expect(class_exists(\Internal\Pasak\Console\Commands\SecurityInstallCommand::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Console\Commands\SecurityUpgradeCommand::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Console\Commands\SecurityBaselineCommand::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Console\Commands\PruneSecurityLogs::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Console\Commands\PurgeInjectedData::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Console\Commands\SecurityScanAccessLogs::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Console\Commands\UnblockIpAddress::class))->toBeTrue();

    // API Controllers
    expect(class_exists(\Internal\Pasak\Http\Controllers\Api\PasakAllowedIpApiController::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Http\Controllers\Api\PasakBlockedIpApiController::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Http\Controllers\Api\PasakIpUnblockRequestApiController::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Http\Controllers\Api\PasakSecurityLogApiController::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Http\Controllers\Api\PasakSecuritySettingApiController::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Http\Controllers\Api\PasakServerSecurityApiController::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Http\Controllers\Api\PasakUserSessionApiController::class))->toBeTrue();

    // Dashboard Controllers
    expect(class_exists(\Internal\Pasak\Http\Controllers\Dashboard\PasakAllowedIpController::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Http\Controllers\Dashboard\PasakBlockedIpController::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Http\Controllers\Dashboard\PasakLogController::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Http\Controllers\Dashboard\PasakOverviewController::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Http\Controllers\Dashboard\PasakServerController::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Http\Controllers\Dashboard\PasakSessionController::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Http\Controllers\Dashboard\PasakSettingController::class))->toBeTrue()
        ->and(class_exists(\Internal\Pasak\Http\Controllers\Dashboard\PasakTicketController::class))->toBeTrue();
});

