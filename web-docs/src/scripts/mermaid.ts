/**
 * Render diagram Mermaid dari blok kode ```mermaid pada halaman dokumentasi.
 *
 * Pustaka `mermaid` diimpor secara dinamis sehingga hanya diunduh pada halaman
 * yang benar-benar memuat diagram (optimasi ukuran bundle).
 */

interface MermaidSource {
    el: HTMLElement;
    source: string;
}

let sources: MermaidSource[] = [];
let mermaidPromise: Promise<MermaidApi> | null = null;

interface MermaidApi {
    initialize: (config: Record<string, unknown>) => void;
    run: (options: { nodes: HTMLElement[] }) => Promise<void>;
}

function currentTheme(): "dark" | "default" {
    const attr = document.documentElement.dataset.theme;

    if (attr === "light") return "default";
    if (attr === "dark") return "dark";

    return window.matchMedia("(prefers-color-scheme: dark)").matches
        ? "dark"
        : "default";
}

function sanitizeMermaidSource(source: string): string {
    return source
        .replace(/[“”„‟«»]/g, '"')
        .replace(/[‘’‚‛]/g, "'")
        .replace(/\u2013/g, "--")
        .replace(/\u2014/g, "---")
        .replace(/\u00A0/g, " ");
}

function extractMermaidSource(block: HTMLElement): string {
    const lines = block.querySelectorAll<HTMLElement>(".ec-line");
    if (lines.length > 0) {
        return Array.from(lines)
            .map((line) => line.textContent ?? "")
            .join("\n");
    }

    const code = block.tagName === "CODE" ? block : block.querySelector("code");
    const target = code ?? block;
    return target.innerText || target.textContent || "";
}

async function loadMermaid(): Promise<MermaidApi> {
    if (!mermaidPromise) {
        mermaidPromise = import("mermaid").then(
            (mod) => mod.default as unknown as MermaidApi,
        );
    }

    return mermaidPromise;
}

async function renderAll(): Promise<void> {
    if (sources.length === 0) return;

    const mermaid = await loadMermaid();

    mermaid.initialize({
        startOnLoad: false,
        theme: currentTheme(),
        securityLevel: "loose",
        fontFamily: "inherit",
    });

    for (const { el, source } of sources) {
        el.removeAttribute("data-processed");
        el.innerHTML = "";
        el.textContent = source;
    }

    await mermaid.run({ nodes: sources.map((item) => item.el) });
}

export async function initMermaid(): Promise<void> {
    const blocks = Array.from(
        document.querySelectorAll<HTMLElement>(
            'pre[data-language="mermaid"], pre.mermaid, code.language-mermaid',
        ),
    );

    if (blocks.length === 0) return;

    sources = blocks.map((block) => {
        const rawSource = extractMermaidSource(block);
        const source = sanitizeMermaidSource(rawSource);
        const el = document.createElement("div");
        el.className = "mermaid";
        el.textContent = source;

        const wrapper =
            block.closest("figure.expressive-code") ??
            block.closest("pre") ??
            block;
        wrapper.replaceWith(el);

        return { el, source };
    });

    await renderAll();

    // Render ulang saat tema terang/gelap diganti.
    new MutationObserver(() => {
        void renderAll();
    }).observe(document.documentElement, {
        attributes: true,
        attributeFilter: ["data-theme"],
    });
}
