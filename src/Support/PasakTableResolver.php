<?php

namespace Internal\Pasak\Support;

use Illuminate\Support\Facades\Schema;

class PasakTableResolver
{
    /**
     * @var array<string, string>
     */
    protected static array $resolvedTables = [];

    /**
     * Resolve the actual database table name with seamless backward compatibility.
     *
     * 1. If user explicitly customized table name to non-standard, return it.
     * 2. If configured is prefixed (e.g. pasak_blocked_ips):
     *    - If prefixed table does not exist in DB yet, but legacy exists, use legacy.
     *    - Otherwise, use prefixed.
     * 3. If configured is legacy (e.g. user has old published config with 'blocked_ips'):
     *    - If legacy does not exist in DB, but prefixed exists (post-migration), use prefixed.
     *    - Otherwise, use legacy.
     */
    public static function resolve(string $key, string $configured, string $legacy, string $prefixed): string
    {
        if (isset(self::$resolvedTables[$key])) {
            return self::$resolvedTables[$key];
        }

        // Custom table name set by user (e.g. 'my_firewall_blocks')
        if ($configured !== $legacy && $configured !== $prefixed) {
            return self::$resolvedTables[$key] = $configured;
        }

        try {
            if ($configured === $prefixed) {
                if (! Schema::hasTable($prefixed) && Schema::hasTable($legacy)) {
                    return self::$resolvedTables[$key] = $legacy;
                }

                return self::$resolvedTables[$key] = $prefixed;
            }

            if ($configured === $legacy) {
                if (! Schema::hasTable($legacy) && Schema::hasTable($prefixed)) {
                    return self::$resolvedTables[$key] = $prefixed;
                }

                return self::$resolvedTables[$key] = $legacy;
            }
        } catch (\Throwable) {
            // Database not connected or schema uninspectable
            return $configured;
        }

        return self::$resolvedTables[$key] = $configured;
    }

    /**
     * Reset the cached resolved table names (useful for tests and migrations).
     */
    public static function clearCache(): void
    {
        self::$resolvedTables = [];
    }
}
