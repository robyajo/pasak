<?php

namespace Internal\Pasak\Rules;

/**
 * Legacy alias for PasakSafeAssetPath.
 */
class SafeAssetPath extends PasakSafeAssetPath {}

if (! class_exists('Internal\Pasak\Rules\SafeAssetPath', false)) {
    class_alias(PasakSafeAssetPath::class, 'Internal\Pasak\Rules\SafeAssetPath');
}
