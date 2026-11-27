# Phase 7C — Completion Report

**Project:** Shipyard CMMS — MNA4003 (S79244)
**Phase:** 7C — Inventory integrity, concurrency and overall Phase 7 completion
**Date:** 27 November 2026
**Verdict:** **PASS** — milestone complete; Phase 7 complete
**Checkpoint base:** `003b9796b825ade709beaa42faf13ebaf1a51cb0`

## Integrity audit

- Supported stock-mutation paths use row locking, revalidation, exact decimal arithmetic and atomic
  movement/audit insertion.
- Master-data edits cannot change `quantity_on_hand`.
- Movement records are append-only and cannot be edited or deleted through the application.
- Failed operations leave no partial stock, movement or audit changes.
- Inactive-item restrictions are enforced for receipt, adjustment and issue.
- Reorder-threshold semantics remain `quantity_on_hand < reorder_level`.

Seeded inventory reconciliation note:

- `SP-002` to `SP-005` reconcile to their movement ledgers.
- `SP-001` has a seeded discrepancy: stored balance 10.00, movement ledger sum -2.00, implying an
  unrecorded opening balance of 12.00. The audit record for the seeded issue records a previous
  balance of 12.00.
- `SP-006` has a stored balance of 1.00 with no movement records, representing unrecorded opening
  stock. This is expected under the approved model where opening stock is not a movement.

No demonstration data was modified to correct these records.

## Duplicate-request behaviour

- No server-side idempotency key is implemented.
- Repeated identical valid requests create separate movement records.
- Browser forms include lightweight `onsubmit` duplicate-submission prevention.
- POST/redirect/get behaviour is used for successful submissions.
- Browser-side protection is not claimed as server-side idempotency.

## Work-order state and permission findings

- Receipt and adjustment are Chief Engineer only.
- Issue is permitted for the Chief Engineer and the assigned Technician.
- Unassigned Technicians, Viewers and guests are rejected.
- Stock can be issued only to active work orders (`open`, `in_progress`, `submitted`).
- Closed work orders cannot receive spare issues.
- Inventory operations do not change work-order state, maintenance baselines or meter readings.
- Whether Technicians should issue stock after submission is preserved as an explicitly permitted
  active-state rule; no silent business-rule change was made.

## Actual multi-process concurrency results (cmms_test)

All scenarios used independent PHP processes and isolated test fixtures.

### Scenario A — Competing stock issues
- Initial stock: 5.00; two processes each attempted to issue 4.00.
- Result: one succeeded, one was rejected for insufficient stock.
- Final stock: 1.00; one new issue movement and one audit event.

### Scenario B — Concurrent valid issues
- Initial stock: 10.00; two processes each issued 4.00.
- Result: both succeeded.
- Final stock: 2.00; two new issue movements and two audit events.

### Scenario C — Stock issue versus work-order closure
- Issue-first run: issue committed, then closure committed; issue was preserved and no duplicate
  baseline advancement occurred.
- Closure-first run: closure committed, then issue was rejected because the work order was closed.
- No issue was committed against an already closed work order.

### Scenario D — Concurrent receipt and issue
- Initial stock: 5.00; receipt of 2.00 and issue of 4.00.
- Result: both orders of execution completed successfully.
- Final stock: 3.00 in both runs; two new movement records in each run.

No deadlocks occurred. Lock contention was observed through elapsed transaction times.

## Rollback and database constraint verification

- Failed operations such as insufficient stock, invalid work-order state and invalid quantities leave
  no partial stock, movement or audit changes.
- PostgreSQL constraints and append-only triggers remain in place.
- Movement records cannot be updated or deleted through the application.

## Test results

| Scope | Result |
|---|---|
| `SpareMovementIntegrityTest` | **9 passed / 29 assertions** |
| Full regression suite | **203 passed / 836 assertions** |
| Phase 7B baseline | 194 tests / 807 assertions preserved |
| Laravel Pint | `vendor/bin/pint --test --format agent` → **passed** |

## HTTP and Playwright verification (`http://localhost:8081`)

- Spare-parts registry, stock-status indicators, receipt and adjustment forms, work-order spare
  issue forms and movement history render correctly.
- Role permissions and validation feedback are enforced.
- Duplicate-submission guards are present on movement forms.
- Development `/up` returns HTTP 200.

## Data preservation

- Development domain fingerprint unchanged:
  `22d51f7002a83902426e34750b20e2cd168dc2d2d5169e5a0d428781bf617c06`.
- Deployment domain fingerprint unchanged:
  `83a1fdf1df81b0c28d9da92f33ef38400b47ac585403cebf4adcaadcd0e5208e`.
- Deployment source and status fingerprints unchanged.
- Test writes target `cmms_test` only.

## Modified and untracked files

Modified: `resources/views/spares/show.blade.php`, `resources/views/work-orders/show.blade.php`,
`docs/phase-7-progress.md`, `docs/phase-7c-completion-report.md`, `docs/phase-7-completion-report.md`,
`docs/decision-log.md`.
Added: `tests/Feature/SpareMovementIntegrityTest.php`.

## Remaining limitations

- No server-side idempotency key for duplicate stock movements.
- The seeded `SP-001` movement ledger does not reconcile to its stored balance because no opening
  receipt was recorded.
- Phase 8 is not started.

Phase 7 is complete.
EOF
echo done