# Phase 6 — Progress

**Project:** Shipyard CMMS — MNA4003
**Student ID:** S79244
**Phase:** 6 — Work orders
**Phase 5 checkpoint:** `639af0647b3430c3c606f6941f37d3cbcba85600`
**Status:** Phase 6 COMPLETE; Phase 7A IN PROGRESS — spare-parts registry and inventory visibility

## Milestones

- 6A — Work-order registry, creation and assignment (complete).
- 6B — Lifecycle transitions, completion submission and return (complete).
- 6C — Closure, approval, atomic baseline advancement and final verification (complete).

## Phase 6B implementation and verification

Authorised transitions only: `Open → In Progress`, `In Progress → Submitted`, and
`Submitted → In Progress` (Chief Engineer return with mandatory reason). Closure and baseline
advancement remain reserved for Phase 6C.

- `WorkOrderLifecycleService` uses database transactions and `lockForUpdate`, re-validating the
  current state after acquiring the lock.
- One event and one audit record per transition, written atomically with the state change.
- Completion fields validated per plan trigger (calendar, running hours, dual); actual completion
  date/hours, notes and optional evidence only; no baseline or meter-reading changes.
- Policy methods `start`, `submit`, `returnToTechnician`; UI actions shown only when role and state
  permit.
- Tests: 26 lifecycle tests / 130 assertions added; full regression 151 tests / 589 assertions.
- Actual concurrency verified with two independent PHP processes and a row-lock demonstration
  against `cmms_test`.
- HTTP and Playwright verification against `http://localhost:8081` passed for all three roles.

## Authoritative environment

| Role | Path |
|---|---|
| Development source (Git) | `/home/zensworth/laravel/dev/CMMS_S79244` |
| Development Compose/tooling | `/home/zensworth/laravel/dev-CMMS_S79244` |
| Development project / URL | `cmms_s79244-dev` / `http://localhost:8081` |
| Deployment source (read-only) | `/home/zensworth/laravel/CMMS_S79244` |
| Deployment project / URL | `cmms_s79244` / `http://localhost:8080` |

## 6A implementation

- `WorkOrderController` (`index`, `show`, `create`, `store`): search by reference/plan code/task,
  state/technician/plan/asset filters, 20-per-page pagination.
- `StoreWorkOrderRequest`: asset/plan/technician validation, plan–asset consistency, active plan,
  active technician.
- `WorkOrderService::create()`: one transaction creates the work order, `created` event and
  `work_order.created` audit record; open state; unique `WO-####` reference.
- `WorkOrderPolicy`: Chief Engineer creates; Chief Engineer and Viewer view all; Technician views
  only assigned work orders.
- Routes: `GET /work-orders`, `GET /work-orders/create`, `POST /work-orders`,
  `GET /work-orders/{workOrder}`. Create/store are behind `role:chief_engineer`.
- Views: `work-orders/index`, `work-orders/show`, `work-orders/create`; navigation entry in the
  main layout; links from asset and plan detail pages.
- All six seeded work orders (`WO-0001`–`WO-0006`) display in the registry.

## Final-verification corrections

1. **Route order:** `GET /work-orders/create` was shadowed by the `{workOrder}` route and
   returned 404. The create route is now registered before the detail route.
2. **Reference collisions:** concurrent creation on different plans could allocate the same
   reference and surface the plan conflict message. The service now retries reference allocation
   up to three times, maps `one_active_work_order_per_plan` to a plan validation error, and maps an
   exhausted reference collision to a clear validation message.

## Integrity and concurrency

- Partial unique index `one_active_work_order_per_plan` remains in place:
  `UNIQUE (plan_id) WHERE state IN ('open', 'in_progress', 'submitted')`.
- Two independent PostgreSQL sessions against `cmms_test` inserted the same plan concurrently:
  the first committed; the second blocked and failed with
  `duplicate key value violates unique constraint "one_active_work_order_per_plan"`.
  Exactly one active row remained.
- Because work order, event and audit inserts share one transaction, a failed insert leaves no
  partial event or audit rows.

## Test results

