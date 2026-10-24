<?php

namespace Internal\Pasak\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Internal\Pasak\Services\PasakDashboardUrlService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validasi + rotasi token URL dashboard acak sekali pakai.
 *
 * - Slug aktif (current): sajikan halaman lalu putar token untuk request
 *   berikutnya (GET HTML saja) sehingga reload selalu berubah.
 * - Slug lama (previous, dalam masa tenggang): untuk GET HTML lakukan 302 ke
 *   URL aktif agar address bar ikut berubah; untuk POST/AJAX/feed cukup
 *   diloloskan tanpa rotasi agar polling & form tidak balapan.
 * - Slug asing: 404 (bukan 403) agar keberadaan dashboard tetap tersamar.
 */
class PasakRotatingDashboard
{
    public function __construct(
        protected PasakDashboardUrlService $urls,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->urls->isRotatingEnabled()) {
            return $next($request);
        }

        $slug = (string) ($request->route('pasakSlug') ?? '');

        if ($slug === '' || ! $this->urls->matchesFormat($slug)) {
            abort(404);
        }

        if ($this->urls->isCurrentSlug($request, $slug)) {
            if ($this->shouldRotate($request)) {
                $fresh = $this->urls->rotate($request);
            } else {
                $fresh = $slug;
                $this->urls->applyUrlDefaults($fresh);
            }

            /** @var Response $response */
            $response = $next($request);

            if ($response instanceof \Illuminate\Http\Response || $response instanceof Response) {
                $response->headers->set('X-Pasak-Dashboard-Slug', $fresh);
            }

            return $response;
        }

        if ($this->urls->isPreviousSlug($request, $slug)) {
            $current = $this->urls->currentSlug($request);
            $this->urls->applyUrlDefaults($current);

            if ($this->shouldRotate($request)) {
                return redirect()->to($this->samePathWithSlug($request, $current), 302);
            }

            return $next($request);
        }

        abort(404);
    }

    /**
     * Rotasi hanya untuk navigasi HTML. Feed JSON, AJAX, dan aksi tulis
     * (POST/PATCH/DELETE) tidak memutar token agar tidak balapan.
     */
    protected function shouldRotate(Request $request): bool
    {
        if (! $this->urls->isSingleUse()) {
            return false;
        }

        if (! $request->isMethod('get')) {
            return false;
        }

        if ($request->expectsJson() || $request->ajax()) {
            return false;
        }

        $path = trim((string) $request->path(), '/');

        if (str_ends_with($path, '/feed') || str_contains($path, 'threat-map/feed')) {
            return false;
        }

        return true;
    }

    protected function samePathWithSlug(Request $request, string $currentSlug): string
    {
        $segments = explode('/', trim((string) $request->path(), '/'));
        array_shift($segments);

        $suffix = implode('/', array_filter($segments));
        $url = url($currentSlug.($suffix === '' ? '' : '/'.$suffix));

        $query = $request->getQueryString();

        return $query !== null && $query !== '' ? $url.'?'.$query : $url;
    }
}
