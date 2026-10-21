<?php

namespace Internal\Pasak\Http\Controllers\Api;

/**
 * Legacy alias for PasakSecurityLogApiController.
 */
class SecurityLogApiController extends PasakSecurityLogApiController
{
}

if (! class_exists('Internal\\Pasak\\Http\\Controllers\\Api\\SecurityLogApiController', false)) {
    class_alias(PasakSecurityLogApiController::class, 'Internal\\Pasak\\Http\\Controllers\\Api\\SecurityLogApiController');
}
