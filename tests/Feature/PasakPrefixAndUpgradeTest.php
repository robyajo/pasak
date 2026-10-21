<?php

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Internal\Pasak\Concerns\HasPasakSecurityRelations;
use Internal\Pasak\Concerns\HasSecurityRelations;
use Internal\Pasak\Concerns\ResolvesPasakTable;
use Internal\Pasak\Console\Commands\PasakBaselineCommand;
use Internal\Pasak\Console\Commands\PasakInstallCommand;
use Internal\Pasak\Console\Commands\PasakPruneSecurityLogsCommand;
use Internal\Pasak\Console\Commands\PasakPurgeInjectedDataCommand;
use Internal\Pasak\Console\Commands\PasakScanAccessLogsCommand;
use Internal\Pasak\Console\Commands\PasakUnblockIpCommand;
use Internal\Pasak\Console\Commands\PasakUninstallCommand;
use Internal\Pasak\Console\Commands\PasakUpgradeCommand;
use Internal\Pasak\Console\Commands\PruneSecurityLogs;
use Internal\Pasak\Console\Commands\PurgeInjectedData;
use Internal\Pasak\Console\Commands\SecurityBaselineCommand;
use Internal\Pasak\Console\Commands\SecurityInstallCommand;
use Internal\Pasak\Console\Commands\SecurityScanAccessLogs;
use Internal\Pasak\Console\Commands\SecurityUpgradeCommand;
use Internal\Pasak\Console\Commands\UnblockIpAddress;
use Internal\Pasak\Http\Controllers\Api\PasakAllowedIpApiController;
use Internal\Pasak\Http\Controllers\Api\PasakBlockedIpApiController;
use Internal\Pasak\Http\Controllers\Api\PasakIpUnblockRequestApiController;
use Internal\Pasak\Http\Controllers\Api\PasakSecurityLogApiController;
use Internal\Pasak\Http\Controllers\Api\PasakSecuritySettingApiController;
use Internal\Pasak\Http\Controllers\Api\PasakServerSecurityApiController;
use Internal\Pasak\Http\Controllers\Api\PasakUserSessionApiController;
use Internal\Pasak\Http\Controllers\Dashboard\PasakAllowedIpController;
use Internal\Pasak\Http\Controllers\Dashboard\PasakBlockedIpController;
use Internal\Pasak\Http\Controllers\Dashboard\PasakLogController;
use Internal\Pasak\Http\Controllers\Dashboard\PasakOverviewController;
use Internal\Pasak\Http\Controllers\Dashboard\PasakServerController;
use Internal\Pasak\Http\Controllers\Dashboard\PasakSessionController;
use Internal\Pasak\Http\Controllers\Dashboard\PasakSettingController;
use Internal\Pasak\Http\Controllers\Dashboard\PasakTicketController;
use Internal\Pasak\Http\Controllers\Dashboard\RespondsWithDashboard;
use Internal\Pasak\Http\Controllers\Dashboard\RespondsWithPasakDashboard;
use Internal\Pasak\Http\Middleware\BlockIpAddress;
use Internal\Pasak\Http\Middleware\DetectSecurityThreats;
use Internal\Pasak\Http\Middleware\EnsureSecurityAdmin;
use Internal\Pasak\Http\Middleware\InjectAnnouncementBanner;
use Internal\Pasak\Http\Middleware\PasakBlockIpAddress;
use Internal\Pasak\Http\Middleware\PasakDetectSecurityThreats;
use Internal\Pasak\Http\Middleware\PasakEnsureSecurityAdmin;
use Internal\Pasak\Http\Middleware\PasakInjectAnnouncementBanner;
use Internal\Pasak\Http\Middleware\PasakTrackUserActivity;
use Internal\Pasak\Http\Middleware\TrackUserActivity;
use Internal\Pasak\Listeners\LogFailedLoginAttempt;
use Internal\Pasak\Listeners\PasakLogFailedLoginAttempt;
use Internal\Pasak\Listeners\PasakRecordUserLogin;
use Internal\Pasak\Listeners\PasakResetLoginAttempts;
use Internal\Pasak\Listeners\RecordUserLogin;
use Internal\Pasak\Listeners\ResetLoginAttempts;
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
use Internal\Pasak\Rules\PasakSafeAssetPath;
use Internal\Pasak\Rules\PasakSafeImageFile;
use Internal\Pasak\Rules\SafeAssetPath;
use Internal\Pasak\Rules\SafeImageFile;
use Internal\Pasak\Services\AccessLogScannerService;
use Internal\Pasak\Services\LoginThrottleService;
use Internal\Pasak\Services\PasakAccessLogScannerService;
use Internal\Pasak\Services\PasakLoginThrottleService;
use Internal\Pasak\Services\PasakServerSecurityService;
use Internal\Pasak\Services\PasakService;
use Internal\Pasak\Services\PasakUserLoginService;
use Internal\Pasak\Services\PasakVersionCheckService;
use Internal\Pasak\Services\ServerSecurityService;
use Internal\Pasak\Services\UserLoginService;
use Internal\Pasak\Services\VersionCheckService;
use Internal\Pasak\Support\DashboardView;
use Internal\Pasak\Support\PasakDashboardView;
use Internal\Pasak\Support\PasakTableResolver;
use Internal\Pasak\Support\TableResolver;

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

    expect($user->securityLogs())->toBeInstanceOf(HasMany::class)
        ->and($user->pasakLogs())->toBeInstanceOf(HasMany::class)
        ->and($user->blockedIps())->toBeInstanceOf(HasMany::class)
        ->and($user->logins())->toBeInstanceOf(HasMany::class)
        ->and($user->trustedIps())->toBeInstanceOf(HasMany::class)
        ->and($user->allowedIps())->toBeInstanceOf(HasMany::class)
        ->and($user->resolvedTickets())->toBeInstanceOf(HasMany::class);
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
    expect(class_exists(PasakBlockIpAddress::class))->toBeTrue()
        ->and(class_exists(PasakDetectSecurityThreats::class))->toBeTrue()
        ->and(class_exists(PasakEnsureSecurityAdmin::class))->toBeTrue()
        ->and(class_exists(PasakInjectAnnouncementBanner::class))->toBeTrue()
        ->and(class_exists(PasakTrackUserActivity::class))->toBeTrue();

    // Middleware legacy aliases
    expect(class_exists(BlockIpAddress::class))->toBeTrue()
        ->and(class_exists(DetectSecurityThreats::class))->toBeTrue()
        ->and(class_exists(EnsureSecurityAdmin::class))->toBeTrue()
        ->and(class_exists(InjectAnnouncementBanner::class))->toBeTrue()
        ->and(class_exists(TrackUserActivity::class))->toBeTrue();

    // Listeners
    expect(class_exists(PasakLogFailedLoginAttempt::class))->toBeTrue()
        ->and(class_exists(PasakRecordUserLogin::class))->toBeTrue()
        ->and(class_exists(PasakResetLoginAttempts::class))->toBeTrue();

    // Listeners legacy aliases
    expect(class_exists(LogFailedLoginAttempt::class))->toBeTrue()
        ->and(class_exists(RecordUserLogin::class))->toBeTrue()
        ->and(class_exists(ResetLoginAttempts::class))->toBeTrue();

    // Rules
    expect(class_exists(PasakSafeAssetPath::class))->toBeTrue()
        ->and(class_exists(PasakSafeImageFile::class))->toBeTrue()
        ->and(class_exists(SafeAssetPath::class))->toBeTrue()
        ->and(class_exists(SafeImageFile::class))->toBeTrue();

    // Services
    expect(class_exists(PasakService::class))->toBeTrue()
        ->and(class_exists(PasakAccessLogScannerService::class))->toBeTrue()
        ->and(class_exists(PasakLoginThrottleService::class))->toBeTrue()
        ->and(class_exists(PasakServerSecurityService::class))->toBeTrue()
        ->and(class_exists(PasakUserLoginService::class))->toBeTrue()
        ->and(class_exists(PasakVersionCheckService::class))->toBeTrue();

    // Services legacy aliases
    expect(class_exists(AccessLogScannerService::class))->toBeTrue()
        ->and(class_exists(LoginThrottleService::class))->toBeTrue()
        ->and(class_exists(ServerSecurityService::class))->toBeTrue()
        ->and(class_exists(UserLoginService::class))->toBeTrue()
        ->and(class_exists(VersionCheckService::class))->toBeTrue();

    // Support
    expect(class_exists(PasakDashboardView::class))->toBeTrue()
        ->and(class_exists(PasakTableResolver::class))->toBeTrue()
        ->and(class_exists(DashboardView::class))->toBeTrue()
        ->and(class_exists(TableResolver::class))->toBeTrue();

    // Concerns / Traits
    expect(trait_exists(HasPasakSecurityRelations::class))->toBeTrue()
        ->and(trait_exists(HasSecurityRelations::class))->toBeTrue()
        ->and(trait_exists(RespondsWithPasakDashboard::class))->toBeTrue()
        ->and(trait_exists(RespondsWithDashboard::class))->toBeTrue();

    // Commands
    expect(class_exists(PasakInstallCommand::class))->toBeTrue()
        ->and(class_exists(PasakUpgradeCommand::class))->toBeTrue()
        ->and(class_exists(PasakBaselineCommand::class))->toBeTrue()
        ->and(class_exists(PasakPruneSecurityLogsCommand::class))->toBeTrue()
        ->and(class_exists(PasakPurgeInjectedDataCommand::class))->toBeTrue()
        ->and(class_exists(PasakScanAccessLogsCommand::class))->toBeTrue()
        ->and(class_exists(PasakUnblockIpCommand::class))->toBeTrue()
        ->and(class_exists(PasakUninstallCommand::class))->toBeTrue();

    // Commands legacy aliases
    expect(class_exists(SecurityInstallCommand::class))->toBeTrue()
        ->and(class_exists(SecurityUpgradeCommand::class))->toBeTrue()
        ->and(class_exists(SecurityBaselineCommand::class))->toBeTrue()
        ->and(class_exists(PruneSecurityLogs::class))->toBeTrue()
        ->and(class_exists(PurgeInjectedData::class))->toBeTrue()
        ->and(class_exists(SecurityScanAccessLogs::class))->toBeTrue()
        ->and(class_exists(UnblockIpAddress::class))->toBeTrue();

    // API Controllers
    expect(class_exists(PasakAllowedIpApiController::class))->toBeTrue()
        ->and(class_exists(PasakBlockedIpApiController::class))->toBeTrue()
        ->and(class_exists(PasakIpUnblockRequestApiController::class))->toBeTrue()
        ->and(class_exists(PasakSecurityLogApiController::class))->toBeTrue()
        ->and(class_exists(PasakSecuritySettingApiController::class))->toBeTrue()
        ->and(class_exists(PasakServerSecurityApiController::class))->toBeTrue()
        ->and(class_exists(PasakUserSessionApiController::class))->toBeTrue();

    // Dashboard Controllers
    expect(class_exists(PasakAllowedIpController::class))->toBeTrue()
        ->and(class_exists(PasakBlockedIpController::class))->toBeTrue()
        ->and(class_exists(PasakLogController::class))->toBeTrue()
        ->and(class_exists(PasakOverviewController::class))->toBeTrue()
        ->and(class_exists(PasakServerController::class))->toBeTrue()
        ->and(class_exists(PasakSessionController::class))->toBeTrue()
        ->and(class_exists(PasakSettingController::class))->toBeTrue()
        ->and(class_exists(PasakTicketController::class))->toBeTrue();
});