| Scope | Result |
|---|---|
| `WorkOrderTest` | **18 passed / 53 assertions** |
| Full regression suite | **125 passed / 459 assertions** |
| Baseline preserved | 107 tests / 406 assertions retained |
| Laravel Pint | `vendor/bin/pint --test --format agent` → **passed** |

Tests force `DB_DATABASE=cmms_test` on the development `db:5432`; operational databases are not
used by the suite.

## HTTP and browser evidence (`http://localhost:8081`)

| Role | `/work-orders` | `/work-orders/create` | Unassigned detail |
|---|---|---|---|
| Chief Engineer | 200 | 200 | 200 |
| Viewer | 200 | 403 | 200 |
| Technician | 200 | 403 | 403 |

- Chief Engineer registry shows `WO-0001`–`WO-0006` and the “New work order” link.
- Viewer and Technician registries show no create link.
- Asset detail links to the filtered registry; plan detail links to each work order.
- Playwright rendered the Chief Engineer create form (“New work order”, 8 active plans,
  2 active technicians) and confirmed a Technician receives 403 on an unassigned detail page.

## Data preservation

Pre- and post-verification domain-table fingerprints (excluding ephemeral sessions/cache):

- Deployment: `83a1fdf1df81b0c28d9da92f33ef38400b47ac585403cebf4adcaadcd0e5208e` (unchanged)
- Development: `22d51f7002a83902426e34750b20e2cd168dc2d2d5169e5a0d428781bf617c06` (unchanged)

Deployment source `git diff` and working-tree status hashes are unchanged. Only ephemeral session
rows changed during role login checks.

## Commands

Development:

```bash
cd /home/zensworth/laravel/dev-CMMS_S79244
docker compose -p cmms_s79244-dev up -d
docker compose -p cmms_s79244-dev exec app php artisan test --compact
docker compose -p cmms_s79244-dev exec app vendor/bin/pint --test --format agent
docker compose -p cmms_s79244-dev exec app php artisan route:list --path=work-orders
```

Deployment (operational, read-only for this milestone):

```bash
cd /home/zensworth/laravel/CMMS_S79244
docker compose -p cmms_s79244 up -d
```

Never run `docker compose down -v` against either project.

## Deployment housekeeping (not resolved here)

1. The deployment working tree still has 12 uncommitted Phase 6A files, preserved but not
   promoted.
2. The deployment PostgreSQL container’s `POSTGRES_PASSWORD` still holds its original
   initialisation value; the live role matches the deployment `.env` after the Phase 5A
   correction.
3. The deployment project retains a stale Compose configuration label from the pre-correction
   swap; actual mounts and runtime settings are correct.

## Phase 6 status

All Phase 6 milestones (6A, 6B and 6C) are complete and verified. Phase 7 (spare parts) is
not started.

## Phase 6C implementation and verification

Phase 6C implements Chief Engineer approval and closure of submitted work orders, atomic
maintenance baseline advancement, baseline history, due-status recalculation and idempotency.

- `WorkOrderClosureService::close()` performs the transition inside one transaction with
  `lockForUpdate` on the work order and plan, re-validating state and completion data.
- Baselines advance only from actual completion date/hours (calendar, hours and dual plans);
  non-applicable fields are preserved.
- `PlanBaselineHistory` records previous and new baselines; `WorkOrderEvent` and `AuditEvent`
  records are written atomically.
- `WorkOrderPolicy::close` restricts closure to the Chief Engineer.
- UI: `Approve and close` action with confirmation on submitted work orders; read-only closed
  work orders; baseline history on the plan detail page.
- Due status is recalculated through the existing `DueStatusCalculator`.

### Verification

- `WorkOrderClosureTest`: **15 passed / 101 assertions**.
- Full regression suite: **166 passed / 690 assertions** (baseline 151 / 589 preserved).
- Laravel Pint: `vendor/bin/pint --test --format agent` → **passed**.
- Actual concurrency (cmms_test, two PHP processes): one closed, one rejected; exactly one
  baseline history, closed event and closed audit.
- HTTP and Playwright verification against `http://localhost:8081` passed for all roles.
- Operational domain fingerprints unchanged for both development and deployment databases.

Phase 6 is complete. Phase 7 (spare parts) is not started.
