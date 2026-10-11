<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Render dashboard pages as plain Blade views on apps without a
 * JavaScript stack (driver "blade"), otherwise via Inertia + React.
 */
trait RespondsWithDashboard
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function dashboardResponse(string $blade, string $inertia, array $data): View|InertiaResponse
    {
        $driver = strtolower((string) config('pasak.dashboard.driver', 'blade'));
        $hasView = view()->exists($blade);
        $hasInertia = class_exists(Inertia::class);

        if ($hasView && (in_array($driver, ['blade', 'plain'], true) || ! $hasInertia)) {
            return view($blade, $data);
        }

        if ($hasInertia) {
            return Inertia::render($inertia, $data);
        }

        if ($hasView) {
            return view($blade, $data);
        }

        abort(500, 'Dashboard views are not published. Run: php artisan pasak:install --with-plain');
    }
}
