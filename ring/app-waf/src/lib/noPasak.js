/**
 * Rekomendasi serangan yang 100% lolos (efektif) ketika Pasak BELUM terpasang.
 * Tanpa WAF, Laravel murni menjawab 200/404 — tidak pernah 403 — sehingga
 * semua vektor di bawah dijamin "berhasil masuk" (bibaca: aplikasinya jebol).
 */
export const noPasakRecommendations = [
    {
        label: "XSS Reflected <script>",
        path: "/?q=%3Cscript%3Ealert(1)%3C%2Fscript%3E",
        method: "GET",
        why: "Tanpa filter XSS, query dicerminkan ke respons tanpa penyaringan.",
        proof: "Status 200, body memuat kembali query. Cek di beranda laravel-none (tercatat di Monitor).",
    },
    {
        label: "SQLi Auth Bypass OR 1=1",
        path: "/?user=admin%27%20OR%20%271%27%3D%271",
        method: "GET",
        why: "Tanpa signature sql_injection, pola OR 1=1 lolos tanpa log/blokir.",
        proof: "Status 200/404, tidak pernah 403. Bukti tidak adanya proteksi.",
    },
    {
        label: "Path Traversal /etc/passwd",
        path: "/../../../../etc/passwd",
        method: "GET",
        why: "Tanpa instant-block path_traversal, traversal mentah diteruskan ke router.",
        proof: "Status 404 (bukan 403) — artinya lolos dari WAF, hanya gagal routing.",
    },
    {
        label: "Probe /.env",
        path: "/.env",
        method: "GET",
        why: "Tanpa config_probe, file kredensial utama tetap bisa di-probe.",
        proof: "Status 404/200, tidak pernah 403. Setelah Pasak: 403.",
    },
    {
        label: "SSTI Canary {{7*7}}",
        path: "/?q=%7B%7B7*7%7D%7D",
        method: "GET",
        why: "Tanpa signature ssti, canary template lolos mentah.",
        proof: "Status 200. Setelah Pasak: 403 instant-block.",
    },
    {
        label: "Scanner UA sqlmap",
        path: "/",
        method: "GET",
        userAgent: "sqlmap/1.7.2#stable (http://sqlmap.org)",
        why: "Tanpa deteksi scanner_ua, tool enumerasi bebas memetakan aplikasi.",
        proof: "Status 200. Setelah Pasak: 403.",
    },
    {
        label: "PHP Injection <?php",
        path: "/?name=%3C%3Fphp%20system(%24_GET%5B%27c%27%5D)%3B%20%3F%3E",
        method: "GET",
        why: "Tanpa signature php_injection, tag PHP via parameter tidak disaring.",
        proof: "Status 200/404. Setelah Pasak: 403.",
    },
    {
        label: "Upload shell.php.jpg",
        path: "/upload",
        method: "POST",
        upload: {
            field: "file",
            filename: "shell.php.jpg",
            content_type: "image/jpeg",
            content: '<?php system($_GET["cmd"]); ?>',
        },
        why: "Tanpa unsafe_filename/webshell_upload, double-extension tidak ditolak di level WAF.",
        proof: "Status 404 (route belum ada) tapi bukan 403 — WAF tidak menolak.",
    },
];
