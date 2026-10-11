// @ts-check
import { defineConfig } from "astro/config";
import starlight from "@astrojs/starlight";
import sitemap from "@astrojs/sitemap";

// https://astro.build/config
export default defineConfig({
  // === Konfigurasi deploy ===
  // Situs dokumentasi ini adalah repo/proyek terpisah dan disajikan di AKAR
  // domainnya sendiri (tanpa `base`), mis. https://docs.example.com/
  //   - Ganti 'site' dengan domain Anda (dipakai canonical + sitemap).
  //   - Untuk deploy pada sub-path (mis. https://example.com/docs/),
  //     tambahkan baris:  base: '/docs'
  site: process.env.SITE_URL || "https://security-dev.pekanbaru.go.id",

  // Muat awal halaman tujuan saat tautan mulai terlihat (navigasi terasa instan).
  prefetch: true,

  // Pustaka mermaid diimpor dinamis (hanya di halaman berdiagram), sehingga
  // chunk-nya wajar besar; naikkan ambang peringatan agar build tetap bersih.
  vite: {
    build: { chunkSizeWarningLimit: 1600 },
    optimizeDeps: {
      include: ["mermaid"],
    },
  },

  integrations: [
    starlight({
      title: "Laravel Security Monitor",
      defaultLocale: "root",
      locales: {
        root: { label: "Bahasa Indonesia", lang: "id" },
      },
      description:
        "Dokumentasi resmi robyajo/laravel-security-monitor (Bulwark) — WAF mandiri, karantina perangkat, proteksi login bertingkat, dashboard monitoring Pure Vanilla CSS, pemindai webshell, dan REST API headless untuk Laravel 10–13. Keamanan Siber & Informasi Pemerintah Kota Pekanbaru (security-dev.pekanbaru.go.id).",
      logo: { src: "./src/assets/logo.svg", alt: "Laravel Security Monitor" },
      favicon: "/favicon.svg",
      social: [
        {
          icon: "github",
          label: "GitHub",
          href: "https://github.com/robyajo/laravel-security-monitor",
        },
        {
          icon: "link",
          label: "Packagist",
          href: "https://packagist.org/packages/robyajo/laravel-security-monitor",
        },
      ],
      lastUpdated: true,
      pagination: true,
      tableOfContents: { minHeadingLevel: 2, maxHeadingLevel: 3 },
      customCss: ["./src/styles/custom.css"],
      components: {
        // Menyuntikkan skrip render Mermaid ke setiap halaman.
        Head: "./src/components/Head.astro",
        // Menambahkan badge versi paket di sebelah judul situs.
        SiteTitle: "./src/components/SiteTitle.astro",
      },
      expressiveCode: {
        // Shiki tidak mengenal bahasa "cron"; tampilkan sebagai bash agar tetap rapi.
        shiki: { langAlias: { cron: "bash" } },
      },
      sidebar: [
        {
          label: "Memulai",
          items: [{ autogenerate: { directory: "getting-started" } }],
        },
        {
          label: "Arsitektur Inti",
          items: [{ autogenerate: { directory: "core-architecture" } }],
        },
        {
          label: "Modul Keamanan",
          items: [{ autogenerate: { directory: "security-modules" } }],
        },
        {
          label: "Referensi REST API",
          items: [{ autogenerate: { directory: "api-reference" } }],
        },
        {
          label: "CLI & Otomasi",
          items: [{ autogenerate: { directory: "cli-automation" } }],
        },
        {
          label: "Hardening Web Server",
          items: [{ autogenerate: { directory: "webserver-hardening" } }],
        },
        {
          label: "Panduan Integrasi",
          items: [{ autogenerate: { directory: "integration-guides" } }],
        },
        { label: "Changelog", slug: "changelog" },
        {
          label: "Packagist",
          link: "https://packagist.org/packages/robyajo/laravel-security-monitor",
          attrs: { target: "_blank", rel: "noopener" },
        },
      ],
    }),
    sitemap(),
  ],
});
