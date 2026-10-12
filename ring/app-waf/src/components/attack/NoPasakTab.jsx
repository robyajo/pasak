import { useState } from "react";
import { noPasakRecommendations } from "../../lib/noPasak.js";

/**
 * Tab "Tanpa Pasak = Jebol": daftar serangan yang 100% lolos bila WAF
 * belum terpasang (respons 200/404, tidak pernah 403).
 */
/**
 * Dampak nyata bila vektor lolos: apa yang bisa dilakukan penyerang.
 * Ditampilkan lewat tombol "Buktikan" per kartu + bukti dari body respons.
 */
const IMPACTS = [
    "Curi sesi & cookie pengunjung via <script> yang tereksekusi, deface halaman, keylogging. Respons 200 = browser korban akan menjalankan JS ini.",
    "Bypass login / dump tabel users via UNION SELECT. Tanpa signature SQLi, penyerang bisa enumerasi kolom password selangkah demi selangkah.",
    "Baca berkas sensitif server (/etc/passwd, config). Langkah awal menuju LFI → RCE via log poisoning.",
    "Ambil APP_KEY, DB_PASSWORD, kredensial SMTP/AWS dari .env → dekripsi cookie, akses database, ambil alih server.",
    "Eksekusi template di server (baca {{7*7}}=49) → RCE. Canary lolos = engine template mengevaluasi input penyerang.",
    "Petakan seluruh route, versi framework, dan celah tanpa hambatan. Tahap awal sebelum eksploitasi terarah.",
    "Suntik kode PHP via parameter → RCE bila ada include/eval. Pintu masuk webshell permanen.",
    "Tanam webshell permanen (shell.php.jpg + .htaccess) → eksekusi perintah jarak jauh (?cmd=id), pencurian data, ransomware.",
];

function evidenceFor(index, res) {
    if (!res?.success) {
        return "Kirim serangan dulu untuk melihat bukti respons.";
    }
    const body = (res.body ?? "").slice(0, 300);
    const snippet = body.replace(/\s+/g, " ").slice(0, 160);
    switch (index) {
        case 0:
            return `Query <script> dikembalikan mentah di body (${res.size} byte). Cuplikan: "${snippet}..." — browser korban mengeksekusinya.`;
        case 1:
            return `Server menjawab ${res.status} tanpa menolak pola OR 1=1. Penyerang bebas mencoba UNION SELECT berikutnya.`;
        case 2:
            return /root:/.test(res.body ?? "")
                ? "Body memuat 'root:' — isi /etc/passwd BERHASIL dibaca!"
                : `Status ${res.status} (bukan 403) = WAF tidak menolak traversal. Ganti path ke file yang ada untuk membaca isinya.`;
        case 3:
            return /APP_KEY|DB_/.test(res.body ?? "")
                ? "Body memuat APP_KEY/DB_* — kredensial BOCOR!"
                : `Status ${res.status} (bukan 403) = probe tidak diblokir. Di server salah konfigurasi, isi .env tampil di sini.`;
        case 4:
            return /49/.test(res.body ?? "")
                ? "Body memuat '49' — template MENGEVALUASI {{7*7}}! RCE selangkah lagi."
                : `Status ${res.status} (bukan 403) = canary tidak disaring. Cari '49' di body sebagai bukti evaluasi.`;
        case 5:
            return `UA sqlmap dijawab ${res.status} ramah. Scanner bebas mengkatalog ${res.size} byte halaman tanpa diblokir.`;
        case 6:
            return `Tag <?php lolos dengan status ${res.status}. Bila ada include/eval, perintah system($_GET) akan berjalan.`;
        default:
            return `Upload dijawab ${res.status} (bukan 403). Tanpa penolakan .php.jpg, webshell bisa ditanam lalu dieksekusi via ?cmd=id.`;
    }
}

