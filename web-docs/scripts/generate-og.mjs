import { writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const here = dirname(fileURLToPath(import.meta.url));
const webRoot = join(here, '..');
const outPng = join(webRoot, 'public', 'og-image.png');

const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630" fill="none">
  <defs>
    <!-- Background Gradients -->
    <linearGradient id="bg" x1="0" y1="0" x2="1200" y2="630" gradientUnits="userSpaceOnUse">
      <stop offset="0%" stop-color="#030712" />
      <stop offset="50%" stop-color="#0f172a" />
      <stop offset="100%" stop-color="#0b132b" />
    </linearGradient>

    <!-- Ambient Glows -->
    <radialGradient id="glowCyan" cx="20%" cy="20%" r="50%">
      <stop offset="0%" stop-color="#0284c7" stop-opacity="0.35" />
      <stop offset="100%" stop-color="#0284c7" stop-opacity="0" />
    </radialGradient>
    <radialGradient id="glowIndigo" cx="85%" cy="85%" r="60%">
      <stop offset="0%" stop-color="#6366f1" stop-opacity="0.3" />
      <stop offset="100%" stop-color="#6366f1" stop-opacity="0" />
    </radialGradient>

    <!-- Shield Gradient -->
    <linearGradient id="shieldGrad" x1="0" y1="0" x2="120" y2="120" gradientUnits="userSpaceOnUse">
      <stop offset="0%" stop-color="#38bdf8" />
      <stop offset="100%" stop-color="#6366f1" />
    </linearGradient>

    <!-- Card Background Gradient -->
    <linearGradient id="cardBg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#1e293b" stop-opacity="0.75" />
      <stop offset="100%" stop-color="#0f172a" stop-opacity="0.85" />
    </linearGradient>

    <!-- Border Gradient -->
    <linearGradient id="borderGrad" x1="0" y1="0" x2="1200" y2="630" gradientUnits="userSpaceOnUse">
      <stop offset="0%" stop-color="#38bdf8" stop-opacity="0.6" />
      <stop offset="50%" stop-color="#6366f1" stop-opacity="0.3" />
      <stop offset="100%" stop-color="#38bdf8" stop-opacity="0.1" />
    </linearGradient>
  </defs>

  <!-- Background Layer -->
  <rect width="1200" height="630" fill="url(#bg)" />
  <rect width="1200" height="630" fill="url(#glowCyan)" />
  <rect width="1200" height="630" fill="url(#glowIndigo)" />

  <!-- Outer Border Frame -->
  <rect x="24" y="24" width="1152" height="582" rx="20" stroke="url(#borderGrad)" stroke-width="2" fill="none" />

  <!-- Grid Decoration -->
  <g opacity="0.08" stroke="#38bdf8" stroke-width="1">
    <path d="M 0 100 L 1200 100 M 0 200 L 1200 200 M 0 300 L 1200 300 M 0 400 L 1200 400 M 0 500 L 1200 500" />
    <path d="M 200 0 L 200 630 M 400 0 L 400 630 M 600 0 L 600 630 M 800 0 L 800 630 M 1000 0 L 1000 630" />
  </g>

  <!-- Left Header Icon & Shield (Position: x=100, y=90) -->
  <g transform="translate(100, 90)">
    <!-- Glow behind shield -->
    <circle cx="60" cy="60" r="75" fill="#38bdf8" opacity="0.18" filter="blur(20px)" />
    
    <!-- Shield Graphic -->
    <g transform="scale(1.875)">
      <path d="M32 4.5 54 12.6v17.1c0 13.9-9.2 24.6-22 30.3-12.8-5.7-22-16.4-22-30.3V12.6L32 4.5Z" fill="url(#shieldGrad)" />
      <path d="M32 4.5 54 12.6v17.1c0 13.9-9.2 24.6-22 30.3-12.8-5.7-22-16.4-22-30.3V12.6L32 4.5Z" fill="#000" fill-opacity="0.08" />
      <path d="m22.5 32.4 6.6 6.6 12.8-13.4" stroke="#ffffff" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" fill="none" />
    </g>
  </g>

  <!-- Title & Branding -->
  <g transform="translate(260, 115)">
    <!-- Pill Badge -->
    <rect x="0" y="0" width="370" height="34" rx="17" fill="#0369a1" fill-opacity="0.4" stroke="#38bdf8" stroke-opacity="0.6" stroke-width="1.2" />
    <text x="18" y="22" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif" font-size="14" font-weight="700" fill="#38bdf8" letter-spacing="1.5">
      HEADLESS WAF • SPATIE-STANDARD
    </text>

    <!-- Main Title -->
    <text x="0" y="80" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif" font-size="52" font-weight="800" fill="#f8fafc" letter-spacing="-1">
      Laravel Security Monitor
    </text>

    <!-- Subtitle -->
    <text x="0" y="125" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif" font-size="22" font-weight="500" fill="#94a3b8">
      Bulwark — Enterprise Headless Web Application Firewall &amp; Audit Library
    </text>
  </g>

  <!-- Divider Line -->
  <line x1="100" y1="285" x2="1100" y2="285" stroke="#334155" stroke-width="1.5" stroke-opacity="0.7" />

  <!-- 4 Feature Highlight Cards -->
  <!-- Card 1: Zero-Tolerance WAF -->
  <g transform="translate(100, 315)">
    <rect width="235" height="155" rx="14" fill="url(#cardBg)" stroke="#334155" stroke-width="1.2" />
    <circle cx="40" cy="40" r="18" fill="#0284c7" fill-opacity="0.25" />
    <text x="40" y="46" font-family="-apple-system, BlinkMacSystemFont, sans-serif" font-size="18" text-anchor="middle" fill="#38bdf8">⚡</text>
    <text x="24" y="88" font-family="-apple-system, BlinkMacSystemFont, sans-serif" font-size="18" font-weight="700" fill="#f1f5f9">Zero-Tolerance</text>
    <text x="24" y="112" font-family="-apple-system, BlinkMacSystemFont, sans-serif" font-size="13" fill="#94a3b8">Blokir seketika pada request</text>
    <text x="24" y="130" font-family="-apple-system, BlinkMacSystemFont, sans-serif" font-size="13" fill="#94a3b8">pertama tanpa toleransi.</text>
  </g>

  <!-- Card 2: Device Quarantine -->
  <g transform="translate(355, 315)">
    <rect width="235" height="155" rx="14" fill="url(#cardBg)" stroke="#334155" stroke-width="1.2" />
    <circle cx="40" cy="40" r="18" fill="#6366f1" fill-opacity="0.25" />
    <text x="40" y="46" font-family="-apple-system, BlinkMacSystemFont, sans-serif" font-size="18" text-anchor="middle" fill="#818cf8">💻</text>
    <text x="24" y="88" font-family="-apple-system, BlinkMacSystemFont, sans-serif" font-size="18" font-weight="700" fill="#f1f5f9">Device Quarantine</text>
    <text x="24" y="112" font-family="-apple-system, BlinkMacSystemFont, sans-serif" font-size="13" fill="#94a3b8">Isolasi perangkat penyerang</text>
    <text x="24" y="130" font-family="-apple-system, BlinkMacSystemFont, sans-serif" font-size="13" fill="#94a3b8">pada IP publik bersama (NAT).</text>
  </g>

  <!-- Card 3: Webshell Scanner -->
  <g transform="translate(610, 315)">
    <rect width="235" height="155" rx="14" fill="url(#cardBg)" stroke="#334155" stroke-width="1.2" />
    <circle cx="40" cy="40" r="18" fill="#10b981" fill-opacity="0.25" />
    <text x="40" y="46" font-family="-apple-system, BlinkMacSystemFont, sans-serif" font-size="18" text-anchor="middle" fill="#34d399">🔍</text>
    <text x="24" y="88" font-family="-apple-system, BlinkMacSystemFont, sans-serif" font-size="18" font-weight="700" fill="#f1f5f9">Webshell Scanner</text>
    <text x="24" y="112" font-family="-apple-system, BlinkMacSystemFont, sans-serif" font-size="13" fill="#94a3b8">Baseline SHA-256 integritas</text>
    <text x="24" y="130" font-family="-apple-system, BlinkMacSystemFont, sans-serif" font-size="13" fill="#94a3b8">server &amp; audit berkas.</text>
  </g>

  <!-- Card 4: Stepped Lockout -->
  <g transform="translate(865, 315)">
    <rect width="235" height="155" rx="14" fill="url(#cardBg)" stroke="#334155" stroke-width="1.2" />
    <circle cx="40" cy="40" r="18" fill="#f59e0b" fill-opacity="0.25" />
    <text x="40" y="46" font-family="-apple-system, BlinkMacSystemFont, sans-serif" font-size="18" text-anchor="middle" fill="#fbbf24">🔒</text>
    <text x="24" y="88" font-family="-apple-system, BlinkMacSystemFont, sans-serif" font-size="18" font-weight="700" fill="#f1f5f9">Stepped Lockout</text>
    <text x="24" y="112" font-family="-apple-system, BlinkMacSystemFont, sans-serif" font-size="13" fill="#94a3b8">Eksponensial 1m hingga 24h</text>
    <text x="24" y="130" font-family="-apple-system, BlinkMacSystemFont, sans-serif" font-size="13" fill="#94a3b8">melawan serangan brute force.</text>
  </g>

  <!-- Footer Metadata Bar -->
  <g transform="translate(100, 520)">
    <text x="0" y="24" font-family="-apple-system, BlinkMacSystemFont, sans-serif" font-size="16" font-weight="600" fill="#64748b">
      PHP ^8.2 | ^8.3 | ^8.4  •  Laravel ^10 | ^11 | ^12 | ^13  •  100% Pure PHP  •  Zero NPM
    </text>
    <text x="1000" y="24" text-anchor="end" font-family="-apple-system, BlinkMacSystemFont, sans-serif" font-size="16" font-weight="700" fill="#38bdf8">
      github.com/robyajo/laravel-security-monitor
    </text>
  </g>
</svg>`;

async function run() {
  const buffer = Buffer.from(svg);
  await sharp(buffer)
    .png({ quality: 95 })
    .toFile(outPng);
  console.log('✓ Successfully generated:', outPng);
}

run().catch((err) => {
  console.error('Error generating OG image:', err);
  process.exit(1);
});
