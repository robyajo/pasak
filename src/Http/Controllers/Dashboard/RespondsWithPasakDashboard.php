<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;

/**
 * Render dashboard pages as plain Blade views.
 *
 * Pasak hanya mendukung dashboard Blade murni (100% Pure PHP, tanpa
 * Livewire/Inertia/Vue).
 */
trait RespondsWithPasakDashboard
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function dashboardResponse(string $blade, array $data): View
    {
        if (! view()->exists($blade)) {
            abort(500, 'Dashboard views are not published. Run: php artisan pasak:install --with-dashboard');
        }

        return view($blade, $data);
    }
}
