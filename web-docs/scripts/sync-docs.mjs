/**
 * Sinkronisasi dokumentasi.
 *
 * Mengubah bab Markdown di `documents/<section>/NN-*.md` menjadi konten
 * Starlight di `src/content/docs/<section>/*.md`:
 *   - slug bersih (tanpa awalan nomor),
 *   - frontmatter (title, description, sidebar.order) ditambahkan otomatis,
 *   - H1 pertama dipindah ke frontmatter `title` agar tidak tampil ganda.
 *
 * Jalankan: node scripts/sync-docs.mjs
 */
import { existsSync, mkdirSync, readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const webRoot = join(here, '..');
const docsRoot = join(webRoot, '..', 'documents');
const outRoot = join(webRoot, 'src', 'content', 'docs');

/** Pemetaan folder sumber -> folder tujuan Starlight. */
const SECTIONS = [
  { from: '01-getting-started', to: 'getting-started' },
  { from: '02-core-architecture', to: 'core-architecture' },
  { from: '03-security-modules', to: 'security-modules' },
  { from: '04-rest-api-reference', to: 'api-reference' },
  { from: '05-cli-and-automation', to: 'cli-automation' },
  { from: '06-webserver-hardening', to: 'webserver-hardening' },
  { from: '07-integration-guides', to: 'integration-guides' },
];

/** "01. Judul" atau "01-Judul" -> "Judul". */
function stripNumber(value) {
  return value.replace(/^\d+[.)-]\s*/, '').trim();
}

/** Ambil paragraf pertama sebagai description (dibersihkan dari markup). */
function firstParagraph(body) {
  for (const rawLine of body.split('\n')) {
    const line = rawLine.trim();

    if (
      line === '' ||
      line.startsWith('#') ||
      line.startsWith('>') ||
      line.startsWith('|') ||
      line.startsWith('```') ||
      line.startsWith('- ') ||
      line.startsWith('* ') ||
      /^\d+\.\s/.test(line) ||
      line === '---'
    ) {
      continue;
    }

    const cleaned = line
      .replace(/`/g, '')
      .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
      .replace(/[*_]/g, '')
      .replace(/\s+/g, ' ')
      .trim();

    if (cleaned.length <= 155) {
      return cleaned;
    }

    const truncated = cleaned.slice(0, 155);
    const lastSpace = truncated.lastIndexOf(' ');
    return (lastSpace > 80 ? truncated.slice(0, lastSpace) : truncated).trim() + '...';
  }

  return '';
}

function yamlQuote(value) {
  return `"${String(value).replace(/\\/g, '\\\\').replace(/"/g, '\\"')}"`;
}

// Bersihkan folder bagian yang dikelola skrip ini (biarkan index.mdx manual).
for (const section of SECTIONS) {
  const dir = join(outRoot, section.to);

  if (existsSync(dir)) {
    rmSync(dir, { recursive: true, force: true });
  }
}

let total = 0;

for (const section of SECTIONS) {
  const srcDir = join(docsRoot, section.from);
  const outDir = join(outRoot, section.to);

  mkdirSync(outDir, { recursive: true });

  const files = readdirSync(srcDir)
    .filter((file) => file.endsWith('.md'))
    .sort();

  for (const file of files) {
    const raw = readFileSync(join(srcDir, file), 'utf8').replace(/\r\n/g, '\n');

    const orderMatch = file.match(/^(\d+)/);
    const order = orderMatch ? Number(orderMatch[1]) : 999;

    const h1Match = raw.match(/^#\s+(.+)$/m);
    const title = h1Match ? stripNumber(h1Match[1]) : stripNumber(file.replace(/\.md$/, ''));

    let body = raw;

    if (h1Match) {
      body = raw.replace(`${h1Match[0]}\n`, '').replace(/^\s*\n/, '');
    }

    const description = firstParagraph(body);
    const outName = file.replace(/^\d+[.)-]\s*/, '');

    const frontmatter = [
      '---',
      `title: ${yamlQuote(title)}`,
      description ? `description: ${yamlQuote(description)}` : null,
      'sidebar:',
      `  order: ${order}`,
      '---',
      '',
    ]
      .filter((line) => line !== null)
      .join('\n');

    writeFileSync(join(outDir, outName), `${frontmatter}\n${body}`, 'utf8');
    total += 1;
  }
}

console.log(`✓ Sinkron ${total} berkas ke src/content/docs/`);
