# Project Decision Log

**Project:** Shipyard CMMS — MNA4003 (FE-MNA4003-S12026-2027)
**Student ID:** **S79244**
**Purpose:** Record confirmed decisions, adopted defaults and their status. Required by the student's Phase 0 condition 3.

**Status values**

- **Accepted** — approved by the student or mandated by the lecturer.
- **Provisional** — a sensible default adopted to avoid blocking work; may be changed without rework.
- **Open** — not decided; requires the student or lecturer.

**Requirement tags:** **[L]** lecturer-mandated, **[L-I]** lecturer-implied, **[R]** architect recommendation.

---

## 1. Binding decisions

| ID | Decision | Status | Basis |
|---|---|---|---|
| ADR-001 | Student ID is **S79244**; hostnames use lowercase **`s79244`**; the short numeric form is never used alone | **Accepted** | Student correction |
| ADR-002 | Stack: **Laravel 13.x**, **PHP 8.4**, **PostgreSQL 18**, **Docker Engine**, **Docker Compose v2**, **Blade + Tailwind CSS**, **Node.js 22**, **Composer 2.9**, **PHPUnit 12.5** | **Accepted** | Student approved the Laravel 13 adjustment on 19 Nov 2026; compatibility verified under condition 5. Supersedes the plan's Laravel 12 selection |
| ADR-003 | Container-only toolchain: no host PHP/Node/Composer required; all build and runtime commands run in Docker | **Accepted** | Reproducibility; Ubuntu 26.04 packages PHP 8.5, not 8.4 |
| ADR-004 | PostgreSQL is never published publicly; only the web app is reachable through Cloudflare Tunnel | **Accepted** | Student condition 7; manual p. 3 |
| ADR-005 | Backups are stored outside the PostgreSQL data volume; never inside `pgdata` | **Accepted** | Student condition 8; manual p. 16 |
| ADR-006 | No secrets in Git; `.env.example` only; backups/dumps/keys ignored | **Accepted** | Student condition 9; manual p. 16 |
| ADR-007 | Existing analysis and plan documents are preserved unchanged | **Accepted** | Student condition 10 |
| ADR-008 | Core assessed functions, tests, backup/restore, clean install and deployment precede optional features | **Accepted** | Student condition 6; manual p. 13 |
| ADR-009 | Do not invent the missing assessment brief, domain, host or budget | **Accepted** | Student condition 4 |
| ADR-010 | Lecturer-stated requirements outrank assumptions; all documents keep [L]/[L-I]/[R]/[?] tags | **Accepted** | Student condition 2 |
| ADR-011 | Timezone `Asia/Kuala_Lumpur`; calendar due dates are local dates | **Accepted** | Manual p. 6 **[L]** |
| ADR-012 | Three fixed roles: Chief Engineer, Technician, Viewer; enforced server-side | **Accepted** | Manual p. 8 **[L]** |
| ADR-013 | One fictional vessel, 10 assets, 8 plans (3 calendar / 3 hours / 2 dual), 6 seed work orders, 6 spare items | **Accepted** | Manual pp. 1, 5 **[L]** |
| ADR-014 | One reusable skill, two bounded agent roles, one read-only MCP, six AI decision records | **Accepted** | Manual pp. 10–12 **[L]** |

### 1.1 Confirmed technology stack (19 November 2026)

| Layer | Confirmed choice |
|---|---|
| Framework | Laravel 13.x |
| Language | PHP 8.4 |
| Database | PostgreSQL 18 |
| Container runtime | Docker Engine |
| Orchestration | Docker Compose v2 |
| Frontend | Blade + Tailwind CSS |
| Build/runtime JS | Node.js 22 |

This list is the authoritative stack for implementation. The earlier Laravel 12 selection in `docs/implementation-plan.md` is superseded but the plan document itself is preserved unchanged.

---

## 2. Adopted defaults (provisional)

These come from `docs/implementation-plan.md` §J.2 and the decision register. They are adopted to avoid blocking Phase 1 and are recorded here as required.

