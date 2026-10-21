<?php

namespace Internal\Pasak\Models;

/**
 * @deprecated Use PasakAllowedIp instead.
 *
 * @mixin PasakAllowedIp
 */
if (! class_exists(AllowedIp::class, false)) {
    class_alias(PasakAllowedIp::class, AllowedIp::class);
}
