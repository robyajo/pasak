<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

/**
 * Legacy alias for PasakBlockedIpController.
 */
class BlockedIpController extends PasakBlockedIpController {}

if (! class_exists('Internal\\Pasak\\Http\\Controllers\\Dashboard\\BlockedIpController', false)) {
    class_alias(PasakBlockedIpController::class, 'Internal\\Pasak\\Http\\Controllers\\Dashboard\\BlockedIpController');
}
