<?php

namespace Internal\Pasak\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Layanan URL dashboard acak sekali pakai (moving-target).
 *
 * Saat "pasak.dashboard.rotating" aktif, dashboard tidak lagi memakai prefix
 * statis "/pasak", melainkan "/pasak-{token}" dengan token acak per sesi yang
 * diputar (rotate) setiap akses HTML. URL lama hanya berlaku selama masa
 * tenggang ("grace_seconds") dan — untuk GET HTML — dialihkan (302) ke URL
 * baru sehingga address bar ikut berubah di setiap reload:
 *
 *   /pasak              → 302 → /pasak-a1b2c3d4e5f6/
 *   /pasak-a1b2c3d4e5f6/logs → 200 (link di halaman memakai token baru)
 *   reload /pasak-a1b2c3d4e5f6/logs → 302 → /pasak-baru/logs
 */
class PasakDashboardUrlService
{
    public const SESSION_KEY = 'pasak.dashboard_slug';

    public const PREV_KEY = 'pasak.dashboard_prev_slug';

    public const PREV_AT_KEY = 'pasak.dashboard_prev_at';

    public const MAC_KEY = 'pasak.dashboard_slug_mac';

    public const PREV_MAC_KEY = 'pasak.dashboard_prev_slug_mac';

    public function isRotatingEnabled(): bool
    {
        return (bool) config('pasak.dashboard.rotating', false) &&
            (bool) config('pasak.dashboard.enabled', false);
    }

    public function basePrefix(): string
    {
        $prefix = trim((string) config('pasak.dashboard.prefix', 'pasak'), '/');

        return $prefix === '' ? 'pasak' : $prefix;
    }

    /**
     * URL pintu masuk stabil dashboard (misal: "http://localhost:8000/pasak").
     */
    public function entryUrl(string $path = ''): string
    {
        $prefix = $this->basePrefix();
        $trimmedPath = ltrim($path, '/');
        $target = $trimmedPath === '' ? $prefix : $prefix.'/'.$trimmedPath;

        return url($target);
    }

    /**
     * Path pintu masuk stabil dashboard (misal: "/pasak").
     */
    public function entryPath(string $path = ''): string
    {
        $prefix = $this->basePrefix();
        $trimmedPath = ltrim($path, '/');

        return '/'.($trimmedPath === '' ? $prefix : $prefix.'/'.$trimmedPath);
    }

    public function tokenLength(): int
    {
        return max(
            12,
            min(32, (int) config('pasak.dashboard.token_length', 16)),
        );
    }

    public function graceSeconds(): int
    {
        return max(15, (int) config('pasak.dashboard.grace_seconds', 120));
    }

    public function isSingleUse(): bool
    {
        return (bool) config('pasak.dashboard.single_use', true);
    }

    /**
     * Buat token acak alfanumerik (huruf kecil + digit) seperti contoh
     * "95856857476jdhjs5".
     */
    public function generateToken(): string
    {
        return strtolower(Str::random($this->tokenLength()));
    }

    public function generateSlug(): string
    {
        return $this->basePrefix().'-'.$this->generateToken();
    }

    /**
     * Pola where() untuk parameter {pasakSlug}, mis. "pasak-[A-Za-z0-9]{12,32}".
     */
    public function slugPattern(): string
    {
        return preg_quote($this->basePrefix(), '/').'-[A-Za-z0-9]{12,32}';
    }

    public function matchesFormat(string $slug): bool
    {
        return (bool) preg_match('/^'.$this->slugPattern().'$/', $slug);
    }

    /**
     * Ambil slug aktif sesi ini; buat baru bila belum ada.
     */
    public function currentSlug(Request $request): string
    {
        $stored = $this->readSession($request, self::SESSION_KEY);

        if (
            is_string($stored) &&
            $this->matchesFormat($stored) &&
            $this->hasValidMac($request, $stored, self::MAC_KEY)
        ) {
            return $stored;
        }

        $slug = $this->generateSlug();
        $this->writeSession($request, self::SESSION_KEY, $slug);
        $this->writeMac($request, $slug, self::MAC_KEY);

        return $slug;
    }

    public function previousSlug(Request $request): ?string
    {
        $stored = $this->previousSlugValue($request);

        if ($stored === null) {
            return null;
        }

        $at = $this->readSession($request, self::PREV_AT_KEY);

        if (is_numeric($at) && time() - (int) $at > $this->graceSeconds()) {
            return null;
        }

        return $stored;
    }