| ID | Area | Default | Status | Can change later? |
|---|---|---|---|---|
| DEF-01 | Framework | Laravel 13 monolith | **Accepted** | No (architectural) |
| DEF-02 | Frontend | Blade + Tailwind 4 + minimal Alpine | **Provisional** | Yes |
| DEF-03 | Asset seed | Manual's suggested A01–A10 set (7 subsystems) | **Provisional** | Yes, schema unchanged |
| DEF-04 | Calendar intervals | Days only; no month-based rules | **Provisional** | Yes, with due-engine work |
| DEF-05 | Stock deduction | At **issue**; returns/receipts are positive movements | **Provisional** | Yes, if lecturer requires closure-time deduction |
| DEF-06 | Dashboard "Due" | Separate Due and Overdue tiles; Overdue ⊆ Due; dual plan counted once; caption states the definition | **Provisional** | Yes |
| DEF-07 | Work-order creation | Chief Engineer only | **Provisional** | Yes |
| DEF-08 | Spare master/corrections | Chief Engineer only | **Provisional** | Yes |
| DEF-09 | Technician visibility | Assigned work orders; read-only asset/plan/spare context | **Provisional** | Yes |
| DEF-10 | Viewer visibility | All operational screens read-only; no audit access | **Provisional** | Yes |
| DEF-11 | Exports | CSV (UTF-8, formula-injection guarded) | **Provisional** | Yes |
| DEF-12 | Primary keys | UUID for domain tables; `bigserial` for audit/history | **Provisional** | Yes |
| DEF-13 | Enums | Native PostgreSQL enum types mirrored by PHP backed enums | **Provisional** | Yes |
| DEF-14 | History tables | `work_order_events` + `plan_baseline_history` | **Provisional** | Yes |
| DEF-15 | Sessions | Database session driver | **Provisional** | Yes |
| DEF-16 | Test date | Seeder uses 2026-12-02, override via `CMMS_TEST_DATE` | **Provisional** | Yes |
| DEF-17 | Accounts | `*.test` synthetic emails; passwords only via approved private channel | **Provisional** | Yes |
| DEF-18 | Release tag | `v1.0.0-s79244` | **Provisional** | Yes, if the brief requires a format |
| DEF-19 | Login throttling | 5 attempts/minute | **Provisional** | Yes |
| DEF-20 | Pagination | 20 rows | **Provisional** | Yes |
| DEF-21 | Confirmation UI | Native browser confirm for close/return | **Provisional** | Yes |
| DEF-22 | Docker images | `php:8.4-apache`, `postgres:18-alpine`, `composer:2`, `node:22`; pin digests after first pull | **Provisional** | Yes |
| DEF-23 | Web server | Apache inside the app image | **Provisional** | Yes |
| DEF-24 | App/DB roles | App role without DDL; migrations use owner role | **Provisional** | Yes |

---

## 3. Open decisions (must not be assumed)

| ID | Question | Whose decision | Default if still unanswered |
|---|---|---|---|
| OPEN-01 | The approved assessment brief (dates, report format, submission channel, demo schedule) | Lecturer | Continue on manual + rubric; re-baseline when provided |
| OPEN-02 | Exact hostname/domain and DNS owner | Lecturer | `s79244-cmms.<lecturer-domain>` |
| OPEN-03 | Hosting arrangement and budget ceiling | Lecturer | Free/local + Cloudflare free tier |
| OPEN-04 | Is the rubric final? | Lecturer | Treat manual as controlling |
| OPEN-05 | Month-based intervals allowed? | Lecturer | Days only (DEF-04) |
| OPEN-06 | Who creates work orders? | Lecturer | Chief Engineer (DEF-07) |
| OPEN-07 | Technician read scope | Lecturer | Assigned only (DEF-09) |
| OPEN-08 | Viewer audit access | Lecturer | No (DEF-10) |
| OPEN-09 | Is CSV sufficient for "exports"? | Lecturer | Yes (DEF-11) |
| OPEN-10 | Stock deduction point | Lecturer/student | At issue (DEF-05) |
| OPEN-11 | Read-only Cloudflare docs MCP acceptable? | Lecturer | Yes; fallback only with approval |

~~OPEN-12~~ **Resolved 19 Nov 2026:** Laravel 13 approved by the student (ADR-002).

No domain, hosting provider, budget figure or missing-brief content has been invented.

---

## 7. Phase 2 database decisions (24 November 2026)

| # | Decision | Status | Note |
|---|---|---|---|
| ADR-015 | `from_state`/`to_state` on `work_order_events` are native `work_order_state` enums, not `varchar` | Accepted | Stricter than requirements §8.2; consistent with plan §B.3 |
| ADR-016 | `audit_events`, `plan_baseline_history`, `work_order_events`, `spare_movements` are append-only via `BEFORE UPDATE OR DELETE` triggers | Accepted | Exceeds the minimum (audit only); matches plan "append-only" wording |
| ADR-017 | Meter monotonicity enforced by a `max(reading_hours)` trigger per asset | Accepted | Stricter than "latest reading" rule; rollover/replacement documented as a limitation |
| ADR-018 | Closed work orders advance plan baselines at seed time (P03 → 2026-11-18, P05 → 4100.00 h) | Accepted | Consistent with FR-18; baselines differ from the illustrative matrix for those two plans |

