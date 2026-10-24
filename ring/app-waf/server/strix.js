import { spawn, spawnSync } from "node:child_process";
import { randomUUID } from "node:crypto";
import {
    existsSync,
    readFileSync,
    readdirSync,
    statSync,
    writeFileSync,
} from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

/**
 * Integrasi Strix (autonomous AI pentest) ke dev-server Vite.
 *
 * Endpoint (hanya menerima koneksi loopback):
 *   GET    /api/strix/status        → kesiapan CLI, Docker, model, daftar run
 *   POST   /api/strix/config        → update konfigurasi model & API key di .env
 *   GET    /api/strix/runs          → ringkasan seluruh run di strix_runs/
 *   GET    /api/strix/runs/:name    → detail run + temuan + laporan
 *   GET    /api/strix/scan          → status job aktif + potongan log (?after=N)
 *   POST   /api/strix/scan          → mulai scan headless baru
 *   DELETE /api/strix/scan          → hentikan scan yang sedang berjalan
 *
 * Mendukung LLM Provider:
 *   - Google Gemini ("gemini/gemini-2.5-pro", "gemini/gemini-1.5-pro", dll)
 *   - OpenAI / ChatGPT ("openai/gpt-4o", "openai/gpt-4o-mini", "openai/o3-mini", dll)
 *   - OpenRouter ("openrouter/z-ai/glm-5.3", "openrouter/anthropic/claude-3.5-sonnet", dll)
 *   - OpenCode / Local AI ("openai/...", "ollama/...", dengan OPENAI_API_BASE)
 */

const APP_ROOT = path.resolve(
    path.dirname(fileURLToPath(import.meta.url)),
    "..",
);
const ENV_FILE = path.join(APP_ROOT, ".env");
const DEFAULT_RUNS_DIR = path.join(APP_ROOT, "strix_runs");
const SCAN_MODES = new Set(["quick", "standard", "deep"]);
const TERMINAL_STATUSES = new Set([
    "completed",
    "stopped",
    "failed",
    "interrupted",
]);
const SEVERITIES = ["critical", "high", "medium", "low"];
const MAX_LOG_LINES = 4000;
const MAX_READ_BYTES = 2_000_000;
const SCAN_BODY_LIMIT = 1_000_000;

let activeJob = null;
let statusCache = null;
let statusCacheAt = 0;
let knownRunNames = new Set();

function invalidateStatus() {
    statusCache = null;
    statusCacheAt = 0;
}

