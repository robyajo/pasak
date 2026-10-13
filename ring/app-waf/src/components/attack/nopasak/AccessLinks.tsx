import { ExternalLinkIcon } from "lucide-react";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import type { AccessLinksProps } from "./types";

/**
 * Cara mengakses hasil upload yang berhasil: link langsung yang bisa diklik
 * (buka di tab baru) — persis seperti yang dilakukan penyerang.
 */
export function AccessLinks({ url, body }: AccessLinksProps) {
    let filename = "shell.php.jpg";
    let filePath = "/storage/demo-uploads/shell.php.jpg";
    try {
        const parsed = JSON.parse(body ?? "");
        if (parsed.filename) {
            filename = parsed.filename;
        }
        if (parsed.url) {
            filePath = parsed.url;
        }
    } catch {
        // Pakai bawaan bila body bukan JSON.
    }
    const base = (url || "http://localhost:8000").replace(/\/+$/, "");
    const links = [
        {
            label: "📂 File publik (isi PHP tampil)",
            href: `${base}${filePath}`,
        },
        {
            label: "☠️ Eksekusi ?cmd=id",
            href: `${base}/shell/${filename}?cmd=id`,
        },
        {
            label: "☠️ Eksekusi ?cmd=whoami",
            href: `${base}/shell/${filename}?cmd=whoami`,
        },
        { label: "📤 Halaman daftar upload (/upload)", href: `${base}/upload` },
    ];
    return (
        <Alert className="mt-2">
            <ExternalLinkIcon data-icon="inline-start" />
            <AlertTitle>
                🔗 Cara mengakses hasil (klik untuk membuka):
            </AlertTitle>
            <AlertDescription>
                <ul className="mt-1 flex flex-col gap-1">
                    {links.map((link) => (
                        <li key={link.href}>
                            <Button
                                variant="link"
                                size="sm"
                                className="h-auto justify-start p-0 font-mono break-all whitespace-normal"
                                render={
                                    <a
                                        href={link.href}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        {link.label} — {link.href}
                                    </a>
                                }
                            />
                        </li>
                    ))}
                </ul>
            </AlertDescription>
        </Alert>
    );
}
