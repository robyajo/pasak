<?php

namespace Internal\Pasak\Models;

use DateTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Internal\Pasak\Concerns\ResolvesPasakTable;
use Internal\Pasak\Support\PasakDashboardView;

/**
 * Persisted key-value settings for Pasak.
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PasakSetting extends Model
{
    use ResolvesPasakTable;

    public function getTable()
    {
        $configured = config('pasak.table_names.settings', 'pasak_settings');

        return static::resolvePasakTable(
            'settings',
            $configured,
            'security_settings',
            'pasak_settings',
        );
    }

    protected $fillable = ['key', 'value'];

    /**
     * Dapatkan nilai setting dari database dengan fallback default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $record = static::query()->where('key', $key)->first();
            if ($record !== null) {
                return $record->value;
            }
        } catch (\Throwable) {
        }

        return $default;
    }

    /**
     * Simpan nilai setting ke database.
     */
    public static function set(string $key, ?string $value): void
    {
        try {
            static::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        } catch (\Throwable) {
        }
    }

    /**
     * Dapatkan zona waktu server yang tersimpan di database.
     */
    public static function getServerTimezone(): ?string
    {
        try {
            $val = static::query()
                ->where('key', 'server_timezone')
                ->value('value');
            if (
                $val !== null &&
                $val !== '' &&
                in_array($val, DateTimeZone::listIdentifiers(), true)
            ) {
                return (string) $val;
            }
        } catch (\Throwable) {
        }

        return null;
    }

    /**
     * Simpan zona waktu server ke database.
     */
    public static function setServerTimezone(?string $timezone): void
    {
        try {
            $cleaned = $timezone !== null ? trim($timezone) : null;
            if (
                $cleaned !== null &&
                ! in_array($cleaned, DateTimeZone::listIdentifiers(), true)
            ) {
                $cleaned = null;
            }

            static::query()->updateOrCreate(
                ['key' => 'server_timezone'],
                ['value' => $cleaned !== '' ? $cleaned : null],
            );

            Cache::forget('pasak:server_timezone');
        } catch (\Throwable) {
        }
    }

    /**
     * Dapatkan konfigurasi lokasi fisik server & koordinat Live Threat Map dari database.
     *
     * @return array{name: string|null, latitude: float|null, longitude: float|null, timezone: string|null, effective_timezone: string, is_custom: bool}
     */
    public static function getServerLocation(): array
    {
        $name = null;
        $lat = null;
        $lon = null;
        $tz = null;

        try {
            $settings = static::query()
                ->whereIn('key', [
                    'server_location_name',
                    'server_latitude',
                    'server_longitude',
                    'server_timezone',
                ])
                ->pluck('value', 'key');

            $name =
                isset($settings['server_location_name']) &&
                $settings['server_location_name'] !== ''
                    ? (string) $settings['server_location_name']
                    : null;
            $lat =
                isset($settings['server_latitude']) &&
                is_numeric($settings['server_latitude'])
                    ? (float) $settings['server_latitude']
                    : null;
            $lon =
                isset($settings['server_longitude']) &&
                is_numeric($settings['server_longitude'])
                    ? (float) $settings['server_longitude']
                    : null;
            $tz =
                isset($settings['server_timezone']) &&
                $settings['server_timezone'] !== '' &&
                in_array(
                    $settings['server_timezone'],
                    DateTimeZone::listIdentifiers(),
                    true,
                )
                    ? (string) $settings['server_timezone']
                    : null;
        } catch (\Throwable) {
        }

        $effectiveTz = $tz ?? PasakDashboardView::timezone();

        return [
            'name' => $name,
            'latitude' => $lat,
            'longitude' => $lon,
            'timezone' => $tz,
            'effective_timezone' => $effectiveTz,
            'is_custom' => ($lat !== null && $lon !== null) ||
                ! empty($name) ||
                ! empty($tz),
        ];
    }

    /**
     * Simpan konfigurasi lokasi fisik server & koordinat Live Threat Map ke database.
     */
    public static function setServerLocation(
        ?string $name,
        ?float $lat,
        ?float $lon,
        ?string $timezone = null,
    ): void {
        try {
            static::query()->updateOrCreate(
                ['key' => 'server_location_name'],
                [
                    'value' => $name !== null && trim($name) !== ''
                            ? trim($name)
                            : null,
                ],
            );

            static::query()->updateOrCreate(
                ['key' => 'server_latitude'],
                ['value' => $lat !== null ? (string) $lat : null],
            );

            static::query()->updateOrCreate(
                ['key' => 'server_longitude'],
                ['value' => $lon !== null ? (string) $lon : null],
            );

            if (func_num_args() >= 4) {
                static::setServerTimezone($timezone);
            }

            Cache::forget('pasak:threat_map_server_target');
            Cache::forget('pasak:server_timezone');
        } catch (\Throwable) {
        }
    }
}
