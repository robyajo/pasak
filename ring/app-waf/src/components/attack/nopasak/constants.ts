import {
    ContactIcon,
    FolderSearchIcon,
    KeyRoundIcon,
    UsersIcon,
} from "lucide-react";
import type { NoPasakRecommendation } from "@/lib/noPasak";
import type { ProxyResult } from "@/hooks/useWafConsole";
import type { ProofGrade } from "../sheet";

export const DEMO_LOCAL_IP = "192.168.1.50";

export const IMPACTS: Record<string, string> = {
    "Baseline GET /":
        "Tidak ada dampak — ini titik acuan. Semua tahap berikutnya dibandingkan terhadap respons normal ini.",
    "Scanner UA sqlmap":
        "Petakan seluruh route, versi framework, dan celah tanpa hambatan. Tahap awal sebelum eksploitasi terarah.",
    "Probe /.env":
        "Ambil APP_KEY, DB_PASSWORD, kredensial SMTP/AWS dari .env → dekripsi cookie, akses database, ambil alih server.",
    "Path Traversal /etc/passwd":
        "Baca berkas sensitif server (/etc/passwd, config). Langkah awal menuju LFI → RCE via log poisoning.",
    "XSS Reflected <script>":
        "Curi sesi & cookie pengunjung via <script> yang tereksekusi, deface halaman, keylogging. Respons 200 = browser korban akan menjalankan JS ini.",
    "SQLi Auth Bypass OR 1=1":
        "Bypass login / dump tabel users via UNION SELECT. Penyerang bisa enumerasi kolom password selangkah demi selangkah.",
    "SSTI Canary {{7*7}}":
        "Eksekusi template di server (baca {{7*7}}=49) → RCE. Canary lolos = engine template mengevaluasi input penyerang.",
    "PHP Injection <?php":
        "Suntik kode PHP via parameter → RCE bila ada include/eval. Pintu masuk webshell permanen.",
    "Upload shell.php.jpg":
        "Tanam webshell permanen (shell.php.jpg) → eksekusi perintah jarak jauh (?cmd=id), pencurian data, ransomware.",
};

export const QUICK_COMMANDS = [
    "id",
    "whoami",
    "pwd",
    "ls",
    "cat /etc/hostname",
] as const;

export const ATTACKER_PRESETS = [
    {
        label: "🗝️ Curi kredensial (.env)",
        cmd: "grep -E '^(APP_KEY|DB_)' .env",
        icon: KeyRoundIcon,
    },
    {
        label: "👥 Curi daftar user (DB)",
        cmd: 'php artisan tinker --execute=\'echo App\\Models\\User::pluck("email")->join(", ");\' 2>/dev/null | tail -1',
        icon: UsersIcon,
    },
    {
        label: "📇 Curi data kontak (DB)",
        cmd: 'php artisan tinker --execute=\'echo App\\Models\\Contact::pluck("email")->join(", ");\' 2>/dev/null | tail -1',
        icon: ContactIcon,
    },
    {
        label: "📁 Petakan file penting",
        cmd: "ls -la .env database/ storage/app/public/ 2>/dev/null",
        icon: FolderSearchIcon,
    },
] as const;

export const PREVIEW_PAGES = ["/", "/upload", "/contacts", "/waf-hits.json"] as const;

export function evidenceFor(
    item: NoPasakRecommendation,
    res: ProxyResult | undefined,
): string {
    if (!res?.success) {
        return "Kirim serangan dulu untuk melihat bukti respons.";
    }
    const body = res.body ?? "";
    const snippet = body.replace(/\s+/g, " ").slice(0, 160);
    switch (item.label) {
        case "Baseline GET /":
            return `GET / menjawab ${res.status} dalam ${res.duration_ms}ms — target hidup, baseline sah.`;
        case "Path Traversal /etc/passwd":
            return /root:/.test(body)
                ? "Body memuat 'root:' — isi /etc/passwd BERHASIL dibaca!"
                : `Status ${res.status} (bukan 403) = WAF tidak menolak traversal.`;
        case "Probe /.env":
            if (/APP_KEY|DB_/.test(body)) {
                return "Body memuat APP_KEY/DB_* — kredensial BOCOR!";
            }
            if (res.status === 403 || res.status === 429) {
                return `Status ${res.status} dengan body kecil (${res.size} byte)? Itu ciri blokir level WEB SERVER (mis. aturan Nginx "deny dotfiles") — BUKAN WAF aplikasi. Cek header Server + ukuran body untuk memastikan, lalu catat sebagai temuan positif infra dan lanjut dengan catatan.`;
            }
            return `Status ${res.status} (bukan 403) = probe tidak diblokir.`;
        case "SSTI Canary {{7*7}}":
            return /49/.test(body)
                ? "Body memuat '49' — template MENGEVALUASI {{7*7}}! RCE selangkah lagi."
                : `Status ${res.status} (bukan 403) = canary tidak disaring.`;
        case "XSS Reflected <script>":
            return `Query <script> dikembalikan mentah (${res.size} byte). Cuplikan: "${snippet}..."`;
        case "Upload shell.php.jpg":
            return /"ok":true/.test(body)
                ? `Respons {"ok":true} — file DITERIMA server. Lanjut ke verifikasi file di bawah.`
                : `Status ${res.status}. Cuplikan: "${snippet}..."`;
        default:
            return `Status ${res.status} (bukan 403) — serangan tidak ditolak. Cuplikan: "${snippet}..."`;
    }
}

export const isPassed = (res: ProxyResult | undefined): boolean =>
    res?.success === true && res.status !== 403 && res.status !== 429;

/**
 * Jujur seperti hacker: 403 = ditolak; lolos tanpa jejak = hanya "tak diblokir";
 * TERBUKTI hanya bila body memuat jejak (refleksi payload, root:, ok:true, ...).
 */
export function gradeVector(res: ProxyResult, path: string): ProofGrade {
    if (res.success !== true || res.status === 403 || res.status === 429) {
        return "blocked";
    }
    const body = res.body ?? "";
    let fragment = "";
    try {
        fragment = decodeURIComponent(path.split("?")[1] ?? "").slice(0, 40);
    } catch {
        fragment = "";
    }
    const markers = [
        fragment.length > 6 ? fragment : "",
        "root:",
        '"ok":true',
        "APP_KEY",
        "document.cookie",
        "onerror=",
        "onload=",
    ].filter((mk) => mk !== "");
    return markers.some((mk) => body.includes(mk)) ? "proven" : "passed";
}
