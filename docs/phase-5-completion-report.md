# Phase 5 — Completion Report

**Project:** Shipyard CMMS — MNA4003 (FE-MNA4003-S12026-2027)
**Student ID:** **S79244**
**Phase:** 5 — Meter reading management
**Date:** 26 November 2026
**Verdict:** **PASS**
**Application URL:** http://localhost:8080

**Protected documents unchanged. No commit or push performed.**

---

## 1. Implementation summary

Phase 5 adds the running-hour meter-reading workflow for hour-metered assets:

- `App\Services\MeterReadingService` — transactional record with asset-row locking,
  monotonicity checks, timestamp validation and an audit event.
- `App\Http\Requests\StoreMeterReadingRequest` — field and business validation.
- `App\Http\Controllers\MeterReadingController` — readings history (`index`) and submission
  (`store`).
- `AssetPolicy::recordReading` and Blade `@can` gates.
- Asset page meter panel (current hours, last accepted reading, five recent readings, form)
  and a paginated readings history page.
- Routes `GET|POST /assets/{asset}/readings`.
- No schema changes; the Phase 2 monotonicity trigger is retained unchanged.

## 2. Routes and permission matrix

| Route | Method | Middleware / checks | Chief Engineer | Technician | Viewer | Guest |
|---|---|---|---|---|---|---|
| `/assets/{asset}/readings` | GET | `auth`, `active` | 200 | 200 | 200 | redirect to login |
| `/assets/{asset}/readings` | POST | `auth`, `active`, `role:chief_engineer,technician`, `StoreMeterReadingRequest::authorize` (`recordReading` policy) | 302 success | 302 success | 403 | redirect to login |

The form is rendered only with `@can('recordReading', $asset)`, but authorisation is enforced
server-side: route middleware, policy and FormRequest all deny unauthorised direct requests.

## 3. Validation rules

| Field | Rule |
|---|---|
| `reading_hours` | required, numeric, `min:0`, `max:9999999999.99` (fits `numeric(12,2)`), `decimal:0,2` |
| `reading_at` | required, valid date, not in the future (`before_or_equal:now`; re-checked in the service against the UTC instant) |
| `note` | optional string, max 1000 characters |
| Asset | must have `has_hour_meter = true` (FormRequest + service) |
| Monotonicity | must be ≥ the highest accepted reading for the asset (equal accepted) |
| Backdating | a backdated reading may only repeat the current maximum; a higher backdated value is rejected |
| Same instant | a different value at an already-used instant is rejected; an exact duplicate is accepted |
| Timestamp | parsed as `Asia/Kuala_Lumpur`, stored as UTC |

## 4. Timestamp and ordering semantics

- **Storage:** `reading_at` is `timestamptz`; the service normalises the input instant to UTC
  (`$at->utc()`), so equivalent instants compare correctly regardless of representation.
- **Input:** the `datetime-local` value is parsed in `Asia/Kuala_Lumpur` by the controller.
- **Display:** `MeterReading::localReadingAt()` converts the stored instant back to
  `Asia/Kuala_Lumpur` for the asset panel and history table.
- **Ordering:** the approved latest-reading rule is preserved — `reading_at DESC, id DESC`,
  implemented once in `Asset::latestMeterReading` (correlated subquery) and reused by the
  Phase 4 calculator, the readings page and the asset panel.
- **Equal timestamps:** `HasUuids` generates UUIDv7, which is ordered only to the millisecond,
  so `id DESC` cannot deterministically rank two different values inserted in the same
  millisecond. Conflicting values at the same instant are therefore rejected; exact duplicates
  are accepted and cannot change the selected value. This preserves chronological monotonicity
  and the approved deterministic ordering.
- **Verified by:** `test_timestamps_round_trip_as_utc_instants_and_display_in_kuala_lumpur`,
  `test_equivalent_instants_are_compared_correctly_across_timezones`,
  `test_midnight_and_now_boundaries_in_kuala_lumpur`,
  `test_equal_timestamps_accept_duplicates_but_reject_conflicting_values`.

