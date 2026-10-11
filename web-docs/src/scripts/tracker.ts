/**
 * Pelacak kunjungan situs dokumentasi Laravel Security Monitor.
 *
 * Mengirimkan telemetry pageview ke Superapp API:
 *   POST /api/master/visitors
 *
 * Konfigurasi (opsional via env web):
 *   PUBLIC_VISITOR_API_URL  = URL endpoint API (default: https://superapp-api.pekanbaru.go.id/api/master/visitors)
 *   PUBLIC_VISITOR_SITE_ID  = Identifier situs (default: laravel-security-monitor)
 */

interface VisitorPayload {
  site: string;
  path: string;
  referrer?: string;
  device?: 'desktop' | 'mobile' | 'tablet' | 'bot' | 'unknown';
}

const DEFAULT_API_URL =
  import.meta.env.PUBLIC_VISITOR_API_URL ||
  'https://superapp-api.pekanbaru.go.id/api/master/visitors';

const SITE_ID =
  import.meta.env.PUBLIC_VISITOR_SITE_ID || 'laravel-security-monitor';

let lastTrackedPath: string | null = null;
let lastTrackedTime = 0;

/**
 * Deteksi kategori perangkat pengunjung berdasarkan User Agent & resolusi layar.
 */
function detectDevice(): 'desktop' | 'mobile' | 'tablet' | 'bot' | 'unknown' {
  if (typeof navigator === 'undefined') return 'unknown';

  const ua = navigator.userAgent.toLowerCase();

  if (/bot|crawl|spider|slurp|headless|lighthouse/i.test(ua)) {
    return 'bot';
  }

  if (
    /ipad|tablet|playbook|silk/i.test(ua) ||
    (navigator.maxTouchPoints > 1 &&
      window.innerWidth >= 768 &&
      window.innerWidth <= 1024)
  ) {
    return 'tablet';
  }

  if (
    /mobile|iphone|ipod|android.*mobile|blackberry|iemobile/i.test(ua) ||
    window.innerWidth < 768
  ) {
    return 'mobile';
  }

  return 'desktop';
}

/**
 * Kirimkan satu hit kunjungan (pageview) ke endpoint API.
 */
export async function trackPageView(customPath?: string): Promise<void> {
  if (typeof window === 'undefined') return;

  const currentPath = customPath || window.location.pathname || '/';
  const now = Date.now();

  // Hindari pengiriman ganda pada path yang sama dalam jeda kurang dari 2 detik
  if (lastTrackedPath === currentPath && now - lastTrackedTime < 2000) {
    return;
  }

  lastTrackedPath = currentPath;
  lastTrackedTime = now;

  const payload: VisitorPayload = {
    site: SITE_ID,
    path: currentPath,
    referrer: document.referrer || undefined,
    device: detectDevice(),
  };

  try {
    const url = DEFAULT_API_URL;

    // Utamakan navigator.sendBeacon jika didukung untuk transmisi yang efisien
    if (navigator.sendBeacon && typeof Blob !== 'undefined') {
      const blob = new Blob([JSON.stringify(payload)], {
        type: 'application/json',
      });
      const sent = navigator.sendBeacon(url, blob);
      if (sent) return;
    }

    // Fallback menggunakan fetch async dengan keepalive
    await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify(payload),
      keepalive: true,
      mode: 'cors',
      credentials: 'omit',
    });
  } catch {
    // Abaikan kegagalan jaringan atau pemblokiran adblocker agar situs tetap berjalan lancar.
  }
}

/**
 * Inisialisasi pelacakan otomatis pada siklus hidup halaman Astro / Starlight.
 */
export function initVisitorTracker(): void {
  if (typeof window === 'undefined') return;

  // Catat saat halaman selesai dimuat pertama kali
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      void trackPageView();
    });
  } else {
    void trackPageView();
  }

  // Dukungan transisi halaman klien Astro (Astro View Transitions & Starlight SPA)
  document.addEventListener('astro:page-load', () => {
    void trackPageView();
  });

  // Navigasi history browser (tombol back/forward)
  window.addEventListener('popstate', () => {
    void trackPageView();
  });
}

