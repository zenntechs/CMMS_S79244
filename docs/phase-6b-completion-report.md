# Phase 6B — Completion Report

**Project:** Shipyard CMMS — MNA4003 (S79244)
**Phase:** 6B — Work-order lifecycle transitions, completion submission and Chief Engineer return
**Date:** 27 November 2026
**Verdict:** **PASS** — milestone complete; Phase 6C not started
**Checkpoint base:** `caefe1423b75cdd8ff1395227d739beb33d0f3fe`

## Scope delivered

- `Open → In Progress` (assigned Technician or Chief Engineer).
- `In Progress → Submitted` (assigned Technician or Chief Engineer), with completion information.
- `Submitted → In Progress` (Chief Engineer return with mandatory reason), preserving the earlier
  submission in the event history.
- Reusable `WorkOrderLifecycleService` with transactions, `lockForUpdate`, state re-validation,
  completion validation, event and audit recording.
- `WorkOrderPolicy::start`, `submit`, `returnToTechnician`.
- `SubmitWorkOrderRequest`, `ReturnWorkOrderRequest`.
- Controller actions `start`, `submit`, `returnToTechnician`.
- Routes `POST /work-orders/{workOrder}/start`, `/submit`, `/return`.
- Extended `work-orders/show.blade.php` with actions, completion information, next action and
  event history.

Closure, baseline advancement and approval remain reserved for Phase 6C.

## Implemented transitions and permissions

| Transition | Allowed roles | State guard | Side effects |
|---|---|---|---|
| Open → In Progress | Assigned Technician, Chief Engineer | state must be `open` | sets `started_at` (preserved on re-start), `started` event + `work_order.started` audit |
| In Progress → Submitted | Assigned Technician, Chief Engineer | state must be `in_progress` | sets `submitted_at`, completion fields, `submitted` event + `work_order.submitted` audit |
| Submitted → In Progress | Chief Engineer only | state must be `submitted`; reason ≥ 5 chars | state only; completion data retained; `returned` event (with prior submission snapshot) + `work_order.returned` audit |

Viewer and guest have no write permissions. Technicians may only act on work orders assigned to
them. No reassignment was added.

## Completion validation

- Actual completion date required, not in the future, and not earlier than the plan baseline
  (calendar and dual plans).
- Actual completion running hours required for hours and dual plans; non-negative and not below the
  plan baseline.
- Completion notes required, minimum 10 characters.
- Evidence reference optional, maximum 255 characters.
- UTC storage with `Asia/Kuala_Lumpur` display; the completion date is parsed in KL and stored as
  a date. No approval timestamp, current meter value or zero is substituted for missing data.
- Submission does not advance maintenance baselines or create meter readings.

## Transactions and concurrency

- Each transition runs in one database transaction; `lockForUpdate` serialises access to the work
  order row, and the current state is re-validated after the lock.
- State change, completion data, event and audit records are written atomically; a failed transition
  leaves no partial records.
- Repeated or stale requests are rejected with a validation error and no state change.
- Closed work orders cannot be started, submitted or returned.
- The partial unique index `one_active_work_order_per_plan` and append-only history constraints
  are preserved.

Actual concurrency evidence (cmms_test, independent processes):

- Two PHP processes called `WorkOrderLifecycleService::start()` on the same open work order:
  one committed (`OK state=in_progress`), the other was rejected with
  `ValidationException: Only an open work order can be started.`. Exactly one `started` event and
  one `work_order.started` audit record remained.
- A row-lock demonstration showed a second session's `SELECT ... FOR UPDATE` blocking for ~1.6 s
  until the first session committed; after acquiring the lock it re-read `in_progress` and its
  conditional `UPDATE ... WHERE state='open'` affected 0 rows.

## Test results

| Scope | Result |
|---|---|
| `WorkOrderLifecycleTest` | **26 passed / 130 assertions** |
| Full regression suite | **151 passed / 589 assertions** |
| Phase 6A baseline | 125 tests / 459 assertions preserved |
| Laravel Pint | `vendor/bin/pint --test --format agent` → **passed** |

Coverage: valid start/submit/return; invalid and skipped transitions; assigned and unassigned
technicians; Chief Engineer permissions; Viewer and guest restrictions; direct unauthorised
requests; mandatory return reason; completion validation for calendar, hours and dual plans;
event and audit attribution; returned work and resubmission; repeated and stale requests; atomic
rollback; concurrent conflicting transitions; closed work-order immutability; unchanged
maintenance baselines and meter readings.

## HTTP and browser evidence (`http://localhost:8081`)

Verified over real HTTP and Playwright for Chief Engineer, Technician and Viewer accounts:

- Chief Engineer sees "Start work" on an open assigned work order and the "Return to technician"
  form with "Return reason" on a submitted work order; completion information displays.
- Technician sees "Start work" on an open assigned work order and "awaiting Chief Engineer review"
  on a submitted work order; no submit or return controls.
- Viewer and unassigned Technician requests return 403; guests are redirected to login.
- Invalid transitions (start on a closed work order, return with a blank reason) redirect back
  with validation errors and leave state unchanged.
- Playwright rendered the Chief Engineer submitted-work review screen and the Technician open-work
  start screen; the mandatory return reason is enforced by the form's `required` attribute and by
  server-side validation.

## Data and environment preservation

- Development domain fingerprint unchanged:
  `22d51f7002a83902426e34750b20e2cd168dc2d2d5169e5a0d428781bf617c06`.
- Deployment domain fingerprint unchanged:
  `83a1fdf1df81b0c28d9da92f33ef38400b47ac585403cebf4adcaadcd0e5208e`.
- Deployment source `git diff` and status hashes unchanged; no deployment source, configuration,
  container, volume or database changes were made.
- Test writes target `cmms_test` only. Only ephemeral session rows changed during login checks.
- Maintenance baselines, meter readings and closed work orders are unchanged.
- Seed data, credentials, protected planning documents and lecturer materials preserved.

## Documentation

- `docs/phase-6-progress.md` updated.
- `docs/decision-log.md` updated (ADR-030 to ADR-032).
- `docs/phase-6b-completion-report.md` added.

## Deployment housekeeping (outside this milestone, unchanged)

1. Uncommitted Phase 6A/6B files remain in the deployment working tree.
2. The deployment PostgreSQL container's initialisation credential differs from the live role
   credential.
3. The deployment project carries a stale Compose configuration label from the pre-correction
   swap.

## Remaining Phase 6C tasks

- Atomic work-order closure (Chief Engineer only).
- Maintenance baseline advancement from actual completion date/hours.
- Baseline history records.
- Due-status recalculation.
- Closure idempotency and concurrency verification.
- No temporary close action; no baseline advancement during start, submission or return.

## Git checkpoint

No commit or push has been made for Phase 6B. The working tree is uncommitted pending review.