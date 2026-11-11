<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Internal\Pasak\Models\PasakSetting;
use Internal\Pasak\Services\PasakLoginThrottleService;
use Internal\Pasak\Support\PasakDashboardSecret;

class PasakAuthController
{
    /**
     * Tampilkan formulir login mandiri dashboard Pasak.
     */
    public function showLoginForm(Request $request)
    {
        // Jika sudah login, langsung alihkan ke overview atau URL tujuan
        if ($request->hasSession() && $request->session()->get('pasak_dashboard_authenticated') === true) {
            $prefix = trim((string) config('pasak.dashboard.prefix', 'pasak'), '/');

            return redirect()->to(url($prefix));
        }

        $throttle = app(PasakLoginThrottleService::class);
        $lockedAttempt = $throttle->enabled() ? $throttle->activeLockoutForIp($request->ip()) : null;

        $secret = $this->resolveExpectedSecret();
        $secretConfigured = $secret !== '';

        /** @var view-string $viewName */
        $viewName = 'pages.pasak.login';

        return view($viewName, [
            'secretConfigured' => $secretConfigured,
            'defaultUsername' => $this->resolveExpectedUsername(),
            'defaultPassword' => $this->resolveDefaultPassword(),
            'isPasswordStored' => PasakDashboardSecret::isPasswordStoredInDatabase(),
            'lockout' => $lockedAttempt ? [
                'seconds' => $lockedAttempt->secondsRemaining(),
                'level' => $lockedAttempt->lockout_level,
                'message' => $throttle->lockoutMessage($lockedAttempt->secondsRemaining()),
            ] : null,
        ]);
    }

    /**
     * Proses pengiriman kredensial login (username & secret password).
     */
    public function login(Request $request)
    {
        $throttle = app(PasakLoginThrottleService::class);
        $username = (string) $request->input('username');
        $ip = (string) $request->ip();

        if ($throttle->enabled()) {
            $status = $throttle->status($username, $ip);
            if ($status['locked']) {
                $msg = $status['message'] ?? $throttle->lockoutMessage($status['seconds']);

                return back()
                    ->withInput($request->only('username'))
                    ->withErrors([
                        'pasak_login' => $msg,
                        'username' => $msg,
                    ]);
            }
        }

        $throttleKey = 'pasak_login_throttle:'.Str::lower($request->ip());

        // Perlindungan brute-force: maksimal 5 percobaan gagal per 60 detik
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $msg = __('Terlalu banyak percobaan login gagal. Silakan coba lagi dalam :seconds detik.', ['seconds' => $seconds]);

            return back()
                ->withInput($request->only('username'))
                ->withErrors([
                    'pasak_login' => $msg,
                    'username' => $msg,
                ]);
        }

        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $expectedUser = $this->resolveExpectedUsername();
        $expectedSecret = $this->resolveExpectedSecret();
        $defaultPassword = $this->resolveDefaultPassword();

        // Jika secret belum terisi di .env
        if ($expectedSecret === '') {
            $msg = __('PASAK_DASHBOARD_SECRET belum dikonfigurasi pada .env. Jalankan "php artisan pasak:secret" terlebih dahulu.');

            return back()
                ->withInput($request->only('username'))
                ->withErrors([
                    'pasak_login' => $msg,
                    'password' => $msg,
                ]);
        }

        $inputUser = (string) $request->input('username');
        $inputPassword = (string) $request->input('password');

        $userValid = hash_equals($expectedUser, $inputUser);
        $passResult = PasakDashboardSecret::verifyPassword($inputPassword, $expectedSecret, $defaultPassword);

        if (! $userValid || ! $passResult['valid']) {
            RateLimiter::hit($throttleKey, 60);

            if ($throttle->enabled()) {
                $failStatus = $throttle->registerFailure($username, $ip);
                if ($failStatus['locked']) {
                    $msg = $failStatus['message'] ?? $throttle->lockoutMessage($failStatus['seconds']);

                    return back()
                        ->withInput($request->only('username'))
                        ->withErrors([
                            'pasak_login' => $msg,
                            'username' => $msg,
                            'password' => $msg,
                        ]);
                }
            }
            $msg = __('Username atau password salah. Periksa kredensial pada berkas .env Anda.');

            return back()
                ->withInput($request->only('username'))
                ->withErrors([
                    'pasak_login' => $msg,
                    'password' => $msg,
                ]);
        }

