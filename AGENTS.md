# Agent Guidelines for `robyajo/pasak`

## 1. Project Overview & Foundational Context

This repository is **`robyajo/pasak`** (Bulwark), an enterprise-grade, standalone, headless self-hosted Web Application Firewall (WAF), threat detection engine, and security auditing library for the Laravel ecosystem.

- **Package Name**: `robyajo/pasak`
- **Root Namespace**: `Internal\Pasak\`
- **Target Environments**: PHP `^8.2 | ^8.3 | ^8.4 | ^8.5`, Laravel `^10.0 | ^11.0 | ^12.0 | ^13.0`
- **Package Type**: `library` (Composer only)

### 1.1. Zero NPM / Pure PHP Principle (Spatie-Standard)

- **100% Pure PHP**: This package MUST NOT introduce any NPM, Node.js, or frontend build step dependency.
- **Spatie-Style Core Integration**: Like popular Spatie packages (`spatie/laravel-permission`, `spatie/laravel-activitylog`, `spatie/laravel-honeypot`), once installed via `composer require`, the package hooks directly and cleanly into Laravel Core:
    1. **Package Auto-Discovery**: `PasakServiceProvider` & `Pasak` Facade.
    2. **Eloquent Model Trait**: `HasSecurityRelations` trait on the host `User` model (analogous to Spatie's `HasRoles`).
    3. **Core Auth Event Hooks**: Listens to native `\Illuminate\Auth\Events\Failed` and `Login` events automatically.
    4. **Core HTTP Kernel & Middleware**: Aliased as `'pasak.block'`, `'pasak.detect'`, `'pasak.admin'`, `'pasak.activity'`, with optional zero-touch auto-registration (`PASAK_AUTO_REGISTER_MIDDLEWARE=true`).
    5. **Core Authorization Gate**: Registers `Gate::define('manage-pasak')`.
    6. **Core Artisan Commands**: 8 commands automatically available under `php artisan pasak:*` (`install`, `scan-logs`, `baseline`, `unblock-ip`, `prune-logs`, `purge-injected-data`, `upgrade`, `uninstall`).
    7. **Core Task Scheduler**: Hooks daily log pruning and heartbeat directly into `Schedule`.
    8. **Core Validation Rules**: Implements `ValidationRule` (`SafeImageFile`, `SafeAssetPath`).
- **Universal Applicability**: Once installed, developers are free to apply it anywhere across their application: pure Blade views, headless APIs, Livewire, Filament/Nova admin panels, Inertia/React/Vue, or console microservices without restriction.

---

## 2. Comprehensive Documentation Suite (`documents/`)

The repository contains an authoritative, 25-chapter documentation suite located in `documents/`. Agents MUST consult these documents for architectural details, schema specs, API payload contracts, and integration recipes:

- **Master Table of Contents**: [`documents/README.md`](./documents/README.md)
- **Interactive Documentation Portal**: [`documents/index.html`](./documents/index.html) (Tailwind CSS Play CDN + marked.js + Mermaid.js single-page app with full-text search, dark/light theme, and copy-to-clipboard code snippets). Regenerate it from the markdown chapters after editing any `documents/**/*.md` file by running `php documents/generate.php`.
- **Modular Guides**:
    1. `documents/01-getting-started/`:
        - `01-introduction.md` — Philosophy, Spatie-standard core integration, headless architecture, ReDoS safety.
        - `02-installation.md` — Zero-NPM Composer setup, bilingual install wizard, tag publishing, and migrations.
        - `03-configuration.md` — Complete breakdown of every key in `config/pasak.php` and `PASAK_*` `.env` variables.
    2. `documents/02-core-architecture/`:
        - `01-threat-detection-engine.md` — Inspection targets, instant vs auto-block, threat levels, ReDoS immunity rules.
        - `02-device-quarantine.md` — Shared IP/NAT mitigation, `X-Device-Id` and `X-Local-Ip` isolation, `block_scope`.
        - `03-reverse-proxy-resolution.md` — Real client IP resolution order (`CF-Connecting-IP`, `X-Real-IP`, `X-Forwarded-For`), spoof prevention.
        - `04-models-and-database.md` — Dynamic table names, `HasSecurityRelations` trait, full Eloquent models & scopes.
    3. `documents/03-security-modules/`:
        - `01-stepped-login-throttling.md` — Stepped lockout tiers, event listeners, manual unlock.
        - `03-server-security-and-webshells.md` — SHA-256 integrity baseline, webshell scanning, defensive deletion safeguards.
        - `04-access-log-streaming.md` — Generator-based streaming log parser (< 15MB RAM), CLI flags, daily cron.
        - `05-appeal-tickets-system.md` — Self-service unblock appeals, 30m cooldown rate-limit, admin approval automation.
        - `06-session-and-activity-tracking.md` — Active online users, throttled 45s activity heartbeat, session invalidation.
    4. `documents/04-rest-api-reference/`:
        - `01-api-overview.md` — JSON envelope format, HTTP status codes, Gate authorization hierarchy.
        - `02-public-endpoints.md` — Appeal submission/status endpoints.
        - `03-user-endpoints.md` — Trusted IP registration endpoint.
        - `04-admin-endpoints.md` — Full API contracts for logs, blocked IPs, server audit, user sessions, and tickets.
    5. `documents/05-cli-and-automation/`:
        - `01-artisan-commands.md` — Complete argument & option reference for all 8 CLI commands.
        - `02-scheduled-tasks.md` — Scheduled cron tasks (log pruning, heartbeat, daily access log scan).
    6. `documents/06-webserver-hardening/`:
        - `01-nginx-hardened-waf.md` — Dual rate-limit zones, Vite bypass, strict single-PHP execution, storage sandbox.
        - `02-apache-htaccess-hardening.md` — Apache & LiteSpeed hardening, webshell defense, double extension mitigation, dotfile & backup exposure lockdown.
        - `03-production-checklist.md` — Pre-flight checklist, permissions, emergency unblock runbook.
    7. `documents/07-integration-guides/`:
        - `01-frontend-react-inertia.md` — Axios interceptor for 403, Appeal modal.
        - `02-frontend-blade-livewire.md` — Customizing `errors/blocked.blade.php` and form protection.
        - `03-custom-rules-and-whitelist.md` — Custom regex signatures, CIDR subnets, URI exclusion paths.

---

## 3. Skills Activation

When working in this repository, you MUST activate the relevant skills located in `.agents/skills/`:

1. **`pasak`** (`.agents/skills/pasak/SKILL.md`):
    - **Activate whenever**: Writing, modifying, tuning, or testing WAF detection signatures, instant blocking rules, device-level quarantine, stepped login throttle, server security & webshell scanning, access log streaming, headless REST API controllers, Eloquent models, or Pest test cases.
2. **`pasak-release`** (`.agents/skills/pasak-release/SKILL.md`):
    - **Activate whenever**: Committing, tagging, versioning, writing CHANGELOG entries, or pushing. Enforces the CHANGELOG-first rule, the `.10`-rollover version policy, and the pre-push checklist (pest + pint + docs regen).
3. **`pest-testing`** (`.agents/skills/pest-testing/SKILL.md`):
    - **Activate whenever**: Writing or running tests using Pest PHP and Orchestra Testbench.
4. **`laravel-best-practices`** (`.agents/skills/laravel-best-practices/SKILL.md`):
    - **Activate whenever**: Implementing general Laravel patterns, Eloquent relationships, validation rules, or Artisan commands.

---

## 4. Critical Architecture Rules & Constraints

### 4.1. Zero-Tolerance Threat Detection & ReDoS Safety

- **Zero-Tolerance Signatures**: Null-byte upload (`.php%00.jpg`), double extensions (`.php.jpg`), path traversal (`../../../public/`), sensitive probe (`.htaccess`, `.env`, `.git`), and SSTI canary (`{{7*7}}`) must trigger an instant block on the very first request without requiring repeated attempts.
- **Strict ReDoS Immunity**: Detection regular expressions must never contain unbounded nested quantifiers like `(a+)+` or `(.*[a-z])+`. Always ensure new regex patterns pass `Tests\Feature\DetectorTuningTest`.
- **False-Positive Prevention**: Detection patterns must not trigger on normal admin actions, valid filenames, standard search queries, or legitimate JSON payloads.

### 4.2. Decoupled Models and Database Tables

- **Never Hardcode User Model**: Always resolve the host application's user model dynamically:
    ```php
    $userModel = config('pasak.user_model', 'App\Models\User');
    ```
- **Dynamic Table Names**: Models must dynamically read table names from configuration:
    ```php
    public function getTable(): string
    {
        return config('pasak.table_names.blocked_ips', parent::getTable());
    }
    ```
- **Eloquent Host Integration**: Host apps integrate with the package using `Internal\Pasak\Concerns\HasSecurityRelations` on their `User` model.

### 4.3. Device-Level Quarantine & Reverse Proxy Support

- Support both `block_scope = 'ip'` (entire router/NAT) and `block_scope = 'device'` (specific `device_id` and `local_ip`).
- Client IP resolution must account for trusted reverse proxy headers (`CF-Connecting-IP`, `X-Real-IP`, `X-Forwarded-For`).

### 4.4. Server Security & Safe Sanitization

- `ServerSecurityService::deleteSuspiciousFile()` must strictly validate:
    1. No path traversal (`..` or null-bytes).
    2. The target file must reside strictly inside `base_path()`.
    3. Core vital system files (`public/index.php`, `.env`, `composer.json`, `bootstrap/app.php`) are strictly protected and can never be deleted through this feature.

---

## 5. Testing & Verification Standards

- **Test Suite**: Built using **Pest PHP** (`^2.0 | ^3.0 | ^4.0`) and **Orchestra Testbench** (`orchestra/testbench`).
- **Test Runner Command**:
    ```bash
    ./vendor/bin/pest
    ```
- **Running a Specific Test**:
    ```bash
    ./vendor/bin/pest --filter=InstantBlockTest
    ```
- **Test Harness**: All tests extend `Internal\Pasak\Tests\TestCase` which sets up an in-memory SQLite database (`:memory:`), auto-migrates package tables, and registers middleware.
- **Quality Invariant**: **All 133 tests and 566 assertions must pass with zero failures before any work is considered complete.**
- **Real-app simulation**: `laravel-test/` holds 4 real Laravel apps (gitignored sandbox). Verify install/upgrade/uninstall end-to-end there before releases touching those flows.

---

## 6. Directory Structure & Conventions

```text
documents/                 # Authoritative 25-chapter documentation suite + index.html
src/
├── Concerns/              # Reusable Eloquent traits (HasSecurityRelations)
├── Console/Commands/      # Artisan commands (pasak:install with bilingual wizard, pasak:upgrade, pasak:uninstall, etc.)
├── Controllers/           # (under Http/) Api + Dashboard (dual-render Blade/Inertia) controllers
├── Facades/               # Static facade accessors (Pasak)
├── Http/
│   ├── Controllers/Api/   # Headless JSON REST API controllers
│   ├── Controllers/Dashboard/ # Blade + Inertia dashboard controllers (RespondsWithDashboard)
│   └── Middleware/        # WAF & session tracking middleware
├── Listeners/             # Auth event listeners (LogFailedLoginAttempt, RecordUserLogin, ResetLoginAttempts)
├── Models/                # Eloquent models with dynamic table names
├── Rules/                 # Validation rules (SafeImageFile, SafeAssetPath)
├── Services/              # Core security engines & business logic
├── Support/               # Shared helpers (DashboardView)
├── compat.php             # Backward-compat aliases for the pre-rename namespace
└── PasakServiceProvider.php # Package bootstrapping & registration

