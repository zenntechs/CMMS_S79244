# Phase 6A — Completion Report

**Project:** Shipyard CMMS — MNA4003 (S79244)
**Phase:** 6A — Work-order registry, creation and assignment
**Date:** 27 November 2026
**Verdict:** **PASS** — milestone complete; Phase 6B/6C not started
**Checkpoint base:** `639af0647b3430c3c606f6941f37d3cbcba85600`

## Scope delivered

- Work-order registry with search (reference, plan code, task), filters (state, technician,
  plan, asset) and 20-per-page pagination.
- Detail page with plan/asset, assignment, state and event history.
- Chief Engineer creation and assignment against an active plan and active technician.
- Server-side RBAC: Chief Engineers create; Chief Engineers and Viewers read all work orders;
  Technicians read only their assigned work orders; guests are redirected to login.
- Navigation from the main layout, asset detail and plan detail.
- Transactional creation of the work order, `created` event and `work_order.created` audit
  record, with `open` as the initial state.

## Final verification corrections

1. **Create-page routing defect:** `GET /work-orders/create` matched the `{workOrder}` detail
   route and returned 404. The create route now precedes the detail route.
2. **Reference-allocation race:** concurrent creation could reuse a reference and report the
   wrong conflict. Reference allocation now retries up to three times; the plan duplicate-index
   violation is mapped to a plan validation error.

No other valid functionality was rebuilt.

## Duplicate prevention and concurrency

The PostgreSQL partial unique index `one_active_work_order_per_plan` is unchanged and effective:

```sql
UNIQUE (plan_id) WHERE state IN ('open', 'in_progress', 'submitted')
```

A two-session concurrency check on `cmms_test` produced exactly one committed active work order;
the second insert failed with `duplicate key value violates unique constraint
"one_active_work_order_per_plan"`. The single transaction covering work order, event and audit
means no partial records remain after a conflict.

## Test and formatting results

| Check | Result |
|---|---|
| `WorkOrderTest` | **18 passed / 53 assertions** |
| Full suite | **125 passed / 459 assertions** |
| Phase 5 baseline | 107 tests / 406 assertions retained |
| `vendor/bin/pint --test --format agent` | **passed** (no Git dirty detection) |

Coverage includes authorised creation/assignment, initial state, audit attribution, duplicate
prevention, inactive plan/technician rejection, plan–asset mismatch, guest redirect, direct HTTP
403s, technician visibility, viewer read-only access, search/filtering, pagination, and
create/detail page access.

## HTTP and browser evidence

Verified over real HTTP against `http://localhost:8081` for Chief Engineer, Viewer and Technician
accounts: registry access; create page 200/403/403; detail access 200/200/403; all six seeded
work orders; create-link visibility; asset and plan navigation links. Playwright rendered the
Chief Engineer create form and confirmed the Technician 403 detail page. Credentials were read
from `.env` at runtime and not printed.

## Data and environment preservation

- Development domain fingerprint unchanged:
  `22d51f7002a83902426e34750b20e2cd168dc2d2d5169e5a0d428781bf617c06`.
- Deployment domain fingerprint unchanged:
  `83a1fdf1df81b0c28d9da92f33ef38400b47ac585403cebf4adcaadcd0e5208e`.
- Deployment source `git diff` and status hashes unchanged; no deployment source, configuration,
  container, volume or database changes were made in this milestone.
- Test writes target `cmms_test` only. Only ephemeral session rows changed during login checks.
- Seed data, credentials, protected planning documents and lecturer materials preserved.

## Documentation

- `docs/phase-6-progress.md` updated.
- `docs/phase-5a-environment-report.md` added as a sanitised development-repository record of the
  corrected isolation layout (no secret values, archives or snapshots).
- `docs/decision-log.md` updated (ADR-028, ADR-029).

## Deployment housekeeping (outside this milestone)

Existing, unresolved and deliberately untouched:

1. 12 uncommitted Phase 6A files remain in the deployment working tree.
2. The deployment PostgreSQL container’s initialisation credential differs from the live role
   credential (the live role matches the deployment `.env`).
3. The deployment project carries a stale Compose configuration label from the pre-correction
   swap.

## Git checkpoint

Commit: `feat: complete Phase 6A work-order registry and assignment`

Local and remote hashes are verified after the non-force push. The commit is not deployed into the
deployment directory.

## Remaining

- Phase 6B: lifecycle transitions, completion and approval.
- Phase 6C: baseline advancement, spare usage, concurrency stress and final verification.
