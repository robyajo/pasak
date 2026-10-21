<?php

namespace Internal\Pasak\Http\Middleware;

/**
 * Legacy alias for PasakBlockIpAddress.
 */
class BlockIpAddress extends PasakBlockIpAddress {}

if (! class_exists('Internal\Pasak\Http\Middleware\BlockIpAddress', false)) {
    class_alias(PasakBlockIpAddress::class, 'Internal\Pasak\Http\Middleware\BlockIpAddress');
}
