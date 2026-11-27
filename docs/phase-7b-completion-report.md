# Phase 7B — Completion Report

**Project:** Shipyard CMMS — MNA4003 (S79244)
**Phase:** 7B — Stock movements and work-order spare parts integration
**Date:** 27 November 2026
**Verdict:** **PASS** — milestone complete; Phase 7C not started
**Checkpoint base:** `065f3611779bae6f8ba02937729e5a99a84b315a`

## Scope delivered

- Stock receipt (Chief Engineer only).
- Stock adjustment/correction with mandatory reason (Chief Engineer only).
- Stock issue to active work orders (Chief Engineer or assigned Technician).
- Spare-parts section on work-order detail pages with movement ledger and issue form.
- Receipt and adjustment forms on spare-item detail pages.
- Transaction-safe row locking and atomic movement/audit creation.
- Decimal quantity handling with `bcadd`/`bcsub`.

Closure and baseline advancement do not consume stock, consistent with the approved design.

## Supported movement types

| Type | Sign | Work order | Roles | Notes |
|---|---|---|---|---|
| Receipt | Positive | None | Chief Engineer | Increases stock |
| Adjustment | Positive or negative | None | Chief Engineer | Reason required; balance cannot go negative |
| Issue | Negative | Required | Chief Engineer, assigned Technician | Work order must be active; insufficient stock rejected |

## Role permissions

| Action | Chief Engineer | Technician (assigned) | Technician (unassigned) | Viewer | Guest |
|---|---|---|---|---|---|
| View inventory and movements | Yes | Yes | Yes | Yes | No |
| Receive stock | Yes | No | No | No | No |
| Adjust stock | Yes | No | No | No | No |
| Issue stock to active work order | Yes | Yes | No | No | No |
| Issue stock to closed work order | No | No | No | No | No |

## Work-order state restrictions

- Stock can be issued only to active work orders (`open`, `in_progress`, `submitted`).
- Closed work orders cannot receive spare issues.
- Issuing stock does not change work-order state, baselines or meter readings.

## Transaction and locking strategy

- Each movement locks the spare item row first, then the work order row for issues.
- Current stock and work-order state are revalidated after locking.
- Balance calculation, stock update, movement insertion and audit insertion occur in one transaction.
- Any validation or database failure rolls back the complete transaction.
- Existing PostgreSQL constraints and append-only triggers are preserved.

## Test results

| Scope | Result |
|---|---|
| `SpareMovementTest` | **15 passed / 60 assertions** |
| Full regression suite | **194 passed / 807 assertions** |
| Phase 7A baseline | 179 tests / 747 assertions preserved |
| Laravel Pint | `vendor/bin/pint --test --format agent` → **passed** |

Coverage: successful receipt, adjustment and issue; correct decimal calculations; stored balances;
movement history; audit records; insufficient stock; exact remaining stock; invalid, zero, negative
and excessive-precision quantities; inactive items; work-order association; invalid work-order
states; unauthorized direct requests; role permissions; transaction rollback; preservation of
work-order lifecycle and baselines.

## HTTP and browser evidence (`http://localhost:8081`)

- Chief Engineer sees receipt and adjustment forms on spare-item details.
- Assigned Technician sees the issue form on an active work order; Viewer does not.
- Closed work orders show no issue form.
- Unauthorized direct requests return 403; guests redirect to login.
- Playwright rendered Chief Engineer, Technician and Viewer inventory and work-order screens.

## Data preservation

- Development domain fingerprint unchanged:
  `22d51f7002a83902426e34750b20e2cd168dc2d2d5169e5a0d428781bf617c06`.
- Deployment domain fingerprint unchanged:
  `83a1fdf1df81b0c28d9da92f33ef38400b47ac585403cebf4adcaadcd0e5208e`.
- Test writes target `cmms_test` only.
- No demonstration stock balances, baselines, meter readings or work-order histories changed.

## Modified and untracked files

Modified: `app/Http/Controllers/SpareItemController.php`, `app/Http/Controllers/WorkOrderController.php`,
`app/Policies/SpareItemPolicy.php`, `app/Policies/WorkOrderPolicy.php`, `resources/views/spares/show.blade.php`,
`resources/views/work-orders/show.blade.php`, `routes/web.php`, `docs/phase-7-progress.md`,
`docs/phase-7b-completion-report.md`, `docs/decision-log.md`.
Added: `app/Http/Controllers/WorkOrderSpareController.php`, `app/Http/Requests/IssueSpareRequest.php`,
`app/Http/Requests/ReceiptSpareRequest.php`, `app/Http/Requests/AdjustSpareRequest.php`,
`app/Services/SpareMovementService.php`, `tests/Feature/SpareMovementTest.php`.

## Remaining limitations and Phase 7C readiness

- Adversarial and actual multi-process concurrency verification is reserved for Phase 7C.
- No commit or push has been made; awaiting approval.
EOF
echo done