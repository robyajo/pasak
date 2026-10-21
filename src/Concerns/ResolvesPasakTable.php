<?php

namespace Internal\Pasak\Concerns;

use Internal\Pasak\Support\PasakTableResolver;

trait ResolvesPasakTable
{
    /**
     * Resolve the actual database table name with seamless backward compatibility.
     */
    public static function resolvePasakTable(string $key, string $configured, string $legacy, string $prefixed): string
    {
        return PasakTableResolver::resolve($key, $configured, $legacy, $prefixed);
    }

    /**
     * Reset the cached resolved table names (useful for tests and migrations).
     */
    public static function clearResolvedTableCache(): void
    {
        PasakTableResolver::clearCache();
    }
}