export default function NoPasakTab({ sendRaw, target, onTargetChange }) {
    const [results, setResults] = useState({});
    const [busy, setBusy] = useState(null);
    const [proved, setProved] = useState({});
    const url = target || "http://localhost:8000";

    const fire = async (item, index) => {
        setBusy(index);
        const res = await sendRaw({
            target: url,
            path: item.path,
            method: item.method,
            userAgent: item.userAgent ?? "",
            upload: item.upload ?? null,
        });
        setResults((prev) => ({ ...prev, [index]: res }));
        setBusy(null);
    };

    const fireAll = async () => {
        for (let i = 0; i < noPasakRecommendations.length; i += 1) {
            // eslint-disable-next-line no-await-in-loop
            await fire(noPasakRecommendations[i], i);
        }
    };

    const done = Object.keys(results).length;
    const passedCount = Object.values(results).filter(
        (r) => r?.success && r.status !== 403 && r.status !== 429,
    ).length;
    const allDone = done >= noPasakRecommendations.length;

    return (
        <div className="flex flex-col gap-4">
            <section className="rounded-2xl border border-red-900 bg-red-950/40 p-5 text-sm">
                <p className="text-base font-bold text-red-300">
                    ⚠️ Tanpa Pasak = 100% lolos
                </p>
                <p className="mt-1 text-slate-300">
                    Laravel murni tidak punya WAF: semua vektor di bawah dijawab{" "}
                    <code>200/404</code> dan <b>tidak pernah 403</b>. Itu buktinya
                    aplikasi jebol. Setelah <code>composer require robyajo/pasak</code>{" "}
                    + <code>pasak:install</code>, vektor yang sama harus jadi{" "}
                    <code>403 diblokir</code>. Cek juga{" "}
                    <code>http://localhost:8000/</code> (Monitor Serangan Masuk).
                </p>
                <button
                    type="button"
                    onClick={fireAll}
                    disabled={busy !== null}
                    className="mt-3 rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-500 disabled:opacity-50"
                >
                    {busy !== null ? "Menyerang..." : "🔥 Jalankan semua (8 serangan)"}
                </button>
                <label className="mt-3 flex flex-col gap-1 text-xs font-semibold text-slate-300">
                    URL target
                    <input
                        type="url"
                        value={url}
                        placeholder="http://localhost:8000"
                        onChange={(e) => onTargetChange?.(e.target.value)}
                        className="w-full max-w-md rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 font-mono text-sm text-slate-100 placeholder:text-slate-600"
                    />
                </label>
                {(done > 0) && (
                    <div className="mt-3 rounded-xl border border-slate-700 bg-slate-950/60 p-3 text-xs">
                        <p className="text-sm font-bold">
                            📊 Verifikasi: {passedCount}/{noPasakRecommendations.length} lolos
                            ({Math.round((passedCount / noPasakRecommendations.length) * 100)}%)
                            {allDone && passedCount === noPasakRecommendations.length && " — TERBUKTI 100% JEBOL ✅"}
                            {allDone && passedCount < noPasakRecommendations.length && " — sebagian diblokir, berarti WAF sudah aktif sebagian."}
                        </p>
                        <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-800">
                            <div className="h-full bg-red-500" style={{ width: `${Math.round((passedCount / noPasakRecommendations.length) * 100)}%` }} />
                        </div>
                        {allDone && passedCount === noPasakRecommendations.length && (
                            <div className="mt-2 text-slate-300">
                                <p><b>Saran karena terbukti bekerja (tanpa Pasak):</b></p>
                                <ol className="ml-4 list-decimal">
                                    <li>Simpan bukti: ekspor History + screenshot badge LOLOS ini sebagai laporan baseline.</li>
                                    <li>Cek <code>http://localhost:8000/</code> — 8 serangan harus tercatat di Monitor = rantai deteksi berjalan.</li>
                                    <li>Pasang Pasak (<code>composer require robyajo/pasak → pasak:install → migrate</code>), ulangi “Jalankan semua” — target: 0/8 lolos, semua 403.</li>
                                    <li>Bila setelah Pasak masih ada yang lolos, laporkan label + statusnya — itu celah signature yang harus ditambal.</li>
                                </ol>
                            </div>
                        )}
                    </div>
                )}
            </section>

            <div className="grid gap-4 lg:grid-cols-2">
                {noPasakRecommendations.map((item, i) => {
                    const res = results[i];
                    const passed =
                        res?.success && res.status !== 403 && res.status !== 429;
                    return (
                        <article
                            key={item.label}
                            className="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 text-sm"
                        >
                            <div className="flex items-center justify-between gap-2">
                                <h3 className="font-bold text-slate-100">
                                    {i + 1}. {item.label}
                                </h3>
                                {res?.success && (
                                    <span
                                        className={`rounded-full px-2 py-0.5 text-[11px] font-bold ${passed ? "bg-red-600 text-white" : "bg-green-700 text-white"}`}
                                    >
                                        {passed
                                            ? `LOLOS (${res.status})`
                                            : `DIBLOKIR (${res.status})`}
                                    </span>
                                )}
                            </div>
                            <p className="mt-1 flex flex-wrap items-center gap-2 font-mono text-xs">
                                <span className={`rounded px-2 py-0.5 font-bold ${item.method === "GET" ? "bg-sky-900 text-sky-300" : item.method === "POST" ? "bg-emerald-900 text-emerald-300" : "bg-violet-900 text-violet-300"}`}>
                                    {item.method}
                                </span>
                                <span className="break-all text-sky-300">{item.path}</span>
                            </p>
                            <p className="mt-1 text-slate-400">
                                <b>Kenapa efektif:</b> {item.why}
                            </p>
                            <p className="text-slate-400">
                                <b>Bukti:</b> {item.proof}
                            </p>
                            <div className="mt-2 flex flex-wrap gap-2">
                            <button
                                type="button"
                                onClick={() => fire(item, i)}
                                disabled={busy !== null}
                                className="rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold hover:bg-slate-800 disabled:opacity-50"
                            >
                                {busy === i ? "Mengirim..." : "Kirim serangan ini"}
                            </button>
                            <button
                                type="button"
                                onClick={() =>
                                    setProved((prev) => ({ ...prev, [i]: !prev[i] }))
                                }
                                className="rounded-lg border border-amber-700 bg-amber-950/40 px-3 py-1.5 text-xs font-bold text-amber-300 hover:bg-amber-900/40"
                            >
                                {proved[i] ? "Sembunyikan bukti" : "💥 Buktikan: kalau lolos, apa yang bisa saya lakukan?"}
                            </button>
                            </div>
                            {proved[i] && (
                                <div className="mt-2 rounded-lg border border-amber-800 bg-amber-950/30 p-3 text-xs text-amber-200">
                                    <p><b>💀 Dampak:</b> {IMPACTS[i]}</p>
                                    <p className="mt-1 text-amber-200/80"><b>Bukti respons:</b> {evidenceFor(i, res)}</p>
                                    {!res && (
                                        <p className="mt-1">Tekan “Kirim serangan ini” dulu, lalu buka bukti ini lagi.</p>
                                    )}
                                </div>
                            )}
                            {res && (
                                <p className="mt-1 text-xs text-slate-500">
                                    {res.success
                                        ? `→ ${res.status} ${res.status_text} · ${res.duration_ms}ms · IP ${res.ip}`
                                        : `→ gagal: ${res.message}`}
                                </p>
                            )}
                        </article>
                    );
                })}
            </div>
        </div>
    );
}
