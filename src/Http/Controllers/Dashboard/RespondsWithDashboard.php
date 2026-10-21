<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

/**
 * Legacy trait alias for RespondsWithPasakDashboard.
 */
trait RespondsWithDashboard
{
    use RespondsWithPasakDashboard;
}

if (! class_exists('Internal\Pasak\Http\Controllers\Dashboard\RespondsWithDashboard', false) && ! trait_exists('Internal\Pasak\Http\Controllers\Dashboard\RespondsWithDashboard', false)) {
    class_alias(RespondsWithPasakDashboard::class, 'Internal\Pasak\Http\Controllers\Dashboard\RespondsWithDashboard');
}
