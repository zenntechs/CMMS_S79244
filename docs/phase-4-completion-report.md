# Phase 4 — Completion Report (Final Closure)

**Project:** Shipyard CMMS — MNA4003 (FE-MNA4003-S12026-2027)
**Student ID:** **S79244**
**Phase:** 4 — Maintenance due calculation engine
**Date:** 25 November 2026
**Verdict:** **PASS**
**Application URL:** http://localhost:8080

**Protected documents (`docs/requirements-analysis.md`, `docs/proposed-system-modules.md`, `docs/implementation-plan.md`) unchanged.**

---

## 1. Implementation summary

Phase 4 adds a deterministic, pure maintenance due-calculation engine and surfaces it on
the existing maintenance-plan list and detail pages.

- `App\Services\DueStatusCalculator` — no DB, no globals; `calculate(PlanSchedule, ?string $currentHours, CarbonImmutable $today)`.
- `App\Enums\DueState` (`not_due`, `due`, `overdue`, `incomplete`), `App\Enums\DueTrigger` (`none`, `calendar`, `hours`, `both`).
- `App\ValueObjects\PlanSchedule` (trigger, intervals, baselines, hour-meter flag) and `DueStatus` (state, cause, per-side states, due date/hours, remaining days/hours, incomplete flag, as-of date).
- `MaintenancePlanController` builds `PlanSchedule` from the plan plus the latest applicable meter reading and passes `DueStatus` to the views.
- `Asset::latestMeterReading` selects `reading_at DESC, id DESC` with a correlated subquery: one eager-load query per page, only the latest row per asset.

## 2. Meter-reading investigation (final audit)

**Finding: confirmed defect.** The N+1 optimisation had used:

```php
$plan->asset->meterReadings->sortByDesc('reading_at')->sortByDesc('id')->first();
```

Laravel collection `sortByDesc()` re-sorts the whole collection each call, and PHP sorting is
stable, so the second call made `id` the effective **primary** key and `reading_at` only a
tie-break. The approved rule (`docs/implementation-plan.md` §C.2, `MeterReadingService`) is
`ORDER BY reading_at DESC, id DESC LIMIT 1`.

**Correction:** added `Asset::latestMeterReading()` (`hasOne` constrained by a correlated
subquery selecting the `reading_at DESC, id DESC` row per asset) and used it identically in
`index()` and `show()`. This preserves N+1 avoidance (verified: exactly one query touching
`meter_readings` for the plan list) and does not hydrate historical readings.

**Regression tests** (`tests/Feature/MaintenancePlanDueStatusTest.php`):

- readings whose largest ID has the **oldest** timestamp → latest timestamp must win;
- equal timestamps → highest ID wins;
- plan list and detail page both show the resulting shared status.

Mutation check: reverting the relation to `id`-only ordering makes the regression test fail.

## 3. Calculation rules (as implemented)

- Calendar: `dueDate = lastCompletionDate + intervalDays`; before → Not due, equal → Due, after → Overdue.
- Hours: `dueHours = lastCompletionHours + intervalHours` via `bcadd`, compared with `bccomp(..., 2)`; below → Not due, equal → Due, above → Overdue.
- Dual (OR): any Overdue → Overdue; else any Due → Due; else any incomplete input → **Not due** with warning (§D.2); else Not due.
- Cause: every side that reached its threshold (Due or Overdue): None / Calendar / Hours / Both.
- Missing baseline or reading is never coerced to zero; single-trigger plans with missing data report `Incomplete`.
- Inactive plans still compute; the UI flags them. Asset service status is displayed; the calculation itself is independent of it (documented limitation).

## 4. Independent verification — all eight seeded plans (reference date 2026-12-02, Asia/Kuala_Lumpur)

Expected values were derived by hand from raw seed data and the documented formulas, not from the calculator. Actual values were read from the calculator output.

| Plan | Trigger | Cal. baseline | Cal. int. | Next due date | Hours baseline | Hours int. | Latest reading | Next due hours | Expected status | Actual status | Expected cause | Actual cause | Result |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| P01 | calendar | 2026-11-01 | 30 d | 2026-12-01 | — | — | 702.50 | — | Overdue | Overdue | calendar | calendar | PASS |
| P02 | calendar | 2026-10-15 | 90 d | 2027-01-13 | — | — | 97.00 | — | Not due | Not due | none | none | PASS |
| P03 | calendar | 2026-11-18 | 180 d | 2027-05-17 | — | — | (none) | — | Not due | Not due | none | none | PASS |
| P04 | hours | — | — | — | 1200.00 | 250.00 | 1265.50 | 1450.00 | Not due | Not due | none | none | PASS |
| P05 | hours | — | — | — | 4100.00 | 500.00 | 4110.25 | 4600.00 | Not due | Not due | none | none | PASS |
| P06 | hours | — | — | — | 800.00 | 200.00 | 902.50 | 1000.00 | Not due | Not due | none | none | PASS |
| P07 | dual | 2026-11-01 | 30 d | 2026-12-01 | 1200.00 | 250.00 | 1272.00 | 1450.00 | Overdue | Overdue | calendar | calendar | PASS |
| P08 | dual | 2026-10-20 | 60 d | 2026-12-19 | 2000.00 | 400.00 | 2145.00 | 2400.00 | Not due | Not due | none | none | PASS |

Worked examples: P01 — 2026-11-01 + 30 d = 2026-12-01; 2026-12-02 > due → Overdue. P04 — 1200.00 + 250.00 = 1450.00; 1265.50 < 1450.00 → Not due. P07 — calendar overdue dominates; hours 1272.00 < 1450.00 so cause is calendar only. P08 — 2026-10-20 + 60 d = 2026-12-19 (future); 2145.00 < 2400.00 → Not due.

