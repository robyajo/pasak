<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

/**
 * Legacy alias for PasakServerController.
 */
class ServerController extends PasakServerController {}

if (! class_exists('Internal\\Pasak\\Http\\Controllers\\Dashboard\\ServerController', false)) {
    class_alias(PasakServerController::class, 'Internal\\Pasak\\Http\\Controllers\\Dashboard\\ServerController');
}
