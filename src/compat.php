<?php

// Backward-compatibility aliases: robyajo/pasak was previously
// published as robyajo/laravel-security-monitor under the
// Internal\SecurityMonitor namespace. Old host applications referencing
// those class names keep working via these aliases.

if (! class_exists('Internal\SecurityMonitor\PasakServiceProvider') && class_exists('Internal\Pasak\PasakServiceProvider')) {
    class_alias('Internal\Pasak\PasakServiceProvider', 'Internal\SecurityMonitor\SecurityMonitorServiceProvider');
}

if (! class_exists('Internal\SecurityMonitor\Facades\SecurityMonitor') && class_exists('Internal\Pasak\Facades\Pasak')) {
    class_alias('Internal\Pasak\Facades\Pasak', 'Internal\SecurityMonitor\Facades\SecurityMonitor');
}

if (! class_exists('Internal\SecurityMonitor\Services\SecurityMonitorService') && class_exists('Internal\Pasak\Services\PasakService')) {
    class_alias('Internal\Pasak\Services\PasakService', 'Internal\SecurityMonitor\Services\SecurityMonitorService');
}

if (! class_exists('Internal\SecurityMonitor\Services\PasakService') && class_exists('Internal\Pasak\Services\PasakService')) {
    class_alias('Internal\Pasak\Services\PasakService', 'Internal\SecurityMonitor\Services\PasakService');
}