    /**
     * Slug "previous" apa adanya, tanpa memedulikan masa tenggang.
     *
     * Dipakai untuk mengenali token lama sesi sendiri yang sudah kedaluwarsa
     * sehingga permintaan bisa dialihkan ke token aktif, bukan dijawab 404.
     */
    public function previousSlugValue(Request $request): ?string
    {
        $stored = $this->readSession($request, self::PREV_KEY);

        if (
            ! is_string($stored) ||
            ! $this->matchesFormat($stored) ||
            ! $this->hasValidMac($request, $stored, self::PREV_MAC_KEY)
        ) {
            return null;
        }

        return $stored;
    }

    public function isCurrentSlug(Request $request, string $slug): bool
    {
        return hash_equals($this->currentSlug($request), $slug);
    }

    public function isPreviousSlug(Request $request, string $slug): bool
    {
        $prev = $this->previousSlug($request);

        return $prev !== null && hash_equals($prev, $slug);
    }

    public function isValidSlug(Request $request, string $slug): bool
    {
        if (! $this->matchesFormat($slug)) {
            return false;
        }

        return $this->isCurrentSlug($request, $slug) ||
            $this->isPreviousSlug($request, $slug);
    }

    /**
     * Putar token: slug lama jadi "previous" (masa tenggang), slug baru aktif.
     * Mengembalikan slug baru dan menyetel URL::defaults agar route()
     * otomatis memakai token baru tanpa mengubah file Blade.
     */
    public function rotate(Request $request): string
    {
        $current = $this->currentSlug($request);
        $next = $this->generateSlug();

        // Hindari tabrakan token yang sama persis (sangat jarang).
        if (hash_equals($current, $next)) {
            $next = $this->generateSlug();
        }

        $this->writeSession($request, self::PREV_KEY, $current);
        $this->writeSession($request, self::PREV_AT_KEY, time());
        $this->writeMac($request, $current, self::PREV_MAC_KEY);
        $this->writeSession($request, self::SESSION_KEY, $next);
        $this->writeMac($request, $next, self::MAC_KEY);
        $this->applyUrlDefaults($next);

        return $next;
    }

    /**
     * Pastikan URL::defaults + config runtime memakai slug tertentu sehingga
     * seluruh route('pasak.dashboard.*') di Blade merender token terbaru.
     */
    public function applyUrlDefaults(string $slug): void
    {
        URL::defaults(['pasakSlug' => $slug]);

        if (function_exists('view') && app()->bound('view')) {
            try {
                view()->share('pasakDashboardSlug', $slug);
            } catch (\Throwable) {
                // Abaikan — share view tidak boleh menggagalkan request.
            }
        }
    }

    /**
     * Bangun URL dashboard untuk path relatif, mis. urlFor($request, 'logs').
     */
    public function urlFor(Request $request, string $path = ''): string
    {
        $slug = $this->isRotatingEnabled()
            ? $this->currentSlug($request)
            : $this->basePrefix();
        $path = ltrim($path, '/');

        return url($slug.($path === '' ? '' : '/'.$path));
    }

    /**
     * Rahasia HMAC per aplikasi; kosong = mode kompatibilitas lama (token
     * hanya terikat sesi tanpa tanda tangan).
     */
    protected function secret(): string
    {
        return trim((string) config('pasak.dashboard.secret', ''));
    }

    protected function sign(string $slug): ?string
    {
        $secret = $this->secret();

        if ($secret === '') {
            return null;
        }

        return hash_hmac('sha256', $slug, $secret);
    }

    protected function writeMac(Request $request, string $slug, string $key): void
    {
        $mac = $this->sign($slug);

        if ($mac === null) {
            return;
        }

        $this->writeSession($request, $key, $mac);
    }

    /**
     * Validasi tanda tangan token. Bila secret belum diisi (instalasi lama),
     * token lama tetap diterima agar pengguna tidak terlempar; setelah
     * `pasak:secret` dijalankan, semua token otomatis ditandatangani ulang.
     */
    protected function hasValidMac(Request $request, string $slug, string $key): bool
    {
        $expected = $this->sign($slug);

        if ($expected === null) {
            return true;
        }

        $stored = $this->readSession($request, $key);

        return is_string($stored) &&
            $stored !== '' &&
            hash_equals($expected, $stored);
    }

    protected function readSession(Request $request, string $key): mixed
    {
        try {
            if (! $request->hasSession()) {
                return null;
            }

            return $request->session()->get($key);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function writeSession(
        Request $request,
        string $key,
        mixed $value,
    ): void {
        try {
            if ($request->hasSession()) {
                $request->session()->put($key, $value);
            }
        } catch (\Throwable) {
            // Abaikan — tanpa sesi, validasi jatuh ke 404 yang aman.
        }
    }
}
