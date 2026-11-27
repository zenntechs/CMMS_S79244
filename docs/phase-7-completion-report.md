# Phase 7 — Completion Report (Overall)

**Project:** Shipyard CMMS — MNA4003 (S79244)
**Phase:** 7 — Spare parts and inventory
**Date:** 27 November 2026
**Verdict:** **PASS** — Phase 7 complete (7A, 7B and 7C)
**Final checkpoint:** `003b9796b825ade709beaa42faf13ebaf1a51cb0` (Phase 7B commit; Phase 7C changes pending commit)

## Milestones

| Milestone | Status |
|---|---|
| 7A — Spare-parts registry and inventory visibility | Complete |
| 7B — Stock movements and work-order spare parts integration | Complete |
| 7C — Inventory integrity, concurrency and overall completion | Complete |

## Requirement coverage

- Spare-item master data with unique code, description, unit, stock and reorder level.
- Inventory listing, details, search, filtering, pagination and low-stock indicators.
- Stock receipt, adjustment and issue.
- Traceable movements linked to work orders where applicable.
- Insufficient-stock rejection and non-negative balances.
- Movement history and audit records.
- Server-side RBAC and validation.

## Supported stock movements

| Type | Sign | Work order | Roles |
|---|---|---|---|
| Receipt | Positive | None | Chief Engineer |
| Adjustment | Positive or negative | None | Chief Engineer (reason required) |
| Issue | Negative | Required | Chief Engineer, assigned Technician |

## Role permission matrix

| Action | Chief Engineer | Technician (assigned) | Technician (unassigned) | Viewer | Guest |
|---|---|---|---|---|---|
| View inventory and movements | Yes | Yes | Yes | Yes | No |
| Create/edit spare item | Yes | No | No | No | No |
| Receive stock | Yes | No | No | No | No |
| Adjust stock | Yes | No | No | No | No |
| Issue stock to active work order | Yes | Yes | No | No | No |
| Issue stock to closed work order | No | No | No | No | No |

## Work-order state restrictions
- Stock can be issued only to active work orders (`open`, `in_progress`, `submitted`).
- Closed work orders cannot receive spare issues.
- Inventory operations do not change work-order state, maintenance baselines or meter readings.

## Inventory semantics
- `quantity_on_hand` is the stored balance.
- `Below reorder` is reported only when `quantity_on_hand < reorder_level`.
- Equality with the reorder level is `OK`.
- Zero stock with a positive reorder level is `Below reorder`.
- Zero stock with a zero reorder level is `OK`.
- New master-data records start at `0.00`.
- Master-data edits cannot modify stock balances.
- Decimal quantities use exact two-decimal arithmetic.

## Duplicate-request behaviour
- No server-side idempotency key is implemented.
- Repeated identical valid requests create separate movement records.
- Browser forms include lightweight duplicate-submission prevention.
- POST/redirect/get behaviour is used for successful submissions.

## Transaction and locking strategy
- Movements lock the spare item row first; issues also lock the work order row.
- Stock and work-order state are revalidated after locking.
- Balance update, movement insertion and audit insertion are atomic.
- Failed operations roll back completely.
- Existing PostgreSQL constraints and append-only triggers remain in place.

## Actual concurrency evidence
- Competing issues: one succeeded, one was rejected; final stock 1.00.
- Concurrent valid issues: both succeeded; final stock 2.00.
- Issue versus closure: issue-first preserved the issue and allowed closure; closure-first rejected
  the issue.
- Concurrent receipt and issue: both orders completed successfully; final stock 3.00.
- No deadlocks occurred.

## Database constraint verification
- Negative balances are prevented.
- Invalid quantities and excessive precision are rejected.
- Movement records are append-only and cannot be edited or deleted.
- Failed operations leave no partial stock, movement or audit records.

## Test results
| Scope | Result |
|---|---|
| Full regression suite | **203 passed / 836 assertions** |
| Phase 7B baseline | 194 tests / 807 assertions |
| Phase 7C additions | 9 tests / 29 assertions |
| Laravel Pint | `vendor/bin/pint --test --format agent` → **passed** |

## HTTP and browser verification
Verified over real HTTP and Playwright against `http://localhost:8081` for Chief Engineer, Technician
and Viewer. Movement forms include duplicate-submission protection. Development `/up` returns 200.

## Data preservation
- Development and deployment domain fingerprints unchanged.
- Deployment source and status fingerprints unchanged.
- Test writes target `cmms_test` only.

## Known seeded data note
`SP-001` has a stored balance of 10.00 and one issue movement of -2.00, implying an unrecorded
opening balance of 12.00. `SP-006` has a stored balance of 1.00 with no movement records,
representing unrecorded opening stock. No demonstration data was modified.

## Remaining limitations
- No server-side idempotency key for duplicate stock movements.
- No stock return movement operation has been implemented.
- Phase 8 is not started.
EOF
echo done