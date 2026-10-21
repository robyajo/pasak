<?php

namespace Internal\Pasak\Support;

/**
 * Legacy alias for PasakTableResolver.
 */
class TableResolver extends PasakTableResolver
{
}

if (! class_exists('Internal\Pasak\Support\TableResolver', false)) {
    class_alias(PasakTableResolver::class, 'Internal\Pasak\Support\TableResolver');
}
