/**
 * Pengumuman situs dokumentasi Pasak (banner + dialog kunjungan pertama).
 *
 * Disuntikkan ke <body> agar tampil di semua halaman tanpa mengubah layout
 * Starlight. Banner tampil sampai ditutup; dialog tampil sekali per versi
 * pengumuman. Idempoten untuk navigasi SPA Astro (astro:page-load).
 */

const BANNER_ID = 'psk-docs-announce-bar';
const MODAL_ID = 'psk-docs-announce-modal';
const BANNER_KEY = 'pasak-docs-banner-dismissed';
const MODAL_KEY = 'pasak-docs-announce-v1';

const CSS_ID = 'psk-docs-announce-css';
const CSS = `#${BANNER_ID}{display:flex;align-items:center;justify-content:center;gap:10px;flex-wrap:wrap;background:linear-gradient(90deg,#1e40af,#2563eb);color:#fff;font:500 13px/1.5 "Plus Jakarta Sans",system-ui,sans-serif;padding:8px 44px 8px 16px;position:relative;z-index:60;}#${BANNER_ID} a{color:#fff;text-decoration:underline;}#${BANNER_ID} button{cursor:pointer;}#${MODAL_ID}{position:fixed;inset:0;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;z-index:100;padding:16px;}#${MODAL_ID}[hidden]{display:none!important;}`;

function ensureCss(): void {
    if (typeof document === 'undefined' || document.getElementById(CSS_ID)) return;

    const style = document.createElement('style');
    style.id = CSS_ID;
    style.textContent = CSS;
    document.head.appendChild(style);
}

function storeGet(key: string): string | null {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

function storeSet(key: string, value: string): void {
    try {
        window.localStorage.setItem(key, value);
    } catch {
        /* abaikan */
    }
}

function buildBanner(): HTMLElement {
    const bar = document.createElement('div');
    bar.id = BANNER_ID;
    bar.innerHTML =
        '<span>🛡️ <strong>Pasak (Bulwark)</strong> — WAF mandiri untuk Laravel. Lihat yang baru di versi terkini.</span>' +
        '<button type="button" data-info style="background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.4);color:#fff;border-radius:6px;padding:2px 10px;font:600 12px/1.6 inherit;">Info</button>' +
        '<a href="/downloads/Pasak-Bulwark.pptx">Unduh presentasi</a>' +
        '<button type="button" data-close aria-label="Tutup" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:transparent;border:none;color:#fff;font-size:16px;line-height:1;">✕</button>';

    const close = bar.querySelector('[data-close]');
    if (close) {
        close.addEventListener('click', () => {
            bar.remove();
            storeSet(BANNER_KEY, '1');
        });
    }

    const info = bar.querySelector('[data-info]');
    if (info) {
        info.addEventListener('click', () => {
            const modal = document.getElementById(MODAL_ID);
            if (modal) modal.hidden = false;
        });
    }

    return bar;
}

function buildModal(): HTMLElement {
    const wrap = document.createElement('div');
    wrap.id = MODAL_ID;
    wrap.hidden = true;
    wrap.innerHTML =
        '<div role="dialog" aria-modal="true" aria-labelledby="psk-docs-announce-title" style="background:#fff;color:#0f172a;border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,.25);max-width:520px;width:100%;overflow:hidden;font:400 14px/1.6 system-ui,sans-serif;">' +
        '<div style="padding:18px 20px;border-bottom:1px solid #e2e8f0;"><h3 id="psk-docs-announce-title" style="margin:0;font-size:16px;">👋 Selamat datang di dokumentasi Pasak</h3></div>' +
        '<div style="padding:20px;display:flex;flex-direction:column;gap:10px;color:#475569;">' +
        '<div>🛡️ Default instalasi adalah <strong>monitoring saja</strong> — tanpa blokir otomatis sampai Anda mengaktifkannya di Settings.</div>' +
        '<div>🚀 Sudah install versi lama? Ikuti <a href="/cli-automation/upgrade-guide/" style="color:#2563eb;">panduan upgrade bertahap</a>.</div>' +
        '<div>📊 Butuh bahan presentasi? <a href="/downloads/Pasak-Bulwark.pptx" style="color:#2563eb;">Unduh deck .pptx</a>.</div>' +
        '</div>' +
        '<div style="padding:14px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:flex-end;">' +
        '<button type="button" data-close style="background:#2563eb;color:#fff;border:none;border-radius:6px;padding:8px 18px;font:600 14px/1.4 inherit;">Mengerti</button>' +
        '</div></div>';

    const btn = wrap.querySelector('[data-close]');
    if (btn) {
        btn.addEventListener('click', () => {
            wrap.hidden = true;
            storeSet(MODAL_KEY, '1');
        });
    }

    return wrap;
}

function mount(): void {
    if (typeof document === 'undefined' || typeof window === 'undefined') return;

    ensureCss();

    if (!storeGet(BANNER_KEY) && !document.getElementById(BANNER_ID)) {
        document.body.prepend(buildBanner());
    }

    let modal = document.getElementById(MODAL_ID);
    if (!modal) {
        modal = buildModal();
        document.body.appendChild(modal);
    }

    // Dialog TIDAK dibuka otomatis agar tidak menutupi header/konten;
    // user membukanya lewat tombol Info di banner. Status "sekali"
    // tetap dicatat saat dialog ditutup via tombol Mengerti.
}

export function initSiteAnnouncement(): void {
    if (typeof window === 'undefined') return;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mount, { once: true });
    } else {
        mount();
    }

    document.addEventListener('astro:page-load', mount);
}
