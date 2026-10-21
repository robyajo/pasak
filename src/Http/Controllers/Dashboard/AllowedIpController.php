<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

/**
 * Legacy alias for PasakAllowedIpController.
 */
class AllowedIpController extends PasakAllowedIpController {}

if (! class_exists('Internal\\Pasak\\Http\\Controllers\\Dashboard\\AllowedIpController', false)) {
    class_alias(PasakAllowedIpController::class, 'Internal\\Pasak\\Http\\Controllers\\Dashboard\\AllowedIpController');
}
