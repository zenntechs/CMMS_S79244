# Shipyard CMMS — Technical Implementation Plan

**Project:** Shipyard: Computerized Maintenance Management System (CMMS)
**Course:** MNA4003 — Individual Final Project, Semester 1 2026/2027
**Reference:** FE-MNA4003-S12026-2027
**Primary requirements source:** `docs/requirements-analysis.md`
**Supporting specification:** `docs/proposed-system-modules.md`
**Status:** PLAN ONLY — no application code to be written until approved.
**Confirmed student ID:** **S79244** (lowercase `s79244` in hostnames, per the manual's lowercase rule)

> **Correction notice.** The student ID is **S79244**, not the placeholder used in the earlier analysis. The implementation plan, all deployment configuration, the `.env`, the release tag and the public hostname must use **S79244 / `s79244`**. The two existing analysis documents are preserved unchanged as instructed; where they mention a placeholder identifier, this plan supersedes them.

---

## 0. Scope and conventions

### 0.1 What this plan covers

A Laravel + PostgreSQL implementation of the 13 modules in `docs/proposed-system-modules.md`, the database in `docs/requirements-analysis.md` §8, the due engine and work-order lifecycle in §7, and the deployment/testing/AI evidence required by the rubric.

### 0.2 Requirement tags

| Tag | Meaning |
|---|---|
| **[L]** | Lecturer-mandated — from the manual, rubric or templates. Must be implemented. |
| **[L-I]** | Lecturer-implied — necessary to meet a rubric level-4 descriptor. |
| **[R]** | Architect recommendation — optional; may be rejected without affecting compliance. |
| **[?]** | Requires confirmation before or during implementation. |

### 0.3 Fixed technical baseline

| Item | Choice | Basis |
|---|---|---|
| Framework | **Laravel 12.x** (latest stable compatible with chosen PHP) | Manual p. 3 permits Laravel or Django; **[L]** framework must be explainable |
| Language | **PHP 8.4** | Laravel 12 requirement; **[R]** |
| Database | **PostgreSQL 18.x** | **[L]** PostgreSQL required; manual cites PG 18 docs **[R]** for version |
| Web server | Apache 2.4 inside the app image (`php:8.4-apache`) | **[R]** simplest single-container origin |
| Frontend | **Blade + Tailwind CSS + minimal Alpine.js** | **[R]** conventional, explainable, no SPA build complexity |
| Build tool | Vite | Laravel default |
| Auth | Laravel session auth (Breeze-style, hand-scoped) | **[R]** |
| Tests | PHPUnit 11 (Laravel default) + shell-based integration checks | **[R]** |
| Local runtime | Docker Compose | **[L]** Docker at least for local execution |
| Public edge | Cloudflare Tunnel | **[L]** |
| Timezone | `Asia/Kuala_Lumpur` | **[L]** manual p. 6 |

### 0.4 Non-goals (explicitly excluded)

Offline mode, condition monitoring, predictive maintenance, multi-vessel tenancy, SSO/full RBAC, native mobile apps, in-app AI that mutates records. Manual p. 20 scopes these out as future work. **[L]**

---

## 1. Mandated vs recommended — decision register

This table prevents scope drift. Every line below is either a lesson requirement or an explicit choice of mine.

| # | Decision | Status | Note |
|---|---|---|---|
| D1 | Laravel 12 + PostgreSQL 18 + Blade + Docker + Cloudflare | **[R]** within **[L]** framework freedom | Confirm with lecturer |
| D2 | Single monolithic application, one deployable web service | **[R]** | Manual p. 3 recommends one coherent app |
| D3 | 10 assets from the manual's suggested set (7 subsystems) | **[R]** implementing **[L]** count | Manual calls the set "suggested" |
| D4 | Calendar intervals in **days only** | **[R]** | Manual defines days; months require extra rules **[?]** |
| D5 | Stock deducted at **issue**, returns are positive movements | **[R]** | Manual says "decide when" (p. 8) **[?]** |
| D6 | Dashboard shows **Due** and **Overdue** as separate tiles; Overdue ⊆ Due; each plan counted once | **[R]** | Manual requires the definition be stated (p. 18) **[?]** |
| D7 | Work orders created by **Chief Engineer only** | **[R]** | Role table is silent **[?]** |
| D8 | Spare master data and corrections: **Chief Engineer only** | **[R]** | Silent in manual **[?]** |
| D9 | Technician sees only assigned work orders (plus read-only asset/plan/spare context) | **[R]** | Manual says "assigned work" **[?]** |
| D10 | Viewer sees all operational screens read-only; audit visible read-only | **[R]** | "authorised screens" undefined **[?]** |
| D11 | CSV is the export format | **[R]** | Rubric says "exports"; manual suggests CSV **[?]** |
| D12 | Native PostgreSQL enums + `varchar` movement/event types | **[R]** | Strength + explainability |
| D13 | UUID primary keys for domain tables; `bigserial` for audit/history | **[R]** | |
| D14 | `work_order_events` and `plan_baseline_history` added to the manual's entity list | **[R]** | Manual explicitly allows extra entities (p. 6) |
| D15 | Hostname `s79244-cmms.<approved-domain>` | **[R]** must contain **S79244** | **[?]** domain owner |
| D16 | Read-only Cloudflare documentation MCP | **[L]** one read-only MCP | Fallback requires lecturer approval |
| D17 | Six AI records from real episodes only | **[L]** | Never fabricate incidents |
| D18 | `sessions` table for database sessions | **[R]** | Simpler persistence across restarts |

---

# A. Proposed architecture

## A.1 Component view

```
                    ┌─────────────────────────────┐
   Browser ──HTTPS──▶│      Cloudflare edge        │
                    │  TLS, DNS, WAF (default)    │
                    └──────────────┬──────────────┘
                                   │ outbound tunnel (no inbound port)
                    ┌──────────────▼──────────────┐
                    │  cloudflared (origin host)  │
                    └──────────────┬──────────────┘
                                   │ http://app:80
              ┌────────────────────▼─────────────────────┐
              │  Docker Compose project (origin host)    │
              │                                          │
              │  ┌──────────────────┐   ┌─────────────┐  │
              │  │ app              │   │ db          │  │
              │  │ php:8.4-apache   │──▶│ postgres:18 │  │
              │  │ Laravel public/  │   │ volume pgdata│ │
              │  │ Blade + policies │   │ private net │  │
              │  └──────────────────┘   └─────────────┘  │
              │        │ storage volume (logs, uploads)  │
              └────────┼─────────────────────────────────┘
                       │
              ┌────────▼─────────┐
              │ backups/ (host)  │  pg_dump custom format
              │ outside pgdata   │  restore into separate DB
              └──────────────────┘
```

**Trust boundaries [L]:** only the web app is published; PostgreSQL is not exposed. Secrets live in the host environment / `.env` outside Git. Audit writes are append-only.

## A.2 Laravel application structure

```
app/
├── Enums/
│   ├── Criticality.php              low|medium|high|critical
│   ├── DueState.php                 not_due|due|overdue|incomplete
│   ├── DueTrigger.php               none|calendar|hours|both
│   ├── MovementType.php             issue|return|receipt|adjustment
│   ├── PlanTrigger.php              calendar|hours|dual
│   ├── ServiceStatus.php            in_service|standby|withdrawn
│   ├── UserRole.php                 chief_engineer|technician|viewer
│   ├── WorkOrderEventType.php       created|started|submitted|returned|closed|corrected
│   └── WorkOrderState.php           open|in_progress|submitted|closed
├── Exceptions/
│   ├── ActiveWorkOrderExists.php
│   ├── InsufficientStock.php
│   └── InvalidWorkOrderTransition.php
├── Http/
│   ├── Controllers/
│   │   ├── Auth/LoginController.php
│   │   ├── DashboardController.php
│   │   ├── AssetController.php
│   │   ├── MaintenancePlanController.php
│   │   ├── PlanActivationController.php
│   │   ├── MeterReadingController.php
│   │   ├── WorkOrderController.php
│   │   ├── WorkOrderTransitionController.php
│   │   ├── WorkOrderSpareController.php
│   │   ├── SpareItemController.php
│   │   ├── ReportController.php
│   │   ├── ExportController.php
│   │   └── AuditController.php
│   ├── Middleware/EnsureRole.php
│   ├── Requests/
│   │   ├── StoreAssetRequest.php          UpdateAssetRequest.php
│   │   ├── StoreMaintenancePlanRequest.php UpdateMaintenancePlanRequest.php
│   │   ├── StoreMeterReadingRequest.php
│   │   ├── StoreWorkOrderRequest.php
│   │   ├── SubmitWorkOrderRequest.php
│   │   ├── ReturnWorkOrderRequest.php
│   │   ├── CloseWorkOrderRequest.php
│   │   ├── IssueSpareRequest.php
│   │   └── UpdateSpareItemRequest.php
│   └── Resources/ (only if an API is added — not planned)
├── Models/ (see §C.1)
├── Policies/ (see §C.4)
├── Providers/AppServiceProvider.php
├── Services/
│   ├── Due/DueStatusCalculator.php
│   ├── Due/DueStatus.php
│   ├── Due/PlanSchedule.php
│   ├── WorkOrder/WorkOrderLifecycleService.php
│   ├── WorkOrder/WorkOrderQueryService.php
│   ├── Inventory/SpareStockService.php
│   ├── Metering/MeterReadingService.php
│   ├── Audit/AuditLogger.php
│   ├── Reporting/DashboardService.php
│   └── Reporting/CsvExporter.php
└── Support/Decimal.php                 bcmath comparison helper

database/
├── migrations/
├── seeders/  DatabaseSeeder, VesselSeeder, AssetSeeder, PlanSeeder,
│             WorkOrderSeeder, SpareSeeder, UserSeeder
└── factories/

resources/views/
├── layouts/app.blade.php, guest.blade.php
├── components/ (see §C.7)
├── auth/login.blade.php
├── dashboard/index.blade.php
├── assets/{index,show,create,edit}.blade.php
├── plans/{index,show,create,edit}.blade.php
├── readings/{index}.blade.php
├── work-orders/{index,show,create}.blade.php
├── spares/{index,show,edit}.blade.php
├── reports/{due,work-orders,stock,audit}.blade.php
└── audit/index.blade.php

routes/web.php
tests/Unit/…
tests/Feature/…
scripts/{backup.sh,restore-verify.sh,clean-install.sh,concurrency-check.sh}
docs/evidence/…
```

## A.3 Request lifecycle (authorisation-first)

```
Request
  → VerifyCsrfToken (web)
  → auth middleware (session)
  → EnsureRole (coarse role gate where declared)
  → Route model binding
  → FormRequest::authorize()  → Policy method
  → FormRequest validation
  → Controller
      → Service (transaction)
          → Eloquent / DB
          → AuditLogger
  → redirect + flash / Blade response
```

Policies are authoritative. Middleware/role checks and hidden buttons are defence-in-depth, not the security boundary. **[L]** p. 8.

## A.4 Database architecture (PostgreSQL)

- Single database `cmms`, single schema `public` (small project; explainable).
- App connects with a least-privilege role `cmms_app` (SELECT/INSERT/UPDATE/DELETE on domain tables, **no DDL**, **no DELETE** on audit/history tables) **[R]**.
- Migrations run with a separate owner role `cmms_owner` **[R]**.
- `search_path` fixed to `public`; no dynamic SQL.
- All timestamps `timestamptz`; all calendar due dates `date`.
- Connection pooling unnecessary at this scale; direct PDO.

## A.5 Docker Compose services

| Service | Image / build | Purpose | Ports | Volumes | Health check |
|---|---|---|---|---|---|
| `app` | build from `Dockerfile` (`php:8.4-apache`) | Laravel + Apache, document root `public/` | `8080:80` (localhost only) | `storage:/var/www/html/storage/app` | `curl -f http://localhost/up` (Laravel health route) |
| `db` | `postgres:18-alpine` | Persistent database | none published | `pgdata:/var/lib/postgresql/data` | `pg_isready -U $POSTGRES_USER -d $POSTGRES_DB` |
| `cloudflared` (optional locally, used on host) | `cloudflare/cloudflared:latest` | Outbound tunnel to Cloudflare | none | token via env | process-level |

Startup: `app` waits for `db` healthy (`depends_on: condition: service_healthy`) and retries migrations. **[L]** p. 14 asks for readiness handling.

`compose.yaml` requirements:
- `env_file: .env`
- `restart: unless-stopped`
- named volumes `pgdata`, `storage`
- no secrets baked into the image
- app `APP_ENV=production`, `APP_DEBUG=false` on the host

## A.6 Cloudflare Tunnel deployment

```
Public hostname  s79244-cmms.<approved-domain>
   │  CNAME / tunnel route managed by Cloudflare
   ▼
Named Tunnel "cmms-s79244"
   │  cloudflared connector on origin host (outbound only)
   ▼
http://app:80  (internal Docker service)
```

- No inbound firewall port is opened. **[L]**
- TLS terminated at Cloudflare; origin HTTP is internal only.
- `cloudflared` runs either as a Compose service or a host systemd service, using a scoped tunnel token in the host secret store. **[R]**
- PostgreSQL has no public route.
- `APP_URL=https://s79244-cmms.<approved-domain>`; `ASSET_URL` unset.
- Confirm the domain owner and nameserver control before purchasing anything. **[L]** p. 15.

## A.7 Authentication and RBAC

**Authentication [R]:** Laravel session guard, `users` table, `password_hash` via `Hash::make` (bcrypt/argon2id), login throttling (`RateLimiter`, 5 attempts), session regeneration on login, CSRF on all mutating web routes.

**Roles [L]** (fixed enum `UserRole`):

| Role | Server-side boundary |
|---|---|
| `chief_engineer` | Plans, assignments, review, return, close, spares master, audit |
| `technician` | Assigned work orders only; start/submit; record readings; issue permitted spares |
| `viewer` | Read-only on authorised screens; no mutating route allowed |

**Enforcement layers:**

1. `EnsureRole` middleware for coarse route groups.
2. Policy methods for record-level rules (`assigned_to === user.id`, role checks).
3. FormRequest `authorize()` delegating to policies.
4. Service-level assertions for state transitions (defence in depth).
5. Database constraints for invariants that must never be bypassed.

**Denied response:** HTTP **403** for authorisation failures, **422** for validation/business-rule failures (insufficient stock, invalid transition). Both leave the DB unchanged. **[L]** TC11/TC12.

**Test-account strategy [L]:** seeded synthetic accounts `chief@cmms.test`, `tech1@cmms.test`, `tech2@cmms.test`, `viewer@cmms.test`; passwords supplied only through the approved private channel, never committed.

---

# B. Database implementation

## B.1 Conventions

- UUID v4 primary keys via Laravel `HasUuids` (`gen_random_uuid()`).
- `created_at`/`updated_at` `timestamptz` on mutable tables.
- Money not used. Quantities/hours `numeric(12,2)`.
- FK actions: `RESTRICT` by default; `SET NULL` only for optional provenance links.
- Enums created once in migration `0001_01_01_000000_create_domain_enums.php`.
- No application-level cascading deletes of history.

## B.2 Enum types

```sql
CREATE TYPE user_role          AS ENUM ('chief_engineer','technician','viewer');
CREATE TYPE criticality        AS ENUM ('low','medium','high','critical');
CREATE TYPE service_status     AS ENUM ('in_service','standby','withdrawn');
CREATE TYPE plan_trigger       AS ENUM ('calendar','hours','dual');
CREATE TYPE work_order_state   AS ENUM ('open','in_progress','submitted','closed');
```

`movement_type`, `work_order_event_type` and audit `action` remain `varchar` with CHECK/application validation so new values do not require type migrations. **[R]**

## B.3 Tables

### `vessels`
| Column | Type | Null | Key / constraint |
|---|---|---|---|
| `id` | uuid | no | PK, default `gen_random_uuid()` |
| `code` | varchar(30) | no | UNIQUE |
| `name` | varchar(120) | no | |
| `description` | text | yes | |
| `created_at`, `updated_at` | timestamptz | no | |

### `assets`
| Column | Type | Null | Key / constraint |
|---|---|---|---|
| `id` | uuid | no | PK |
| `vessel_id` | uuid | no | FK → `vessels.id` RESTRICT |
| `code` | varchar(20) | no | UNIQUE |
| `name` | varchar(120) | no | |
| `subsystem` | varchar(80) | no | |
| `location` | varchar(120) | yes | |
| `criticality` | criticality | no | |
| `service_status` | service_status | no | default `in_service` |
| `has_hour_meter` | boolean | no | default false |
| `meter_unit` | varchar(10) | no | default `'hours'`, CHECK `= 'hours'` |
| `created_at`, `updated_at` | timestamptz | no | |

Indexes: `(subsystem)`, `(criticality)`, `(service_status)`.

### `meter_readings`
| Column | Type | Null | Key / constraint |
|---|---|---|---|
| `id` | uuid | no | PK |
| `asset_id` | uuid | no | FK → `assets.id` RESTRICT |
| `reading_hours` | numeric(12,2) | no | CHECK `>= 0` |
| `reading_at` | timestamptz | no | default `now()` |
| `recorded_by` | uuid | no | FK → `users.id` RESTRICT |
| `note` | text | yes | |
| `created_at` | timestamptz | no | |

Index: `(asset_id, reading_at DESC, id DESC)`.
**[R]** Trigger/transaction check that `reading_hours >=` latest existing reading for the same asset.

### `maintenance_plans`
| Column | Type | Null | Key / constraint |
|---|---|---|---|
| `id` | uuid | no | PK |
| `asset_id` | uuid | no | FK → `assets.id` RESTRICT |
| `code` | varchar(20) | no | UNIQUE |
| `task_description` | text | no | |
| `trigger_type` | plan_trigger | no | |
| `interval_days` | integer | yes | CHECK `> 0` |
| `interval_hours` | numeric(12,2) | yes | CHECK `> 0` |
| `last_completion_date` | date | yes | |
| `last_completion_hours` | numeric(12,2) | yes | CHECK `>= 0` |
| `last_completion_work_order_id` | uuid | yes | FK → `work_orders.id` SET NULL |
| `source_label` | varchar(120) | no | default `'Synthetic teaching scenario'` |
| `is_active` | boolean | no | default false |
| `created_at`, `updated_at` | timestamptz | no | |

**Activation-completeness CHECK [L]:**
```sql
ALTER TABLE maintenance_plans ADD CONSTRAINT chk_plan_activation_complete CHECK (
  NOT is_active OR (
       (trigger_type = 'calendar' AND interval_days IS NOT NULL AND last_completion_date IS NOT NULL)
    OR (trigger_type = 'hours'    AND interval_hours IS NOT NULL AND last_completion_hours IS NOT NULL)
    OR (trigger_type = 'dual'     AND interval_days IS NOT NULL AND last_completion_date IS NOT NULL
                                  AND interval_hours IS NOT NULL AND last_completion_hours IS NOT NULL)
  )
);
```
Also:
```sql
CHECK (trigger_type <> 'calendar' OR interval_days IS NOT NULL);
CHECK (trigger_type <> 'hours'    OR interval_hours IS NOT NULL);
CHECK (trigger_type <> 'dual'     OR (interval_days IS NOT NULL AND interval_hours IS NOT NULL));
```
The second set prevents a plan of a given type from being saved without the matching interval even while inactive. Baselines may be absent until activation.

Index: `(asset_id)`, `(is_active)`.

### `work_orders`
| Column | Type | Null | Key / constraint |
|---|---|---|---|
| `id` | uuid | no | PK |
| `reference` | varchar(20) | no | UNIQUE |
| `plan_id` | uuid | no | FK → `maintenance_plans.id` RESTRICT |
| `state` | work_order_state | no | default `open` |
| `assigned_to` | uuid | yes | FK → `users.id` RESTRICT |
| `created_by` | uuid | no | FK → `users.id` RESTRICT |
| `created_at` | timestamptz | no | |
| `started_at` | timestamptz | yes | |
| `submitted_at` | timestamptz | yes | |
| `closed_at` | timestamptz | yes | |
| `actual_completion_date` | date | yes | |
| `actual_completion_hours` | numeric(12,2) | yes | CHECK `>= 0` |
| `completion_notes` | text | yes | |
| `evidence_ref` | varchar(255) | yes | |
| `approved_by` | uuid | yes | FK → `users.id` RESTRICT |

Checks:
```sql
CHECK (state <> 'closed'   OR closed_at IS NOT NULL);
CHECK (state <> 'submitted' OR submitted_at IS NOT NULL);
CHECK (actual_completion_hours IS NULL OR actual_completion_hours >= 0);
```
Indexes: `(plan_id)`, `(state)`, `(assigned_to, state)`, `(created_at DESC)`.

### `work_order_events`
| Column | Type | Null | Key / constraint |
|---|---|---|---|
| `id` | uuid | no | PK |
| `work_order_id` | uuid | no | FK → `work_orders.id` CASCADE |
| `event_type` | varchar(20) | no | CHECK IN (`created`,`started`,`submitted`,`returned`,`closed`,`corrected`) |
| `from_state` | work_order_state | yes | |
| `to_state` | work_order_state | yes | |
| `actor_id` | uuid | no | FK → `users.id` RESTRICT |
| `reason` | text | yes | |
| `snapshot` | jsonb | yes | completion fields at submission |
| `occurred_at` | timestamptz | no | default now() |

CHECK: `event_type <> 'returned' OR (reason IS NOT NULL AND length(btrim(reason)) > 0)` **[L]**.
Index: `(work_order_id, occurred_at)`.

### `spare_items`
| Column | Type | Null | Key / constraint |
|---|---|---|---|
| `id` | uuid | no | PK |
| `code` | varchar(30) | no | UNIQUE |
| `description` | varchar(160) | no | |
| `unit` | varchar(20) | no | e.g. `piece`, `litre`, `kg` |
| `quantity_on_hand` | numeric(12,2) | no | default 0, CHECK `>= 0` |
| `reorder_level` | numeric(12,2) | no | default 0, CHECK `>= 0` |
| `is_active` | boolean | no | default true |
| `created_at`, `updated_at` | timestamptz | no | |

### `spare_movements`
| Column | Type | Null | Key / constraint |
|---|---|---|---|
| `id` | uuid | no | PK |
| `spare_item_id` | uuid | no | FK → `spare_items.id` RESTRICT |
| `work_order_id` | uuid | yes | FK → `work_orders.id` RESTRICT |
| `movement_type` | varchar(20) | no | CHECK IN (`issue`,`return`,`receipt`,`adjustment`) |
| `quantity_delta` | numeric(12,2) | no | CHECK `<> 0` |
| `balance_after` | numeric(12,2) | no | CHECK `>= 0` |
| `actor_id` | uuid | no | FK → `users.id` RESTRICT |
| `reason` | text | yes | required for `adjustment` |
| `occurred_at` | timestamptz | no | default now() |

Checks:
```sql
CHECK (movement_type <> 'issue'      OR quantity_delta < 0);
CHECK (movement_type <> 'receipt'    OR quantity_delta > 0);
CHECK (movement_type <> 'return'     OR quantity_delta > 0);
CHECK (movement_type <> 'adjustment' OR reason IS NOT NULL);
CHECK (movement_type <> 'issue'      OR work_order_id IS NOT NULL);
```
Index: `(spare_item_id, occurred_at)`, `(work_order_id)`.

### `users`
| Column | Type | Null | Key / constraint |
|---|---|---|---|
| `id` | uuid | no | PK |
| `name` | varchar(120) | no | |
| `email` | varchar(160) | no | UNIQUE |
| `password` | varchar(255) | no | hashed |
| `role` | user_role | no | |
| `is_active` | boolean | no | default true |
| `remember_token` | varchar(100) | yes | |
| `created_at`, `updated_at` | timestamptz | no | |

### `audit_events`
| Column | Type | Null | Key / constraint |
|---|---|---|---|
| `id` | bigserial | no | PK |
| `actor_id` | uuid | yes | FK → `users.id` RESTRICT (null = system) |
| `action` | varchar(50) | no | e.g. `work_order.closed` |
| `entity_type` | varchar(50) | no | |
| `entity_id` | uuid | no | |
| `before` | jsonb | yes | |
| `after` | jsonb | yes | |
| `reason` | text | yes | |
| `occurred_at` | timestamptz | no | default now() |

Indexes: `(entity_type, entity_id, occurred_at DESC)`, `(actor_id)`, `(occurred_at DESC)`.
**[R]** Revoke UPDATE/DELETE from the app role.

### `plan_baseline_history` **[R]**
| Column | Type | Null | Key / constraint |
|---|---|---|---|
| `id` | bigserial | no | PK |
| `plan_id` | uuid | no | FK → `maintenance_plans.id` RESTRICT |
| `work_order_id` | uuid | no | FK → `work_orders.id` RESTRICT |
| `previous_date` | date | yes | |
| `new_date` | date | yes | |
| `previous_hours` | numeric(12,2) | yes | |
| `new_hours` | numeric(12,2) | yes | |
| `changed_by` | uuid | no | FK → `users.id` RESTRICT |
| `changed_at` | timestamptz | no | default now() |

### `sessions` (Laravel)
Standard Laravel schema: `id` varchar PK, `user_id` uuid nullable indexed, `ip_address`, `user_agent`, `payload` text, `last_activity` integer.

## B.4 Relationships

| Parent | Child | Cardinality | FK |
|---|---|---|---|
| vessels | assets | 1:N | `assets.vessel_id` |
| assets | meter_readings | 1:N | `meter_readings.asset_id` |
| assets | maintenance_plans | 1:N | `maintenance_plans.asset_id` |
| maintenance_plans | work_orders | 1:N (≤1 active) | `work_orders.plan_id` |
| work_orders | work_order_events | 1:N | `work_order_events.work_order_id` |
| spare_items | spare_movements | 1:N | `spare_movements.spare_item_id` |
| work_orders | spare_movements | 1:N | `spare_movements.work_order_id` |
| users | meter_readings / work_orders / events / movements / audit | 1:N | various |
| work_orders | maintenance_plans (baseline provenance) | 1:0..1 | `maintenance_plans.last_completion_work_order_id` |
| maintenance_plans | plan_baseline_history | 1:N | `plan_baseline_history.plan_id` |

## B.5 The partial unique index (critical) **[L]**

```sql
CREATE UNIQUE INDEX one_active_work_order_per_plan
    ON work_orders (plan_id)
    WHERE state IN ('open', 'in_progress', 'submitted');
```

Why it is mandatory:

- An application "if no active exists then insert" is racy under concurrent requests (TC09).
- The partial index makes the second insert fail atomically at the database.
- The application catches SQLSTATE `23505` on this index name and returns a clear validation message.
- Closed orders are excluded, so a new instance can be created later.

Laravel migration:
```php
DB::statement(
  "CREATE UNIQUE INDEX one_active_work_order_per_plan
   ON work_orders (plan_id)
   WHERE state IN ('open','in_progress','submitted')"
);
```
Rollback drops the index.

## B.6 Closure transaction and idempotency **[L]**

**Rule:** closure may run once per submission; it must never advance the baseline twice or double-deduct stock.

```
BEGIN;
  -- 1. Guarded state transition (0 rows = already closed / wrong state)
  UPDATE work_orders
     SET state = 'closed',
         closed_at = now(),
         approved_by = :ce,
         actual_completion_date = :actual_date,
         actual_completion_hours = :actual_hours
   WHERE id = :id
     AND state = 'submitted'
  RETURNING *;

  IF 0 rows:
     ROLLBACK; return "already closed or not submittable"   -- idempotent no-op

  -- 2. Re-validate completion data for this plan type
  -- 3. Advance only applicable baselines from ACTUAL completion values
  UPDATE maintenance_plans
     SET last_completion_date = :actual_date,        -- calendar/dual only
         last_completion_hours = :actual_hours,      -- hours/dual only
         last_completion_work_order_id = :id
   WHERE id = :plan_id;

  -- 4. Record baseline history
  INSERT INTO plan_baseline_history (...);

  -- 5. Append event + audit
  INSERT INTO work_order_events (..., event_type='closed', ...);
  INSERT INTO audit_events (..., action='work_order.closed', ...);
COMMIT;
```

Laravel implementation notes:

- Wrap in `DB::transaction(..., attempts: 3)`.
- Use `WorkOrder::whereKey($id)->where('state', WorkOrderState::Submitted)->lockForUpdate()->first()` **or** the conditional `UPDATE` above. Prefer the conditional `UPDATE` + row count; it is the clearest idempotency guard.
- The plan row is locked implicitly by the FK/update; explicit `lockForUpdate()` on the plan is acceptable.
- Never use `Carbon::now()` for the baseline — use the submitted `actual_completion_date` / `actual_completion_hours`.
- If the plan is `calendar`, do not overwrite `last_completion_hours`; if `hours`, do not overwrite `last_completion_date`; if `dual`, update both.
- A repeated close returns a flash message and changes nothing. No second `plan_baseline_history` row.

**Stock interaction:** if spare deduction happens at issue (default), closure does **not** touch stock, so repeated closure cannot double-deduct. This is a deliberate simplification and is documented. **[R]** If the lecturer requires deduction at closure, the same conditional-update guard must gate the deduction in the same transaction.

## B.7 Audit and historical records

| Record | Where stored | Immutability |
|---|---|---|
| Lifecycle transitions | `work_order_events` | append-only |
| Prior submissions/return reasons | `work_order_events` (`submitted`/`returned` with `snapshot`) | append-only |
| Baseline advances | `plan_baseline_history` | append-only |
| Stock changes | `spare_movements` | append-only |
| Privileged actions | `audit_events` | append-only |
| Closed work orders | `work_orders` row retained | no delete route |
| Withdrawn assets | `assets.service_status` | no delete route |

Every mutation service writes audit in the **same transaction** as the change. If audit insert fails, the action rolls back. **[L]** p. 8.

## B.8 Migration order

1. Laravel framework tables (`users` is overridden by ours) + `sessions`
2. `create_domain_enums`
3. `vessels`
4. `assets`
5. `meter_readings`
6. `maintenance_plans` (without the circular `last_completion_work_order_id` FK)
7. `work_orders`
8. add `maintenance_plans.last_completion_work_order_id` FK
9. `work_order_events`
10. `spare_items`
11. `spare_movements`
12. `plan_baseline_history`
13. `audit_events`
14. indexes (including the partial unique index)

Circularity between `maintenance_plans` and `work_orders` is resolved by adding the provenance FK after both tables exist.

## B.9 Seed data

| Entity | Count | Specification |
|---|---|---|
| Vessel | 1 | *Training Vessel Seri Ilmu* (fictional) |
| Assets | 10 | A01–A10 as in analysis §3, 7 subsystems |
| Plans | 8 | 3 calendar, 3 hours, 2 dual (matrix below) |
| Work orders | 6 | 1 Open, 1 In progress, 2 Submitted, 2 Closed; **no** duplicate active per plan |
| Spares | 6 | one below reorder, one short for the shortage demo |
| Users | 5 | 1 CE, 2 Technicians, 1 Viewer, plus 1 inactive (for permission tests) |

**Plan matrix (recommended) [R]:**

| Plan | Asset | Trigger | Interval | Baseline |
|---|---|---|---|---|
| P01 | A05 Generator | calendar | 30 d | 2026-11-01 |
| P02 | A07 Fire pump | calendar | 90 d | 2026-10-15 |
| P03 | A08 Steering | calendar | 180 d | 2026-09-01 |
| P04 | A01 Port main engine | hours | 250 h | 1200.00 h |
| P05 | A03 Port gearbox | hours | 500 h | 4000.00 h |
| P06 | A10 Air compressor | hours | 200 h | 800.00 h |
| P07 | A02 Starboard main engine | dual | 30 d / 250 h | 2026-11-01 / 1200.00 h |
| P08 | A09 Sea-water cooling pump | dual | 60 d / 400 h | 2026-10-20 / 2000.00 h |

Fixed demo/test date: **2026-12-02** in `Asia/Kuala_Lumpur` (after the TC01–TC03 boundary) can be overridden with `CMMS_TEST_DATE` in the seeder. **[R]** This makes boundary demonstrations deterministic without changing business logic.

All source labels read `Synthetic teaching scenario`. All names/IDs synthetic; no real serial numbers. **[L]**

---

# C. Laravel implementation

## C.1 Models

| Model | Table | Key relationships | Casts / notes |
|---|---|---|---|
| `Vessel` | vessels | `hasMany(Asset)` | |
| `Asset` | assets | `belongsTo(Vessel)`, `hasMany(MeterReading)`, `hasMany(MaintenancePlan)` | `criticality`, `service_status` enums; `has_hour_meter` bool |
| `MeterReading` | meter_readings | `belongsTo(Asset)`, `belongsTo(User,'recorded_by')` | `reading_at` datetime, `reading_hours` decimal:2 |
| `MaintenancePlan` | maintenance_plans | `belongsTo(Asset)`, `hasMany(WorkOrder)`, `belongsTo(WorkOrder,'last_completion_work_order_id')`, `hasMany(PlanBaselineHistory)` | `trigger_type` enum; dates; `is_active` bool |
| `WorkOrder` | work_orders | `belongsTo(MaintenancePlan)`, `belongsTo(User,'assigned_to'/'created_by'/'approved_by')`, `hasMany(WorkOrderEvent)`, `hasMany(SpareMovement)` | `state` enum; completion fields |
| `WorkOrderEvent` | work_order_events | `belongsTo(WorkOrder)`, `belongsTo(User,'actor_id')` | `snapshot` array/jsonb |
| `SpareItem` | spare_items | `hasMany(SpareMovement)` | decimal |
| `SpareMovement` | spare_movements | `belongsTo(SpareItem)`, `belongsTo(WorkOrder)`, `belongsTo(User,'actor_id')` | decimal |
| `User` | users | `hasMany(WorkOrder,'assigned_to')` etc. | `role` enum, `password` hashed, `is_active` |
| `AuditEvent` | audit_events | `belongsTo(User,'actor_id')` | `before`/`after` array |
| `PlanBaselineHistory` | plan_baseline_history | `belongsTo(MaintenancePlan)`, `belongsTo(WorkOrder)` | |

**Scopes:**
- `WorkOrder::scopeActive()` → `whereIn('state', ['open','in_progress','submitted'])`
- `WorkOrder::scopeVisibleTo($user)` → CE: all; Technician: `assigned_to = user.id`; Viewer: all read-only
- `MaintenancePlan::scopeActive()`

**Accessors:**
- `WorkOrder::isActive()`, `canBeClosed()`
- `MaintenancePlan::usesCalendar()`, `usesHours()`

## C.2 Services (business logic lives here, not controllers)

### `DueStatusCalculator` — pure, no DB, no globals

```php
interface DueStatusCalculator
{
    public function calculate(PlanSchedule $plan, ?string $currentHours, CarbonImmutable $today): DueStatus;
}
```

`PlanSchedule` (read model):
- `trigger: PlanTrigger`
- `intervalDays: ?int`
- `intervalHours: ?string` (decimal string)
- `lastCompletionDate: ?CarbonImmutable` (date-only)
- `lastCompletionHours: ?string`
- `hasHourMeter: bool`

`DueStatus` (value object):
- `state: DueState` (`not_due|due|overdue|incomplete`)
- `trigger: DueTrigger` (`none|calendar|hours|both`)
- `calendarState`, `hoursState` (each `not_due|due|overdue|incomplete`)
- `dueDate: ?CarbonImmutable`
- `hoursDueAt: ?string`
- `hoursCheckIncomplete: bool`
- `asOfDate: CarbonImmutable`

### `MeterReadingService`
- `record(Asset $asset, string $hours, CarbonImmutable $at, User $actor): MeterReading`
- Locks the asset row in a transaction, reads the latest reading (`ORDER BY reading_at DESC, id DESC LIMIT 1`), rejects a decrease, inserts, audits.

### `WorkOrderLifecycleService`
- `create(plan, actor, assignee): WorkOrder`
- `start(WorkOrder, actor): void`
- `submit(WorkOrder, actor, CompletionData): void`
- `returnToProgress(WorkOrder, actor, string $reason): void`
- `close(WorkOrder, actor, CompletionData): void` (transaction + idempotency)
- Every method asserts current state, writes a `work_order_events` row and an `audit_events` row.

### `SpareStockService`
- `issue(SpareItem, WorkOrder, string $qty, User $actor, ?string $reason): SpareMovement`
- `adjust(...)` for corrections; `receive(...)`.
- Uses `lockForUpdate()`, checks balance, inserts movement, updates `quantity_on_hand`, audits.

### `AuditLogger`
- `log(User|null $actor, string $action, string $entityType, string $entityId, array $before, array $after, ?string $reason): void`
- Never receives or stores raw request payloads containing passwords.

### `DashboardService` / `ReportService`
- Loads active plans with assets and latest readings, delegates each row to `DueStatusCalculator`, aggregates once per plan.
- Exports reuse the same query builder as the on-screen lists.

### `CsvExporter`
- Streams `text/csv` with `Content-Disposition`; same filters as the request; no Blade buffering.

## C.3 Controllers

| Controller | Methods | Responsibility |
|---|---|---|
| `Auth\LoginController` | `create`, `store`, `destroy` | Session login/logout + throttling |
| `DashboardController` | `__invoke` | Tiles + recent activity |
| `AssetController` | `index`, `show`, `create`, `store`, `edit`, `update` | Asset register |
| `MaintenancePlanController` | `index`, `show`, `create`, `store`, `edit`, `update` | Plan CRUD |
| `PlanActivationController` | `store`, `destroy` | activate/deactivate with completeness validation |
| `MeterReadingController` | `index`, `store` | Readings per asset |
| `WorkOrderController` | `index`, `show`, `create`, `store` | List/detail/create |
| `WorkOrderTransitionController` | `start`, `submit`, `returnToProgress`, `close` | Lifecycle actions |
| `WorkOrderSpareController` | `store` | Issue spare to a work order |
| `SpareItemController` | `index`, `show`, `edit`, `update` | Stock read + CE master edits |
| `ReportController` | `due`, `workOrders`, `stock`, `audit` | Reports |
| `ExportController` | `due`, `workOrders`, `stock`, `audit` | CSV exports |
| `AuditController` | `index` | Audit viewer |

Controllers must be thin: validate via FormRequest, authorise via policy, call one service, redirect with a flash message. No business rules in Blade or controllers. **[R]**

## C.4 Policies

| Policy | Methods | Rules |
|---|---|---|
| `AssetPolicy` | `viewAny`, `view`, `create`, `update` | All view; CE writes |
| `MaintenancePlanPolicy` | `viewAny`, `view`, `create`, `update`, `activate` | All view; CE writes/activates |
| `MeterReadingPolicy` | `viewAny`, `create` | All view; CE + Technician create (Technician only for permitted assets) |
| `WorkOrderPolicy` | `viewAny`, `view`, `create`, `start`, `submit`, `returnToProgress`, `close`, `issueSpare` | See matrix below |
| `SpareItemPolicy` | `viewAny`, `view`, `update`, `adjust` | All view; CE update/adjust |
| `AuditPolicy` | `viewAny` | CE; Viewer read-only **[?]** |

**Work-order policy matrix [L]:**

| Ability | CE | Technician | Viewer |
|---|:--:|:--:|:--:|
| `viewAny` | ✅ | ✅ (scoped list) | ✅ |
| `view` | ✅ | assigned only | ✅ |
| `create` | ✅ | ❌ | ❌ |
| `start` | ✅ (authorised) | assigned only | ❌ |
| `submit` | ✅ | assigned only | ❌ |
| `returnToProgress` | ✅ | ❌ | ❌ |
| `close` | ✅ | ❌ | ❌ |
| `issueSpare` | ✅ | assigned only | ❌ |

`AppServiceProvider` registers `Gate::policy()` mappings (Laravel auto-discovery also works, but explicit is easier to explain in C9).

## C.5 Middleware

| Alias | Class | Purpose |
|---|---|---|
| `auth` | Laravel | session authentication |
| `guest` | Laravel | login page only |
| `role` | `EnsureRole` | `role:chief_engineer` coarse gate for admin route groups |
| `throttle` | Laravel | login + exports |

`EnsureRole` returns 403 and never redirects to a "safe" page that hides the failure. Direct unauthorised requests must be visibly denied. **[L]**

## C.6 Form requests and validation

| Request | Key rules |
|---|---|
| `StoreAssetRequest` | `code` required unique:assets; `name`; `subsystem`; `criticality` Rule::enum; `service_status`; `has_hour_meter` boolean |
| `UpdateAssetRequest` | same, unique ignoring current id |
| `StoreMaintenancePlanRequest` | `asset_id` exists; `trigger_type` enum; `interval_days` `integer|gt:0` required_if calendar/dual; `interval_hours` `numeric|gt:0` required_if hours/dual; baselines nullable; `source_label` required |
| `UpdateMaintenancePlanRequest` | same + forbid changing trigger/asset when active work orders exist **[R]** |
| `StoreMeterReadingRequest` | `reading_hours` `required|numeric|min:0|decimal:0,2`; `reading_at` date; custom rule: not lower than latest |
| `StoreWorkOrderRequest` | `plan_id` exists and active; `assigned_to` exists and role technician; no active order (DB is final arbiter) |
| `SubmitWorkOrderRequest` | `actual_completion_date` required_date, `before_or_equal:today` **[R]**; `actual_completion_hours` required_if plan uses hours, numeric, min 0; `completion_notes` required min 10; `evidence_ref` nullable |
| `ReturnWorkOrderRequest` | `reason` required string min 5 max 1000 |
| `CloseWorkOrderRequest` | confirmation of completion data; server re-reads from the order, not the client |
| `IssueSpareRequest` | `spare_item_id` exists; `quantity` required numeric gt:0 decimal:0,2; custom rule: available stock |
| `UpdateSpareItemRequest` | description, unit, reorder_level; **not** `quantity_on_hand` (quantity changes only via movements) |

Cross-field validation (baseline completeness, hours-required) uses `after()` callbacks. Database constraints are the final defence. **[L]**

## C.7 Routes (web.php)

| Method | URI | Controller | Middleware / policy |
|---|---|---|---|
| GET | `/login` | LoginController@create | guest |
| POST | `/login` | LoginController@store | guest, throttle |
| POST | `/logout` | LoginController@destroy | auth |
| GET | `/` | redirect `/dashboard` | auth |
| GET | `/dashboard` | DashboardController | auth |
| GET | `/assets` | AssetController@index | auth |
| GET | `/assets/{asset}` | AssetController@show | auth |
| GET | `/assets/create` | AssetController@create | auth, role:chief_engineer |
| POST | `/assets` | AssetController@store | auth, role:chief_engineer |
| GET | `/assets/{asset}/edit` | AssetController@edit | auth, role:chief_engineer |
| PUT | `/assets/{asset}` | AssetController@update | auth, role:chief_engineer |
| GET | `/plans` | MaintenancePlanController@index | auth |
| GET | `/plans/{plan}` | MaintenancePlanController@show | auth |
| GET | `/plans/create` | MaintenancePlanController@create | auth, role:chief_engineer |
| POST | `/plans` | MaintenancePlanController@store | auth, role:chief_engineer |
| GET | `/plans/{plan}/edit` | MaintenancePlanController@edit | auth, role:chief_engineer |
| PUT | `/plans/{plan}` | MaintenancePlanController@update | auth, role:chief_engineer |
| POST | `/plans/{plan}/activate` | PlanActivationController@store | auth, role:chief_engineer |
| DELETE | `/plans/{plan}/activate` | PlanActivationController@destroy | auth, role:chief_engineer |
| GET | `/assets/{asset}/readings` | MeterReadingController@index | auth |
| POST | `/assets/{asset}/readings` | MeterReadingController@store | auth, role:chief_engineer,technician |
| GET | `/work-orders` | WorkOrderController@index | auth |
| GET | `/work-orders/{workOrder}` | WorkOrderController@show | auth, policy:view |
| GET | `/work-orders/create` | WorkOrderController@create | auth, role:chief_engineer |
| POST | `/work-orders` | WorkOrderController@store | auth, role:chief_engineer |
| POST | `/work-orders/{workOrder}/start` | WorkOrderTransitionController@start | auth, policy:start |
| POST | `/work-orders/{workOrder}/submit` | WorkOrderTransitionController@submit | auth, policy:submit |
| POST | `/work-orders/{workOrder}/return` | WorkOrderTransitionController@returnToProgress | auth, role:chief_engineer |
| POST | `/work-orders/{workOrder}/close` | WorkOrderTransitionController@close | auth, role:chief_engineer |
| POST | `/work-orders/{workOrder}/spares` | WorkOrderSpareController@store | auth, policy:issueSpare |
| GET | `/spares` | SpareItemController@index | auth |
| GET | `/spares/{spareItem}` | SpareItemController@show | auth |
| GET | `/spares/{spareItem}/edit` | SpareItemController@edit | auth, role:chief_engineer |
| PUT | `/spares/{spareItem}` | SpareItemController@update | auth, role:chief_engineer |
| GET | `/reports/due` | ReportController@due | auth |
| GET | `/reports/work-orders` | ReportController@workOrders | auth |
| GET | `/reports/stock` | ReportController@stock | auth |
| GET | `/reports/audit` | ReportController@audit | auth, role:chief_engineer |
| GET | `/exports/due.csv` | ExportController@due | auth |
| GET | `/exports/work-orders.csv` | ExportController@workOrders | auth |
| GET | `/exports/stock.csv` | ExportController@stock | auth |
| GET | `/exports/audit.csv` | ExportController@audit | auth, role:chief_engineer |
| GET | `/audit` | AuditController@index | auth, role:chief_engineer |
| GET | `/up` | framework health | none (internal) |

Route model binding uses UUIDs. Policies are also called inside FormRequests, so a route middleware omission still fails closed.

## C.8 Blade views and reusable components

**Layouts**
- `layouts/app` — sidebar/topbar, role badge, flash region, `@vite`.
- `layouts/guest` — centred login.

**Components**
| Component | Purpose |
|---|---|
| `x-status-badge` | plan state / WO state / service status |
| `x-due-badge` | Not due / Due / Overdue / Incomplete + trigger icon |
| `x-stat-card` | dashboard tile (label, value, hint, link) |
| `x-data-table` | responsive table shell + empty state |
| `x-form.input`, `x-form.select`, `x-form.textarea` | label + error + `aria-describedby` |
| `x-form.errors` | validation summary |
| `x-flash` | success/error banners |
| `x-confirm` | confirmation for close/return (native `confirm()`; no heavy modal) |
| `x-empty-state` | guidance when no records |
| `x-role-only` | **cosmetic only**; never the security boundary |

**Pages**

| Module | Views | Key content |
|---|---|---|
| Auth | `auth/login` | email, password, error |
| Dashboard | `dashboard/index` | plan status tiles, WO state counts, low stock, recent audit |
| Assets | `assets/index/show/create/edit` | register, current hours, plans, history |
| Plans | `plans/index/show/create/edit` | trigger, intervals, baseline, computed due, activate |
| Readings | `readings/index` | current reading, history, add form |
| Work orders | `work-orders/index/show/create` | list, timeline, action panel |
| Spares | `spares/index/show/edit` | stock, reorder flag, ledger, issue form |
| Reports | `reports/due|work-orders|stock|audit` | filters + export button |
| Audit | `audit/index` | filterable append-only log |

**UI rules [L]/[R]:** labelled inputs, visible focus, error text tied to fields, tables scroll on small screens, action buttons only shown when permitted (cosmetic), 403 page explains denial, no reliance on colour alone (text + icon).

---

# D. Maintenance calculation logic

## D.1 Inputs

| Input | Source | Notes |
|---|---|---|
| `today` | `CarbonImmutable::today('Asia/Kuala_Lumpur')` | injected in tests |
| plan trigger type | `maintenance_plans.trigger_type` | |
| `interval_days` | plan | positive int |
| `interval_hours` | plan | positive decimal string |
| `last_completion_date` | plan | date, nullable |
| `last_completion_hours` | plan | decimal, nullable |
| `current_hours` | latest `meter_readings.reading_hours` for the asset | nullable if none/meterless |

Decimal comparisons use **bcmath** (`bccomp($a, $b, 2)`) to avoid float equality errors. **[R]**

## D.2 Algorithm (authoritative)

```
function calculate(plan, currentHours, today):
    calState = INCOMPLETE
    if plan.usesCalendar():
        if plan.lastCompletionDate is null: calState = INCOMPLETE
        else:
            dueDate = plan.lastCompletionDate + plan.intervalDays days
            calState = OVERDUE if today > dueDate
                     = DUE     if today == dueDate
                     = NOT_DUE otherwise

    hoursState = INCOMPLETE
    if plan.usesHours():
        if plan.lastCompletionHours is null or currentHours is null:
            hoursState = INCOMPLETE
        else:
            dueAt = plan.lastCompletionHours + plan.intervalHours
            cmp = bccomp(currentHours, dueAt, 2)
            hoursState = OVERDUE if cmp > 0
                       = DUE     if cmp == 0
                       = NOT_DUE otherwise

    # overall state
    if plan.trigger == CALENDAR: state = calState
    elif plan.trigger == HOURS:  state = hoursState
    else:  # DUAL - OR logic
        reached = [s for s in (calState, hoursState) if s in (DUE, OVERDUE)]
        if any OVERDUE in reached: state = OVERDUE
        elif any DUE   in reached: state = DUE
        elif any INCOMPLETE:       state = NOT_DUE with incomplete warning  # calendar may be not due
        else:                      state = NOT_DUE

    # trigger cause: which side(s) reached a threshold
    cause = NONE
    if calState in (DUE, OVERDUE) and hoursState in (DUE, OVERDUE): cause = BOTH
    elif calState in (DUE, OVERDUE): cause = CALENDAR
    elif hoursState in (DUE, OVERDUE): cause = HOURS

    return DueStatus(state, cause, calState, hoursState, dueDate, dueAt,
                     hoursCheckIncomplete = (plan.usesHours() and hoursState == INCOMPLETE))
```

**Clarification for dual plans [L] p. 7:** if the calendar side is Not due and the hours side is Incomplete (no reading), the plan is shown **Not due** with a prominent "hours check incomplete — record a meter reading" warning. If the calendar side is Due/Overdue, that state is preserved even when hours are missing.

## D.3 Boundaries

| Case | Rule | Result |
|---|---|---|
| `today == due_date` | `>=` | **Due** |
| `today > due_date` | strict | **Overdue** |
| `current == due_at` | bcmath `== 0` | **Due** |
| `current > due_at` | bcmath `> 0` | **Overdue** |
| dual, calendar due, hours not | OR | Due, cause CALENDAR |
| dual, hours due, calendar not | OR | Due, cause HOURS |
| dual, both due | OR | Due, cause BOTH, counted once |
| dual, one overdue | precedence | Overdue, cause that side or BOTH |
| missing current hours | no zero substitution | Incomplete hours check + warning |
| plan inactive with missing baseline | activation blocked | 422 with field error |

## D.4 Meter-reading validation **[L]**

```
BEGIN
  asset = Asset::whereKey($assetId)->lockForUpdate()->firstOrFail();
  previous = MeterReading::where('asset_id',$asset->id)
              ->orderByDesc('reading_at')->orderByDesc('id')->first();
  if previous && bccomp($newHours, previous->reading_hours, 2) < 0:
      reject 422 "reading must not be lower than the last accepted reading"
  reading = MeterReading::create(...);
  audit(...);
COMMIT
```

- New readings may equal the previous reading (no increase is not a decrease). **[R]**
- Readings are append-only; corrections create a new superseding reading with a note. **[R]**
- Meter replacement/rollover is documented as a limitation unless a reset workflow is implemented. **[L]** p. 7.

## D.5 Baseline advancement **[L]**

| Plan type | Fields advanced | Source of values |
|---|---|---|
| calendar | `last_completion_date` | `work_orders.actual_completion_date` |
| hours | `last_completion_hours` | `work_orders.actual_completion_hours` |
| dual | both | both actual fields |

Never use `closed_at`, `submitted_at` or `approved_at`. Example (manual p. 7): completion 5 Nov 2026 at 1500 h, approval 7 Nov → next thresholds 5 Dec 2026 and 1750 h. A repeated close changes nothing.

## D.6 Worked verification (must be encoded as unit tests)

| Test | Given | Expect |
|---|---|---|
| Calendar before | 1 Nov + 30 d, today 30 Nov | Not due; due 1 Dec |
| Calendar equality | today 1 Dec | Due |
| Calendar after | today 2 Dec | Overdue, 1 day |
| Hours before | 1200 + 250, current 1449 | Not due; due at 1450 |
| Hours equality | current 1450 | Due |
| Hours after | current 1451 | Overdue |
| Dual hours path | 20 Nov, 1450 h | Due, cause HOURS |
| Dual date path | 1 Dec, 1400 h | Due, cause CALENDAR |
| Dual both | 1 Dec, 1450 h | Due once, cause BOTH |
| Missing reading | hours plan, null | Incomplete, warning |
| Missing baseline | dual activation | 422, no zero |

---

# E. Testing strategy

## E.1 Layers

| Layer | Tool | Scope |
|---|---|---|
| Unit | PHPUnit | `DueStatusCalculator` boundaries, decimal comparisons, enum logic |
| Feature (HTTP) | PHPUnit `$this->actingAs()` | policies, routes, validation, lifecycle, stock, exports |
| Database constraint | PHPUnit + raw SQL | unique codes, partial index, checks, activation check |
| Concurrency | shell script / two-process HTTP | duplicate active order under parallel requests |
| Integration (manual/shell) | `pg_dump`/`pg_restore`, Docker | persistence, restore, clean install |
| Deployment | second device/network | HTTPS, login, role behaviour |

The lecturer requires **≥15 meaningful cases** from TC01–TC20 (manual p. 17). The plan below implements **24**, covering all 20 lecturer cases plus four integrity additions.

## E.2 Test register

| ID | Requirement | Type | Case | Expected | Lecturer case |
|---|---|---|---|---|---|
| T01 | Calendar due | Unit | baseline 2026-11-01 +30 d; 2026-11-30 | Not due, due 2026-12-01 | TC01 |
| T02 | Calendar equality | Unit | same, 2026-12-01 | Due | TC02 |
| T03 | Calendar overdue | Unit | same, 2026-12-02 | Overdue by 1 day | TC03 |
| T04 | Hours boundaries | Unit | 1200+250; 1449/1450/1451 | Not due / Due / Overdue | TC04 |
| T05 | Dual date path | Unit | 2026-12-01, 1400 h | Due, cause CALENDAR | TC05 |
| T06 | Dual hours path | Unit | 2026-11-20, 1450 h | Due, cause HOURS | TC06 |
| T07 | Dual both | Unit | both thresholds | Due, cause BOTH, one status | extra |
| T08 | Missing reading | Unit | hours plan, null current | Incomplete + warning | extra |
| T09 | Missing baseline activation | Feature | activate dual without baseline | 422, not active | TC08 |
| T10 | Positive intervals | Feature/DB | zero/negative days/hours | validation + CHECK reject | extra |
| T11 | Meter decrease | Feature | new reading < previous | 422, no row | TC07 |
| T12 | Work-order happy path | Feature | Open→In progress→Submitted→Closed | states + baseline | TC10 |
| T13 | Baseline uses actual values | Feature | complete 5 Nov 1500, approve 7 Nov | new 5 Dec / 1750 h | TC10 |
| T14 | Repeat close | Feature | close twice | second no-op, one baseline history row | TC10 |
| T15 | Return without reason | Feature | CE returns empty reason | 422, state unchanged | TC13 |
| T16 | Return with reason | Feature | CE returns with reason | In progress, reason + prior submission preserved | TC13 |
| T17 | Viewer direct update | Feature | PATCH asset/WO as viewer | 403, DB unchanged | TC11 |
| T18 | Technician close | Feature | POST close as technician | 403, state/baseline unchanged | TC12 |
| T19 | Invalid transition | Feature | Open→Closed endpoint | 422/403, no baseline change | TC14 |
| T20 | Missing completion details | Feature | submit without actual fields | 422, prior state | TC15 |
| T21 | Insufficient stock | Feature | issue 3 with 2 on hand | 422, stock 2, no movement | TC16 |
| T22 | Duplicate active order | DB + concurrency | second insert / parallel requests | exactly one active row | TC09 |
| T23 | One active per plan (closed excluded) | DB | close then create new | allowed | extra |
| T24 | Persistence across restart | Integration | create record, `compose restart`, reload | record intact | TC17 |
| T25 | Backup restore | Integration | dump → restore separate DB → compare | counts/values match | TC18 |
| T26 | Clean install | Integration | fresh clone + compose + migrate + seed | login + workflow | TC19 |
| T27 | Public HTTPS | Deployment | second device, hostname | TLS, login, role check | TC20 |
| T28 | Dashboard reconciliation | Feature | known dataset vs tiles | counts match; dual once | extra |
| T29 | CSV export parity | Feature | filtered list vs CSV | identical rows/values | extra |
| T30 | Audit completeness | Feature | close/return/issue | actor/action/time/reason recorded | extra |

## E.3 Unit test detail (must be exhaustive)

`tests/Unit/DueStatusCalculatorTest.php`:
- 11 cases in §D.6, each with explicit `today` injected; no `now()` in the class.
- Equality and one-step-before/after for both calendar and hours.
- Dual OR truth table (4 combinations of due/not due) + both overdue + one incomplete.
- Cause identification for CALENDAR / HOURS / BOTH / NONE.
- Decimal edge: `1449.99`, `1450.00`, `1450.01`; string-input safety.
- Timezone: `2026-12-01 00:00 Asia/Kuala_Lumpur` is Due, while `2026-11-30 23:59` is not.
- A plan with `has_hour_meter = false` never reports an incomplete hours check.

## E.4 Feature/permission test detail

- Every write route tested as each role (5 users × ~10 routes) with a data provider.
- Assert HTTP status **and** database state (`assertDatabaseHas` / `assertDatabaseMissing`).
- Direct-request tests use the actual route, not a UI helper, so policies and middleware both execute.
- Validation tests assert field-level errors and unchanged rows.
- CSRF is active in tests except where the framework disables it; do not disable auth middleware.

## E.5 Database constraint tests

Use raw inserts inside `DB::transaction` + rollback:

```php
$this->expectException(QueryException::class);
DB::table('work_orders')->insert([... second active for same plan ...]);
```

Cover: duplicate `assets.code`, duplicate `spare_items.code`, duplicate `work_orders.reference`, `interval_days = 0`, `interval_hours = -1`, active plan missing baseline, `quantity_on_hand < 0`, issue movement with positive delta, returned event without reason (if triggered at DB level).

## E.6 Concurrency testing (practical approach)

PHPUnit cannot reliably interleave two HTTP requests in one process. Use two complementary checks:

1. **Deterministic constraint test (always run):** attempt the second active insert directly; assert unique violation `23505` on `one_active_work_order_per_plan`.
2. **Parallel HTTP script (`scripts/concurrency-check.sh`, run as integration evidence):**
   - Seed a plan with no active order.
   - Fire two `curl` POSTs simultaneously with the CE session cookie.
   - Assert one 302 success and one 4xx/redirect-with-error.
   - Query `SELECT count(*) FROM work_orders WHERE plan_id=? AND state IN (...)` → **1**.
   Record the command and output in the test evidence. **[L]** TC09 explicitly says test the stored result, not just messages.

## E.7 Persistence, backup and restore

**Persistence (T24):** create a synthetic reading, `docker compose restart`, reload; assert row and values. Keep the volume; do not delete it.

**Backup/restore (T25):**
```bash
docker compose exec -T db pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" \
  --format=custom --file=/tmp/cmms_demo.dump
docker compose cp db:/tmp/cmms_demo.dump ./backups/cmms_demo_$(date +%Y%m%d_%H%M).dump
# separate target
docker compose exec -T db createdb -U "$POSTGRES_USER" cmms_restore_check
docker compose exec -T db pg_restore -U "$POSTGRES_USER" -d cmms_restore_check \
  --no-owner --no-acl --exit-on-error /tmp/cmms_demo.dump
```
Verify: table counts, one closed WO + completion fields + plan baseline, spare balances, audit rows; then run a login + representative workflow against the restored DB. **[L]** p. 18.

**Clean install (T26):** `scripts/clean-install.sh` clones to a temp dir, copies `.env.example`, builds, migrates, seeds, and runs a smoke request. Run at least once before submission.

## E.8 Evidence format

A test register (Markdown or CSV) with: test ID, requirement ID, precondition, action, expected, observed, pass/fail, evidence path, commit SHA, date. Keep failed attempts and their corrections. Do not rewrite expected results to match bugs. **[L]** p. 17.

---

# F. User interface

## F.1 Navigation by role

| Menu | CE | Technician | Viewer |
|---|:--:|:--:|:--:|
| Dashboard | ✅ | ✅ | ✅ |
| Assets | ✅ | ✅ | ✅ |
| Maintenance plans | ✅ | ✅ | ✅ |
| Work orders | ✅ | ✅ (assigned) | ✅ |
| Spare parts | ✅ | ✅ (read) | ✅ |
| Reports | ✅ | ✅ | ✅ |
| Audit | ✅ | ❌ | ❌ (or read) **[?]** |
| Administration | ✅ | ❌ | ❌ |

Hidden menu items are cosmetic only; policies remain authoritative. **[L]**

## F.2 Dashboard **[L]**

Tiles:
1. Maintenance plans — **Not due / Due / Overdue** counts, with a caption: *"Overdue is included in Due"* (or the chosen definition) and *"as at 2026-12-02 10:00 +08:00"*.
2. Active work orders — Open / In progress / Submitted.
3. Closed this month.
4. Spares below reorder level.
5. Recent activity (audit excerpt, CE only).

Below: "Due soon / Overdue" table (plan, asset, subsystem, trigger, due date or hours threshold, current reading, status, cause). Dual plans appear once. Empty/partial-data rows show an "hours reading missing" warning.

## F.3 Vessel and asset registry **[L]**

- `/assets`: searchable/filterable table — code, name, subsystem, criticality, status, current hours, active plans, active WO.
- `/assets/{asset}`: identity panel; meter panel (current + history + add form for CE/Tech); plans list with due badges; work-order history; spare usage.
- Create/edit (CE): all fields; hour-meter toggle hides/disables meter fields.
- Withdraw: status select, never delete.

## F.4 Maintenance plans **[L]**

- `/plans`: table with trigger, interval(s), baseline(s), computed status, cause, active flag.
- `/plans/{plan}`: formula card showing inputs and computed due date/threshold, activation state, related work orders, baseline history.
- Create/edit (CE): trigger radio reveals the relevant interval/baseline fields; inline help states the OR rule for dual plans.
- Activate: button disabled with explanation until every applicable baseline exists; server re-validates.

## F.5 Meter readings **[L]**

- `/assets/{asset}/readings`: current reading, units, history (time, hours, user, note), add form.
- Validation error on decrease is shown next to the field; the previous reading is visible for context.
- Missing-reading state visible on plan and dashboard.

## F.6 Work orders **[L]**

- `/work-orders`: role-scoped list — reference, plan, asset, state, assignee, created, due context.
- `/work-orders/{wo}`:
  - Header: reference, state badge, asset, plan, assignee.
  - Timeline: created / started / submitted / returned (reason) / closed, with actor and timestamps.
  - Action panel by state + role:
    - Open: **Start** (CE or assigned Tech).
    - In progress: completion form + **Submit** (CE or assigned Tech).
    - Submitted: **Return** (reason required) and **Close** (CE only), both confirmation-guarded.
    - Closed: read-only + baseline effect summary.
  - Spares panel: issue form (CE or assigned Tech), ledger.
- Invalid/duplicate actions show flash errors and leave state unchanged.

## F.7 Spare parts **[L]**

- `/spares`: code, description, unit, on hand, reorder level, status (OK / Below reorder).
- `/spares/{spare}`: detail + movement ledger (date, type, ±qty, work order, actor, reason, balance after).
- CE edit: description/unit/reorder only; quantity changed only via movements.
- Issue form is on the work-order page (contextual), not the spare page, to force traceability to a job.

## F.8 Audit history **[L]**

- `/audit`: filter by actor, action, entity type, date; columns actor/action/entity/time/reason.
- Read-only, paginated, no edit/delete controls.
- CSV export for CE.

## F.9 Reports and CSV exports **[L]**

| Report | Filters | Export |
|---|---|---|
| Due & overdue | subsystem, criticality, trigger, status | `/exports/due.csv` |
| Work orders | state, assignee, asset, date | `/exports/work-orders.csv` |
| Stock | below-reorder only, subsystem via asset usage **[R]** | `/exports/stock.csv` |
| Audit | actor, action, date | `/exports/audit.csv` |

Rules: exports honour the same filters; headers match on-screen labels; UTF-8; no formulas injected (prefix cells starting with `= + - @` with `'`). **[R]** security detail.

## F.10 UX/accessibility **[L]/[R]**

- Mobile-first responsive tables (horizontal scroll wrapper), readable at 360 px.
- Visible labels, error text linked via `aria-describedby`, focus rings.
- Status never conveyed by colour alone — text + icon.
- Confirmation for destructive/irreversible actions (close, return).
- Flash messages distinguish success/validation/authorisation.
- 403 page explains the missing permission without leaking record details.

---

# G. Deployment

## G.1 Prerequisites and current environment warning

This workstation currently has **no `php`, `composer`, `node`, `psql`, and no Docker Compose plugin** (`docker compose` is unavailable; only the Docker CLI is present). Before Phase 1:

- **Option A (recommended):** install Docker Engine + Compose plugin, then do all PHP/Node work inside containers. This satisfies the Docker requirement and avoids host toolchain drift.
- **Option B:** install PHP 8.4, Composer, Node 22, and the `pgsql` extension locally, and still provide Compose for the assessed deployment.

This is recorded as risk **R-01**. **[R]**

## G.2 Dockerfile (multi-stage, concept)

1. `composer:2` stage — `composer install --no-dev --optimize-autoloader`.
2. `node:22` stage — `npm ci && npm run build`.
3. `php:8.4-apache` runtime — install `pdo_pgsql`, `pgsql`, `bcmath`, `intl`, `zip`; enable `mod_rewrite`; set `DocumentRoot /var/www/html/public`; copy app, vendor and built assets; `chown storage bootstrap/cache`.
4. Entrypoint: wait for DB readiness (bounded retries), run `php artisan migrate --force` **only when `RUN_MIGRATIONS=true`** (documented), then `apache2-foreground`. **[R]**

Do not bake `.env` or secrets into any layer. **[L]**

## G.3 Environment configuration (`.env.example`)

```
APP_NAME="Shipyard CMMS"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8080
APP_TIMEZONE=Asia/Kuala_Lumpur

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=cmms
DB_USERNAME=cmms
DB_PASSWORD=change-me

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=false

CACHE_STORE=file
QUEUE_CONNECTION=sync

CMMS_TEST_DATE=2026-12-02
CMMS_STUDENT_ID=S79244
```

Production overrides: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://s79244-cmms.<domain>`, `SESSION_SECURE_COOKIE=true`, real DB password from host secret, `LOG_LEVEL=warning`.

Never commit `.env`; commit only `.env.example`. **[L]**

## G.4 Docker Compose (production/local)

```yaml
services:
  app:
    build: .
    restart: unless-stopped
    env_file: .env
    ports: ["127.0.0.1:8080:80"]
    depends_on:
      db: { condition: service_healthy }
    volumes:
      - storage:/var/www/html/storage/app
  db:
    image: postgres:18-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: ${DB_DATABASE}
      POSTGRES_USER: ${DB_USERNAME}
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    volumes:
      - pgdata:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME} -d ${DB_DATABASE}"]
      interval: 5s
      timeout: 3s
      retries: 12
volumes:
  pgdata:
  storage:
```

`cloudflared` may be added as a third service using `TUNNEL_TOKEN` from the host environment, or run as a host service. **[R]**

## G.5 Cloudflare Tunnel and HTTPS hostname

1. Verify ownership/control of the domain or lecturer-provided subdomain. **[L]**
2. Create a named tunnel `cmms-s79244`.
3. Route **`s79244-cmms.<approved-domain>`** → `http://app:80` (or `http://localhost:8080` if the connector runs on the host).
4. Run the connector with a scoped token from the host secret store.
5. Verify from a second device/network: HTTPS certificate valid, login works, a permitted action succeeds, a forbidden action is denied. **[L]** TC20.
6. Record URL, release tag, commit SHA, deployment date in the deployment record.

**Hostname rules:** lowercase; contains **`s79244`**; never the short numeric form alone. Suggested candidates:

- `s79244-cmms.it.com` (if registrable and within budget)
- `s79244-cmms.<lecturer-domain>` (preferred if provided)
- `s79244-cmms.<institution-subdomain>`

Do not claim availability until DNS confirms it. **[L]** p. 15.

## G.6 Backup and restoration

**Backup (host, outside `pgdata`):**
```bash
mkdir -p backups
docker compose exec -T db pg_dump -U "$DB_USERNAME" -d "$DB_DATABASE" \
  --format=custom > "backups/cmms_$(date +%Y%m%d_%H%M).dump"
sha256sum "backups/cmms_$(date +%Y%m%d_%H%M).dump"
```
Record: database version, client version, timestamp, file hash, location.

**Restore verification:**
```bash
docker compose exec -T db createdb -U "$DB_USERNAME" cmms_restore_check
docker compose exec -T db pg_restore -U "$DB_USERNAME" -d cmms_restore_check \
  --no-owner --no-acl --exit-on-error /tmp/cmms_check.dump
```
Then compare counts and selected values with the source, and run a login + workflow against the restored database. Never restore over the marking database. **[L]** p. 16.

**Retention:** keep at least one pre-deployment and one post-seed backup; store outside the DB volume; do not commit dumps to Git. **[R]**

## G.7 Release and clean install

- Tag the release `v1.0.0-s79244` (or the brief's required format) and record the commit SHA. **[L]**
- `README.md` sections: purpose, stack/versions, prerequisites, local start, `.env`, key generation, migrations, seed, test accounts (private channel reference), test commands, backup/restore, deployment, known limitations.
- `scripts/clean-install.sh` proves TC19.
- The hosted release and the submitted source must point to the same commit. **[L]** p. 19.

---

# H. AI-assisted development evidence

> These are **process requirements**, separate from any optional AI feature inside the application. **[L]** Manual pp. 10–12; rubric C4.

## H.1 One reusable skill

**Name:** `cmms-schedule-review`
**Canonical content location:** `ai/skills/cmms-schedule-review/SKILL.md`, copied/symlinked to the harness's documented discovery path (OpenCode, Codex and Claude Code use different paths — confirm at install time; do not assume).

**Procedure:**
1. Read the approved date/hours rules from `docs/requirements-analysis.md` §7.
2. Check calendar, hours and dual OR cases.
3. Check equality, one step before/after, missing data and completion baselines.
4. Check the cause/trigger identification.
5. Report expected vs observed.
6. Propose a focused fix and a test.
7. **Wait for student review before changing scope.**

**Evidence:** a real invocation on a real change (e.g. the dual-trigger implementation), the diff reviewed, and the test result. A screenshot of the file alone is not sufficient. **[L]**

## H.2 Two bounded AI agent roles

| Role | Task | Allowed inputs/tools | Forbidden | Output | Review point |
|---|---|---|---|---|---|
| **Builder** | Implement one selected feature + its tests | Project files in the feature area, test runner, local DB | Deploy, change accounts, secrets, unrelated files | Proposed diff, affected files, test evidence | Student reviews diff before commit |
| **Reviewer** | Inspect the changed feature against requirement/test | Read-only project + test results | Edit files, run destructive DB commands | Findings, boundary cases, verification advice | Student accepts/rejects each finding |

Run sequentially. If run in parallel, avoid overlapping files. **[L]** p. 11.
Evidence: two role cards from the template, with actual inputs/outputs and the student's verification.

## H.3 One read-only MCP

**Primary:** Cloudflare documentation MCP (`https://docs.mcp.cloudflare.com/mcp`) — documentation tools only, no account-changing API. This supports the Tunnel/deployment work. **[L]** p. 12.

Steps:
1. Inspect the official server description and confirm the documentation endpoint.
2. Add it via the harness's MCP configuration.
3. Reload and list tools; verify no deployment/account write tools are exposed.
4. Ask one real question (e.g. named Tunnel → origin service routing).
5. Read the returned source, verify the claim, record the call + source URL + decision.
6. Redact credentials from screenshots.

**Fallback:** if institutional restriction or tool failure blocks MCP, capture the exact error and request a **course-approved documented fallback**; do not silently substitute. **[L]**

## H.4 Six AI decision records

Use the supplied template. Record **real** episodes only; do not manufacture mistakes. Planned coverage (fill with actual events as they occur):

| Record | Planned subject | Evidence link |
|---|---|---|
| AI-01 | Convert the lecturer manual into testable requirements (C1) | requirement records, traceability table |
| AI-02 | Review schema/partial unique index for one-active-order | migration, constraint test T22 |
| AI-03 | Review dual OR logic and equality boundary (only if a real issue is found) | calculator diff, T05–T07 |
| AI-04 | Server-side authorisation review (viewer/technician direct requests) | policy diff, T17–T18 |
| AI-05 | Docker/Cloudflare deployment diagnosis with redacted logs | compose diff, TC20 evidence |
| AI-06 | Test-gap or limitation review (e.g. meter rollover) | test diff, documented limitation |

Each record: date, tool/model/version/access route (or "not exposed by the provider"), purpose + requirement ID, prompt excerpt, output summary, accepted/modified/rejected + reason, student's technical explanation, files/commit, validation test + result, skill/agent role + permission boundary, limitations/source checks/cost.

## H.5 Verification and redaction

- Review every AI-proposed diff before commit; run the affected test.
- Never paste API keys, passwords, personal data or real vessel data into prompts or evidence.
- Cite official sources even when AI suggested them.
- Keep evidence in `docs/evidence/ai/` with commit references.
- The application must remain explainable without the AI service. **[L]**

---

# I. Development roadmap

Indicative effort ~64 hours (manual suggests ~60). Each phase ends with a runnable, testable state; no phase depends on unfinished later work.

| Phase | Scope | Key outputs | Exit criteria | Est. |
|---|---|---|---|---|
| **P0 — Confirm & prepare** | Resolve §J decisions; install Docker Compose; confirm domain/host; scaffold repo hygiene | decision record, `.gitignore`, `.env.example`, README skeleton | Toolchain runs; open questions logged | 3 h |
| **P1 — Skeleton + auth + RBAC** | Laravel install (container), layouts, login, users table, roles, `EnsureRole` | working login for 4 seeded roles; 403 on wrong role | Login/logout; role gate test | 6 h |
| **P2 — Schema + seed + constraints** | Enums, 11 domain tables, indexes, partial unique index, seeders | migrations + seed + ER diagram | Fresh migrate/seed; constraint smoke tests; 10/8/6/6 counts | 8 h |
| **P3 — Assets + plans CRUD** | Asset register, plan CRUD, activation validation | asset/plan screens | Create/edit/activate; missing-baseline 422 | 6 h |
| **P4 — Due engine + unit tests** | `DueStatusCalculator`, DTOs, plan status display | pure service + T01–T08 | All boundary tests green | 7 h |
| **P5 — Meter readings** | Reading service, validation, history, asset lock | readings UI + T11 | Decrease rejected; current hours feeds engine | 4 h |
| **P6 — Work-order lifecycle** | Create/start/submit/return/close, events, idempotency, duplicate prevention | WO UI + T12–T20, T22–T23 | Full happy path + permission/transition tests green | 9 h |
| **P7 — Spares** | Item master, issue/adjust, ledger, stock race safety | spares UI + T21 | Successful issue + shortage demo; no negative balance | 4 h |
| **P8 — Dashboard, reports, exports** | Aggregation service, reports, CSV | dashboard + 4 reports + 4 exports | T28–T29 reconcile | 5 h |
| **P9 — Audit & hardening** | Audit wiring everywhere, read-only role, secret review, validation sweep | audit viewer + T30 | Every mutation audited; no secrets in repo | 4 h |
| **P10 — Full verification** | Persistence, backup/restore, clean install, concurrency script, test register | evidence pack | T24–T26 pass; register complete | 5 h |
| **P11 — Deploy** | Docker image, host, tunnel, HTTPS, second-device check | live `s79244` hostname | T27 pass; release tag matches source | 4 h |
| **P12 — AI evidence, docs, demo** | 6 records, skill/roles/MCP evidence, report, rehearsal | D1–D7 package | Docs accurate; 15-min dry run | 5 h |

**Freeze rule:** after P10, only defect fixes. Optional UI polish does not start until P11 is live and stable. **[L]** p. 13.

### Vertical-slice order (first demonstrable path)

`P1 login` → `P2 asset table` → `P3 one plan` → `P4 due status` → `P6 one work order to Closed` → `P7 one spare issue` → `P8 dashboard`. This is the shortest path to a demonstrable CMMS and exposes architecture problems early. **[L]** p. 9.

---

# J. Decisions requiring confirmation before implementation

## J.1 Blocking — student/lecturer must decide

| # | Decision | Default if no answer | Owner |
|---|---|---|---|
| Q1 | Is **Laravel 12** acceptable (vs Django)? | Laravel | Lecturer |
| Q2 | Exact **approved hostname/domain** and who controls DNS | `s79244-cmms.<lecturer-domain>` | Lecturer |
| Q3 | Is the **suggested 10-asset set** acceptable? | Use A01–A10 | Lecturer |
| Q4 | Are **month-based** calendar intervals allowed? | Days only | Lecturer |
| Q5 | Who **creates work orders**? | CE only | Lecturer/student |
| Q6 | What may **Technicians** see — assigned only, or all read? | Assigned WOs; rest read-only | Lecturer |
| Q7 | May **Viewers** see audit? | No (CE only) | Lecturer |
| Q8 | Is **CSV** sufficient for "exports"? | CSV | Lecturer |
| Q9 | Stock deducted **at issue** or **at closure**? | At issue | Student/lecturer |
| Q10 | Is "Due" a superset of "Overdue" on the dashboard? | Separate tiles, Overdue ⊆ Due, plan counted once | Student |
| Q11 | Is the **read-only Cloudflare docs MCP** acceptable, or is a fallback needed? | Cloudflare docs MCP | Lecturer |
| Q12 | **Budget ceiling** and hosting arrangement | Free/local + Cloudflare free tier | Lecturer |
| Q13 | Missing **approved assessment brief**: dates, report format, submission channel, demo schedule | Proceed on manual; re-baseline when provided | Lecturer |
| Q14 | Is the rubric final (currently "draft")? | Treat manual as controlling | Lecturer |

**Resolved:** student ID is **S79244**; hostnames use lowercase **`s79244`**.

## J.2 Non-blocking defaults (can proceed)

| Area | Default |
|---|---|
| UUID PKs | yes |
| Native PG enums | yes |
| `work_order_events` + `plan_baseline_history` | include |
| DB sessions | yes |
| Evidence timestamp display | `Asia/Kuala_Lumpur`, `+08:00` |
| CSV formula-injection guard | yes |
| Login throttling | 5/min |
| Pagination | 20 rows |
| Confirmation dialogs | native, no JS framework |
| Seed test date | 2026-12-02 (env-overridable) |
| Test accounts | `*.test` emails, private passwords |
| Release tag | `v1.0.0-s79244` |

---

# K. Major technical risks and mitigations

| # | Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| R-01 | No PHP/Composer/Node/Compose on workstation | High | Blocks start | Install Compose plugin; container-only toolchain (P0) |
| R-02 | Missing approved brief → wrong format/dates | High | Lost marks | Request immediately; design to manual + rubric; re-check before submission |
| R-03 | Cloudflare domain/host not ready | Medium | C5 loss | Confirm DNS in P0; prepare local fallback evidence; inform lecturer early |
| R-04 | Due-engine boundary bugs | Medium | 25-mark impact | Pure service + 11 unit cases + mutation checks |
| R-05 | Duplicate active orders under concurrency | Medium | C2/C3 | Partial unique index + SQLSTATE handling + parallel script |
| R-06 | Duplicate closure / baseline drift | Medium | C3 | Guarded conditional update + transaction + repeat-close test |
| R-07 | Timezone/equality off-by-one | Medium | C3 | Inject `today`; Asia/Kuala_Lumpur tests around midnight |
| R-08 | Float precision on hours | Low | C3 | `numeric(12,2)` + bcmath |
| R-09 | Stock race / negative balance | Medium | C3 | Row lock + CHECK + non-negative movement test |
| R-10 | Permission bypass | Medium | C6 | Policy + FormRequest + middleware + direct-request tests |
| R-11 | Secret leakage in repo/screenshots | Medium | C6 | `.gitignore`, `.env.example`, redaction review, secret scan before release |
| R-12 | Backup not actually restorable | Medium | C5/C7 | Restore into separate DB + record comparison + workflow test |
| R-13 | Time overrun (~60 h) | High | Scope | Freeze optional features; phase exit criteria; prioritize C3 |
| R-14 | MCP unavailable | Medium | C4 | Capture error; request approved fallback |
| R-15 | AI evidence thin/fabricated | Medium | C4 | Record real episodes as they happen; link commits/tests |
| R-16 | Host goes offline during marking | Low | C5 | Always-on host; local fallback; notify lecturer per outage procedure |

---

# L. Definition of done (submission gate)

- [ ] 1 vessel, 10 assets, ≥3 subsystems, 8 plans (3/3/2), 6 WOs, 6 spares seeded repeatably. **[L]**
- [ ] All four states and all permitted transitions work; invalid transitions rejected server-side. **[L]**
- [ ] Calendar/hours/dual boundaries and equality correct; cause identified. **[L]**
- [ ] One active WO per plan enforced by the partial index; concurrency evidence recorded. **[L]**
- [ ] Closure advances baseline from actual completion values, atomically, idempotently. **[L]**
- [ ] Meter decrease rejected; missing reading shown as incomplete, not zero. **[L]**
- [ ] Spare issue traceable; shortage rejected; no negative balance. **[L]**
- [ ] Roles enforced on every route; direct-request tests prove it. **[L]**
- [ ] Audit trail complete and append-only. **[L]**
- [ ] Dashboard and CSV exports reconcile with stored data. **[L]**
- [ ] ≥15 (target 24–30) tests with expected/observed evidence, including failures corrected. **[L]**
- [ ] Docker Compose runs from clean checkout; data persists. **[L]**
- [ ] HTTPS hostname contains **S79244** and works from a second device. **[L]**
- [ ] Backup restored into a separate DB and verified. **[L]**
- [ ] Skill + 2 agent roles + read-only MCP + 6 honest AI records. **[L]**
- [ ] Report, user/deployment guides, D1–D7 manifest, frozen release tag. **[L]**
- [ ] 15-minute demo rehearsed (6 + 6 + 3). **[L]**

---

## M. Appendix — seed and test data summary

| Item | Value |
|---|---|
| Vessel | Training Vessel Seri Ilmu (fictional) |
| Student ID in docs/host | **S79244** / `s79244` |
| Timezone | Asia/Kuala_Lumpur |
| Fixed demo date | 2026-12-02 (override `CMMS_TEST_DATE`) |
| Calendar plans | P01 30 d, P02 90 d, P03 180 d |
| Hours plans | P04 250 h @1200, P05 500 h @4000, P06 200 h @800 |
| Dual plans | P07 30 d/250 h, P08 60 d/400 h |
| Seed WOs | 1 open, 1 in progress, 2 submitted, 2 closed |
| Seed spares | 6; one below reorder, one short for TC16 |
| Accounts | 1 CE, 2 Tech, 1 Viewer, 1 inactive |
| Release tag | `v1.0.0-s79244` |

---

**End of plan. No application code has been written and no existing analysis document has been modified. Awaiting explicit approval to begin Phase 1.**
