import { useState } from "react";
import { randomPublicIp } from "@/lib/ip";
import type { ProxyResult, SendRawFn } from "@/hooks/useWafConsole";
import type { AttackJob, AttackRequestOverride } from "@/components/attack/attackBuilder";

/** Satu hasil serangan yang dikumpulkan untuk AttackResults. */
export interface AttackResult extends AttackJob {
  request: AttackRequestOverride;
  ip: string;
  status: number;
  statusText: string;
  blocked: boolean;
  durationMs: number;
  size: number;
  responseHeaders: string;
  body: string;
}

/**
 * Menjalankan rangkaian serangan lewat `sendRaw` tanpa mengubah state tab
 * pertama. Hasil dikumpulkan agar bisa ditampilkan di `AttackResults`.
 */
export function useAttackRunner(sendRaw: SendRawFn | null | undefined) {
    const [running, setRunning] = useState<boolean>(false);
    const [results, setResults] = useState<AttackResult[]>([]);
    const [progress, setProgress] = useState<string>("");

    const run = async (jobs: AttackJob[]): Promise<AttackResult[]> => {
        if (!sendRaw || jobs.length === 0) {
            return [];
        }

        setRunning(true);
        const collected: AttackResult[] = [];

        for (let index = 0; index < jobs.length; index += 1) {
            setProgress(`Menjalankan serangan ${index + 1}/${jobs.length}...`);

            const job = jobs[index];
            const data: ProxyResult = await sendRaw({
                ...job.request,
                ip: randomPublicIp(),
            });

            collected.push({
                ...job,
                request: job.request as AttackRequestOverride,
                ip: typeof data?.ip === "string" ? data.ip : "",
                status: typeof data?.status === "number" ? data.status : 0,
                statusText: typeof data?.status_text === "string" ? data.status_text : "",
                blocked: data?.blocked === true,
                durationMs: typeof data?.duration_ms === "number" ? data.duration_ms : 0,
                size: typeof data?.size === "number" ? data.size : 0,
                responseHeaders: typeof data?.response_headers === "string" ? data.response_headers : "",
                body: typeof data?.body === "string" ? data.body : (typeof data?.message === "string" ? data.message : ""),
            });
            setResults([...collected]);
        }

        setProgress("");
        setRunning(false);

        return collected;
    };

    const clear = (): void => setResults([]);

    return { running, results, progress, run, clear };
}

export type AttackRunner = ReturnType<typeof useAttackRunner>;
