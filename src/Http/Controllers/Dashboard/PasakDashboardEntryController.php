<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Internal\Pasak\Services\PasakDashboardUrlService;

/**
 * Pintu masuk stabil "/pasak" untuk mode URL berputar.
 *
 * Pengguna menandai /pasak, setiap kunjungan dialihkan (302) ke slug acak
 * aktif "/pasak-{token}/" milik sesinya. Slug langsung (dengan token) yang
 * dipakai untuk navigasi harian sehingga scanner tidak bisa menebak URL.
 */
class PasakDashboardEntryController extends Controller
{
    public function __invoke(Request $request, PasakDashboardUrlService $urls): RedirectResponse
    {
        $slug = $urls->currentSlug($request);
        $urls->applyUrlDefaults($slug);

        $query = $request->getQueryString();

        return redirect()->to(url($slug).($query !== null && $query !== '' ? '?'.$query : ''), 302);
    }
}
