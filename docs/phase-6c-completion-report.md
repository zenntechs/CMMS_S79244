# Phase 6C — Completion Report

**Project:** Shipyard CMMS — MNA4003 (S79244)
**Phase:** 6C — Atomic closure and maintenance baseline advancement
**Date:** 27 November 2026
**Verdict:** **PASS** — milestone complete; Phase 6 complete
**Checkpoint base:** `14722c3915aa6c94446f92cff7dc02ceeb3ac2bf`

## Scope delivered

- `Submitted → Closed` transition (Chief Engineer only).
- Atomic maintenance baseline advancement for calendar, running-hour and dual-trigger plans.
- `PlanBaselineHistory` records for every closure.
- Due-status recalculation through the existing `DueStatusCalculator`.
- Idempotent and concurrent closure handling.
- UI approval/close action with confirmation and read-only closed work orders.

## Implementation

- `WorkOrderClosureService::close()` runs in one transaction with `lockForUpdate` on the work
  order and plan, re-validating state and completion data after the locks.
- Baselines advance only from actual completion date/hours; non-applicable fields are preserved.
- `WorkOrderPolicy::close` restricts closure to the Chief Engineer.
- Route `POST /work-orders/{workOrder}/close`; controller action `close`.
- `work-orders/show.blade.php` shows `Approve and close` for eligible submitted work orders and
  read-only status for closed work orders.
- `plans/show.blade.php` shows baseline history.

## Test results

| Scope | Result |
|---|---|
| `WorkOrderClosureTest` | **15 passed / 101 assertions** |
| Full regression suite | **166 passed / 690 assertions** |
| Phase 6B baseline | 151 tests / 589 assertions preserved |
| Laravel Pint | `vendor/bin/pint --test --format agent` → **passed** |

Coverage: successful calendar, hours and dual closure; actual completion versus approval timestamp;
baseline history; event and audit attribution; recalculated due status; missing and invalid
completion information; baseline regression prevention; invalid state transitions; Technician,
Viewer and guest authorization; direct unauthorised HTTP requests; repeated closure idempotency;
atomic rollback on failure; actual concurrent closure; subsequent work-order creation; no
unintended meter-reading changes; preservation of unrelated plans and work orders.

## Concurrency evidence (cmms_test)

Two PHP processes called `WorkOrderClosureService::close()` on the same submitted work order:
one committed (`OK state=closed`), the other was rejected with
`ValidationException: Only a submitted work order can be closed.`. Exactly one baseline history
row, one `closed` event and one `work_order.closed` audit record remained.

## HTTP and browser evidence (`http://localhost:8081`)

- Chief Engineer sees `Approve and close` and `Return to technician` on a submitted work order;
  closed work orders display `closed and cannot be modified` with no close action.
- Technician and Viewer do not see the close action; their close requests return 403; guests are
  redirected to login.
- Invalid close attempts on open or closed work orders redirect back with validation errors and
  leave state unchanged.
- Playwright rendered the Chief Engineer submitted-work review screen and the closed work-order
  read-only screen; the confirmation dialog was dismissed without mutating data.

## Data preservation

- Development domain fingerprint unchanged:
  `22d51f7002a83902426e34750b20e2cd168dc2d2d5169e5a0d428781bf617c06`.
- Deployment domain fingerprint unchanged:
  `83a1fdf1df81b0c28d9da92f33ef38400b47ac585403cebf4adcaadcd0e5208e`.
- Maintenance baselines of unrelated plans, meter readings and closed work orders unchanged.
- Test writes target `cmms_test` only.

## Remaining

None for Phase 6. Phase 7 (spare parts) is not started.
EOF
echo done