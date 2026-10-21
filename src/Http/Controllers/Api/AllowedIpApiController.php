<?php

namespace Internal\Pasak\Http\Controllers\Api;

/**
 * Legacy alias for PasakAllowedIpApiController.
 */
class AllowedIpApiController extends PasakAllowedIpApiController {}

if (! class_exists('Internal\\Pasak\\Http\\Controllers\\Api\\AllowedIpApiController', false)) {
    class_alias(PasakAllowedIpApiController::class, 'Internal\\Pasak\\Http\\Controllers\\Api\\AllowedIpApiController');
}
