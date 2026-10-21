<?php

namespace Internal\Pasak\Models;

/**
 * @deprecated Use PasakLog instead.
 *
 * @mixin PasakLog
 */
if (! class_exists(SecurityLog::class, false)) {
    class_alias(PasakLog::class, SecurityLog::class);
}