---

## 4. Version policy

- Pin exact versions in `composer.lock`, `package-lock.json` and Docker image digests.
- Record installed versions in the README and deployment record.
- Do not use floating `latest` tags in the committed Compose file.
- Re-verify compatibility before any major upgrade.
- Security fixes take priority over feature work near release.

---

## 5. Change procedure

Any change to an **Accepted** decision requires:

1. A new ADR entry in this file (or a superseding entry).
2. A note in the Phase report or implementation notes.
3. Confirmation that no lecturer-mandated requirement is weakened.

Changes to **Provisional** defaults may be made during implementation with a one-line note in the relevant phase report.

---

## 6. Development tooling — AI MCP servers (24 November 2026)

**Status:** Accepted. This records developer tooling only; it does **not** change ADR-002 (stack) or ADR-003 (container-only runtime).

| Item | Value |
|---|---|
| Laravel Boost | **2.10.4** (`laravel/boost`, `--dev`) |
| Laravel / PHP (container) | **13.43.0** / **8.4.28** |
| OpenCode | **v2.3.2**, V2 `mcp.servers` schema |
| Node / npm | **22.22.1** / **9.2.0** |
| Playwright MCP | `@playwright/mcp@latest` (headed default), bundled Chromium revision 1255 |
| Host PHP | 8.5.4 — host Composer/scaffolding only; **not** used by the Boost MCP server |

### 6.1 Scopes and configuration

- **Laravel Boost — project scope.** `opencode.json` registers `laravel-boost` as `docker compose exec -T app php artisan boost:mcp`, with the project root as working directory. Boost therefore executes under PHP 8.4 inside the `app` container, consistent with ADR-003.
- **Playwright — global scope.** `~/.config/opencode/opencode.json` registers `playwright` for all projects. It runs headed by default (browser visible) and uses the installed Chromium binary; the absolute browser path lives only in the user-level global config and is not committed.
- **Preserved.** The existing Ollama provider/model (`ollama/qwen2.5-coder:14b`) is unchanged.
- **Generated agent files.** Boost wrote `AGENTS.md`, `CLAUDE.md`, `boost.json`, `.mcp.json`, `.agents/` and `.claude/`. These are agent instruction/configuration files, not application code.

### 6.2 Start and verify

```sh
cd ~/laravel/CMMS_S79244
docker compose up -d
docker compose exec -T app php artisan boost:mcp   # starts; exits 0 when stdin closes
opencode mcp list                                   # expect: laravel-boost connected, playwright connected
```

### 6.3 Functional verification (executed 24 Nov 2026)

- **Boost:** default connection `pgsql`; schema read from PostgreSQL 18; `get-absolute-url` returned `http://localhost:8080`; Laravel 13.x documentation search returned results; 10 routes listed.
- **Playwright:** opened `https://demo.playwright.dev/todomvc`, added the todo "MCP smoke test", confirmed it appeared ("1 item left"), then closed the browser.

### 6.4 Deliberate limitation

These are general-purpose development assistants. They do **not** satisfy the assignment's separate read-only MCP deliverable (ADR-014; manual pp. 10–12). That deliverable still requires a purpose-built, read-only MCP server for the CMMS, with its own design and evidence.

### 6.5 Portability fix (24 November 2026)

The absolute host project path has been removed from both tracked MCP files:

- `opencode.json` — now uses `"cwd": "."`. This is supported by the OpenCode local-server schema; relative paths resolve from the workspace.
- `.mcp.json` — the Claude Code stdio schema defines no `cwd` field, so the Docker project directory is supplied as `--project-directory ${CLAUDE_PROJECT_DIR:-.}` (documented `${VAR:-default}` expansion). The redundant `wsl.exe` wrapper was removed; the Docker execution command is otherwise unchanged.

Verified after the change: both files parse as valid JSON, `opencode mcp list` reports `laravel-boost` and `playwright` connected, Boost returned `http://localhost:8080`, and Playwright loaded `https://example.com`. No credentials are stored in either file; `.env` remains ignored.

---

## 8. Phase 5 meter-reading decisions (26 November 2026)

