<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Internal\Pasak\Support\PasakDashboardView;

/**
 * Render dashboard pages as plain Blade views.
 *
 * Pasak hanya mendukung dashboard Blade murni (100% Pure PHP, tanpa
 * Livewire/Inertia/Vue).
 */
trait RespondsWithPasakDashboard
{
    /**
     * Execute an action on the controller.
     *
     * Menghapus parameter {pasakSlug} dari URL berotasi agar controller
     * menerima parameter rute yang sebenarnya (mis. $id) tanpa tergeser.
     *
     * @param  string  $method
     * @param  array<string, mixed>  $parameters
     * @return mixed
     */
    public function callAction($method, $parameters)
    {
        unset($parameters['pasakSlug']);

        return parent::callAction($method, $parameters);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function dashboardResponse(string $blade, array $data): View
    {
        if (! view()->exists($blade)) {
            abort(500, 'Dashboard views are not published. Run: php artisan pasak:install --with-dashboard');
        }

        $appLocale = (string) config('app.locale', 'en');
        if (! in_array($appLocale, ['en', 'id'], true)) {
            $appLocale = 'en';
        }

        $locale = (string) (request()->query('lang')
            ?? (request()->hasSession() ? session('pasak_locale') : null)
            ?? request()->cookie('pasak_locale')
            ?? $appLocale);

        if (! in_array($locale, ['en', 'id'], true)) {
            $locale = $appLocale;
        }

        app()->setLocale($locale);
        Carbon::setLocale($locale);
        if (class_exists(\Carbon\Carbon::class)) {
            \Carbon\Carbon::setLocale($locale);
        }

        if (request()->hasSession() && request()->query('lang')) {
            session(['pasak_locale' => $locale]);
        }

        $data['currentLocale'] = $locale;
        $data['pasakTimezone'] = PasakDashboardView::timezone();

        return view($blade, $data);
    }
}
