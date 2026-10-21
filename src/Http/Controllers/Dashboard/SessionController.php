<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

/**
 * Legacy alias for PasakSessionController.
 */
class SessionController extends PasakSessionController
{
}

if (! class_exists('Internal\\Pasak\\Http\\Controllers\\Dashboard\\SessionController', false)) {
    class_alias(PasakSessionController::class, 'Internal\\Pasak\\Http\\Controllers\\Dashboard\\SessionController');
}