| # | Decision | Status | Note |
|---|---|---|---|
| ADR-019 | Meter readings are written by `MeterReadingService` inside a database transaction that locks the asset row (`SELECT … FOR UPDATE`) before checking monotonicity and inserting | Accepted | Serialises concurrent submissions per asset; the `meter_readings_no_decrease` trigger remains the final integrity backstop |
| ADR-020 | `reading_at` is stored as a true UTC instant (`timestamptz`); form input is interpreted in `Asia/Kuala_Lumpur` and converted to UTC, and display converts back to KL | Accepted | Matches ADR-011. The Phase 2 seeder wrote UTC-labelled wall-clock strings, so the 16 existing development rows stored 09:30 UTC (17:30 KL) instead of the intended 09:30 KL. The seeder was corrected for fresh databases and the 16 legacy rows were corrected with a narrowly scoped, transactional command (`php artisan cmms:correct-legacy-meter-timestamps --apply`); a fresh backup is retained and the command is idempotent. |
| ADR-021 | Monotonicity floor is the highest accepted reading per asset; equal readings are accepted; future-dated readings are rejected; backdated increases are rejected; a different value at an already-used instant is rejected | Accepted | UUIDv7 ids are only millisecond-ordered, so `id DESC` cannot deterministically rank conflicting values at the same instant; exact duplicates remain harmless |
| ADR-022 | Withdrawn or standby assets may still receive readings when `has_hour_meter` is true; only the meter flag gates submission | Accepted | FR-05 keeps withdrawn assets as history; no planning document authorises blocking their meter records |
| ADR-023 | Test factories use non-overlapping code prefixes (`AST-####`, `PLN-####`, `TST-SP-####`, `TST-WO-####`) | Accepted | Root cause of intermittent `UniqueConstraintViolationException`: random `A##`/`P##` values collided with seeded `A01…`/`P01…` codes |
| ADR-024 | The 16 legacy seed meter-reading timestamps are corrected by `cmms:correct-legacy-meter-timestamps`, which disables `meter_readings_no_decrease` only inside its transaction and always re-enables it; the command is idempotent and refuses unexpected row states | Accepted | Trigger comparison uses `max(reading_hours)` across all other rows, so updating an earlier row's timestamp would otherwise fail even though hours are unchanged. Backup `/tmp/opencode/p5backup/cmms-pre-phase5-fix.dump` (outside repository) precedes the change. |
| ADR-025 | The audit payload and the value returned by `MeterReadingService::record()` use the normalised UTC instant (`$at->toIso8601String()`) and a refreshed model | Accepted | Eloquent stores the naive UTC wall-clock string on insert, which the `datetime` cast would otherwise re-interpret in the application timezone; the service now refreshes the model before returning. |
| ADR-026 | Phase 6A work orders are created only for active plans by Chief Engineers; the plan and asset must match and the assignee must be an active technician | Accepted | Follows the approved plan/activity rules; duplicate active work orders are rejected by the partial unique index `one_active_work_order_per_plan` and surfaced as a validation error. |
| ADR-027 | Technician visibility is limited to work orders assigned to them; Chief Engineers and Viewers may view all work orders | Accepted | Implements the approved RBAC matrix for 6A; start/submit/close remain unimplemented. |

---

## 9. Phase 6A final verification decisions (27 November 2026)

| # | Decision | Status | Note |
|---|---|---|---|
| ADR-028 | The work-order create route is registered before the `{workOrder}` detail route; reference-allocation collisions are retried up to three times and plan conflicts are mapped from `one_active_work_order_per_plan` to a validation error | Accepted | Fixes the create-page 404 and the misleading conflict message found during final verification. The partial unique index remains the concurrency backstop. |
| ADR-029 | The authoritative development source is `/home/zensworth/laravel/dev/CMMS_S79244`, with Compose/tooling in `/home/zensworth/laravel/dev-CMMS_S79244`; deployment remains `/home/zensworth/laravel/CMMS_S79244` | Accepted | Records the corrected Phase 5A isolation layout. Development uses project `cmms_s79244-dev` on port 8081; deployment uses `cmms_s79244` on port 8080. |

---

## 10. Phase 6B lifecycle decisions (27 November 2026)

| # | Decision | Status | Note |
|---|---|---|---|
| ADR-030 | Lifecycle transitions are implemented in a reusable `WorkOrderLifecycleService` using database transactions and `lockForUpdate`, re-validating state after the lock | Accepted | One event and one audit record per transition, written atomically with the state change. |
| ADR-031 | Completion information is validated per plan trigger (calendar, running hours, dual); actual completion date/hours, notes and optional evidence only; no baseline or meter-reading changes | Accepted | UTC storage with `Asia/Kuala_Lumpur` display; no approval timestamp, current meter value or zero is substituted for missing data. |
| ADR-032 | Chief Engineer return requires a reason of at least 5 characters and preserves the earlier submission in the `returned` event snapshot | Accepted | Completion data is retained on return so the technician can correct and resubmit. |

