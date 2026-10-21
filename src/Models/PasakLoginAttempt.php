<?php

namespace Internal\Pasak\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Internal\Pasak\Concerns\ResolvesPasakTable;

/**
 * Berisi hitungan percobaan login gagal per kombinasi email + IP, dipakai oleh
 * LoginThrottleService untuk menerapkan cooldown bertingkat
 * (3x salah → 1 menit, 3x salah lagi → 2 menit, dan seterusnya).
 *
 * @property int $id
 * @property string|null $email
 * @property string|null $ip_address
 * @property int $attempts
 * @property int $lockout_level
 * @property Carbon|null $locked_until
 * @property Carbon|null $last_attempt_at
 */
class PasakLoginAttempt extends Model
{
    use ResolvesPasakTable;

    public function getTable()
    {
        $configured = config('pasak.table_names.login_attempts', 'pasak_login_attempts');

        return static::resolvePasakTable('login_attempts', $configured, 'login_attempts', 'pasak_login_attempts');
    }

    protected $fillable = [
        'email',
        'ip_address',
        'attempts',
        'lockout_level',
        'locked_until',
        'last_attempt_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'lockout_level' => 'integer',
        'locked_until' => 'datetime',
        'last_attempt_at' => 'datetime',
    ];

    /**
     * Hanya record yang sedang terkunci.
     *
     * @param  Builder<PasakLoginAttempt>  $query
     * @return Builder<PasakLoginAttempt>
     */
    public function scopeLocked(Builder $query): Builder
    {
        return $query->whereNotNull('locked_until')
            ->where('locked_until', '>', now());
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * Sisa detik sampai blokir sementara berakhir.
     */
    public function secondsRemaining(): int
    {
        if (! $this->isLocked()) {
            return 0;
        }

        return max(0, (int) now()->diffInSeconds($this->locked_until, false));
    }
}
