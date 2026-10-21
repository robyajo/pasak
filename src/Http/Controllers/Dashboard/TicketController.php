<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

/**
 * Legacy alias for PasakTicketController.
 */
class TicketController extends PasakTicketController {}

if (! class_exists('Internal\\Pasak\\Http\\Controllers\\Dashboard\\TicketController', false)) {
    class_alias(PasakTicketController::class, 'Internal\\Pasak\\Http\\Controllers\\Dashboard\\TicketController');
}
