<?php

namespace Internal\Pasak\Http\Controllers\Api;

/**
 * Legacy alias for PasakBlockedIpApiController.
 */
class BlockedIpApiController extends PasakBlockedIpApiController
{
}

if (! class_exists('Internal\\Pasak\\Http\\Controllers\\Api\\BlockedIpApiController', false)) {
    class_alias(PasakBlockedIpApiController::class, 'Internal\\Pasak\\Http\\Controllers\\Api\\BlockedIpApiController');
}
