<?php

namespace Internal\Pasak\Http\Controllers\Api;

/**
 * Legacy alias for PasakSecuritySettingApiController.
 */
class SecuritySettingApiController extends PasakSecuritySettingApiController
{
}

if (! class_exists('Internal\\Pasak\\Http\\Controllers\\Api\\SecuritySettingApiController', false)) {
    class_alias(PasakSecuritySettingApiController::class, 'Internal\\Pasak\\Http\\Controllers\\Api\\SecuritySettingApiController');
}