config/
└── pasak.php             # Full signature definitions & default options (PASAK_* env)

database/migrations/       # Single consolidated migration (create_pasak_tables)

routes/
├── pasak.php             # Headless REST API routes
├── pasak-dashboard.php   # Livewire dashboard routes (auth + pasak.admin)
├── pasak-dashboard-react.php # Inertia + React dashboard routes (auth + pasak.admin)
└── pasak-dashboard-blade.php # Plain-Blade dashboard routes (auth + pasak.admin)

stubs/
├── nginx.conf.stub        # Hardened Nginx WAF configuration template
├── apache2.conf.stub      # Hardened Apache 2 VirtualHost configuration template
├── htaccess.stub          # Hardened Apache .htaccess template
├── blocked.blade.php      # Default 403 "blocked" page
├── env.stub               # Documented PASAK_* environment block
├── blade/pages/pasak/     # Plain-Blade monitoring dashboard (tag: plain-views)
├── livewire/pages/pasak/  # Livewire Starter Kit monitoring dashboard (tag: starterkit-livewire)
└── react/                 # React Starter Kit monitoring dashboard (tag: starterkit-react)

push.sh                    # Commit + auto-tag helper (patch caps at .10, majors manual-only)
finis.sh                   # Root wrapper for web-docs VPS build & deploy
```

---

## 7. Coding Style & Release Discipline

- Use explicit return type declarations and parameter type hints on all methods and functions.
- Use PHP 8 constructor property promotion:
    ```php
    public function __construct(
        protected PasakService $security,
    ) {}
    ```
- Prefer PHPDoc blocks with array shape type definitions for complex array structures.
- Always use curly braces for all control structures.
- **Pint-clean**: `./vendor/bin/pint --test` must pass. Host files edited by installer/uninstaller must also stay Pint-clean (single trailing newline, max one blank line between blocks).
- **CHANGELOG-first**: every user-facing change gets a `CHANGELOG.md` entry before push (see `pasak-release` skill).
- **Naming invariant**: no user-visible `security.*` / `SECURITY_*` / `SecurityMonitor` identifiers remain — only `pasak` / `PASAK_*`. Legacy names survive solely as silent fallbacks (env, config keys, table renames, class aliases). Grep before release.
- **Docs sync**: `README.md` ↔ `documents/` ↔ `web-docs/` must agree on install flow, publish tags, flags, route names, and middleware aliases.

<mcp_instructions>
  <server name="mcp-server-context7">
    Use this server to fetch current documentation whenever the user asks about a library, framework, SDK, API, CLI tool, or cloud service — even well-known ones like React, Next.js, Prisma, Express, Tailwind, Django, or Spring Boot. This includes API syntax, configuration, version migration, library-specific debugging, setup instructions, and CLI tool usage. Use even when you think you know the answer — your training data may not reflect recent changes. Prefer this over web search for library docs.
    
    Do not use for: refactoring, writing scripts from scratch, debugging business logic, code review, or general programming concepts.
  </server>
  <server name="mcp-server-github">
    The GitHub MCP Server provides tools to interact with GitHub platform.
    
    Tool selection guidance:
    	1. Use 'list_*' tools for broad, simple retrieval and pagination of all items of a type (e.g., all issues, all PRs, all branches) with basic filtering.
    	2. Use 'search_*' tools for targeted queries with specific criteria, keywords, or plain-language filters (e.g., issues with certain text, PRs by author, code containing functions).
    
    Context management:
    	1. Use pagination whenever possible with batches of 5-10 items.
    	2. Use minimal_output parameter set to true if the full information is not needed to accomplish a task.
    
    Tool usage guidance:
    	1. For 'search_*' tools: Use separate 'sort' and 'order' parameters if available for sorting results - do not include 'sort:' syntax in query strings. Query strings should contain only search criteria (e.g., 'org:google language:python'), not sorting instructions. Always call 'get_me' first to understand current user permissions and context. ## Issues
    
    Check 'list_issue_types' first for organizations to use proper issue types. Use 'search_issues' before creating new issues to avoid duplicates. Always set 'state_reason' when closing issues. ## Pull Requests
    
    PR review workflow: Always use 'pull_request_review_write' with method 'create' to create a pending review, then 'add_comment_to_pending_review' to add comments, and finally 'pull_request_review_write' with method 'submit_pending' to submit the review for complex reviews with line-specific comments.
    
    Before creating a pull request, search for pull request templates in the repository. Template files are called pull_request_template.md or they're located in '.github/PULL_REQUEST_TEMPLATE' directory. Use the template content to structure the PR description and then call create_pull_request tool.
  </server>
</mcp_instructions>
