<?php

namespace Internal\Pasak\Http\Middleware;

/**
 * Legacy alias for PasakDetectSecurityThreats.
 */
class DetectSecurityThreats extends PasakDetectSecurityThreats {}

if (! class_exists('Internal\Pasak\Http\Middleware\DetectSecurityThreats', false)) {
    class_alias(PasakDetectSecurityThreats::class, 'Internal\Pasak\Http\Middleware\DetectSecurityThreats');
}