---

## 11. Phase 6C closure decisions (27 November 2026)

| # | Decision | Status | Note |
|---|---|---|---|
| ADR-033 | Submitted work orders are closed only by the Chief Engineer in one transaction that locks the work order and plan rows | Accepted | Re-validates state and completion data after acquiring the locks; rolls back entirely on failure. |
| ADR-034 | Maintenance baselines advance only from actual completion date and hours, and history records the previous and new values | Accepted | Calendar, hours and dual plans update only their applicable fields; non-applicable fields are preserved. |
| ADR-035 | Closure is idempotent: a repeated request leaves state, baselines, history, events and audits unchanged | Accepted | The state guard rejects a second closure with a validation error. |

---

## 12. Phase 7A spare-parts registry decisions (27 November 2026)

| # | Decision | Status | Note |
|---|---|---|---|
| ADR-036 | Spare-item master data is managed by the Chief Engineer only; stock balances are never edited directly | Accepted | New items start at zero stock; all stock changes are reserved for spare movements. |
| ADR-037 | Low-stock status uses `quantity_on_hand < reorder_level` | Accepted | Equality with the reorder level is reported as OK. |
| ADR-038 | All authenticated active users may view spare inventory and movement history; only the Chief Engineer may create or edit master data | Accepted | Hiding controls is not authorization; policies and middleware enforce access. |

---

## 13. Phase 7B stock movement decisions (27 November 2026)

| # | Decision | Status | Note |
|---|---|---|---|
| ADR-039 | Stock changes use signed spare movements with row locking and one transaction | Accepted | Receipt, adjustment and issue update `quantity_on_hand`, insert the movement and write audit records atomically. |
| ADR-040 | Stock can be issued only to active work orders by the Chief Engineer or the assigned Technician | Accepted | Closed work orders cannot receive spare issues. |
| ADR-041 | Stock movements use exact decimal arithmetic with `bcadd` and `bcsub` | Accepted | No floating-point arithmetic is used for inventory quantities. |

---

## 14. Phase 7C inventory integrity decisions (27 November 2026)

| # | Decision | Status | Note |
|---|---|---|---|
| ADR-042 | Duplicate stock submissions are prevented only in the browser; server-side idempotency is not implemented | Accepted | Repeated identical valid requests remain separate movements. |
| ADR-043 | Issuing stock to submitted work orders remains permitted for active work orders | Accepted | The approved requirements do not require rejection after submission; the behaviour is documented. |
| ADR-044 | The seeded `SP-001` ledger discrepancy is reported but not corrected | Accepted | Correcting demonstration stock would violate data-preservation requirements. |

---

## 15. Phase 8A dashboard decisions (27 November 2026)

| # | Decision | Status | Note |
|---|---|---|---|
| ADR-045 | Dashboard due status is calculated only through the existing DueStatusCalculator | Accepted | No second scheduling implementation was introduced. |
| ADR-046 | Technician work-order statistics are scoped to assigned work orders | Accepted | Chief Engineer and Viewer see aggregate totals for all work orders. |
| ADR-047 | Dashboard inventory low-stock status uses quantity_on_hand < reorder_level | Accepted | Consistent with Phase 7A and 7B. |

---

## 16. Phase 8B reporting decisions (27 November 2026)

| # | Decision | Status | Note |
|---|---|---|---|
| ADR-048 | Reports use GET query parameters and server-side validation | Accepted | Filters are preserved across pagination and rejected safely when invalid. |
| ADR-049 | Technician report visibility is scoped to assigned work orders and related movements | Accepted | Filters cannot expose records outside the Technician's authorization. |
| ADR-050 | The dashboard no longer sums spare-item quantities across incompatible units | Accepted | It displays the count of active spare-item types instead. |

---

## 17. Phase 8C CSV export decisions (27 November 2026)

| # | Decision | Status | Note |
|---|---|---|---|
| ADR-051 | CSV exports reuse the existing report query methods and authorization scopes | Accepted | Avoids a second reporting implementation. |
| ADR-052 | Formula injection is neutralised by prefixing dangerous text with an apostrophe | Accepted | Trusted numeric quantities, including negative stock movements, remain numeric. |
| ADR-053 | CSV files include a UTF-8 BOM and use streamed output | Accepted | Improves Excel compatibility and avoids loading all records into memory. |
