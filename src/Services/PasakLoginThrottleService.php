<?php

namespace Internal\Pasak\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Internal\Pasak\Models\PasakLog;
use Internal\Pasak\Models\PasakLoginAttempt;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Cooldown login bertingkat (Stepped Rate Limiting / Countdown).
 *
 * Menggantikan pemblokiran permanen WAF 403 dengan masa jeda manusiawi:
 *   Tahap 1 gagal -> jeda/countdown 30 detik
 *   Tahap 2 gagal -> jeda/countdown 60 detik
 *   Tahap 3 gagal -> jeda/countdown 120 detik (dan berlipat hingga batas maksimal)
 *
 * Siklus hukuman berlaku selama 6 jam (decay 360 menit). Setelah 6 jam tanpa
 * kegagalan, atau bila login berhasil, status kembali normal.
 */
class PasakLoginThrottleService
{
    public function __construct(protected PasakService $security) {}

    public function enabled(): bool
    {
        return $this->config('enabled', true);
    }

    /**
     * Status blokir untuk kombinasi email + IP.
     *
     * @return array{locked: bool, seconds: int, level: int, attempts: int, threshold: int, message: string|null, email: string|null}
     */
    public function status(?string $email, ?string $ip): array
    {
        $threshold = (int) $this->config('threshold', 3);
        $empty = [
            'locked' => false,
            'seconds' => 0,
            'level' => 0,
            'attempts' => 0,
            'threshold' => $threshold,
            'message' => null,
            'email' => $email,
        ];

        if (! $this->enabled()) {
            return $empty;
        }

        $record = $this->find($email, $ip);

        if ($record === null) {
            return $empty;
        }

        $this->decay($record);

        if (! $record->isLocked()) {
            return [
                'locked' => false,
                'seconds' => 0,
                'level' => $record->lockout_level,
                'attempts' => $record->attempts,
                'threshold' => $threshold,
                'message' => null,
                'email' => $record->email ?? $email,
            ];
        }

        $seconds = $record->secondsRemaining();

        return [
            'locked' => true,
            'seconds' => $seconds,
            'level' => $record->lockout_level,
            'attempts' => $record->attempts,
            'threshold' => $threshold,
            'message' => $this->lockoutMessage($seconds),
            'email' => $record->email ?? $email,
        ];
    }

    /**
     * Periksa apakah alamat IP sedang memiliki lockout aktif.
     */
    public function isIpLocked(?string $ip): bool
    {
        return $this->activeLockoutForIp($ip) !== null;
    }