        // Login berhasil, bersihkan riwayat throttle
        RateLimiter::clear($throttleKey);
        if ($throttle->enabled()) {
            $throttle->registerSuccess($inputUser, $ip);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
            $request->session()->put('pasak_dashboard_authenticated', true);
            $request->session()->put('pasak_dashboard_user', $inputUser);
            $request->session()->put('pasak_dashboard_login_at', now()->timestamp);

            // Jika login menggunakan secret darurat atau password default, minta ganti password
            if (! empty($passResult['is_emergency']) || ! empty($passResult['is_default'])) {
                $request->session()->put('pasak_dashboard_needs_password_change', true);
                if (! empty($passResult['is_emergency'])) {
                    $request->session()->put('pasak_dashboard_is_emergency_login', true);
                } else {
                    $request->session()->forget('pasak_dashboard_is_emergency_login');
                }
            } else {
                $request->session()->forget(['pasak_dashboard_needs_password_change', 'pasak_dashboard_is_emergency_login']);
                $request->session()->forget('pasak_dashboard_is_emergency_login');
            }
        }

        $intended = $request->session()->pull('pasak_dashboard_intended');
        $prefix = trim((string) config('pasak.dashboard.prefix', 'pasak'), '/');

        // Pastikan URL tujuan memang mengarah ke area dashboard Pasak
        if (! empty($intended) && (str_contains($intended, '/'.$prefix) || str_contains($intended, $prefix.'-'))) {
            return redirect()->to($intended);
        }

        return redirect()->to(url($prefix));
    }

    /**
     * Perbarui password dasbor dan simpan hash HMAC dengan PASAK_DASHBOARD_SECRET ke database.
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $secret = $this->resolveExpectedSecret();
        if ($secret === '') {
            return back()->with('toast', [
                'type' => 'error',
                'message' => __('PASAK_DASHBOARD_SECRET belum dikonfigurasi pada .env.'),
            ]);
        }

        $newPassword = (string) $request->input('password');

        // Simpan hash password ke database
        PasakDashboardSecret::storePasswordInDatabase($newPassword, $secret);

        // Bersihkan tanda kebutuhan ganti password
        if ($request->hasSession()) {
            $request->session()->forget(['pasak_dashboard_needs_password_change', 'pasak_dashboard_is_emergency_login']);
        }

        $successMsg = __('Password dasbor berhasil diperbarui dan disimpan aman di database.');

        return back()->with('toast', [
            'type' => 'success',
            'message' => $successMsg,
        ])->with('status', $successMsg);
    }

    /**
     * Logout dari sesi dashboard Pasak.
     */
    public function logout(Request $request)
    {
        if ($request->hasSession()) {
            $request->session()->forget([
                'pasak_dashboard_authenticated',
                'pasak_dashboard_user',
                'pasak_dashboard_login_at',
                'pasak_dashboard_intended',
                'pasak_dashboard_needs_password_change',
            ]);
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('pasak.dashboard.login')->with('status', __('Sesi dashboard Pasak telah diakhiri.'));
    }

    /**
     * Dapatkan secret password yang diharapkan dari config atau berkas .env.
     */
    protected function resolveExpectedSecret(): string
    {
        $secret = (string) config('pasak.dashboard.secret');
        if ($secret !== '') {
            return $secret;
        }

        return (string) (PasakDashboardSecret::readFromEnvFile(base_path('.env')) ?? '');
    }

    /**
     * Dapatkan username yang diharapkan dari database, config, atau berkas .env.
     */
    protected function resolveExpectedUsername(): string
    {
        try {
            $dbUser = PasakSetting::query()
                ->where('key', PasakDashboardSecret::DB_USERNAME_KEY)
                ->value('value');
            if (! empty($dbUser)) {
                return (string) $dbUser;
            }
        } catch (\Throwable) {
        }

        $username = (string) config('pasak.dashboard.auth.username');
        if ($username !== '') {
            return $username;
        }

        return (string) (PasakDashboardSecret::readUsernameFromEnvFile(base_path('.env')) ?? PasakDashboardSecret::DEFAULT_USERNAME);
    }

    /**
     * Dapatkan default password awal dari config atau berkas .env.
     */
    protected function resolveDefaultPassword(): string
    {
        $password = (string) config('pasak.dashboard.auth.default_password');
        if ($password !== '') {
            return $password;
        }

        return (string) (PasakDashboardSecret::readPasswordFromEnvFile(base_path('.env')) ?? PasakDashboardSecret::DEFAULT_PASSWORD);
    }
}
