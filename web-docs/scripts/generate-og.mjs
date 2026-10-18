import { writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const here = dirname(fileURLToPath(import.meta.url));
const webRoot = join(here, '..');
const outPng = join(webRoot, 'public', 'og-image.png');

const FONT = `'DejaVu Sans', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif`;

const chipsRow1 = ['Zero-Tolerance Block', 'Karantina Perangkat', 'Proteksi Login Bertingkat'];
const chipsRow2 = ['Pemindai Webshell', 'REST API Headless'];

function chip(text, x, y) {
    const width = 42 + text.length * 11.5;
    return `
  <rect x="${x}" y="${y}" width="${width}" height="42" rx="21" fill="#0ea5e9" fill-opacity="0.10" stroke="#0ea5e9" stroke-opacity="0.45" stroke-width="1.4"/>
  <circle cx="${x + 22}" cy="${y + 21}" r="5" fill="#38bdf8"/>
  <text x="${x + 36}" y="${y + 27}" font-family="${FONT}" font-size="17" font-weight="700" fill="#e2f4ff">${text}</text>`;
}

const row1 = chipsRow1.reduce((state, text) => {
    const width = 42 + text.length * 11.5;
    return { x: state.x + width + 16, svg: `${state.svg}${chip(text, state.x, 452)}` };
}, { x: 96, svg: '' }).svg;

const row2 = chipsRow2.reduce((state, text) => {
    const width = 42 + text.length * 11.5;
    return { x: state.x + width + 16, svg: `${state.svg}${chip(text, state.x, 508)}` };
}, { x: 96, svg: '' }).svg;

const svg = `<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="1200" height="630" viewBox="0 0 1200 630" fill="none">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1200" y2="630" gradientUnits="userSpaceOnUse">
      <stop offset="0" stop-color="#020817"/>
      <stop offset="0.55" stop-color="#0a1526"/>
      <stop offset="1" stop-color="#0b1a33"/>
    </linearGradient>
    <radialGradient id="glow1" cx="0.12" cy="0.1" r="0.55" gradientUnits="objectBoundingBox">
      <stop offset="0" stop-color="#0ea5e9" stop-opacity="0.22"/>
      <stop offset="1" stop-color="#0ea5e9" stop-opacity="0"/>
    </radialGradient>
    <radialGradient id="glow2" cx="0.9" cy="0.95" r="0.65" gradientUnits="objectBoundingBox">
      <stop offset="0" stop-color="#4f46e5" stop-opacity="0.28"/>
      <stop offset="1" stop-color="#4f46e5" stop-opacity="0"/>
    </radialGradient>
    <linearGradient id="logoGrad" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#38bdf8"/>
      <stop offset="1" stop-color="#2563eb"/>
    </linearGradient>
    <linearGradient id="ruleGrad" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0" stop-color="#38bdf8"/>
      <stop offset="1" stop-color="#6366f1"/>
    </linearGradient>
    <g id="logo" width="84" height="84" viewBox="0 0 84 84" x="0" y="0">
      <rect width="84" height="84" rx="20" fill="url(#logoGrad)"/>
      <path d="M28 38 39 49 57 29" stroke="#ffffff" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>
    </g>
  </defs>

  <rect width="1200" height="630" fill="url(#bg)"/>
  <rect width="1200" height="630" fill="url(#glow1)"/>
  <rect width="1200" height="630" fill="url(#glow2)"/>

  <g stroke="#1e3a5f" stroke-opacity="0.5" stroke-width="1">
    <path d="M0 105h1200M0 210h1200M0 315h1200M0 420h1200M0 525h1200"/>
    <path d="M200 0v630M400 0v630M600 0v630M800 0v630M1000 0v630"/>
  </g>

  <rect x="0" y="606" width="1200" height="24" fill="#1e3a8a" fill-opacity="0.85"/>
  <rect x="0" y="604" width="1200" height="2" fill="#3b82f6" fill-opacity="0.5"/>

  <use xlink:href="#logo" x="104" y="64"/>
  <text x="206" y="116" font-family="${FONT}" font-size="38" font-weight="700" fill="#f8fafc">Pasak</text>
  <text x="206" y="146" font-family="${FONT}" font-size="17" font-weight="700" fill="#7dd3fc">Bulwark  •  Enterprise Headless WAF</text>

  <text x="100" y="286" font-family="${FONT}" font-size="58" font-weight="800" letter-spacing="-1" fill="#f8fafc">Dokumentasi Resmi Pasak</text>
  <text x="100" y="350" font-family="${FONT}" font-size="22" fill="#94a3b8">Panduan lengkap 25 bab — REST API • Dashboard • Hardening Server</text>

  <rect x="100" y="386" width="550" height="4" rx="2" fill="url(#ruleGrad)"/>
${row1}
${row2}

  <rect x="96" y="576" width="224" height="42" rx="21" fill="#031a2e" fill-opacity="0.9" stroke="#22d3ee" stroke-opacity="0.6" stroke-width="1.6"/>
  <text x="120" y="606" font-family="${FONT}" font-size="19" font-weight="700" fill="#67e8f9">pasak.is-a.dev</text>
  <text x="1104" y="608" text-anchor="end" font-family="${FONT}" font-size="17" font-weight="700" fill="#94a3b8">GitHub: robyajo/pasak</text>
</svg>`;

async function run() {
    const buffer = Buffer.from(svg);
    const info = await sharp(buffer)
        .resize(1200, 630)
        .png({ palette: true, colors: 64, compressionLevel: 9 })
        .toFile(outPng);
    console.log(`✓ OG image generated: ${outPng} (${info.width}x${info.height}, ${Math.round(info.size / 1024)} KB, ${info.channels} channels)`);
}

run().catch((err) => {
    console.error('Error generating OG image:', err);
    process.exit(1);
});
