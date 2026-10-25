<?php

namespace Internal\Pasak\Facades;

use Illuminate\Support\Facades\Facade;
use Internal\Pasak\Services\PasakService;

/**
 * @method static bool isEnabled()
 * @method static bool isBlockEnforcementEnabled()
 * @method static bool isWhitelist(string $ip)
 * @method static bool isAutoBlockEnabled()
 * @method static bool isInstantBlockEnabled()
 * @method static array instantBlockSignatures()
 * @method static array allowedExtensions()
 * @method static array sensitivePaths()
 * @method static bool isBlocked(string $ip, ?string $deviceId = null)
 * @method static bool isDeviceBlocked(string $deviceId)
 * @method static bool isIpBlocked(string $ip)
 * @method static bool blockIp(string $ip, ?string $reason = null, ?int $durationHours = null, string $source = 'manual', ?int $userId = null, ?string $deviceId = null, ?string $localIp = null, string $scope = 'ip')
 * @method static bool unblockIp(string $ip)
 * @method static bool unblockDevice(string $deviceId)
 * @method static bool unblockIpOrDevice(string $identifier)
 * @method static array checkPatterns(string $value, string $source = 'input')
 * @method static array detectThreats(\Illuminate\Http\Request $request)
 * @method static bool isExcludedRoute(\Illuminate\Http\Request $request)
 * @method static bool isAdminRequest(\Illuminate\Http\Request $request)
 * @method static \Internal\Pasak\Models\SecurityLog|null log(array $attributes)
 * @method static \Internal\Pasak\Models\BlockedIp|null autoBlockIfNeeded(string $ip, ?string $deviceId = null, ?string $localIp = null)
 * @method static array stats()
 * @method static array getSettings()
 * @method static array updateSettings(array $values)
 * @method static array defaultSettings()
 * @method static array<int, string> seedDefaultSettings(bool $overwrite = false)
 * @method static array<string, int> autoBlockLevelThresholds()
 * @method static \Illuminate\Support\Collection<int, \Internal\Pasak\Models\SecurityLog> topAttackers(int $limit = 5, int $hours = 24)
 * @method static array levelBreakdown(int $hours = 24)
 * @method static array attackTrend(string $range = 'week')
 *
 * @see PasakService
 */
class Pasak extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PasakService::class;
    }
}