    /**
     * Ambil record lockout aktif untuk alamat IP tertentu.
     */
    public function activeLockoutForIp(?string $ip): ?PasakLoginAttempt
    {
        if (empty($ip)) {
            return null;
        }

        try {
            return PasakLoginAttempt::query()
                ->where('ip_address', $ip)
                ->locked()
                ->orderByDesc('locked_until')
                ->first();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Catat satu kegagalan login. Bila mencapai threshold, akun dikunci
     * sementara dan level kunci dinaikkan (30s -> 60s -> 120s ...).
     *
     * @return array{locked: bool, seconds: int, level: int, attempts: int, threshold: int, message: string|null, email: string|null}
     */
    public function registerFailure(?string $email, ?string $ip): array
    {
        $threshold = (int) $this->config('threshold', 3);

        if (! $this->enabled()) {
            return [
                'locked' => false, 'seconds' => 0, 'level' => 0,
                'attempts' => 0, 'threshold' => $threshold, 'message' => null,
                'email' => $email,
            ];
        }

        try {
            $record = $this->find($email, $ip) ?? new PasakLoginAttempt([
                'email' => $email,
                'ip_address' => $ip,
            ]);

            $this->decay($record);

            // Jika sedang terkunci, jangan tambah percobaan baru, pertahankan hitungan sisa
            if ($record->isLocked()) {
                return $this->status($email, $ip);
            }

            $record->attempts = (int) $record->attempts + 1;
            $record->last_attempt_at = now();

            // Bila sudah pernah terkunci (level >= 1) dalam siklus 6 jam,
            // 1 kegagalan berikutnya langsung menaikkan ke tahap cooldown berikutnya.
            $requiredAttempts = $record->lockout_level > 0 ? 1 : $threshold;

            if ($record->attempts >= $requiredAttempts) {
                $record->lockout_level = (int) $record->lockout_level + 1;
                $record->attempts = 0;
                $seconds = $this->secondsForLevel((int) $record->lockout_level);
                $record->locked_until = now()->addSeconds($seconds);
            }

            $record->save();
        } catch (Throwable) {
            return [
                'locked' => false, 'seconds' => 0, 'level' => 0,
                'attempts' => 0, 'threshold' => $threshold, 'message' => null,
                'email' => $email,
            ];
        }

        if ($record->isLocked()) {
            $this->recordLockout($record, $email, $ip);
        }

        return $this->status($email, $ip);
    }

    /**
     * Reset riwayat kegagalan setelah login berhasil.
     */
    public function registerSuccess(?string $email, ?string $ip): void
    {
        $record = $this->find($email, $ip);

        if ($record === null) {
            return;
        }

        try {
            $record->delete();
        } catch (Throwable) {
            // Mengosongkan riwayat tidak boleh menggagalkan proses login.
        }
    }

    /**
     * Buka paksa blokir untuk sebuah email + IP (aksi admin).
     */
    public function release(string $email, ?string $ip = null): int
    {
        return PasakLoginAttempt::query()
            ->where('email', $email)
            ->when($ip !== null, fn ($query) => $query->where('ip_address', $ip))
            ->delete();
    }

    /**
     * Daftar blokir yang sedang aktif untuk ditampilkan di panel admin.
     *
     * @return Collection<int, PasakLoginAttempt>
     */
    public function activeLockouts(): Collection
    {
        return PasakLoginAttempt::query()
            ->locked()
            ->orderByDesc('locked_until')
            ->get();
    }

    /**
     * Durasi jeda bertingkat (dalam satuan detik) untuk level tertentu:
     * Level 1: 30 detik (default)
     * Level 2: 60 detik (default)
     * Level 3: 120 detik (default)
     * Level 4+: kelipatan eksponensial hingga batas maksimum (default 3600 detik)
     */
    public function secondsForLevel(int $level): int
    {
        $step1 = max(1, (int) $this->config('step1_seconds', 30));
        $step2 = max($step1, (int) $this->config('step2_seconds', 60));
        $step3 = max($step2, (int) $this->config('step3_seconds', 120));
        $maxSeconds = max($step3, (int) $this->config('max_seconds', 3600));

        // Kompatibilitas mundur jika base_minutes dikonfigurasi > 1
        $baseMinutes = (int) $this->config('base_minutes', 0);
        if ($baseMinutes > 1) {
            $maxMinutes = max($baseMinutes, (int) $this->config('max_minutes', 30));

            return min($level * $baseMinutes * 60, $maxMinutes * 60);
        }

        return match ($level) {
            1 => $step1,
            2 => $step2,
            3 => $step3,
            default => min((int) ($step3 * pow(2, max(0, $level - 3))), $maxSeconds),
        };
    }

    /**
     * Durasi blokir (menit) untuk level tertentu.
     * Menjaga kompatibilitas mundur dengan kode lama.
     */
    public function minutesForLevel(int $level): int
    {
        $baseMinutes = (int) $this->config('base_minutes', 0);
        if ($baseMinutes > 1) {
            $maxMinutes = max($baseMinutes, (int) $this->config('max_minutes', 30));

            return min($level * $baseMinutes, $maxMinutes);
        }

        return (int) ceil($this->secondsForLevel($level) / 60);
    }

    public function lockoutMessage(int $seconds): string
    {
        if ($seconds < 60) {
            return sprintf(
                'Terlalu banyak percobaan login yang gagal. Akun Anda sedang dalam masa jeda pengamanan (cooldown). Silakan coba lagi dalam %d detik.',
                $seconds
            );
        }

        $minutes = (int) ceil($seconds / 60);

        return sprintf(
            'Terlalu banyak percobaan login yang gagal. Akun Anda sedang dalam masa jeda pengamanan (cooldown). Silakan coba lagi dalam %d menit.',
            $minutes
        );
    }

    /**
     * Render respons HTTP 429 Too Many Requests lengkap dengan tampilan hitung mundur (countdown).
     */
    public function renderLockoutResponse(Request $request, array $status, ?string $retryUrl = null): Response
    {
        $seconds = max(1, (int) ($status['seconds'] ?? 30));
        $level = (int) ($status['level'] ?? 1);
        $message = $status['message'] ?? $this->lockoutMessage($seconds);
        $email = $status['email'] ?? $request->input('email') ?? $request->input('username');
        $previous = $request->headers->get('referer') ?: url()->previous();
        $retryUrl = $retryUrl ?: (($previous && $previous !== $request->fullUrl()) ? $previous : url('/'));
        $decayHours = max(1, (int) round(((int) $this->config('decay_minutes', 360)) / 60));

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'locked' => true,
                'lockout_level' => $level,
                'seconds_remaining' => $seconds,
                'retry_after' => $seconds,
            ], 429, [
                'Retry-After' => (string) $seconds,
                'Content-Type' => 'application/json',
            ]);
        }

