import { useEffect, useState } from "react";

/**
 * Lokasi geografis IP penyerang (negara, provinsi, kota) dari
 * dev-server (`GET /api/geo?ip=`). `lokasi: false` berarti alamat
 * privat/reserved atau tak dikenal — label biarkan kosong.
 */
export interface AttackerLocation {
    ip: string;
    lokasi: boolean;
    country?: string;
    country_code?: string;
    region?: string;
    city?: string;
    lat?: number;
    lon?: number;
    isp?: string;
    message?: string;
}

interface GeoResponse extends AttackerLocation {
    success: boolean;
}

/** Cache antar render: IP sama tidak di-fetch ulang saat pindah tahap. */
const locationCache = new Map<string, AttackerLocation>();

/**
 * Ambil lokasi IP penyerang dari dev-server sekali per alamat.
 * Dipakai kartu identitas dan kartu penyerang di tab Tanpa-Pasak.
 */
export function useAttackerLocation(ip: string): AttackerLocation | null {
    const key = ip.trim();
    const cached = key === "" ? undefined : locationCache.get(key);
    const [location, setLocation] = useState<AttackerLocation | null>(
        cached ?? null,
    );

    useEffect(() => {
        if (key === "") {
            setLocation(null);

            return undefined;
        }

        const hit = locationCache.get(key);

        if (hit !== undefined) {
            setLocation(hit);

            return undefined;
        }

        const controller = new AbortController();
        let active = true;

        fetch(`/api/geo?ip=${encodeURIComponent(key)}`, {
            signal: controller.signal,
        })
            .then((res) => (res.ok ? res.json() : null))
            .then((data: GeoResponse | null) => {
                if (!active || !data || data.success !== true) {
                    return;
                }
                const { success, ...location } = data;
                void success;
                locationCache.set(key, location);
                setLocation(location);
            })
            .catch(() => {
                // Dev-server mati / offline — biarkan kosong, tak pernah
                // mengganggu alur audit.
            });

        return () => {
            active = false;
            controller.abort();
        };
    }, [key]);

    return location;
}

/**
 * Label satu baris "Kota, Provinsi, Negara" — kosong bila datanya
 * tidak lengkap / IP tak berlokasi.
 */
export function attackerLocationLabel(
    location: AttackerLocation | null,
): string {
    if (!location || location.lokasi !== true) {
        return "";
    }

    const parts = [location.city, location.region, location.country];

    return parts
        .filter(
            (part): part is string =>
                typeof part === "string" && part.trim() !== "",
        )
        .join(", ");
}
