<?php

namespace Internal\Pasak\Support;

/**
 * Legacy alias for PasakDashboardView.
 */
class DashboardView extends PasakDashboardView {}

if (! class_exists('Internal\Pasak\Support\DashboardView', false)) {
    class_alias(PasakDashboardView::class, 'Internal\Pasak\Support\DashboardView');
}
