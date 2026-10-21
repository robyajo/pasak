<?php

namespace Internal\Pasak\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Internal\Pasak\Concerns\ResolvesPasakTable;

/**
 * Global allowlist entries managed by administrators.
 *
 * The `ip_address` column accepts an exact IP, a wildcard (192.168.1.*)
 * or a CIDR range (10.0.0.0/8) — see PasakService::ipMatches().
 *
 * @property int $id
 * @property string $ip_address
 * @property string|null $label
 * @property string|null $notes
 * @property int|null $allowed_by
 * @property bool $is_active
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PasakAllowedIp extends Model
{
    use ResolvesPasakTable;

    public function getTable()
    {
        $configured = config('pasak.table_names.allowed_ips', 'pasak_allowed_ips');

        return static::resolvePasakTable('allowed_ips', $configured, 'allowed_ips', 'pasak_allowed_ips');
    }

    protected $fillable = [
        'ip_address',
        'label',
        'notes',
        'allowed_by',
        'is_active',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Model, $this>
     */
    public function allowedBy(): BelongsTo
    {
        $userModel = config('pasak.user_model', 'App\Models\User');

        return $this->belongsTo($userModel, 'allowed_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $query) {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    public function isPermanent(): bool
    {
        return $this->expires_at === null;
    }

    /**
     * Whether the stored entry currently grants access.
     */
    public function isEffective(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * Validate an allowlist pattern: exact IP, wildcard, or CIDR range.
     */
    public static function isValidPattern(string $pattern): bool
    {
        $pattern = trim($pattern);

        if ($pattern === '') {
            return false;
        }

        if (filter_var($pattern, FILTER_VALIDATE_IP)) {
            return true;
        }

        if (str_contains($pattern, '/')) {
            $parts = explode('/', $pattern, 2);
            $subnet = $parts[0];
            $bits = $parts[1] ?? null;

            if ($bits === null || ! is_numeric($bits)) {
                return false;
            }

            if (! filter_var($subnet, FILTER_VALIDATE_IP)) {
                return false;
            }

            $maxBits = str_contains($subnet, ':') ? 128 : 32;
            $bitsInt = (int) $bits;

            return $bitsInt >= 0 && $bitsInt <= $maxBits;
        }

        return (bool) preg_match('/^[0-9a-fA-F:.*]+$/', $pattern) && str_contains($pattern, '*');
    }
}
