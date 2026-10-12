/**
 * Pengumuman situs dokumentasi Pasak (banner + dialog info terkini & CLI upgrade).
 *
 * Disuntikkan ke <body> agar tampil di semua halaman tanpa mengubah layout
 * Starlight. Banner tampil sampai ditutup; dialog tampil sekali per versi
 * pengumuman atau saat tombol "Info & Upgrade" diklik.
 */

import pkg from "../data/package.json";

const version = pkg?.packagist?.version ?? pkg?.version ?? "1.4.1";

const BANNER_ID = "psk-docs-announce-bar";
const MODAL_ID = "psk-docs-announce-modal";
const BANNER_KEY = `pasak-docs-banner-dismissed-v${version}`;
const MODAL_KEY = `pasak-docs-announce-v${version}`;

const CSS_ID = "psk-docs-announce-css";
const CSS = `#${BANNER_ID}{position:fixed;top:0;left:0;right:0;width:100%;box-sizing:border-box;display:flex;align-items:center;justify-content:center;gap:10px;flex-wrap:wrap;background:linear-gradient(90deg,#1e40af,#2563eb);color:#fff;font:500 13px/1.5 "Plus Jakarta Sans",system-ui,sans-serif;padding:8px 44px 8px 16px;z-index:60;border-bottom:1px solid rgba(255,255,255,.2);}#${BANNER_ID} a{color:#fff;text-decoration:underline;font-weight:600;}#${BANNER_ID} button{cursor:pointer;}#${MODAL_ID}{position:fixed;inset:0;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;z-index:100;padding:16px;}#${MODAL_ID}[hidden]{display:none!important;}`;

