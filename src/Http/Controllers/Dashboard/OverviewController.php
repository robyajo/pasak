<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

/**
 * Legacy alias for PasakOverviewController.
 */
class OverviewController extends PasakOverviewController
{
}

if (! class_exists('Internal\\Pasak\\Http\\Controllers\\Dashboard\\OverviewController', false)) {
    class_alias(PasakOverviewController::class, 'Internal\\Pasak\\Http\\Controllers\\Dashboard\\OverviewController');
}
