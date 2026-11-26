# Phase 5 — Progress

**Project:** Shipyard CMMS — MNA4003
**Student ID:** S79244
**Phase:** 5 — Meter reading management
**Phase 4 checkpoint:** 8dfe4738e4dd1666e7c60c634e308a30443afb5c
**Status:** COMPLETE — awaiting review (no commit/push)

## Completed

- `MeterReadingService` — transactional record with `lockForUpdate()` on the asset row,
  monotonicity checks, timestamp validation, audit event.
- `StoreMeterReadingRequest` — required/numeric/non-negative/two-decimal hours, timestamp
  validation, non-metered asset rejection.
- `MeterReadingController` (`index`, `store`) and routes
  `GET|POST /assets/{asset}/readings`; POST restricted to Chief Engineer and Technician.
- `AssetPolicy::recordReading` (server-side) plus Blade `@can` for the forms.
- Asset page running-hour panel (current hours, last accepted reading, 5 recent readings,
  form) and a full readings history page with pagination.
- Timestamps stored as true UTC instants; parsed from Asia/Kuala_Lumpur form input and
  displayed back in Asia/Kuala_Lumpur (`MeterReading::localReadingAt()`).
- Equal-timestamp ambiguity resolved: exact duplicates allowed, differing values at the same
  instant rejected (UUIDv7 ids are only millisecond-ordered).
- Test fixtures de-flaked: factory codes now use `AST-`, `PLN-`, `TST-SP-`, `TST-WO-`
  prefixes so they cannot collide with seeded `A01…`, `P01…`, `SP-00x`, `WO-000x` codes.

## Decisions (decision log ADR-019…ADR-023)

1. Transaction + asset row lock; DB trigger retained as backstop.
2. Timestamps stored as UTC instants; KL for input/display. Existing development rows were
   seeded under a UTC session without an offset, so they represent instants 8 hours later
   than intended. The seeder is corrected for fresh databases; a migration for the existing
   development data is proposed in the completion report and **not applied**.
3. Monotonicity floor is the highest accepted reading; equal readings accepted; future-dated
   readings rejected; backdated increases rejected; same-instant conflicting values rejected.
4. Withdrawn/standby assets may still receive readings when they have an hour meter.
5. No correction/deletion workflow (not authorised by the planning documents).

## Milestones

- [x] Service, request validation, policy and routes
- [x] Asset page meter panel and readings history page
- [x] Automated + HTTP tests
- [x] Full regression, Pint, DB isolation evidence
- [x] Completion report

## Test results

- `docker compose exec app php artisan test --compact` → **107 passed / 406 assertions**
  (three consecutive green runs: 107/406, 107/406, 107/406)
- Pint: passed
- Legacy correction: 16 rows corrected via `php artisan cmms:correct-legacy-meter-timestamps --apply`
  (backup `/tmp/opencode/p5backup/cmms-pre-phase5-fix.dump`, trigger re-enabled, idempotent).
- Concurrency (cmms_test, two independent processes): Scenario A blocked ~2.03 s then rejected;
  1 reading + 1 audit event. Scenario B: two accepted, 2 readings + 2 audit events, latest 7000.00.
- Audit timestamp serialization defect fixed; returned model refreshed from the database.
- Pint → passed
- Dev DB `cmms` counts and per-table content hashes identical before/after the suite.

## Exact next action

Await user review; commit/push only with explicit approval.
