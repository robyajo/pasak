<?php

namespace Internal\Pasak\Http\Controllers\Dashboard;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Internal\Pasak\Services\PasakDashboardUrlService;

/**
 * Alias stabil "/pasak/*" untuk mode URL berputar.
 *
 * Bookmark lama, link layout host, dan route() di luar konteks dashboard
 * menghasilkan URL stabil ini; semuanya dialihkan (302) ke slug acak aktif
 * milik sesi pengguna dengan path + query dipertahankan:
 *
 *   /pasak/logs  → 302 → /pasak-a1b2c3d4e5f6/logs
 */
class PasakDashboardAliasController extends Controller
{
    public function __invoke(Request $request, PasakDashboardUrlService $urls): RedirectResponse
    {
        $slug = $urls->currentSlug($request);
        $urls->applyUrlDefaults($slug);

        $any = trim((string) ($request->route('any') ?? ''), '/');
        $url = url($slug.($any === '' ? '' : '/'.$any));
        $query = $request->getQueryString();

        return redirect()->to($url.(is_string($query) && $query !== '' ? '?'.$query : ''), 302);
    }
}