**Historical seed-timestamp anomaly (corrected):** the Phase 2 seeder wrote `2026-11-25 09:30:00`
without an offset under a UTC session, so the 16 existing development rows stored 09:30 UTC
(17:30 KL) instead of the intended 09:30 KL. The seeder was corrected to parse KL and convert
to UTC, so fresh databases (including the test database) store the correct instants.

The 16 legacy rows were then corrected with a fresh backup and a narrowly scoped, transactional
command:

```
php artisan cmms:correct-legacy-meter-timestamps --apply
```

- Backup: `/tmp/opencode/p5backup/cmms-pre-phase5-fix.dump` (custom format, verified readable,
  19 TABLE DATA entries); stored outside the repository.
- Only the 16 seed rows (UUID suffixes 201–216) with their exact original timestamps and hours
  were touched; only `reading_at` changed (-8 hours). All other columns and tables unchanged.
- The `meter_readings_no_decrease` trigger is disabled only inside the correction transaction
  and re-enabled before commit; rollback restores the enabled trigger. Verified enabled (`O`).
- The command is idempotent: a second run reports 16 already corrected and changes nothing.
- Unexpected row states cause a safe refusal with no changes.
- Rollback: restore from the backup, or re-run the command (no-op once corrected).

After correction the development readings display at 08:00 KL and 09:30 KL and match a fresh
seed exactly (UTC instant and hours).

## 5. Concurrency and transaction strategy

- `MeterReadingService::record()` runs inside `DB::transaction`.
- The asset row is locked with `lockForUpdate()` before reading the latest/maximum values, so
  concurrent submissions for the same asset serialise; the second transaction sees the first.
- The PostgreSQL trigger `meter_readings_no_decrease` remains as an integrity backstop for any
  path that bypasses the service.
- Validation runs before insertion, so rejected submissions leave no row and no audit event.
- Verified by `test_service_locks_the_asset_row_and_uses_a_transaction` (query log proves the
  `FOR UPDATE` statement) and
  `test_database_trigger_blocks_decreasing_inserts_that_bypass_the_service` (direct insert
  fails at the trigger; row count unchanged).

## 5a. Actual concurrency verification (two independent PostgreSQL connections, cmms_test)

Harness: `/tmp/opencode/p5_concurrency.php` (restricted to `cmms_test`; refuses any other database).
Both scenarios were re-run after the audit-timestamp fix and passed.

**Scenario A — competing submissions, one decreasing:**
- Process 1 (holder) acquired the asset row with `FOR UPDATE`, inserted an accepted reading
  `5000.00` h and committed after a 2.5 s sleep.
- Process 2 (competitor) started ~0.5 s later and called `MeterReadingService::record(..., 4500.00)`
  at a later timestamp. It blocked for **2029 ms** on the asset row lock, then was rejected
  with `reading_hours: must not be lower than the highest accepted reading (5000.00 h)`.
- Result: 1 reading and 1 audit event; 0 rows/audit events for the rejected submission;
  `work_orders` unchanged (6).

**Scenario B — simultaneous valid submissions:**
- Two processes started within 0.2 ms of each other recording `6000.00` (10:00 KL) and
  `7000.00` (10:05 KL).
- Both were accepted; 2 readings and 2 audit events; chronological sequence 6000 then 7000;
  latest reading 7000.00; no rejected audit events.

**Audit timestamp fix:** the service previously serialised `$reading->reading_at` (a naive UTC
wall-clock re-parsed in the application timezone) into the audit payload. It now serialises
`$at->toIso8601String()` and refreshes the returned model from the database. Verified by
`test_returned_model_and_audit_payload_carry_the_true_utc_instant`.

## 6. Phase 4 integration evidence

- New readings immediately drive the existing `DueStatusCalculator` through
  `Asset::latestMeterReading`; no calculator changes were needed.