/** Parser .env ringan: dukung `export`, spasi, tanda kutip, dan komentar. */
export function readDotEnv(file = ENV_FILE) {
    if (!existsSync(file)) {
        return {};
    }

    const values = {};

    for (const rawLine of readFileSync(file, "utf8").split(/\r?\n/)) {
        const line = rawLine.trim();

        if (line === "" || line.startsWith("#")) {
            continue;
        }

        const match = line.match(
            /^(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/,
        );

        if (!match) {
            continue;
        }

        let value = match[2].trim();

        if (
            (value.startsWith('"') && value.endsWith('"')) ||
            (value.startsWith("'") && value.endsWith("'"))
        ) {
            value = value.slice(1, -1);
        } else {
            const comment = value.indexOf(" #");

            if (comment >= 0) {
                value = value.slice(0, comment).trim();
            }
        }

        values[match[1]] = value;
    }

    return values;
}

/** Env efektif: nilai .env sebagai dasar, variabel shell menang. */
function effectiveEnv() {
    return { ...readDotEnv(), ...process.env };
}

function binPath(env = effectiveEnv()) {
    const configured = env.STRIX_BIN?.trim();
    if (configured) {
        const resolved = path.isAbsolute(configured)
            ? configured
            : path.resolve(APP_ROOT, configured);
        if (existsSync(resolved)) {
            return resolved;
        }
    }
    const internalVenv = path.join(APP_ROOT, "strix", ".venv", "bin", "strix");
    if (existsSync(internalVenv)) {
        return internalVenv;
    }
    const localBin = path.join(process.env.HOME || "", ".local", "bin", "strix");
    if (existsSync(localBin)) {
        return localBin;
    }
    return "strix";
}

export function runsDirectory(env = effectiveEnv()) {
    const configured = env.STRIX_RUNS_DIR?.trim();

    if (!configured) {
        return DEFAULT_RUNS_DIR;
    }

    return path.isAbsolute(configured)
        ? configured
        : path.resolve(APP_ROOT, configured);
}

function sendJson(res, status, payload) {
    res.statusCode = status;
    res.setHeader("Content-Type", "application/json; charset=utf-8");
    res.setHeader("Cache-Control", "no-store");
    res.end(JSON.stringify(payload));
}

function readJsonBody(req, limit = SCAN_BODY_LIMIT) {
    return new Promise((resolve, reject) => {
        const chunks = [];
        let size = 0;

        req.on("data", (chunk) => {
            size += chunk.length;

            if (size > limit) {
                reject(new Error("Body request terlalu besar."));
                req.destroy();

                return;
            }

            chunks.push(chunk);
        });

        req.on("end", () => {
            const raw = Buffer.concat(chunks).toString("utf8").trim();

            if (raw === "") {
                resolve({});

                return;
            }

            try {
                resolve(JSON.parse(raw));
            } catch {
                reject(new Error("Body JSON tidak valid."));
            }
        });

        req.on("error", reject);
    });
}

function isLoopback(req) {
    const address = req.socket?.remoteAddress ?? "";

    if (address === "") {
        return true;
    }

    return (
        address === "127.0.0.1" ||
        address === "::1" ||
        address === "::ffff:127.0.0.1"
    );
}

function stripAnsi(text) {
    return text.replace(/\u001b\[[0-?]*[ -/]*[@-~]/g, "");
}

function readJsonFile(file, fallback) {
    try {
        const size = statSync(file).size;

        if (size > MAX_READ_BYTES) {
            return fallback;
        }

        return JSON.parse(readFileSync(file, "utf8"));
    } catch {
        return fallback;
    }
}

function readTextFile(file) {
    try {
        const size = statSync(file).size;

        if (size > MAX_READ_BYTES) {
            return "";
        }

        return readFileSync(file, "utf8");
    } catch {
        return "";
    }
}

function primaryTarget(record) {
    const targets = Array.isArray(record?.targets_info)
        ? record.targets_info
        : [];

    for (const entry of targets) {
        if (entry && typeof entry.original === "string" && entry.original) {
            return entry.original;
        }
    }

    if (typeof record?.workspace_mount === "string" && record.workspace_mount) {
        return `${record.workspace_mount} (workspace)`;
    }

    const instruction = String(
        record?.user_instruction ?? record?.instruction ?? "",
    ).trim();

    return instruction.split("\n")[0] || "";
}

function severityCounts(vulnerabilities) {
    const counts = Object.fromEntries(SEVERITIES.map((s) => [s, 0]));

    for (const vuln of vulnerabilities) {
        const raw = String(vuln?.severity ?? "")
            .trim()
            .toLowerCase();
        const severity = raw in counts ? raw : "low";
        counts[severity] += 1;
    }

    return counts;
}

function runDirectories(env = effectiveEnv()) {
    const base = runsDirectory(env);

    if (!existsSync(base)) {
        return [];
    }

    return readdirSync(base, { withFileTypes: true })
        .filter((entry) => entry.isDirectory())
        .map((entry) => path.join(base, entry.name))
        .filter((dir) => existsSync(path.join(dir, "run.json")))
        .sort((a, b) => {
            try {
                return (
                    statSync(path.join(b, "run.json")).mtimeMs -
                    statSync(path.join(a, "run.json")).mtimeMs
                );
            } catch {
                return 0;
            }
        });
}

function runSummary(runDir) {
    const record = readJsonFile(path.join(runDir, "run.json"), {});
    const vulnerabilities = readJsonFile(
        path.join(runDir, "vulnerabilities.json"),
        [],
    );
    const list = Array.isArray(vulnerabilities) ? vulnerabilities : [];
    const status =
        typeof record.status === "string" ? record.status : "unknown";
    const updatedAt = (() => {
        try {
            return statSync(path.join(runDir, "run.json")).mtimeMs;
        } catch {
            return 0;
        }
    })();

    return {
        name: path.basename(runDir),
        target: primaryTarget(record),
        scan_mode: record.scan_mode ?? null,
        status,
        start_time: record.start_time ?? null,
        end_time: record.end_time ?? null,
        finished: TERMINAL_STATUSES.has(status) && Boolean(record.end_time),
        findings: list.length,
        severity_counts: severityCounts(list),
        updated_at: updatedAt,
    };
}

export function listRuns(env = effectiveEnv()) {
    return runDirectories(env).map(runSummary);
}

function safeRunDir(name, env = effectiveEnv()) {
    if (
        typeof name !== "string" ||
        !/^[A-Za-z0-9][A-Za-z0-9._-]{0,120}$/.test(name) ||
        name.includes("..")
    ) {
        return null;
    }

    const base = path.resolve(runsDirectory(env));
    const candidate = path.resolve(base, name);

    if (path.dirname(candidate) !== base) {
        return null;
    }

    return existsSync(path.join(candidate, "run.json")) ? candidate : null;
}

export function readRunDetail(name, env = effectiveEnv()) {
    const runDir = safeRunDir(name, env);

    if (!runDir) {
        return null;
    }

    const record = readJsonFile(path.join(runDir, "run.json"), {});
    const rawVulnerabilities = readJsonFile(
        path.join(runDir, "vulnerabilities.json"),
        [],
    );
    const vulnerabilities = Array.isArray(rawVulnerabilities)
        ? rawVulnerabilities.map((vuln) => {
              const id =
                  typeof vuln?.id === "string" ? path.basename(vuln.id) : "";
              const markdown = id
                  ? readTextFile(
                        path.join(runDir, "vulnerabilities", `${id}.md`),
                    )
                  : "";

              return { ...vuln, markdown };
          })
        : [];

    return {
        run: {
            ...record,
            name: path.basename(runDir),
            target: primaryTarget(record),
            finished:
                TERMINAL_STATUSES.has(String(record.status)) &&
                Boolean(record.end_time),
        },
        vulnerabilities,
        severity_counts: severityCounts(vulnerabilities),
        report: readTextFile(path.join(runDir, "penetration_test_report.md")),
    };
}

function versionLine(result) {
    const text = `${result?.stdout ?? ""}\n${result?.stderr ?? ""}`.trim();

    return text.split(/\r?\n/).find((line) => line.trim() !== "") ?? null;
}

function probeStrix(env) {
    const bin = binPath(env);
    const result = spawnSync(bin, ["--version"], {
        cwd: APP_ROOT,
        encoding: "utf8",
        timeout: 8000,
        env,
    });

    if (result.error) {
        return {
            installed: false,
            path: bin,
            version: null,
            message:
                result.error.code === "ENOENT"
                    ? "Strix CLI tidak ditemukan di PATH. Pasang via 'pip install strix' atau 'pipx install strix-ai', atau set STRIX_BIN di .env."
                    : result.error.message,
        };
    }

    return {
        installed: result.status === 0,
        path: bin,
        version: versionLine(result),
        message:
            result.status === 0
                ? null
                : "Strix CLI ada tetapi gagal melaporkan versi.",
    };
}

function probeDocker(env) {
    const version = spawnSync("docker", ["--version"], {
        encoding: "utf8",
        timeout: 3000,
        env,
    });
    const stdout = version.stdout ?? "";
    const stderr = version.stderr ?? "";
    const rawOut = `${stdout}\n${stderr}`.trim();

    if (rawOut.includes("WSL integration") || rawOut.includes("WSL 2 distro")) {
        return {
            installed: false,
            running: false,
            version: null,
            message:
                "Docker Desktop terpasang di Windows, namun integrasi WSL 2 belum aktif. Buka Docker Desktop → Settings → Resources → WSL Integration.",
        };
    }

    const installed = !version.error && version.status === 0;
    if (!installed) {
        return {
            installed: false,
            running: false,
            version: null,
            message:
                "Docker tidak ditemukan di PATH. Strix membutuhkan Docker yang berjalan.",
        };
    }

    const info = spawnSync(
        "docker",
        ["info", "--format", "{{.ServerVersion}}"],
        {
            encoding: "utf8",
            timeout: 3000,
            env,
        },
    );
    const running = !info.error && info.status === 0;
    let sandboxReady = false;
    if (running) {
        const inspect = spawnSync(
            "docker",
            ["image", "inspect", "ghcr.io/usestrix/strix-sandbox:1.3.0"],
            { encoding: "utf8", timeout: 2000, env }
        );
        sandboxReady = !inspect.error && inspect.status === 0;
    }

    return {
        installed,
        running,
        version: versionLine(version),
        sandbox_ready: sandboxReady,
        message: running
            ? null
            : "Docker terpasang tetapi daemon belum berjalan / integrasi WSL belum aktif.",
    };
}

export function detectProvider(llm = "") {
    const raw = String(llm || "")
        .trim()
        .toLowerCase();
    if (raw.startsWith("gemini/") || raw.includes("gemini")) {
        return "gemini";
    }
    if (
        raw.startsWith("openai/") ||
        raw.startsWith("gpt-") ||
        raw.startsWith("o1") ||
        raw.startsWith("o3") ||
        raw.startsWith("chatgpt")
    ) {
        return "openai";
    }
    if (raw.startsWith("openrouter/")) {
        return "openrouter";
    }
    if (
        raw.startsWith("opencode/") ||
        raw.startsWith("ollama/") ||
        raw.includes("localhost") ||
        raw.includes("127.0.0.1")
    ) {
        return "opencode";
    }
    return "custom";
}

function isPlaceholder(val) {
    if (!val) return false;
    const clean = String(val).trim().toLowerCase();
    return (
        clean === "your-api-key" ||
        clean === "your-gemini-api-key" ||
        clean === "your-openai-api-key" ||
        clean === "your-openrouter-api-key" ||
        clean.startsWith("your-") ||
        clean.includes("placeholder")
    );
}

function getProviderKeys(env) {
    const geminiKey =
        env.GEMINI_API_KEY?.trim() || env.GOOGLE_API_KEY?.trim() || "";
    const openaiKey = env.OPENAI_API_KEY?.trim() || "";
    const openrouterKey =
        env.OPENROUTER_API_KEY?.trim() || env.LLM_API_KEY?.trim() || "";

    return {
        gemini: {
            configured: Boolean(geminiKey && !isPlaceholder(geminiKey)),
            is_placeholder: Boolean(geminiKey && isPlaceholder(geminiKey)),
            key: geminiKey,
        },
        openai: {
            configured: Boolean(openaiKey && !isPlaceholder(openaiKey)),
            is_placeholder: Boolean(openaiKey && isPlaceholder(openaiKey)),
            key: openaiKey,
        },
        openrouter: {
            configured: Boolean(openrouterKey && !isPlaceholder(openrouterKey)),
            is_placeholder: Boolean(
                openrouterKey && isPlaceholder(openrouterKey),
            ),
            key: openrouterKey,
        },
    };
}

export function checkKeyForModel(llm, env) {
    const provider = detectProvider(llm);
    const pKeys = getProviderKeys(env);

    if (provider === "gemini") {
        return {
            has_api_key: pKeys.gemini.configured,
            is_placeholder_key: pKeys.gemini.is_placeholder,
        };
    }
    if (provider === "openai") {
        return {
            has_api_key: pKeys.openai.configured,
            is_placeholder_key: pKeys.openai.is_placeholder,
        };
    }
    if (provider === "openrouter") {
        return {
            has_api_key: pKeys.openrouter.configured,
            is_placeholder_key: pKeys.openrouter.is_placeholder,
        };
    }

    const hasAny =
        pKeys.gemini.configured ||
        pKeys.openai.configured ||
        pKeys.openrouter.configured;
    const isAnyPlace =
        pKeys.gemini.is_placeholder ||
        pKeys.openai.is_placeholder ||
        pKeys.openrouter.is_placeholder;

    return {
        has_api_key: hasAny,
        is_placeholder_key: !hasAny && isAnyPlace,
    };
}

export function updateConfig(input) {
    const llm = typeof input?.llm === "string" ? input.llm.trim() : null;
    const provider =
        typeof input?.provider === "string" ? input.provider.trim() : null;
    const apiKey =
        typeof input?.apiKey === "string" ? input.apiKey.trim() : null;
    const geminiApiKey =
        typeof input?.geminiApiKey === "string"
            ? input.geminiApiKey.trim()
            : null;
    const openaiApiKey =
        typeof input?.openaiApiKey === "string"
            ? input.openaiApiKey.trim()
            : null;
    const openrouterApiKey =
        typeof input?.openrouterApiKey === "string"
            ? input.openrouterApiKey.trim()
            : null;
    const apiBase =
        typeof input?.apiBase === "string" ? input.apiBase.trim() : null;

    let content = existsSync(ENV_FILE) ? readFileSync(ENV_FILE, "utf8") : "";
    const lines = content.split(/\r?\n/);
    const newLines = [];
    const updatedKeys = new Set();

    const updates = {};
    if (llm !== null) updates.STRIX_LLM = llm;

    if (geminiApiKey !== null) {
        updates.GEMINI_API_KEY = geminiApiKey;
    }
    if (openaiApiKey !== null) {
        updates.OPENAI_API_KEY = openaiApiKey;
    }
    if (openrouterApiKey !== null) {
        updates.OPENROUTER_API_KEY = openrouterApiKey;
        updates.LLM_API_KEY = openrouterApiKey;
    }

    if (apiKey !== null) {
        const targetProv = provider || detectProvider(llm || "");
        if (targetProv === "gemini") {
            updates.GEMINI_API_KEY = apiKey;
        } else if (targetProv === "openai") {
            updates.OPENAI_API_KEY = apiKey;
        } else if (
            targetProv === "openrouter" ||
            targetProv === "opencode" ||
            targetProv === "custom"
        ) {
            updates.OPENROUTER_API_KEY = apiKey;
            updates.LLM_API_KEY = apiKey;
        }
    }

    if (apiBase !== null) {
        updates.OPENAI_API_BASE = apiBase;
    }

    for (const line of lines) {
        const match = line.match(/^(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=/);
        if (match) {
            const key = match[1];
            if (key in updates) {
                newLines.push(`${key}="${updates[key]}"`);
                updatedKeys.add(key);
                continue;
            }
        }
        newLines.push(line);
    }

    for (const [key, val] of Object.entries(updates)) {
        if (!updatedKeys.has(key)) {
            newLines.push(`${key}="${val}"`);
        }
    }

    writeFileSync(ENV_FILE, newLines.join("\n"), "utf8");
    invalidateStatus();

    const env = effectiveEnv();
    const activeLlm = env.STRIX_LLM?.trim() || null;
    const modelCheck = checkKeyForModel(activeLlm, env);
    const provs = getProviderKeys(env);

    return {
        env_file: ENV_FILE,
        llm: activeLlm,
        provider: detectProvider(activeLlm),
        has_api_key: modelCheck.has_api_key,
        is_placeholder_key: modelCheck.is_placeholder_key,
        providers: {
            gemini: {
                configured: provs.gemini.configured,
                is_placeholder: provs.gemini.is_placeholder,
            },
            openai: {
                configured: provs.openai.configured,
                is_placeholder: provs.openai.is_placeholder,
            },
            openrouter: {
                configured: provs.openrouter.configured,
                is_placeholder: provs.openrouter.is_placeholder,
            },
        },
        binary: binPath(env),
    };
}

export function buildStatus(force = false) {
    const now = Date.now();

    if (!force && statusCache && now - statusCacheAt < 5000) {
        return statusCache;
    }

    const env = effectiveEnv();
    const strix = probeStrix(env);
    const docker = probeDocker(env);
    const runs = listRuns(env);
    const activeLlm = env.STRIX_LLM?.trim() || null;
    const modelCheck = checkKeyForModel(activeLlm, env);
    const provs = getProviderKeys(env);

    statusCache = {
        success: true,
        checked_at: new Date(now).toISOString(),
        strix,
        docker,
        config: {
            env_file: ENV_FILE,
            llm: activeLlm,
            provider: detectProvider(activeLlm),
            has_api_key: modelCheck.has_api_key,
            is_placeholder_key: modelCheck.is_placeholder_key,
            providers: {
                gemini: {
                    configured: provs.gemini.configured,
                    is_placeholder: provs.gemini.is_placeholder,
                },
                openai: {
                    configured: provs.openai.configured,
                    is_placeholder: provs.openai.is_placeholder,
                },
                openrouter: {
                    configured: provs.openrouter.configured,
                    is_placeholder: provs.openrouter.is_placeholder,
                },
            },
            binary: binPath(env),
        },
        runs_dir: runsDirectory(env),
        run_count: runs.length,
        finding_count: runs.reduce((sum, run) => sum + run.findings, 0),
        active: activeJob ? jobPayload(activeJob, 0, false) : null,
    };
    statusCacheAt = now;

    return statusCache;
}

function appendLog(job, stream, text) {
    const clean = stripAnsi(text).replace(/\r$/, "");

    if (clean.trim() === "" && job.logs.length > 0) {
        return;
    }

    job.seq += 1;
    job.logs.push({
        seq: job.seq,
        stream,
        text: clean,
        at: new Date().toISOString(),
    });

    if (job.logs.length > MAX_LOG_LINES) {
        job.logs.splice(0, job.logs.length - MAX_LOG_LINES);
        job.dropped = (job.dropped ?? 0) + 1;
    }
}

function pipeLines(job, stream, name) {
    let buffer = "";

    stream?.setEncoding("utf8");
    stream?.on("data", (chunk) => {
        buffer += chunk;
        const lines = buffer.split(/\r?\n/);
        buffer = lines.pop() ?? "";

        for (const line of lines) {
            appendLog(job, name, line);
        }

        if (buffer.length > 8000) {
            appendLog(job, name, buffer);
            buffer = "";
        }
    });

    stream?.on("end", () => {
        if (buffer !== "") {
            appendLog(job, name, buffer);
            buffer = "";
        }
    });
}

function discoverRun(job) {
    if (!job || job.runName) {
        return;
    }

    const env = effectiveEnv();
    const base = runsDirectory(env);

    if (!existsSync(base)) {
        return;
    }

    const candidates = readdirSync(base, { withFileTypes: true })
        .filter((entry) => entry.isDirectory())
        .map((entry) => entry.name)
        .filter((name) => !job.knownRuns.has(name))
        .filter((name) => existsSync(path.join(base, name, "run.json")))
        .sort((a, b) => {
            try {
                return (
                    statSync(path.join(base, b, "run.json")).mtimeMs -
                    statSync(path.join(base, a, "run.json")).mtimeMs
                );
            } catch {
                return 0;
            }
        });

    if (candidates.length > 0) {
        job.runName = candidates[0];
        job.knownRuns.add(job.runName);
        invalidateStatus();
    }
}

function jobPayload(job, after = 0, includeLogs = true) {
    const startedAt = job.startedAt ? Date.parse(job.startedAt) : Date.now();
    const finishedAt = job.finishedAt ? Date.parse(job.finishedAt) : Date.now();
    const logs = includeLogs
        ? job.logs.filter((line) => line.seq > Number(after || 0))
        : [];

    return {
        id: job.id,
        target: job.target,
        mode: job.mode,
        budget: job.budget,
        instruction: job.instruction,
        status: job.status,
        started_at: job.startedAt,
        finished_at: job.finishedAt,
        duration_ms: Math.max(0, finishedAt - startedAt),
        exit_code: job.exitCode,
        run_name: job.runName,
        error: job.error,
        dropped_logs: job.dropped ?? 0,
        logs,
        next_after: job.seq,
    };
}

function findNewRuns(env = effectiveEnv()) {
    return new Set(runDirectories(env).map((dir) => path.basename(dir)));
}

function startScan(input) {
    const env = effectiveEnv();
    const strix = probeStrix(env);

    if (!strix.installed) {
        const error = new Error(strix.message ?? "Strix CLI tidak tersedia.");
        error.statusCode = 422;

        throw error;
    }

    if (
        activeJob &&
        ["running", "starting", "stopping"].includes(activeJob.status)
    ) {
        const error = new Error(
            "Masih ada scan berjalan. Hentikan atau tunggu hingga selesai.",
        );
        error.statusCode = 409;

        throw error;
    }

    const target = String(input.target ?? "").trim();
    const mode = String(input.mode ?? "quick").trim();
    const budgetRaw = Number(input.budget ?? 10);
    const instruction = String(input.instruction ?? "").trim();

    if (target === "") {
        const error = new Error("Target wajib diisi.");
        error.statusCode = 422;

        throw error;
    }

    if (target.length > 2048 || /[\u0000-\u001f\u007f]/.test(target)) {
        const error = new Error("Target tidak valid.");
        error.statusCode = 422;

        throw error;
    }

    if (target.startsWith("-")) {
        const error = new Error("Target tidak boleh diawali tanda minus.");
        error.statusCode = 422;

        throw error;
    }

    if (!SCAN_MODES.has(mode)) {
        const error = new Error("Mode scan tidak dikenal.");
        error.statusCode = 422;

        throw error;
    }

    if (!Number.isFinite(budgetRaw) || budgetRaw < 1 || budgetRaw > 1000) {
        const error = new Error("Budget harus di antara 1 dan 1000 USD.");
        error.statusCode = 422;

        throw error;
    }

    if (instruction.length > 4000) {
        const error = new Error("Instruksi maksimal 4000 karakter.");
        error.statusCode = 422;

        throw error;
    }

    const bin = binPath(env);
    const args = [
        "-n",
        "-t",
        target,
        "--scan-mode",
        mode,
        "--max-budget",
        String(Number(budgetRaw.toFixed(2))),
    ];

    if (instruction !== "") {
        args.push("--instruction", instruction);
    }

    knownRunNames = findNewRuns(env);

    const job = {
        id: randomUUID(),
        target,
        mode,
        budget: Number(budgetRaw.toFixed(2)),
        instruction,
        status: "starting",
        startedAt: new Date().toISOString(),
        finishedAt: null,
        exitCode: null,
        runName: null,
        error: null,
        logs: [],
        seq: 0,
        dropped: 0,
        knownRuns: knownRunNames,
        child: null,
        discoverTimer: null,
        killTimer: null,
    };

    const childEnv = {
        ...env,
        NO_COLOR: "1",
        FORCE_COLOR: "0",
        TERM: "dumb",
        PYTHONUNBUFFERED: "1",
    };

    // Forward Gemini keys for LiteLLM / Strix
    if (env.GEMINI_API_KEY && !childEnv.GOOGLE_API_KEY) {
        childEnv.GOOGLE_API_KEY = env.GEMINI_API_KEY;
    }
    if (env.GOOGLE_API_KEY && !childEnv.GEMINI_API_KEY) {
        childEnv.GEMINI_API_KEY = env.GOOGLE_API_KEY;
    }

    // Forward OpenAI / ChatGPT keys
    if (env.OPENAI_API_KEY) {
        childEnv.OPENAI_API_KEY = env.OPENAI_API_KEY;
    }

    // Forward OpenRouter keys
    if (env.OPENROUTER_API_KEY) {
        childEnv.OPENROUTER_API_KEY = env.OPENROUTER_API_KEY;
    }

    // Fallback dari LLM_API_KEY jika spesifik belum ada
    if (env.LLM_API_KEY) {
        if (!childEnv.OPENROUTER_API_KEY)
            childEnv.OPENROUTER_API_KEY = env.LLM_API_KEY;
        const prov = detectProvider(env.STRIX_LLM);
        if (prov === "openai" && !childEnv.OPENAI_API_KEY) {
            childEnv.OPENAI_API_KEY = env.LLM_API_KEY;
        }
        if (prov === "gemini" && !childEnv.GEMINI_API_KEY) {
            childEnv.GEMINI_API_KEY = env.LLM_API_KEY;
            childEnv.GOOGLE_API_KEY = env.LLM_API_KEY;
        }
    }

    // Forward Base URL untuk OpenCode / Ollama / LocalAI
    if (env.OPENAI_API_BASE) {
        childEnv.OPENAI_API_BASE = env.OPENAI_API_BASE;
        childEnv.OPENAI_BASE_URL = env.OPENAI_API_BASE;
    }

    appendLog(job, "system", `$ ${bin} ${args.join(" ")}`);

    const child = spawn(bin, args, {
        cwd: APP_ROOT,
        env: childEnv,
        shell: false,
        stdio: ["ignore", "pipe", "pipe"],
    });

    job.child = child;
    activeJob = job;
    invalidateStatus();

    pipeLines(job, child.stdout, "stdout");
    pipeLines(job, child.stderr, "stderr");

    job.discoverTimer = setInterval(() => discoverRun(job), 1200);
    job.discoverTimer.unref?.();

    child.on("spawn", () => {
        job.status = "running";
        appendLog(
            job,
            "system",
            `Scan dimulai (${job.id}). Model: ${env.STRIX_LLM || "default"}`,
        );
        invalidateStatus();
    });

    child.on("error", (error) => {
        job.status = "failed";
        job.error =
            error.code === "ENOENT"
                ? `Binary Strix tidak ditemukan: ${bin}`
                : error.message;
        job.finishedAt = new Date().toISOString();
        appendLog(job, "system", `Gagal menjalankan Strix: ${job.error}`);
        clearInterval(job.discoverTimer);
        invalidateStatus();
    });

    child.on("close", (code, signal) => {
        clearInterval(job.discoverTimer);

        if (job.killTimer) {
            clearTimeout(job.killTimer);
            job.killTimer = null;
        }

        discoverRun(job);
        job.exitCode = code;
        job.finishedAt = new Date().toISOString();

        if (job.status !== "failed") {
            if (job.status === "stopping") {
                job.status = "stopped";
            } else if (code === 0) {
                job.status = "completed";
            } else if (code === 2) {
                job.status = "findings";
            } else {
                job.status = "failed";
                job.error =
                    code === null
                        ? `Strix berhenti karena sinyal ${signal}.`
                        : `Strix keluar dengan kode ${code}.`;
            }
        }

        appendLog(
            job,
            "system",
            `Proses selesai: status=${job.status}, exit=${code ?? signal ?? "?"}.`,
        );
        job.child = null;
        invalidateStatus();
    });

    return jobPayload(job, 0);
}

function stopScan() {
    if (!activeJob || !activeJob.child) {
        const error = new Error("Tidak ada scan yang sedang berjalan.");
        error.statusCode = 409;

        throw error;
    }

    if (activeJob.status === "stopping") {
        return jobPayload(activeJob, 0, false);
    }

    activeJob.status = "stopping";
    appendLog(activeJob, "system", "Mengirim sinyal berhenti (SIGINT)…");
    activeJob.child.kill("SIGINT");

    activeJob.killTimer = setTimeout(() => {
        if (activeJob?.child) {
            appendLog(
                activeJob,
                "system",
                "Strix belum berhenti; memaksa berhenti (SIGKILL).",
            );
            activeJob.child.kill("SIGKILL");
        }
    }, 8000);
    activeJob.killTimer.unref?.();

    invalidateStatus();

    return jobPayload(activeJob, 0, false);
}

function routeName(pathname) {
    const match = pathname.match(/^\/api\/strix\/runs\/([^/]+)\/?$/);

    if (!match) {
        return null;
    }

    try {
        return decodeURIComponent(match[1]);
    } catch {
        return "";
    }
}

/**
 * Middleware Vite. Mengembalikan `next()` untuk route di luar /api/strix.
 */
export function createStrixHandler() {
    return async (req, res, next) => {
        const pathname = (req.url ?? "").split("?")[0];

        if (!pathname.startsWith("/api/strix")) {
            next();

            return;
        }

        if (!isLoopback(req)) {
            sendJson(res, 403, {
                success: false,
                message: "Endpoint Strix hanya tersedia dari localhost.",
            });

            return;
        }

        try {
            if (pathname === "/api/strix/status" && req.method === "GET") {
                sendJson(res, 200, buildStatus());

                return;
            }

            if (pathname === "/api/strix/config" && req.method === "POST") {
                const input = await readJsonBody(req);
                const config = updateConfig(input);

                sendJson(res, 200, { success: true, config });

                return;
            }

            if (pathname === "/api/strix/runs" && req.method === "GET") {
                const runs = listRuns();

                sendJson(res, 200, {
                    success: true,
                    total: runs.length,
                    runs,
                });

                return;
            }

            if (pathname === "/api/strix/scan" && req.method === "GET") {
                const url = new URL(req.url ?? "/", "http://localhost");
                const after = Number(url.searchParams.get("after") ?? 0);
                const payload = activeJob
                    ? jobPayload(activeJob, after)
                    : {
                          id: null,
                          status: "idle",
                          logs: [],
                          next_after: 0,
                      };

                sendJson(res, 200, { success: true, job: payload });

                return;
            }

            if (pathname === "/api/strix/scan" && req.method === "POST") {
                const input = await readJsonBody(req);
                const job = startScan(input);

                sendJson(res, 201, { success: true, job });

                return;
            }

            if (pathname === "/api/strix/scan" && req.method === "DELETE") {
                const job = stopScan();

                sendJson(res, 200, { success: true, job });

                return;
            }

            const name = routeName(pathname);

            if (name !== null && req.method === "GET") {
                const detail = readRunDetail(name);

                if (!detail) {
                    sendJson(res, 404, {
                        success: false,
                        message: "Run tidak ditemukan.",
                    });

                    return;
                }

                sendJson(res, 200, { success: true, ...detail });

                return;
            }

            sendJson(res, 404, {
                success: false,
                message: "Endpoint Strix tidak dikenal.",
            });
        } catch (error) {
            const status = error?.statusCode ?? 500;

            if (status >= 500) {
                console.error("[strix] kesalahan:", error);
            }

            sendJson(res, status, {
                success: false,
                message:
                    error instanceof Error
                        ? error.message
                        : "Kesalahan internal Strix.",
            });
        }
    };
}

/** Reset state proses (dipakai verifikasi/manual test). */
export function resetStrixState() {
    if (activeJob?.child) {
        activeJob.child.kill("SIGKILL");
    }

    activeJob = null;
    knownRunNames = new Set();
    invalidateStatus();
}
