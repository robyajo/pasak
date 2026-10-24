<?php

namespace Internal\Pasak\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Internal\Pasak\Services\PasakDashboardUrlService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menjamin route('pasak.dashboard.*') tidak pernah fatal di luar dashboard.
 *
 * View milik host (mis. navigasi di layout) boleh memanggil route dashboard
 * dari halaman mana pun. Middleware ringan ini (grup "web") mengisi
 * URL::defaults dari slug sesi bila ada, atau membiarkan fallback bawaan
 * (prefix stabil "/pasak/...") yang ditangani alias redirect. Tanpa ini,
 * parameter {pasakSlug} yang wajib membuat seluruh halaman host 500.
 */
class PasakShareDashboardSlug
{
    public function __construct(
        protected PasakDashboardUrlService $urls,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->urls->isRotatingEnabled()) {
            try {
                if ($request->hasSession()) {
                    $stored = $request->session()->get(PasakDashboardUrlService::SESSION_KEY);

                    if (is_string($stored) && $this->urls->matchesFormat($stored)) {
                        $this->urls->applyUrlDefaults($stored);
                    }
                }
            } catch (\Throwable) {
                // Abaikan — fallback bawaan tetap berlaku.
            }
        }

        return $next($request);
    }
}
