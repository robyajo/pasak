/**
 * Sinkronisasi metadata paket ke situs dokumentasi:
 *   - versi paket (dari git tag repo paket; fallback ke Packagist/CHANGELOG),
 *   - `CHANGELOG.md` -> `src/content/docs/changelog.md`,
 *   - versi terbaru Packagist -> `src/data/package.json`.
 *
 * Aman dijalankan tanpa jaringan: bila pengambilan gagal, berkas hasil generate
 * sebelumnya dipertahankan dan skrip tetap keluar dengan status 0 (tidak
 * menggagalkan build).
 *
 * Jalankan: node scripts/sync-package.mjs
 */
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const webRoot = join(here, '..');
const pkgRoot = join(webRoot, '..');

const PACKAGE = 'robyajo/laravel-security-monitor';
const REPOSITORY = `https://github.com/${PACKAGE}`;
const PACKAGIST = `https://packagist.org/packages/${PACKAGE}`;
const RAW_CHANGELOG = `https://raw.githubusercontent.com/${PACKAGE}/main/CHANGELOG.md`;

const dataDir = join(webRoot, 'src', 'data');
const dataFile = join(dataDir, 'package.json');
const changelogFile = join(webRoot, 'src', 'content', 'docs', 'changelog.md');

function readJson(path) {
	try {
		return JSON.parse(readFileSync(path, 'utf8'));
	} catch {
		return null;
	}
}

/** Versi dari tag git repo paket, hanya bila repo paket tersedia lokal. */
function localVersion() {
	const composerPath = join(pkgRoot, 'composer.json');

	if (!existsSync(composerPath) || readJson(composerPath)?.name !== PACKAGE) {
		return null;
	}

	try {
		const tag = execFileSync('git', ['-C', pkgRoot, 'describe', '--tags', '--abbrev=0'], {
			encoding: 'utf8',
		}).trim();

		return tag ? tag.replace(/^v/, '') : null;
	} catch {
		return null;
	}
}

async function fetchWithTimeout(url, as = 'text', timeout = 15000) {
	const controller = new AbortController();
	const timer = setTimeout(() => controller.abort(), timeout);

	try {
		const response = await fetch(url, {
			signal: controller.signal,
			headers: { 'User-Agent': 'laravel-security-monitor-docs' },
		});

		if (!response.ok) {
			throw new Error(`HTTP ${response.status}`);
		}

		return as === 'json' ? await response.json() : await response.text();
	} finally {
		clearTimeout(timer);
	}
}

/** Buang H1 pertama (judul "Changelog") karena Starlight merender `title`. */
function stripLeadingH1(markdown) {
	return markdown.replace(/^#\s+.*\n+/, '');
}

function firstVersionFromChangelog(markdown) {
	const match = markdown.match(/^##\s+\[?v?(\d+\.\d+\.\d+[^\]\s]*)\]?/m);

	return match ? match[1] : null;
}

async function main() {
	// --- CHANGELOG: utamakan repo paket lokal, fallback ke GitHub raw ---
	let changelog = null;

	if (existsSync(join(pkgRoot, 'CHANGELOG.md'))) {
		changelog = readFileSync(join(pkgRoot, 'CHANGELOG.md'), 'utf8');
		console.log('• CHANGELOG : repo lokal');
	} else {
		try {
			changelog = await fetchWithTimeout(RAW_CHANGELOG);
			console.log('• CHANGELOG : GitHub raw');
		} catch (error) {
			console.warn(`! CHANGELOG gagal diambil: ${error.message}`);
		}
	}

	// --- Versi paket ---
	const version = localVersion() ?? (changelog ? firstVersionFromChangelog(changelog) : null);

	// --- Versi terbaru di Packagist ---
	let packagist = null;

	try {
		const data = await fetchWithTimeout(`https://repo.packagist.org/p2/${PACKAGE}.json`, 'json');
		const releases = data?.packages?.[PACKAGE] ?? [];
		const stable = releases.find((release) => /^v?\d+\.\d+\.\d+$/.test(release.version ?? ''));

		if (stable) {
			packagist = {
				version: String(stable.version).replace(/^v/, ''),
				released: (stable.time ?? '').slice(0, 10) || null,
			};
			console.log(`• Packagist : v${packagist.version}`);
		}
	} catch (error) {
		console.warn(`! Packagist gagal diambil: ${error.message}`);
	}

	const previous = readJson(dataFile) ?? {};
	const finalVersion = version ?? packagist?.version ?? previous.version ?? '0.0.0';
	const finalPackagistVersion = packagist?.version ?? previous.packagist?.version ?? finalVersion;
	const finalReleased = packagist?.released ?? previous.packagist?.released ?? null;

	// Pertahankan updatedAt bila versi tidak berubah agar git status tidak terus-menerus kotor
	const isSameVersion = previous.version === finalVersion && previous.packagist?.version === finalPackagistVersion;
	const updatedAt = isSameVersion && previous.updatedAt ? previous.updatedAt : new Date().toISOString().slice(0, 10);

	const payload = {
		name: PACKAGE,
		version: finalVersion,
		repository: REPOSITORY,
		packagist: {
			url: PACKAGIST,
			version: finalPackagistVersion,
			released: finalReleased,
		},
		updatedAt,
	};

	mkdirSync(dataDir, { recursive: true });
	const newJson = `${JSON.stringify(payload, null, 2)}\n`;
	if (!existsSync(dataFile) || readFileSync(dataFile, 'utf8') !== newJson) {
		writeFileSync(dataFile, newJson, 'utf8');
		console.log('✓ src/data/package.json');
	}

	// --- changelog.md ---
	if (changelog) {
		const frontmatter = [
			'---',
			'title: "Changelog"',
			`description: "Riwayat lengkap perubahan paket ${PACKAGE}. Versi terbaru: v${payload.packagist.version}."`,
			'---',
			'',
		].join('\n');

		const note = [
			`Versi terbaru: **v${payload.packagist.version}**`,
			`[Packagist](${PACKAGIST})`,
			`[Repositori GitHub](${REPOSITORY})`,
		].join(' · ');

		const body = stripLeadingH1(changelog).trim();
		const newChangelog = `${frontmatter}\n${note}\n\n${body}\n`;

		if (!existsSync(changelogFile) || readFileSync(changelogFile, 'utf8') !== newChangelog) {
			writeFileSync(changelogFile, newChangelog, 'utf8');
			console.log('✓ src/content/docs/changelog.md');
		}
	} else {
		console.warn('! changelog.md tidak diperbarui (sumber tidak tersedia).');
	}
}

main().catch((error) => {
	console.warn(`! sync-package dilewati: ${error.message}`);
});
