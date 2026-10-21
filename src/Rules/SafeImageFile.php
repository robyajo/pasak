<?php

namespace Internal\Pasak\Rules;

/**
 * Legacy alias for PasakSafeImageFile.
 */
class SafeImageFile extends PasakSafeImageFile {}

if (! class_exists('Internal\Pasak\Rules\SafeImageFile', false)) {
    class_alias(PasakSafeImageFile::class, 'Internal\Pasak\Rules\SafeImageFile');
}
