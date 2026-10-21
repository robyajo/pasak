<?php

namespace Internal\Pasak\Models;

/**
 * @deprecated Use PasakSetting instead.
 *
 * @mixin PasakSetting
 */
if (! class_exists(SecuritySetting::class, false)) {
    class_alias(PasakSetting::class, SecuritySetting::class);
}