- `test_new_reading_updates_hours_and_dual_status_but_not_calendar` proves an hours plan and a
  dual plan move from `Incomplete` to `Overdue` (hours cause) while a calendar-only plan stays
  `Not due`; plant baselines are unchanged and no work orders are created.
- No N+1 regression: the plan list still issues exactly one query touching `meter_readings`
  (existing Phase 4 test remains green).
- No baseline mutation and no work-order generation.

## 7. Automated verification

Command: `docker compose exec app php artisan test --compact`

```
Tests:    107 passed (406 assertions)
```

Two final consecutive runs were green (105/390 each). Phase 5 adds 17 feature tests:

| Coverage | Test |
|---|---|
| CE + Technician submission, attribution, audit | `test_chief_and_technician_can_record_a_reading_with_attribution_and_audit` |
| Guest redirect; Viewer 403 | `test_guest_cannot_submit_readings`, `test_viewer_cannot_submit_readings` |
| Non-metered asset rejection | `test_non_metered_asset_is_rejected` |
| Increasing and equal readings | `test_increasing_and_equal_readings_are_accepted` |
| Decreasing reading rejected without side effects | `test_decreasing_reading_is_rejected_without_side_effects` |
| Negative / non-numeric / precision / range / missing timestamp | `test_invalid_numeric_values_are_rejected` |
| Decimal precision | `test_decimal_precision_is_preserved_at_two_places` |
| Future and backdated timestamps | `test_future_timestamps_are_rejected_but_backdated_rules_are_enforced` |
| Equal timestamps determinism | `test_equal_timestamps_accept_duplicates_but_reject_conflicting_values` |
| UTC/KL round trip and display | `test_timestamps_round_trip_as_utc_instants_and_display_in_kuala_lumpur` |
| Equivalent instants across timezones | `test_equivalent_instants_are_compared_correctly_across_timezones` |
| Midnight / now boundaries | `test_midnight_and_now_boundaries_in_kuala_lumpur` |
| Role-based page rendering | `test_readings_page_and_asset_panel_follow_role_permissions` |
| Phase 4 status integration | `test_new_reading_updates_hours_and_dual_status_but_not_calendar` |
| Row locking / transaction | `test_service_locks_the_asset_row_and_uses_a_transaction` |
| DB trigger backstop | `test_database_trigger_blocks_decreasing_inserts_that_bypass_the_service` |
| Audit instant serialization | `test_returned_model_and_audit_payload_carry_the_true_utc_instant` |
| Legacy correction no-op | `LegacyMeterTimestampCorrectionTest::test_fresh_seed_stores_true_utc_instants_and_the_correction_is_a_noop` |

**Mutation checks (correct code restored immediately):**

| Mutation | Result |
|---|---|
| Remove UTC normalisation of the input instant | timezone round-trip test fails |
| Remove the same-instant conflict check | equal-timestamp determinism test fails |

Pint: passed (`app`, `database`, `tests`, `resources`, `routes`).

## 8. HTTP verification (actual requests)

| Action | Result |
|---|---|
| Login as `tech1@cmms.test` | 302 |
| GET `/assets/{A01}/readings` as Technician | 200, form present |
| GET `/assets/{A01}/readings` as Viewer | 200, form absent |
| POST reading as Viewer (valid CSRF) | **403** |
| POST decreasing reading as Technician | 302 with validation error |
| Reading count after rejected submissions | **16 (unchanged)** |
| `GET /up` | 200 |

## 9. Database isolation evidence

