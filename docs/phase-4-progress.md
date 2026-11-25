# Phase 4 — Progress

**Project:** Shipyard CMMS — MNA4003
**Student ID:** S79244
**Phase:** 4 — Maintenance due calculation engine
**Latest verified Git checkpoint (Phase 3):** fb6654323990f6b4b87bc59f6b02ac1e19299c29
**Status:** COMPLETE — final audit PASS — see `docs/phase-4-completion-report.md`

## Completed

- `DueState`, `DueTrigger` enums.
- `PlanSchedule`, `DueStatus` value objects (with `remainingDays()`, `remainingHours()`, `hasIncompleteCheck()`).
- `DueStatusCalculator` service:
  - calendar, running-hour and dual triggers;
  - Overdue > Due > (dual: Not due + warning) > Incomplete > Not due precedence;
  - trigger cause from every side that reached its threshold (Due or Overdue);
  - `bcmath` decimal comparisons;
  - `Asia/Kuala_Lumpur` calendar dates;
  - missing data never coerced to zero.
- `Asset::latestMeterReading` relation — `reading_at DESC, id DESC` via correlated subquery; single eager-load query, latest row per asset only.
- Plan list Status column; plan detail calculated-status panel (status, cause, thresholds, remaining days/hours, incomplete/inactive/asset-status indicators).
- Tests: 21 unit tests (`DueStatusCalculatorTest`) + 7 feature tests (`MaintenancePlanDueStatusTest`).
- Full suite: **88 passed / 293 assertions**; Pint clean.

## Corrections made during final audit

1. **Meter-reading ordering bug (real defect).** The N+1 optimisation used
   `$plan->asset->meterReadings->sortByDesc('reading_at')->sortByDesc('id')->first()`.
   The second collection sort re-sorted the whole collection, making `id` the primary
   criterion and `reading_at` only a tie-break. Replaced with the `Asset::latestMeterReading`
   relation (`reading_at DESC` primary, `id DESC` deterministic tie-break) used identically
   by list and detail pages. Regression tests use deliberately conflicting timestamp/ID
   orderings and equal timestamps.
2. **Dual-trigger incomplete semantics.** The implementation returned `Incomplete` for a
   dual plan when neither side reached its threshold but an input was missing. The protected
   specification (§D.2) requires **Not due with an incomplete warning** for dual plans;
   single-trigger plans still return `Incomplete`. Corrected and covered by tests.
3. **Trigger cause for mixed states.** Cause now reports every side that reached a threshold
   (Due or Overdue) per §D.2, e.g. calendar Due + hours Overdue → `Overdue` with cause
   `Both` (previously `Hours`).
4. **Calendar incomplete warning** added to the detail page (previously only the hours
   warning was shown); index empty-state `colspan` fixed (5 → 6).

## Pending

- None. Awaiting review.

## Files created/modified

- `app/Enums/DueState.php`, `app/Enums/DueTrigger.php`
- `app/ValueObjects/PlanSchedule.php`, `app/ValueObjects/DueStatus.php`
- `app/Services/DueStatusCalculator.php`
- `app/Models/Asset.php` (latest-reading relation)
- `app/Http/Controllers/MaintenancePlanController.php`
- `resources/views/plans/index.blade.php`, `resources/views/plans/show.blade.php`
- `tests/Unit/DueStatusCalculatorTest.php`
- `tests/Feature/MaintenancePlanDueStatusTest.php`
- `docs/phase-4-progress.md`, `docs/phase-4-completion-report.md`

## Database changes

- None (no migrations, no schema changes).

## Test results

- `docker compose exec app php artisan test --compact` → **88 passed / 293 assertions**
- Pint → passed
- Dev DB `cmms` row counts + content hashes identical before/after the suite.

## Known issues / limitations

- Meter-rollover not modelled (Phase 5 concern).
- No work-order generation or dashboard integration (later phases).
- Due calculation intentionally ignores asset service status; the detail page displays it
  and inactive plans are flagged, but status is still computed for reference.

## Exact next action

Await user review; commit/push if approved.
