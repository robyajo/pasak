<?php

namespace Internal\Pasak\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Internal\Pasak\Concerns\ResolvesPasakTable;
use Internal\Pasak\Services\PasakUserLoginService;

/**
 * @property int $id
 * @property int $user_id
 * @property string $ip_address
 * @property string|null $local_ip
 * @property string|null $device_id
 * @property string|null $device_name
 * @property string|null $operating_system
 * @property string|null $browser
 * @property string|null $location
 * @property bool $is_active
 * @property Carbon|null $verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PasakTrustedIp extends Model
{
    use ResolvesPasakTable;

    public function getTable()
    {
        $configured = config('pasak.table_names.trusted_ips', 'pasak_trusted_ips');

        return static::resolvePasakTable('trusted_ips', $configured, 'trusted_ips', 'pasak_trusted_ips');
    }

    protected $fillable = [
        'user_id',
        'ip_address',
        'local_ip',
        'device_id',
        'device_name',
        'operating_system',
        'browser',
        'location',
        'is_active',
        'verified_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function setOperatingSystemAttribute(?string $value): void
    {
        if ($value === null) {
            $this->attributes['operating_system'] = null;

            return;
        }

        if (strlen($value) > 30 && preg_match('/(Mozilla|AppleWebKit|Gecko|Chrome|Safari|Edge|Opera)/i', $value)) {
            $parsed = app(PasakUserLoginService::class)->parseUserAgent($value);
            $value = $parsed['os'];
        }

        $this->attributes['operating_system'] = Str::limit($value, 255, '');
    }

    public function setBrowserAttribute(?string $value): void
    {
        $this->attributes['browser'] = $value !== null ? Str::limit($value, 255, '') : null;
    }

    public function setDeviceNameAttribute(?string $value): void
    {
        $this->attributes['device_name'] = $value !== null ? Str::limit($value, 255, '') : null;
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            config('pasak.user_model', 'App\\Models\\User'),
        );
    }

    /**
     * @param  Builder<PasakTrustedIp>  $query
     * @return Builder<PasakTrustedIp>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if a specific IP / device is trusted for a user.
     */
    public static function isTrusted(
        string $ip,
        int $userId,
        ?string $deviceId = null,
        ?string $localIp = null,
    ): bool {
        return static::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->where(function (Builder $query) use ($ip, $deviceId, $localIp) {
                $query->where('ip_address', $ip);
                if ($deviceId) {
                    $query->orWhere('device_id', $deviceId);
                }
                if ($localIp) {
                    $query->orWhere('local_ip', $localIp);
                }
            })
            ->exists();
    }

    /**
     * Check if an IP / device is trusted by any user.
     */
    public static function isAnyTrusted(
        string $ip,
        ?string $deviceId = null,
        ?string $localIp = null,
    ): bool {
        return static::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($ip, $deviceId, $localIp) {
                $query->where('ip_address', $ip);
                if ($deviceId) {
                    $query->orWhere('device_id', $deviceId);
                }
                if ($localIp) {
                    $query->orWhere('local_ip', $localIp);
                }
            })
            ->exists();
    }
}