        $viewData = [
            'seconds' => $seconds,
            'lockoutLevel' => $level,
            'message' => $message,
            'email' => is_string($email) ? $email : null,
            'ip' => $request->ip(),
            'decayHours' => $decayHours,
            'retryUrl' => $retryUrl,
        ];

        $pasakView = (string) 'errors.pasak-login-throttle';
        if (view()->exists($pasakView)) {
            return response()->view($pasakView, $viewData, 429, [
                'Retry-After' => (string) $seconds,
            ]);
        }

        $legacyView = (string) 'errors.login-throttle';
        if (view()->exists($legacyView)) {
            return response()->view($legacyView, $viewData, 429, [
                'Retry-After' => (string) $seconds,
            ]);
        }

        return response(
            $this->renderFallbackView($viewData),
            429,
            [
                'Retry-After' => (string) $seconds,
                'Content-Type' => 'text/html; charset=utf-8',
            ]
        );
    }

    /**
     * Fallback template inline jika view Blade belum dipublikasikan oleh pengguna.
     */
    public function renderFallbackView(array $data): string
    {
        $seconds = (int) ($data['seconds'] ?? 30);
        $level = (int) ($data['lockoutLevel'] ?? 1);
        $message = htmlspecialchars((string) ($data['message'] ?? 'Akses login dibatasi sementara.'), ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars((string) ($data['email'] ?? 'Kredensial Pengguna'), ENT_QUOTES, 'UTF-8');
        $ip = htmlspecialchars((string) ($data['ip'] ?? 'unknown'), ENT_QUOTES, 'UTF-8');
        $decayHours = (int) ($data['decayHours'] ?? 6);
        $retryUrl = htmlspecialchars((string) ($data['retryUrl'] ?? '/'), ENT_QUOTES, 'UTF-8');

        $active1 = $level === 1 ? 'active' : '';
        $active2 = $level === 2 ? 'active' : '';
        $active3 = $level >= 3 ? 'active' : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>({$seconds}s) Akses Login Dibatasi — Pasak</title>
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: radial-gradient(1200px 600px at 50% -10%, #1e293b 0%, #0f172a 55%, #020617 100%);
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #e2e8f0; padding: 24px;
        }
        .pasak-card {
            width: 100%; max-width: 520px; background: rgba(15, 23, 42, .88);
            backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border: 1px solid #1e293b; border-radius: 20px; padding: 32px;
            box-shadow: 0 30px 60px rgba(2, 6, 23, .65); text-align: center;
        }
        .pasak-badge-header {
            display: inline-flex; align-items: center; gap: 6px; font-weight: 700;
            letter-spacing: .08em; font-size: 11.5px; color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, .35); background: rgba(245, 158, 11, .1);
            padding: 5px 12px; border-radius: 999px; text-transform: uppercase;
        }
        .pasak-title { margin: 18px 0 8px; font-size: 22px; color: #f8fafc; font-weight: 700; }
        .pasak-desc { margin: 0 0 24px; color: #94a3b8; font-size: 13.5px; line-height: 1.6; }
        .pasak-timer-circle {
            position: relative; width: 140px; height: 140px; margin: 0 auto 20px;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            border-radius: 50%; background: rgba(11, 18, 32, .8);
            border: 3px solid rgba(6, 182, 212, .25);
            box-shadow: 0 0 25px rgba(6, 182, 212, .15);
        }
        .pasak-timer-val { font-size: 44px; font-weight: 800; color: #06b6d4; font-variant-numeric: tabular-nums; line-height: 1; }
        .pasak-timer-unit { font-size: 11px; font-weight: 600; text-transform: uppercase; color: #64748b; margin-top: 4px; letter-spacing: .05em; }
        .pasak-steps {
            display: flex; justify-content: center; gap: 8px; margin-bottom: 20px; flex-wrap: wrap;
        }
        .pasak-step-chip {
            font-size: 11px; padding: 4px 10px; border-radius: 6px; font-weight: 600;
            background: #0b1220; border: 1px solid #1e293b; color: #64748b;
        }
        .pasak-step-chip.active {
            background: rgba(6, 182, 212, .12); border-color: rgba(6, 182, 212, .4); color: #22d3ee;
        }
        .pasak-rows { display: grid; gap: 8px; margin: 0 0 24px; text-align: left; }
        .pasak-row {
            display: flex; justify-content: space-between; gap: 12px; font-size: 12.5px;
            background: #0b1220; border: 1px solid #1e293b; border-radius: 8px; padding: 9px 12px;
        }
        .pasak-row span { color: #64748b; }
        .pasak-row b { color: #cbd5e1; font-weight: 600; }
        .pasak-btn-retry {
            width: 100%; padding: 13px 18px; border: 0; border-radius: 10px;
            background: #334155; color: #94a3b8; font-weight: 700; font-size: 14px;
            cursor: not-allowed; transition: all .25s ease;
        }
        .pasak-btn-retry.ready {
            background: #06b6d4; color: #04212b; cursor: pointer;
            box-shadow: 0 0 20px rgba(6, 182, 212, .4);
        }
        .pasak-btn-retry.ready:hover { background: #22d3ee; }
        .pasak-footer-note { font-size: 12px; color: #64748b; margin-top: 16px; line-height: 1.5; }
    </style>
</head>
<body>
<main class="pasak-card">
    <div class="pasak-badge-header">⏳ Jeda Pengamanan Login Bertingkat</div>
    <h1 class="pasak-title">Akses Login Dibatasi Sementara</h1>
    <p class="pasak-desc">{$message}</p>

    <div class="pasak-timer-circle">
        <div class="pasak-timer-val" id="pasak-countdown-val">{$seconds}</div>
        <div class="pasak-timer-unit">Detik Tersisa</div>
    </div>

    <div class="pasak-steps">
        <div class="pasak-step-chip {$active1}">Tahap 1: 30s</div>
        <div class="step-chip {$active2}">Tahap 2: 60s</div>
        <div class="step-chip {$active3}">Tahap 3: 120s</div>
        <div class="step-chip">Reset: {$decayHours} Jam</div>
    </div>

    <div class="pasak-rows">
        <div class="pasak-row"><span>Status Akun</span><b style="color: #f59e0b;" id="pasak-status-label">Dalam Masa Cooldown (Level {$level})</b></div>
        <div class="row"><span>Alamat IP</span><b>{$ip}</b></div>
        <div class="row"><span>Target Akun</span><b>{$email}</b></div>
        <div class="row"><span>Periode Reset Siklus</span><b>{$decayHours} Jam Tanpa Kegagalan</b></div>
    </div>

    <button id="pasak-retry-btn" class="pasak-btn-retry" disabled onclick="window.location.href='{$retryUrl}'">
        <span id="pasak-btn-text">Tunggu Cooldown Selesai ({$seconds}s)...</span>
    </button>

    <div class="pasak-footer-note">
        Sistem membuka kembali form login secara otomatis setelah hitungan mundur berakhir tanpa perlu mengajukan tiket ke admin.
    </div>
</main>

<script>
(function() {
    var sec = {$seconds};
    var valEl = document.getElementById('pasak-countdown-val');
    var btn = document.getElementById('pasak-retry-btn');
    var btnText = document.getElementById('pasak-btn-text');
    var statusLabel = document.getElementById('pasak-status-label');
    var retryUrl = "{$retryUrl}";

    function tick() {
        if (sec <= 0) {
            if (valEl) valEl.textContent = '0';
            if (btn) {
                btn.disabled = false;
                btn.classList.add('ready');
            }
            if (btnText) btnText.textContent = '✓ Coba Login Lagi Sekarang';
            if (statusLabel) {
                statusLabel.textContent = 'Siap Mencoba Lagi';
                statusLabel.style.color = '#10b981';
            }
            document.title = '✓ Siap Login — Pasak';
            return;
        }

        if (valEl) valEl.textContent = sec;
        if (btnText) btnText.textContent = 'Tunggu (' + sec + 's)...';
        document.title = '(' + sec + 's) Cooldown Login — Pasak';
        sec--;
        setTimeout(tick, 1000);
    }

    tick();
})();
</script>
</body>
</html>
HTML;
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return $this->security->getSetting('login_lockout.'.$key, config('pasak.login_lockout.'.$key, $default));
    }

    protected function find(?string $email, ?string $ip): ?PasakLoginAttempt
    {
        if ($email === null && $ip === null) {
            return null;
        }

        try {
            $query = PasakLoginAttempt::query();

            if ($email !== null && $ip !== null) {
                return $query->where('email', $email)->where('ip_address', $ip)->first();
            }

            if ($email !== null) {
                return $query->where('email', $email)->first();
            }

            return $query->where('ip_address', $ip)->first();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Reset level kunci bila riwayat sudah terlalu lama (default 360 menit = 6 jam).
     */
    protected function decay(PasakLoginAttempt $record): void
    {
        if ($record->lockout_level === 0 && $record->attempts === 0) {
            return;
        }

        $decayMinutes = max(1, (int) $this->config('decay_minutes', 360));
        $lastAttempt = $record->last_attempt_at;

        if ($lastAttempt === null || $lastAttempt->diffInMinutes(now()) < $decayMinutes) {
            return;
        }

        // Blokir yang masih berjalan tidak dihapus oleh decay.
        if ($record->isLocked()) {
            return;
        }

        $record->attempts = 0;
        $record->lockout_level = 0;
        $record->save();
    }

    protected function recordLockout(PasakLoginAttempt $record, ?string $email, ?string $ip): void
    {
        $request = request();
        $deviceId = $request ? $this->security->resolveDeviceId($request) : null;
        $localIp = $request ? $this->security->resolveLocalIp($request) : null;
        $seconds = $this->secondsForLevel((int) $record->lockout_level);

        $this->security->log([
            'ip_address' => (string) ($ip ?? 'unknown'),
            'device_id' => $deviceId,
            'local_ip' => $localIp,
            'event_type' => 'login_lockout',
            'threat_level' => 'low',
            'method' => 'POST',
            'path' => '/login',
            'rule_label' => 'Akun terkunci sementara karena percobaan login gagal berulang (stepped cooldown)',
            'evidence' => sprintf(
                'Akun %s dari IP %s dikunci %d detik (level %d).',
                Str::limit((string) $email, 120, ''),
                $ip ?? 'unknown',
                $seconds,
                $record->lockout_level
            ),
            'user_agent' => Str::limit((string) request()?->userAgent(), 500, '') ?: null,
            'action_taken' => 'temporary_lockout',
            'meta' => [
                'lockout_level' => $record->lockout_level,
                'seconds' => $seconds,
                'locked_until' => $record->locked_until?->toIso8601String(),
            ],
        ]);

        // Jeda login bertingkat (Rate limiting) terpisah dari WAF IP auto-block
        // sehingga kegagalan login biasa tidak memblokir IP secara keras dengan 403.
    }

    /**
     * Ringkasan untuk panel keamanan.
     *
     * @return array<string, int>
     */
    public function stats(): array
    {
        return [
            'failed_today' => PasakLog::query()
                ->where('event_type', 'login_failed')
                ->where('created_at', '>=', now()->startOfDay())
                ->count(),
            'lockouts_today' => PasakLog::query()
                ->where('event_type', 'login_lockout')
                ->where('created_at', '>=', now()->startOfDay())
                ->count(),
            'active_lockouts' => PasakLoginAttempt::query()->locked()->count(),
            'tracked_accounts' => PasakLoginAttempt::query()->count(),
        ];
    }
}