## 5. Edge-case coverage matrix

| Case | Test evidence |
|---|---|
| Calendar −1 day / equality / +1 day | `test_calendar_below_equal_above`, `test_midnight_boundary_at_kuala_lumpur` |
| Hours −0.01 / equality / +0.01 | `test_hours_below_equal_above` |
| All nine dual combinations | `test_dual_trigger_covers_all_nine_combinations` (3 dates × 3 readings) |
| OR logic and precedence | same test + `test_dual_both_overdue` |
| Trigger cause | same test, including mixed Due/Overdue → `Both` |
| Missing calendar baseline | `test_missing_calendar_baseline_is_incomplete` |
| Missing hours baseline / reading | `test_missing_hours_reading_is_incomplete` |
| Calendar-only plan without meter | `test_no_hour_meter_never_incomplete_for_hours`, P03 |
| Dual, one input unavailable | `test_dual_one_missing_calendar_incomplete_preserves_hours_due` |
| Dual, one overdue + one unavailable | `test_dual_one_overdue_and_other_incomplete_is_overdue` |
| Dual, both unavailable | `test_dual_both_missing_is_not_due_with_incomplete_warning` |
| Inactive plan | `test_incomplete_and_inactive_indicators_are_shown` |
| Retired/withdrawn asset | `test_plan_on_withdrawn_asset_still_calculates_and_shows_asset_status` |
| Future baseline | `test_future_baseline_is_not_due` |
| Month-end / year-end | `test_month_end_and_year_end_transitions` |
| Leap year | `test_leap_year_transition` |
| Asia/Kuala_Lumpur midnight | `test_midnight_boundary_at_kuala_lumpur`, `test_timezone_equality` |
| Decimal precision | `test_hours_below_equal_above`, `test_remaining_days_and_hours_semantics` |
| Remaining days/hours semantics | `test_remaining_days_and_hours_semantics`, `test_remaining_values_are_null_when_threshold_is_unknown` |
| No baseline mutation | `test_does_not_mutate_inputs`, `test_calculation_does_not_mutate_baselines_or_create_work_orders` |
| No work-order creation | same feature test |

Documented resolution of ambiguity: the protected spec (§D.2) explicitly requires dual plans
with no reached threshold but a missing input to be **Not due with an incomplete warning**;
single-trigger plans remain `Incomplete`. `remainingDays()` is negative when overdue, `0` on
the due date, positive in the future; `remainingHours()` is a 2-decimal string, negative when
overdue.

## 6. Test quality and mutation checks

Mutation checks performed against `DueStatusCalculator` and the reading selector (correct code restored immediately after each):

| Mutation | Tests failing |
|---|---|
| Overdue detection disabled | 5 |
| Equality handling changed | 2 |
| Missing data coerced to zero | 2 |
| OR changed to AND (both must be overdue) | 4 |
| Reading selector ordered by ID only (original defect) | 1+ (new regression test) |

Full suite green after restoring: **88 passed / 293 assertions** (21 unit + 7 feature tests for Phase 4; 60 pre-existing regression tests still pass).

## 7. Integration verification

- `/plans` shows a Status column; `/plans/{plan}` shows a status panel (status, cause, due date/hours, remaining days/hours, warnings, inactive indicator, asset service status).
- Verified over HTTP tests for all three authorised roles (`chief`, `tech1`, `viewer`): 200 responses with expected statuses.
- Production pages use `CarbonImmutable::today(config('app.timezone'))`; the 2026-12-02 date is used only in tests/verification.
- Exactly one query touches `meter_readings` when rendering the plan list (no N+1).
- No RBAC changes; no schema changes; baselines and work-order counts unchanged after page loads.

## 8. Database safety

- Tests ran only against `cmms_test` (isolation gates in `tests/bootstrap.php` and `tests/TestCase.php` intact).
- Development DB `cmms` row counts and per-table content hashes were identical before and after the suite (users, vessels, assets, plans, readings, work orders, spares, movements, audit events).
- Seed counts preserved: 5 users / 1 vessel / 10 assets / 8 plans / 16 readings / 6 work orders / 6 spare items.
- No destructive commands used. Pint passes.

## 9. Files created/modified

Created: `app/Enums/DueState.php`, `app/Enums/DueTrigger.php`, `app/ValueObjects/PlanSchedule.php`, `app/ValueObjects/DueStatus.php`, `app/Services/DueStatusCalculator.php`, `tests/Unit/DueStatusCalculatorTest.php`, `tests/Feature/MaintenancePlanDueStatusTest.php`.

Modified: `app/Models/Asset.php`, `app/Http/Controllers/MaintenancePlanController.php`, `resources/views/plans/index.blade.php`, `resources/views/plans/show.blade.php`.

## 10. Remaining limitations

- Meter rollover is not modelled (deferred to Phase 5).
- No work-order generation, dashboard aggregation or reporting (later phases).
- Due calculation ignores asset service status by design; the status is displayed alongside it and inactive plans are flagged.

## 11. Exit criteria

| Criterion | Status |
|---|---|
| Calendar / hours / dual calculation with OR logic | PASS |
| Trigger-cause reporting | PASS |
| Exact decimal comparison (`bcmath`) | PASS |
| Missing-data handling without zero substitution | PASS |
| Latest meter reading selection (timestamp + tie-break) | PASS |
| Edge, boundary, timezone, leap-year coverage | PASS |
| Independent verification of 8 seeded plans | PASS |
| No baseline mutation / no work-order creation | PASS |
| No N+1 regression | PASS |
| Regression suite (60 pre-existing + 28 Phase 4) | PASS |
| Database isolation and safety | PASS |
| Pint | PASS |

**Phase 4 verdict: PASS.**