| Table | Count:hash before | Count:hash after |
|---|---|---|
| users | 5:495c7ed7dfa4 | 5:495c7ed7dfa4 |
| vessels | 1:a76b0eccc1a5 | 1:a76b0eccc1a5 |
| assets | 10:bcbcfde53cc5 | 10:bcbcfde53cc5 |
| maintenance_plans | 8:62ba7aa4f0ab | 8:62ba7aa4f0ab |
| meter_readings | 16:4e15dafd2765 | 16:4e15dafd2765 |
| work_orders | 6:470f80f88287 | 6:470f80f88287 |
| spare_items | 6:cf9bc34c7d5c | 6:cf9bc34c7d5c |
| spare_movements | 11:5f14be137415 | 11:5f14be137415 |
| audit_events | 5:40522cdcfec7 | 5:40522cdcfec7 |

Tests run only against `cmms_test`; the isolation gates in `tests/bootstrap.php` and
`tests/TestCase.php` are intact. Containers: `app` and `db` healthy. PostgreSQL remains
unpublished.

## 10. Root cause of the intermittent test failures

`UniqueConstraintViolationException` came from `assets_code_unique`: `AssetFactory` generated
random `A##` codes that could collide with seeded `A01…A10` (likewise `P##` with `P01…P08`).
Factory defaults now use `AST-####`, `PLN-####`, `TST-SP-####` and `TST-WO-####`, which cannot
collide with seeded codes. No retries or suppressions were used; the suite has run green
repeatedly since.

## 11. Files created or modified

**Created:** `app/Services/MeterReadingService.php`,
`app/Http/Requests/StoreMeterReadingRequest.php`,
`app/Http/Controllers/MeterReadingController.php`,
`app/Console/Commands/CorrectLegacyMeterTimestamps.php`,
`resources/views/readings/index.blade.php`, `resources/views/readings/_form.blade.php`,
`tests/Feature/MeterReadingTest.php`,
`tests/Feature/LegacyMeterTimestampCorrectionTest.php`,
`docs/phase-5-progress.md`, `docs/phase-5-completion-report.md`.

**Modified:** `app/Policies/AssetPolicy.php`, `app/Models/MeterReading.php`,
`app/Http/Controllers/AssetController.php`, `routes/web.php`,
`resources/views/assets/show.blade.php`, `database/seeders/MeterReadingSeeder.php`,
`database/factories/{Asset,MaintenancePlan,SpareItem,WorkOrder}Factory.php`,
`tests/Feature/MaintenancePlanDueStatusTest.php` (deterministic fixture codes),
`docs/decision-log.md`.

## 12. Technical decisions and deviations

- Server-side authorisation is layered (route middleware + policy + FormRequest).
- Monotonicity floor equals the DB trigger's `max(reading_hours)`, so service and database
  agree; equal readings are accepted.
- Timestamps are true UTC instants; the legacy development seed anomaly is documented with a
  proposed (unapplied) migration.
- Same-instant conflicting values are rejected rather than relying on UUID ordering.
- Withdrawn/standby assets may continue to receive readings; no correction/deletion workflow
  was invented.

## 13. Remaining limitations

- Meter rollover and meter replacement are not modelled (documented Phase 2 limitation).
- No correction or deletion workflow (requires an approved correction process).
- No remaining timestamp anomaly: the 16 legacy development readings were corrected to true UTC
  instants and now match a fresh seed.
- Future-dated readings are rejected conservatively; backdating is limited to repeating the
  current maximum.

## 14. Exit criteria

| Criterion | Status |
|---|---|
| Meter history, latest reading, submission form, attribution, notes | PASS |
| Server-side RBAC (CE + Technician write; all roles read) | PASS |
| Validation (required, numeric, non-negative, 12,2 precision, timestamps) | PASS |
| Monotonicity, equal readings, backdating and future handling | PASS |
| Equal-timestamp determinism | PASS |
| Transaction + row locking; trigger retained; no partial writes | PASS |
| Phase 4 integration (hours/dual update, calendar unaffected) | PASS |
| No baseline mutation / no work orders | PASS |
| Automated suite (107 tests / 406 assertions) and Pint | PASS |
| HTTP verification for all three roles | PASS |
| Database isolation and preserved development data | PASS |

**Phase 5 verdict: PASS.** Awaiting review; no commit or push performed.
