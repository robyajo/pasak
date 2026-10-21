<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

/**
 * Legacy alias for PasakSettingController.
 */
class SettingController extends PasakSettingController {}

if (! class_exists('Internal\\Pasak\\Http\\Controllers\\Dashboard\\SettingController', false)) {
    class_alias(PasakSettingController::class, 'Internal\\Pasak\\Http\\Controllers\\Dashboard\\SettingController');
}
