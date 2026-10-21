<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

/**
 * Legacy alias for PasakLogController.
 */
class LogController extends PasakLogController {}

if (! class_exists('Internal\\Pasak\\Http\\Controllers\\Dashboard\\LogController', false)) {
    class_alias(PasakLogController::class, 'Internal\\Pasak\\Http\\Controllers\\Dashboard\\LogController');
}