function ensureCss(): void {
  if (typeof document === "undefined" || document.getElementById(CSS_ID))
    return;

  const style = document.createElement("style");
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

let resizeObserver: ResizeObserver | null = null;

function updateBannerHeight(bar: HTMLElement | null): void {
  if (typeof document === "undefined") return;
  if (bar && document.body.contains(bar)) {
    const height = Math.round(bar.getBoundingClientRect().height);
    document.documentElement.style.setProperty(
      "--psk-banner-height",
      `${height}px`,
    );
  } else {
    document.documentElement.style.setProperty(
      "--psk-banner-height",
      "0px",
    );
  }
}

function buildBanner(): HTMLElement {
  const bar = document.createElement("div");
  bar.id = BANNER_ID;
  bar.innerHTML =
    `<span>🛡️ <strong>Pasak v${version}</strong> — WAF mandiri untuk Laravel. Lihat pembaruan &amp; panduan upgrade.</span>` +
    '<button type="button" data-info style="background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.4);color:#fff;border-radius:6px;padding:2px 10px;font:600 12px/1.6 inherit;">Info &amp; Upgrade</button>' +
    '<a href="/cli-automation/upgrade-guide/">Panduan CLI</a>' +
    '<button type="button" data-close aria-label="Tutup" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:transparent;border:none;color:#fff;font-size:16px;line-height:1;">✕</button>';

  const close = bar.querySelector("[data-close]");
  if (close) {
    close.addEventListener("click", () => {
      if (resizeObserver) {
        resizeObserver.disconnect();
        resizeObserver = null;
      }
      bar.remove();
      updateBannerHeight(null);
      storeSet(BANNER_KEY, "1");
    });
  }

  const info = bar.querySelector("[data-info]");
  if (info) {
    info.addEventListener("click", () => {
      const modal = document.getElementById(MODAL_ID);
      if (modal) modal.hidden = false;
    });
  }

  return bar;
}

function buildModal(): HTMLElement {
  const wrap = document.createElement("div");
  wrap.id = MODAL_ID;
  wrap.hidden = true;
  wrap.innerHTML =
    '<div role="dialog" aria-modal="true" aria-labelledby="psk-docs-announce-title" style="background:#fff;color:#0f172a;border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,.25);max-width:540px;width:100%;overflow:hidden;font:400 14px/1.6 system-ui,sans-serif;">' +
    '<div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;">' +
    `<h3 id="psk-docs-announce-title" style="margin:0;font-size:16px;font-weight:750;color:#0f172a;">🛡️ Pasak v${version} — Informasi Terkini</h3>` +
    '<button type="button" data-close aria-label="Tutup" style="background:transparent;border:none;color:#64748b;font-size:18px;cursor:pointer;line-height:1;padding:4px;">✕</button>' +
    "</div>" +
    '<div style="padding:20px;display:flex;flex-direction:column;gap:12px;color:#334155;max-height:75vh;overflow-y:auto;">' +
    '<div><strong style="color:#0f172a;">✨ Yang Baru di Rilis Terkini:</strong>' +
    '<ul style="margin:6px 0 0 18px;padding:0;color:#475569;font-size:13px;line-height:1.6;">' +
    "<li><strong>CRUD Allowed IPs</strong>: Kelola IP allowlist global (tunggal / CIDR / wildcard).</li>" +
    "<li><strong>Halaman Detail Dashboard</strong>: Inspeksi detail log ancaman, IP terblokir, &amp; tiket banding.</li>" +
    "<li><strong>Dark Mode Manual</strong>: Tombol tema Terang / Gelap / Auto tanpa flicker di semua stack.</li>" +
    "<li><strong>Arsitektur WAF Mandiri</strong>: Zero-tolerance instant block &amp; stepped login lockout.</li>" +
    "</ul></div>" +
    '<div style="background:#f8fafc;border-radius:8px;padding:12px;border:1px solid #cbd5e1;">' +
    '<div style="font-weight:700;font-size:12px;color:#0f172a;margin-bottom:6px;">⚡ Rekomendasi CLI Upgrade (2 Langkah):</div>' +
    '<pre style="margin:0;background:#0f172a;color:#f8fafc;padding:10px 12px;border-radius:6px;font:600 12px/1.5 ui-monospace,monospace;overflow-x:auto;">composer update robyajo/pasak --with-all-dependencies\nphp artisan pasak:upgrade</pre>' +
    '<div style="margin-top:6px;font-size:11px;color:#64748b;">*Perintah ini otomatis mempublish migrasi baru, migrasi DB, dan sinkronisasi berkas tanpa menimpa konfigurasi kustom.</div>' +
    "</div>" +
    '<div style="display:flex;flex-wrap:wrap;gap:12px;font-size:13px;padding-top:2px;">' +
    '<a href="/cli-automation/upgrade-guide/" style="color:#2563eb;text-decoration:underline;font-weight:600;">📖 Panduan Upgrade Bertahap</a>' +
    '<a href="/changelog/" style="color:#2563eb;text-decoration:underline;font-weight:600;">📋 Catatan Rilis (Changelog)</a>' +
    '<a href="/downloads/Pasak-Bulwark.pptx" style="color:#2563eb;text-decoration:underline;font-weight:600;">📊 Unduh Presentasi (.pptx)</a>' +
    "</div>" +
    "</div>" +
    '<div style="padding:12px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:flex-end;">' +
    '<button type="button" data-close style="background:#2563eb;color:#fff;border:none;border-radius:6px;padding:8px 18px;font:600 13px/1.4 inherit;cursor:pointer;">Mengerti &amp; Tutup</button>' +
    "</div></div>";

  const closeBtns = wrap.querySelectorAll("[data-close]");
  closeBtns.forEach((btn) => {
    btn.addEventListener("click", () => {
      wrap.hidden = true;
      storeSet(MODAL_KEY, "1");
    });
  });

  return wrap;
}

function mount(): void {
  if (typeof document === "undefined" || typeof window === "undefined")
    return;

  ensureCss();

  const isDismissed = Boolean(storeGet(BANNER_KEY));
  let bar = document.getElementById(BANNER_ID) as HTMLElement | null;

  if (isDismissed) {
    if (bar) bar.remove();
    updateBannerHeight(null);
  } else {
    if (!bar) {
      bar = buildBanner();
      document.body.prepend(bar);
    }
    updateBannerHeight(bar);

    if (typeof ResizeObserver !== "undefined") {
      if (resizeObserver) resizeObserver.disconnect();
      resizeObserver = new ResizeObserver(() => {
        const currentBar = document.getElementById(
          BANNER_ID,
        ) as HTMLElement | null;
        updateBannerHeight(currentBar);
      });
      resizeObserver.observe(bar);
    }
  }

  let modal = document.getElementById(MODAL_ID);
  if (!modal) {
    modal = buildModal();
    document.body.appendChild(modal);
  }
}

export function initSiteAnnouncement(): void {
  if (typeof window === "undefined") return;

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", mount, { once: true });
  } else {
    mount();
  }

  window.addEventListener("resize", () => {
    const bar = document.getElementById(BANNER_ID) as HTMLElement | null;
    updateBannerHeight(bar);
  });

  document.addEventListener("astro:page-load", mount);
}
