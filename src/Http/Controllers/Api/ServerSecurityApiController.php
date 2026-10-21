<?php

namespace Internal\Pasak\Http\Controllers\Api;

/**
 * Legacy alias for PasakServerSecurityApiController.
 */
class ServerSecurityApiController extends PasakServerSecurityApiController
{
}

if (! class_exists('Internal\\Pasak\\Http\\Controllers\\Api\\ServerSecurityApiController', false)) {
    class_alias(PasakServerSecurityApiController::class, 'Internal\\Pasak\\Http\\Controllers\\Api\\ServerSecurityApiController');
}