// Backward-compatibility aliases: Pasak* (prefixed class names) <=> legacy non-prefixed class names
$aliases = [
    // Models
    ['Internal\Pasak\Models\PasakBlockedIp', 'Internal\Pasak\Models\BlockedIp'],
    ['Internal\Pasak\Models\PasakLog', 'Internal\Pasak\Models\SecurityLog'],
    ['Internal\Pasak\Models\PasakLog', 'Internal\Pasak\Models\PasakSecurityLog'],
    ['Internal\Pasak\Models\PasakLoginAttempt', 'Internal\Pasak\Models\LoginAttempt'],
    ['Internal\Pasak\Models\PasakIpUnblockRequest', 'Internal\Pasak\Models\IpUnblockRequest'],
    ['Internal\Pasak\Models\PasakUserLogin', 'Internal\Pasak\Models\UserLogin'],
    ['Internal\Pasak\Models\PasakTrustedIp', 'Internal\Pasak\Models\TrustedIp'],
    ['Internal\Pasak\Models\PasakAllowedIp', 'Internal\Pasak\Models\AllowedIp'],
    ['Internal\Pasak\Models\PasakSetting', 'Internal\Pasak\Models\SecuritySetting'],
    ['Internal\Pasak\Models\PasakSetting', 'Internal\Pasak\Models\PasakSecuritySetting'],

    // Middleware
    ['Internal\Pasak\Http\Middleware\PasakBlockIpAddress', 'Internal\Pasak\Http\Middleware\BlockIpAddress'],
    ['Internal\Pasak\Http\Middleware\PasakDetectSecurityThreats', 'Internal\Pasak\Http\Middleware\DetectSecurityThreats'],
    ['Internal\Pasak\Http\Middleware\PasakEnsureSecurityAdmin', 'Internal\Pasak\Http\Middleware\EnsureSecurityAdmin'],
    ['Internal\Pasak\Http\Middleware\PasakInjectAnnouncementBanner', 'Internal\Pasak\Http\Middleware\InjectAnnouncementBanner'],
    ['Internal\Pasak\Http\Middleware\PasakTrackUserActivity', 'Internal\Pasak\Http\Middleware\TrackUserActivity'],

    // Listeners
    ['Internal\Pasak\Listeners\PasakLogFailedLoginAttempt', 'Internal\Pasak\Listeners\LogFailedLoginAttempt'],
    ['Internal\Pasak\Listeners\PasakRecordUserLogin', 'Internal\Pasak\Listeners\RecordUserLogin'],
    ['Internal\Pasak\Listeners\PasakResetLoginAttempts', 'Internal\Pasak\Listeners\ResetLoginAttempts'],

    // Rules
    ['Internal\Pasak\Rules\PasakSafeAssetPath', 'Internal\Pasak\Rules\SafeAssetPath'],
    ['Internal\Pasak\Rules\PasakSafeImageFile', 'Internal\Pasak\Rules\SafeImageFile'],

    // Services
    ['Internal\Pasak\Services\PasakAccessLogScannerService', 'Internal\Pasak\Services\AccessLogScannerService'],
    ['Internal\Pasak\Services\PasakLoginThrottleService', 'Internal\Pasak\Services\LoginThrottleService'],
    ['Internal\Pasak\Services\PasakServerSecurityService', 'Internal\Pasak\Services\ServerSecurityService'],
    ['Internal\Pasak\Services\PasakUserLoginService', 'Internal\Pasak\Services\UserLoginService'],
    ['Internal\Pasak\Services\PasakVersionCheckService', 'Internal\Pasak\Services\VersionCheckService'],

    // Support
    ['Internal\Pasak\Support\PasakDashboardView', 'Internal\Pasak\Support\DashboardView'],
    ['Internal\Pasak\Support\PasakTableResolver', 'Internal\Pasak\Support\TableResolver'],

    // Concerns / Traits
    ['Internal\Pasak\Concerns\HasPasakSecurityRelations', 'Internal\Pasak\Concerns\HasSecurityRelations'],
    ['Internal\Pasak\Http\Controllers\Dashboard\RespondsWithPasakDashboard', 'Internal\Pasak\Http\Controllers\Dashboard\RespondsWithDashboard'],

    // Commands
    ['Internal\Pasak\Console\Commands\PasakInstallCommand', 'Internal\Pasak\Console\Commands\SecurityInstallCommand'],
    ['Internal\Pasak\Console\Commands\PasakUpgradeCommand', 'Internal\Pasak\Console\Commands\SecurityUpgradeCommand'],
    ['Internal\Pasak\Console\Commands\PasakBaselineCommand', 'Internal\Pasak\Console\Commands\SecurityBaselineCommand'],
    ['Internal\Pasak\Console\Commands\PasakPruneSecurityLogsCommand', 'Internal\Pasak\Console\Commands\PruneSecurityLogs'],
    ['Internal\Pasak\Console\Commands\PasakPurgeInjectedDataCommand', 'Internal\Pasak\Console\Commands\PurgeInjectedData'],
    ['Internal\Pasak\Console\Commands\PasakScanAccessLogsCommand', 'Internal\Pasak\Console\Commands\SecurityScanAccessLogs'],
    ['Internal\Pasak\Console\Commands\PasakUnblockIpCommand', 'Internal\Pasak\Console\Commands\UnblockIpAddress'],

    // API Controllers
    ['Internal\Pasak\Http\Controllers\Api\PasakAllowedIpApiController', 'Internal\Pasak\Http\Controllers\Api\AllowedIpApiController'],
    ['Internal\Pasak\Http\Controllers\Api\PasakBlockedIpApiController', 'Internal\Pasak\Http\Controllers\Api\BlockedIpApiController'],
    ['Internal\Pasak\Http\Controllers\Api\PasakIpUnblockRequestApiController', 'Internal\Pasak\Http\Controllers\Api\IpUnblockRequestApiController'],
    ['Internal\Pasak\Http\Controllers\Api\PasakSecurityLogApiController', 'Internal\Pasak\Http\Controllers\Api\SecurityLogApiController'],
    ['Internal\Pasak\Http\Controllers\Api\PasakSecuritySettingApiController', 'Internal\Pasak\Http\Controllers\Api\SecuritySettingApiController'],
    ['Internal\Pasak\Http\Controllers\Api\PasakServerSecurityApiController', 'Internal\Pasak\Http\Controllers\Api\ServerSecurityApiController'],
    ['Internal\Pasak\Http\Controllers\Api\PasakUserSessionApiController', 'Internal\Pasak\Http\Controllers\Api\UserSessionApiController'],

    // Dashboard Controllers
    ['Internal\Pasak\Http\Controllers\Dashboard\PasakAllowedIpController', 'Internal\Pasak\Http\Controllers\Dashboard\AllowedIpController'],
    ['Internal\Pasak\Http\Controllers\Dashboard\PasakBlockedIpController', 'Internal\Pasak\Http\Controllers\Dashboard\BlockedIpController'],
    ['Internal\Pasak\Http\Controllers\Dashboard\PasakLogController', 'Internal\Pasak\Http\Controllers\Dashboard\LogController'],
    ['Internal\Pasak\Http\Controllers\Dashboard\PasakOverviewController', 'Internal\Pasak\Http\Controllers\Dashboard\OverviewController'],
    ['Internal\Pasak\Http\Controllers\Dashboard\PasakServerController', 'Internal\Pasak\Http\Controllers\Dashboard\ServerController'],
    ['Internal\Pasak\Http\Controllers\Dashboard\PasakSessionController', 'Internal\Pasak\Http\Controllers\Dashboard\SessionController'],
    ['Internal\Pasak\Http\Controllers\Dashboard\PasakSettingController', 'Internal\Pasak\Http\Controllers\Dashboard\SettingController'],
    ['Internal\Pasak\Http\Controllers\Dashboard\PasakTicketController', 'Internal\Pasak\Http\Controllers\Dashboard\TicketController'],

    // Pre-rename namespace (Internal\SecurityMonitor\Models\*)
    ['Internal\Pasak\Models\PasakBlockedIp', 'Internal\SecurityMonitor\Models\BlockedIp'],
    ['Internal\Pasak\Models\PasakLog', 'Internal\SecurityMonitor\Models\SecurityLog'],
    ['Internal\Pasak\Models\PasakLoginAttempt', 'Internal\SecurityMonitor\Models\LoginAttempt'],
    ['Internal\Pasak\Models\PasakIpUnblockRequest', 'Internal\SecurityMonitor\Models\IpUnblockRequest'],
    ['Internal\Pasak\Models\PasakUserLogin', 'Internal\SecurityMonitor\Models\UserLogin'],
    ['Internal\Pasak\Models\PasakTrustedIp', 'Internal\SecurityMonitor\Models\TrustedIp'],
    ['Internal\Pasak\Models\PasakAllowedIp', 'Internal\SecurityMonitor\Models\AllowedIp'],
    ['Internal\Pasak\Models\PasakSetting', 'Internal\SecurityMonitor\Models\SecuritySetting'],
];

foreach ($aliases as [$target, $alias]) {
    if (! class_exists($alias, false) && ! trait_exists($alias, false) && (class_exists($target) || trait_exists($target))) {
        class_alias($target, $alias);
    }
}
