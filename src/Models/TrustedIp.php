<?php

namespace Internal\Pasak\Models;

/**
 * @deprecated Use PasakTrustedIp instead.
 *
 * @mixin PasakTrustedIp
 */
if (! class_exists(TrustedIp::class, false)) {
    class_alias(PasakTrustedIp::class, TrustedIp::class);
}
