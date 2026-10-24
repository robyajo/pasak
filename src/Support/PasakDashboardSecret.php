<?php

namespace Internal\Pasak\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Pengelola rahasia URL dashboard acak (`PASAK_DASHBOARD_SECRET`).
 *
 * Rahasia ini dibuat sekali saat instalasi (atau lewat `pasak:secret`) dan
 * dipakai sebagai kunci HMAC untuk menandatangani token dashboard aktif pada
 * sesi. Nilainya HARUS berbeda per aplikasi dan tidak boleh ikut ter-commit —
 * karena itu `.env.example` hanya diberi baris kosong, sedangkan nilai nyata
 * hanya ditulis ke `.env`.
 */
class PasakDashboardSecret
{
    public const ENV_KEY = 'PASAK_DASHBOARD_SECRET';

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

        return self::normalize((string) ($matches[1] ?? ''));
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

        $content = File::get($path);
        $pattern = '/^'.preg_quote(self::ENV_KEY, '/').'=(.*)$/m';
        $hasLine = preg_match($pattern, $content, $matches) === 1;
        $current = $hasLine
            ? self::normalize((string) ($matches[1] ?? ''))
            : null;
        $result['secret'] = $current;

        $needsValue = $force ||
            ! $hasLine ||
            $current === null ||
            $current === '';

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

        if (! $generate && $hasLine && $current !== null) {
            // Contoh konfigurasi tidak boleh menyimpan rahasia nyata, termasuk
            // nilai warisan instalasi lama yang mungkin sudah ter-commit.
            $content = (string) preg_replace(
                $pattern,
                self::ENV_KEY.'=',
                $content,
                1,
            );

            File::put($path, $content);

            $result['changed'] = true;
            $result['secret'] = null;

            return $result;
        }

        if (! $hasLine) {
            $content = rtrim($content)."\n".self::ENV_KEY."=\n";
            File::put($path, $content);

            $result['changed'] = true;
        }

        return $result;
    }

    protected static function normalize(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (
            strlen($value) >= 2 &&
            (($value[0] === '"' && $value[-1] === '"') ||
                ($value[0] === "'" && $value[-1] === "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        return $value === '' ? null : $value;
    }
}
