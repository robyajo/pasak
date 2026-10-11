import fs from 'node:fs';
import path from 'node:path';
import sharp from 'sharp';

const ROOT = path.resolve(import.meta.dirname, '..');
const SVG_PATH = path.join(ROOT, 'src/assets/logo.svg');
const PUBLIC_DIR = path.join(ROOT, 'public');

async function main() {
  const svgBuffer = fs.readFileSync(SVG_PATH);

  // 1. Copy exact high-res SVG favicon to public/favicon.svg
  fs.writeFileSync(path.join(PUBLIC_DIR, 'favicon.svg'), svgBuffer);
  console.log('✓ Updated public/favicon.svg');

  // 2. Generate PNG sizes
  const sizes = [
    { name: 'favicon-16x16.png', size: 16 },
    { name: 'favicon-32x32.png', size: 32 },
    { name: 'apple-touch-icon.png', size: 180 },
    { name: 'android-chrome-192x192.png', size: 192 },
    { name: 'android-chrome-512x512.png', size: 512 }
  ];

  for (const item of sizes) {
    const dest = path.join(PUBLIC_DIR, item.name);
    await sharp(svgBuffer)
      .resize(item.size, item.size)
      .png({ compressionLevel: 9 })
      .toFile(dest);
    console.log(`✓ Generated ${item.name} (${item.size}x${item.size})`);
  }

  // 3. Update site.webmanifest
  const manifest = {
    name: 'Laravel Security Monitor',
    short_name: 'Bulwark WAF',
    description: 'Dokumentasi resmi robyajo/laravel-security-monitor (Bulwark) — Enterprise Headless Web Application Firewall untuk Laravel.',
    icons: [
      {
        src: '/android-chrome-192x192.png',
        sizes: '192x192',
        type: 'image/png'
      },
      {
        src: '/android-chrome-512x512.png',
        sizes: '512x512',
        type: 'image/png'
      }
    ],
    theme_color: '#0f172a',
    background_color: '#0b0f19',
    display: 'standalone',
    start_url: '/'
  };
  fs.writeFileSync(
    path.join(PUBLIC_DIR, 'site.webmanifest'),
    JSON.stringify(manifest, null, 2) + '\n'
  );
  console.log('✓ Updated public/site.webmanifest');
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
