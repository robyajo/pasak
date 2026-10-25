import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { ShieldCheck, ExternalLink, Code2, Globe, FileText, Lock } from "lucide-react";

interface PrivacyPolicyModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

export default function PrivacyPolicyModal({
    open,
    onOpenChange,
}: PrivacyPolicyModalProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl max-h-[85vh] overflow-y-auto">
                <DialogHeader className="gap-1 text-left border-b pb-3">
                    <div className="flex items-center gap-2 text-primary">
                        <ShieldCheck className="size-5" />
                        <DialogTitle className="text-lg font-bold">
                            Kebijakan Penggunaan &amp; Privasi
                        </DialogTitle>
                    </div>
                    <DialogDescription className="text-xs text-muted-foreground">
                        Informasi resmi pengembang, etika pengujian keamanan, dan privasi data aplikasi.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-2 text-sm text-foreground/90 leading-relaxed">
                    {/* Profil Pengembang */}
                    <div className="rounded-lg border bg-muted/40 p-3.5 space-y-2">
                        <div className="flex items-center gap-2 font-semibold text-xs tracking-wide uppercase text-muted-foreground">
                            <Code2 className="size-4 text-primary" />
                            Informasi Pengembang
                        </div>
                        <p className="text-xs">
                            Aplikasi konsol keamanan dan pengujian WAF ini dikembangkan secara independen oleh:
                        </p>
                        <div className="flex flex-wrap items-center gap-3 pt-1 text-xs">
                            <span className="font-bold text-foreground">
                                👤 Roby Karti S
                            </span>
                            <span className="text-muted-foreground">•</span>
                            <a
                                href="https://pasak.is-a.dev"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1 font-medium text-primary hover:underline"
                            >
                                <Globe className="size-3.5" />
                                pasak.is-a.dev
                                <ExternalLink className="size-3 opacity-70" />
                            </a>
                            <span className="text-muted-foreground">•</span>
                            <a
                                href="https://github.com/robyajo/pasak"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1 font-medium text-primary hover:underline"
                            >
                                <ExternalLink className="size-3.5" />
                                github.com/robyajo/pasak
                            </a>
                        </div>
                    </div>

                    {/* Etika & Kebijakan Pengujian */}
                    <div className="space-y-2">
                        <div className="flex items-center gap-2 font-semibold text-xs uppercase tracking-wide text-foreground">
                            <FileText className="size-4 text-amber-500" />
                            1. Kebijakan Pengujian Keamanan (Authorized Testing)
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Konsol uji WAF ini ditujukan semata-mata untuk verifikasi ketahanan sistem, audit keamanan defensif, simulasi serangan terkontrol, dan pengujian aturan filter WAF Pasak.
                        </p>
                        <ul className="list-disc list-inside text-xs space-y-1 text-muted-foreground pl-1">
                            <li>
                                Pengguna <strong>hanya diizinkan</strong> melakukan pengujian pada lingkungan lokal (<code className="bg-muted px-1 py-0.5 rounded">localhost</code>, <code className="bg-muted px-1 py-0.5 rounded">127.0.0.1</code>) atau sistem yang telah memberikan otorisasi tertulis resmi.
                            </li>
                            <li>
                                Dilarang keras memanfaatkan perangkat lunak ini untuk melancarkan serangan terhadap sasaran tanpa hak atau tanpa izin tertulis.
                            </li>
                        </ul>
                    </div>

                    {/* Kebijakan Privasi & Data */}
                    <div className="space-y-2">
                        <div className="flex items-center gap-2 font-semibold text-xs uppercase tracking-wide text-foreground">
                            <Lock className="size-4 text-emerald-500" />
                            2. Privasi Data &amp; Tanpa Telemetri Pihak Ketiga
                        </div>
                        <ul className="list-disc list-inside text-xs space-y-1 text-muted-foreground pl-1">
                            <li>
                                <strong>Penyimpanan Lokal:</strong> Seluruh riwayat pengujian, pengaturan target, log konsol, dan preferensi tema disimpan secara lokal di peramban Anda (<code className="bg-muted px-1 py-0.5 rounded">localStorage</code>) dan lingkungan server lokal Anda.
                            </li>
                            <li>
                                <strong>Kerahasiaan Kunci API:</strong> Jika Anda menggunakan integrasi Strix AI Pentest, kunci API LLM dan token rahasia tidak pernah dikirimkan atau dikoleksi ke server pihak ketiga milik pengembang.
                            </li>
                            <li>
                                <strong>Nol Pelacakan:</strong> Kami tidak memasang skrip analitik invasif atau pelacak data pribadi apa pun pada konsol ini.
                            </li>
                        </ul>
                    </div>

                    {/* Lisensi & Batasan Tanggung Jawab */}
                    <div className="rounded-md border border-border/80 bg-background/50 p-3 text-xs text-muted-foreground space-y-1">
                        <div className="font-semibold text-foreground">
                            3. Lisensi &amp; Batasan Tanggung Jawab
                        </div>
                        <p>
                            Perangkat lunak ini dirilis di bawah lisensi terbuka <strong>MIT License</strong>. Pengembang (Roby Karti S) tidak bertanggung jawab atas segala kerugian, kerusakan, atau implikasi hukum yang diakibatkan oleh penyalahgunaan aplikasi di luar batas etika dan hukum yang berlaku.
                        </p>
                    </div>
                </div>

                <DialogFooter className="pt-2 border-t flex flex-row items-center justify-between sm:justify-between">
                    <span className="text-[11px] text-muted-foreground">
                        © 2026 Roby Karti S • Pasak Security
                    </span>
                    <DialogClose render={<Button type="button" variant="default" size="sm">Tutup</Button>} />
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
