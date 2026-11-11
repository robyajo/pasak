<?php

namespace Internal\Pasak\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Internal\Pasak\Models\PasakSetting;

/**
 * Pengelola rahasia URL dashboard acak (`PASAK_DASHBOARD_SECRET`) dan
 * kredensial login dashboard (`PASAK_DASHBOARD_USERNAME` & `PASAK_DASHBOARD_PASSWORD`).
 *
 * Rahasia ini dibuat sekali saat instalasi (atau lewat `pasak:secret`) dan
 * dipakai sebagai kunci HMAC untuk menandatangani token dashboard aktif pada
 * sesi sekaligus sebagai secret key untuk hashing password dashboard yang
 * disimpan ke database.
 */
class PasakDashboardSecret
{
    public const ENV_KEY = 'PASAK_DASHBOARD_SECRET';

    public const USERNAME_ENV_KEY = 'PASAK_DASHBOARD_USERNAME';

    public const PASSWORD_ENV_KEY = 'PASAK_DASHBOARD_PASSWORD';

    public const DEFAULT_USERNAME = 'admin';

    public const DEFAULT_PASSWORD = 'Admin';

    public const DB_PASSWORD_HASH_KEY = 'dashboard_password_hash';

    public const DB_USERNAME_KEY = 'dashboard_username';

    public const LENGTH = 48;

    /**
     * Buat nilai rahasia baru yang aman secara kriptografis.
     */
    public static function generate(): string
    {
        return Str::random(self::LENGTH);
    }

