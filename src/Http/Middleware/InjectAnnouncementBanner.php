<?php

namespace Internal\Pasak\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Internal\Pasak\Services\PasakService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menyuntikkan banner + dialog pengumuman Pasak pada halaman yang
 * dikonfigurasi (bawaan: `/`) tanpa mengubah view aplikasi host.
 *
 * Hanya menyentuh respons HTML GET berstatus 200 yang memuat tag <body>.
 * Banner tampil setiap kunjungan sampai ditutup; dialog tampil sekali
 * per versi paket. Nonaktifkan via `PASAK_ANNOUNCEMENT_ENABLED=false`.
 * Hanya aktif saat `APP_ENV=local` dan `APP_DEBUG=true`.
 */
class InjectAnnouncementBanner
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldInject($request, $response)) {
            return $response;
        }

        /** @var HttpResponse $response */
        $html = (string) $response->getContent();

        $banner = $this->bannerHtml();
        $dialog = $this->dialogHtml();

        if (preg_match('/<body[^>]*>/i', $html, $m)) {
            $html = str_replace($m[0], $m[0].$banner, $html);
        } else {
            return $response;
        }

        $pos = strripos($html, '</body>');

        if ($pos !== false) {
            $html = substr_replace($html, $dialog, $pos, 0);
        }

        $response->setContent($html);

        return $response;
    }

    protected function shouldInject(Request $request, mixed $response): bool
    {
        if (! app()->environment('local') || ! (bool) config('app.debug')) {
            return false;
        }

        if (! (bool) config('pasak.announcement.enabled', true)) {
            return false;
        }

        if (! $request->isMethod('get') || $request->ajax() || $request->expectsJson()) {
            return false;
        }

        if (! $response instanceof HttpResponse || $response->getStatusCode() !== 200) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        if ($contentType !== '' && ! str_contains(strtolower($contentType), 'text/html')) {
            return false;
        }

        $patterns = config('pasak.announcement.paths', ['/']);

        if (! is_array($patterns)) {
            $patterns = ['/'];
        }

        foreach ($patterns as $pattern) {
            if ($request->is((string) $pattern)) {
                return true;
            }
        }

        return false;
    }

    protected function bannerHtml(): string
    {
        return <<<'HTML'
<div id="psk-announce-bar" style="display:flex;align-items:center;justify-content:center;gap:10px;flex-wrap:wrap;background:linear-gradient(90deg,#1d4ed8,#2563eb);color:#fff;font:500 13px/1.5 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;padding:8px 44px 8px 16px;position:relative;z-index:9990;">
<span>🛡️ Dilindungi <strong>Pasak (Bulwark)</strong> — monitoring keamanan aktif</span>
<button type="button" data-psk-announce-info style="background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.4);color:#fff;border-radius:6px;padding:2px 10px;font:600 12px/1.6 inherit;cursor:pointer;">Info</button>
<a href="https://security-dev.pekanbaru.go.id/" target="_blank" rel="noopener" style="color:#fff;text-decoration:underline;font-size:12px;">Dokumentasi</a>
<button type="button" data-psk-announce-dismiss aria-label="Tutup" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:transparent;border:none;color:#fff;font-size:16px;cursor:pointer;line-height:1;">✕</button>
</div>
HTML;
    }

    protected function dialogHtml(): string
    {
        $version = str_replace('.', '-', PasakService::VERSION);

        return <<<HTML
<style>#psk-announce-modal{display:flex;}#psk-announce-modal[hidden]{display:none!important;}</style>
<div id="psk-announce-modal" hidden style="position:fixed;inset:0;background:rgba(0,0,0,.55);align-items:center;justify-content:center;z-index:9999;padding:16px;">
<div role="dialog" aria-modal="true" aria-labelledby="psk-announce-title" style="background:#fff;color:#0f172a;border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,.25);max-width:520px;width:100%;overflow:hidden;font:400 14px/1.6 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
<div style="padding:18px 20px;border-bottom:1px solid #e2e8f0;"><h3 id="psk-announce-title" style="margin:0;font-size:16px;">👋 Selamat datang — situs ini diproteksi Pasak</h3></div>
<div style="padding:20px;display:flex;flex-direction:column;gap:10px;color:#475569;">
<div>🛡️ Bawaan instalasi adalah <strong>monitoring saja</strong>: ancaman dideteksi dan dicatat tanpa blokir otomatis.</div>
<div>⚙️ Admin dapat mengaktifkan karantina otomatis di halaman Settings atau via API.</div>
<div>📖 Panduan lengkap: <a href="https://security-dev.pekanbaru.go.id/" target="_blank" rel="noopener" style="color:#2563eb;">security-dev.pekanbaru.go.id</a></div>
</div>
<div style="padding:14px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:flex-end;">
<button type="button" data-psk-announce-close style="background:#2563eb;color:#fff;border:none;border-radius:6px;padding:8px 18px;font:600 14px/1.4 inherit;cursor:pointer;">Mengerti</button>
</div>
</div>
</div>
<script>
(function () {
    try {
        var vKey = 'pasak-announce-{$version}';
        var bar = document.getElementById('psk-announce-bar');
        var modal = document.getElementById('psk-announce-modal');
        var dismissed = window.localStorage && localStorage.getItem('pasak-banner-dismissed');
        if (bar) {
            if (dismissed) { bar.style.display = 'none'; }
            var x = bar.querySelector('[data-psk-announce-dismiss]');
            if (x) x.addEventListener('click', function () {
                bar.style.display = 'none';
                try { localStorage.setItem('pasak-banner-dismissed', '1'); } catch (e) {}
            });
            var info = bar.querySelector('[data-psk-announce-info]');
            if (info && modal) info.addEventListener('click', function () { modal.hidden = false; });
        }
        if (modal) {
            var seen = window.localStorage && localStorage.getItem(vKey);
            if (!seen && !dismissed) { modal.hidden = false; }
            var btn = modal.querySelector('[data-psk-announce-close]');
            if (btn) btn.addEventListener('click', function () {
                modal.hidden = true;
                try { localStorage.setItem(vKey, '1'); } catch (e) {}
            });
        }
    } catch (e) {}
})();
</script>
HTML;
    }
}
