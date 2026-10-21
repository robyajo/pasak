<?php

namespace Internal\Pasak\Http\Controllers\Api;

/**
 * Legacy alias for PasakUserSessionApiController.
 */
class UserSessionApiController extends PasakUserSessionApiController {}

if (! class_exists('Internal\\Pasak\\Http\\Controllers\\Api\\UserSessionApiController', false)) {
    class_alias(PasakUserSessionApiController::class, 'Internal\\Pasak\\Http\\Controllers\\Api\\UserSessionApiController');
}
