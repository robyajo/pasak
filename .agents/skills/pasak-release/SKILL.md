# Skill: pasak-release

Release discipline for `robyajo/pasak`. Activate whenever committing, tagging, versioning, writing changelog entries, or pushing this repository.

## 1. CHANGELOG-first rule (mandatory)

- **Every user-facing change MUST be recorded in `CHANGELOG.md`** under a new version section before push. No silent changes.
- Format: `## [X.Y.Z] - YYYY-MM-DD` with `### Added / Changed / Fixed` subsections, bilingual-friendly Indonesian prose matching existing entries.

## 2. Version policy (`push.sh` enforced)

- Patch numbers cap at `.10`, then roll over to the next minor (`v1.0.10` → `v1.1.0`, `v1.1.10` → `v1.2.0`).
- Major versions are NEVER automatic — only via explicit `-t vX.0.0` from the owner.
- `PasakService::VERSION` is auto-synced to the new tag by `push.sh`; never edit it by hand except to fix drift.

## 3. Pre-push checklist (in order)

1. `./vendor/bin/pest` — full suite green (currently 133 tests / 566 assertions).
2. `./vendor/bin/pint --test` — clean (install tools via `composer dev:tools`; Pint is NOT a committed dependency).
3. `php documents/generate.php` — regenerate the portal after any `documents/**/*.md` edit.
4. `php paparan/generate.php` — regenerate the deck after messaging changes.
5. `./push.sh -m "<conventional-commit>"` — auto-tags (add `--no-tag` only for non-release chores, `--no-docs-build` to skip the web-docs build).

## 4. Docs sync map (keep aligned)

- `README.md` ↔ `documents/01-getting-started/02-installation.md` ↔ `web-docs/src/content/docs/getting-started/installation.md` (install flow, tags, flags).
- `config/pasak.php` comments ↔ `documents/01-getting-started/03-configuration.md` ↔ web-docs configuration page (env vars, defaults).
- Publish tags / route names / middleware aliases / gate must be identical in code, README, `documents/`, and `web-docs/`. Grep for `security.` leftovers before release.
