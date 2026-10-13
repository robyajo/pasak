import { create } from "zustand";
import { persist } from "zustand/middleware";
import { randomPublicIp } from "@/lib/ip";

function newDeviceId(browser = "lab"): string {
    return `${browser}-${Math.random().toString(36).slice(2, 10)}`;
}

export interface LabBrowser {
    code: string;
    label: string;
    ua: string;
}

export const LAB_BROWSERS: LabBrowser[] = [
    {
        code: "lab",
        label: "Default lab",
        ua: "Mozilla/5.0 (WAF-Lab)",
    },
    {
        code: "chrome",
        label: "Chrome (Windows)",
        ua: "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36",
    },
    {
        code: "firefox",
        label: "Firefox (Linux)",
        ua: "Mozilla/5.0 (X11; Linux x86_64; rv:133.0) Gecko/20100101 Firefox/133.0",
    },
    {
        code: "safari",
        label: "Safari (macOS)",
        ua: "Mozilla/5.0 (Macintosh; Intel Mac OS X 14_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15",
    },
    {
        code: "edge",
        label: "Edge (Windows)",
        ua: "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0",
    },
];

export function labBrowserUa(code: string): string {
    return LAB_BROWSERS.find((b) => b.code === code)?.ua ?? LAB_BROWSERS[0].ua;
}

interface LabState {
    /** URL target audit (cermin form konsol, tersimpan antar reload). */
    target: string;
    /** IP penyerang simulasi (selalu terisi; acak baru tiap buka halaman). */
    attackerIp: string;
    /** ID perangkat lab — X-Device-Id di semua kiriman + preview (tersimpan). */
    deviceId: string;
    /** Browser simulasi — UA bawaan + prefix ID perangkat. */
    browser: string;
    setTarget: (target: string) => void;
    setAttackerIp: (attackerIp: string) => void;
    newAttackerIp: () => void;
    rotateDevice: () => void;
    setBrowser: (code: string) => void;
    /** Kembalikan store ke awal (target kosong, IP acak, device baru). */
    resetLab: () => void;
}

/**
 * Identitas lab terpusat (Zustand): URL target + IP penyerang dipakai
 * serangan, preview korban, cek blokir, dan laporan — satu sumber.
 * Hanya target + deviceId yang persist; IP selalu acak baru tiap dibuka.
 */
export const useLabStore = create<LabState>()(
    persist(
        (set) => ({
            target: "",
            attackerIp: randomPublicIp(),
            deviceId: newDeviceId(),
            browser: "lab",
            setTarget: (target) => set({ target }),
            setAttackerIp: (attackerIp) => set({ attackerIp }),
            newAttackerIp: () => set({ attackerIp: randomPublicIp() }),
            rotateDevice: () =>
                set((s) => ({ deviceId: newDeviceId(s.browser) })),
            setBrowser: (browser) =>
                set({ browser, deviceId: newDeviceId(browser) }),
            resetLab: () =>
                set({
                    target: "",
                    attackerIp: randomPublicIp(),
                    deviceId: newDeviceId(),
                    browser: "lab",
                }),
        }),
        {
            name: "waf-lab-store",
            partialize: (state) => ({
                target: state.target,
                deviceId: state.deviceId,
                browser: state.browser,
            }),
        },
    ),
);