    /**
     * Baca nilai rahasia dari berkas env tanpa mengubahnya.
     */
    public static function readFromEnvFile(string $path): ?string
    {
        if (! File::exists($path)) {
            return null;
        }

        $content = File::get($path);
        $pattern = '/^'.preg_quote(self::ENV_KEY, '/').'=(.*)$/m';

        if (preg_match($pattern, $content, $matches) !== 1) {
            return null;
        }

        $value = self::normalize((string) ($matches[1] ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * Baca nilai username dari berkas env tanpa mengubahnya.
     */
    public static function readUsernameFromEnvFile(string $path): ?string
    {
        if (! File::exists($path)) {
            return null;
        }

        $content = File::get($path);
        $pattern = '/^'.preg_quote(self::USERNAME_ENV_KEY, '/').'=(.*)$/m';

        if (preg_match($pattern, $content, $matches) !== 1) {
            return null;
        }

        $value = self::normalize((string) ($matches[1] ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * Baca nilai default password dari berkas env tanpa mengubahnya.
     */
    public static function readPasswordFromEnvFile(string $path): ?string
    {
        if (! File::exists($path)) {
            return null;
        }

        $content = File::get($path);
        $pattern = '/^'.preg_quote(self::PASSWORD_ENV_KEY, '/').'=(.*)$/m';

        if (preg_match($pattern, $content, $matches) !== 1) {
            return null;
        }

        $value = self::normalize((string) ($matches[1] ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * Pastikan baris `PASAK_DASHBOARD_USERNAME` ada pada berkas env.
     */
    public static function ensureUsernameInEnvFile(string $path, string $default = self::DEFAULT_USERNAME): bool
    {
        if (! File::exists($path)) {
            return false;
        }

        $content = File::get($path);
        $pattern = '/^'.preg_quote(self::USERNAME_ENV_KEY, '/').'=(.*)$/m';

        if (preg_match($pattern, $content) === 1) {
            return false;
        }

        $passwordPattern = '/^('.preg_quote(self::PASSWORD_ENV_KEY, '/').'=.*)$/m';
        $secretPattern = '/^('.preg_quote(self::ENV_KEY, '/').'=.*)$/m';

        if (preg_match($passwordPattern, $content) === 1) {
            $content = (string) preg_replace(
                $passwordPattern,
                self::USERNAME_ENV_KEY.'='.$default."\n$1",
                $content,
                1,
            );
        } elseif (preg_match($secretPattern, $content) === 1) {
            $content = (string) preg_replace(
                $secretPattern,
                self::USERNAME_ENV_KEY.'='.$default."\n$1",
                $content,
                1,
            );
        } else {
            $content = rtrim($content)."\n".self::USERNAME_ENV_KEY.'='.$default."\n";
        }

        File::put($path, $content);

        return true;
    }

    /**
     * Pastikan baris `PASAK_DASHBOARD_PASSWORD` ada pada berkas env.
     */
    public static function ensurePasswordInEnvFile(string $path, string $default = self::DEFAULT_PASSWORD): bool
    {
        if (! File::exists($path)) {
            return false;
        }

        $content = File::get($path);
        $pattern = '/^'.preg_quote(self::PASSWORD_ENV_KEY, '/').'=(.*)$/m';

        if (preg_match($pattern, $content) === 1) {
            return false;
        }

        $secretPattern = '/^('.preg_quote(self::ENV_KEY, '/').'=.*)$/m';
        if (preg_match($secretPattern, $content) === 1) {
            $content = (string) preg_replace(
                $secretPattern,
                self::PASSWORD_ENV_KEY.'='.$default."\n$1",
                $content,
                1,
            );
        } else {
            $content = rtrim($content)."\n".self::PASSWORD_ENV_KEY.'='.$default."\n";
        }

        File::put($path, $content);

        return true;
    }

    /**
     * Pastikan baris `PASAK_DASHBOARD_SECRET` ada pada berkas env.
     *
     * - `$generate = true`: isi kosong / baris hilang / `--force` dibuatkan
     *   nilai baru.
     * - `$generate = false`: hanya memastikan baris ada, dibiarkan kosong
     *   (dipakai untuk `.env.example` agar rahasia tidak ikut ter-commit).
     *
     * @return array{exists: bool, changed: bool, generated: bool, secret: ?string}
     */
    public static function ensureInEnvFile(
        string $path,
        bool $force = false,
        bool $generate = true,
    ): array {
        $result = [
            'exists' => File::exists($path),
            'changed' => false,
            'generated' => false,
            'secret' => null,
        ];

        if (! $result['exists']) {
            return $result;
        }

        // Pastikan juga baris PASAK_DASHBOARD_USERNAME & PASAK_DASHBOARD_PASSWORD ada
        self::ensureUsernameInEnvFile($path);
        self::ensurePasswordInEnvFile($path);

        $content = File::get($path);
        $pattern = '/^'.preg_quote(self::ENV_KEY, '/').'=(.*)$/m';
        $hasLine = preg_match($pattern, $content, $matches) === 1;
        $current = $hasLine
            ? self::normalize((string) ($matches[1] ?? ''))
            : null;
        if ($current === '') {
            $current = null;
        }
        $result['secret'] = $current;

        $needsValue = $force ||
            ! $hasLine ||
            $current === null;

        if ($generate && $needsValue) {
            $secret = self::generate();

            if ($hasLine) {
                $content = (string) preg_replace(
                    $pattern,
                    self::ENV_KEY.'='.$secret,
                    $content,
                    1,
                );
            } else {
                $content = rtrim($content)."\n".self::ENV_KEY.'='.$secret."\n";
            }

            File::put($path, $content);

            $result['changed'] = true;
            $result['generated'] = true;
            $result['secret'] = $secret;

            return $result;
        }

        if (! $hasLine) {
            $content = rtrim($content)."\n".self::ENV_KEY.'='."\n";
            File::put($path, $content);
            $result['changed'] = true;

            return $result;
        }

        if (! $generate && $hasLine) {
            // Contoh konfigurasi tidak boleh menyimpan rahasia nyata, termasuk
            // nilai warisan instalasi lama yang mungkin sudah ter-commit.
            $content = (string) preg_replace(
                $pattern,
                self::ENV_KEY.'=',
                $content,
                1,
            );

            if ($content !== File::get($path)) {
                File::put($path, $content);
                $result['changed'] = true;
            }

            $result['secret'] = null;

            return $result;
        }

        return $result;
    }

    /**
     * Hash password dasbor menggunakan HMAC SHA-256 dengan secret key PASAK_DASHBOARD_SECRET.
     */
    public static function hashPassword(string $password, string $secret): string
    {
        return hash_hmac('sha256', $password, $secret);
    }

    /**
     * Dapatkan hash password yang tersimpan di database (tabel pasak_settings).
     */
    public static function getStoredPasswordHash(): ?string
    {
        try {
            return PasakSetting::query()
                ->where('key', self::DB_PASSWORD_HASH_KEY)
                ->value('value');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Simpan hash password baru ke database (tabel pasak_settings).
     */
    public static function storePasswordInDatabase(string $password, string $secret): void
    {
        $hash = self::hashPassword($password, $secret);

        PasakSetting::query()->updateOrCreate(
            ['key' => self::DB_PASSWORD_HASH_KEY],
            ['value' => $hash],
        );
    }

    /**
     * Periksa apakah password sudah pernah diperbarui dan disimpan di database.
     */
    public static function isPasswordStoredInDatabase(): bool
    {
        $hash = self::getStoredPasswordHash();

        return ! empty($hash);
    }

    /**
     * Verifikasi password input terhadap database, default bawaan, atau secret darurat.
     *
     * @return array{valid: bool, is_default: bool, is_emergency: bool}
     */
    public static function verifyPassword(
        string $inputPassword,
        string $secret,
        string $defaultPassword = self::DEFAULT_PASSWORD,
    ): array {
        $storedHash = self::getStoredPasswordHash();
        $isEmergencySecret = $secret !== '' && hash_equals($secret, $inputPassword);

        // 1. Jika password kustom sudah pernah disimpan di database
        if (! empty($storedHash)) {
            $calculated = self::hashPassword($inputPassword, $secret);
            $isCustomValid = hash_equals($storedHash, $calculated);
            $valid = $isCustomValid || $isEmergencySecret;

            return [
                'valid' => $valid,
                'is_default' => false,
                'is_emergency' => $isEmergencySecret && ! $isCustomValid,
            ];
        }

        // 2. Jika belum disimpan di database, periksa terhadap default password atau secret
        $isDefault = hash_equals($defaultPassword, $inputPassword);
        $valid = $isDefault || $isEmergencySecret;

        return [
            'valid' => $valid,
            'is_default' => $isDefault,
            'is_emergency' => $isEmergencySecret && ! $isDefault,
        ];
    }

    /**
     * Buat format tampilan untuk konsol (mis. baris penugasan env).
     */
    public static function formatEnvLine(string $secret): string
    {
        return self::ENV_KEY.'='.$secret;
    }

    /**
     * Normalisasi nilai dari berkas .env: buang petik dan whitespace luar.
     */
    protected static function normalize(string $raw): string
    {
        $trimmed = trim($raw);

        // Jika dibungkus tanda kutip tunggal/ganda
        if (
            (str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"')) ||
            (str_starts_with($trimmed, "'") && str_ends_with($trimmed, "'"))
        ) {
            return trim(substr($trimmed, 1, -1));
        }

        // Potong komentar inline (# ...) jika ada
        if (str_contains($trimmed, '#')) {
            $parts = explode('#', $trimmed, 2);
            $trimmed = trim($parts[0]);
        }

        return $trimmed;
    }
}
