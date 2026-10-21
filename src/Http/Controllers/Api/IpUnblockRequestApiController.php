<?php

namespace Internal\Pasak\Http\Controllers\Api;

/**
 * Legacy alias for PasakIpUnblockRequestApiController.
 */
class IpUnblockRequestApiController extends PasakIpUnblockRequestApiController
{
}

if (! class_exists('Internal\\Pasak\\Http\\Controllers\\Api\\IpUnblockRequestApiController', false)) {
    class_alias(PasakIpUnblockRequestApiController::class, 'Internal\\Pasak\\Http\\Controllers\\Api\\IpUnblockRequestApiController');
}
