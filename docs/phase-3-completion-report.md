# Phase 3 — Completion Report

**Project:** Shipyard CMMS — MNA4003 (FE-MNA4003-S12026-2027)
**Student ID:** **S79244**
**Phase:** 3 — Vessel, asset registry and maintenance plans
**Date:** 24 November 2026
**Verdict:** **PASS**
**Application URL:** http://localhost:8080

**Protected documents preserved unchanged:** `docs/requirements-analysis.md`, `docs/proposed-system-modules.md`, `docs/implementation-plan.md`. No commit or push performed.

---

## 1. Implementation summary

Phase 3 delivers the vessel information page, the asset registry (list with search/filter/pagination, detail, create and edit) and maintenance-plan management (list with filters, detail, create, edit, activate/deactivate) on top of the existing Phase 1/2 platform. All routes are guarded by server-side policies and role middleware; no schema changes were required.

## 2. Implemented functionality

### Vessel
- `/vessel` — vessel code, name, description, asset count and asset list (all roles).

### Assets
- `/assets` — paginated list, search by code/name/subsystem, filters by subsystem and service status.
- `/assets/{asset}` — detail with vessel, criticality, service status, hour-meter flag, maintenance plans.
- `/assets/create`, `/assets/{asset}/edit` — Chief Engineer only; validated form (vessel, code uniqueness, enums).

### Maintenance plans
- `/plans` — paginated list with trigger/active filters.
- `/plans/{plan}` — detail with baselines, activation control (CE only), work-order list.
- `/plans/create`, `/plans/{plan}/edit` — CE only; trigger-specific interval/baseline validation; running-hour plans require a metered asset.
- Activate/deactivate enforces baseline completeness before flipping `is_active`.

## 3. Routes and role-permission matrix

| Route | CE | Technician | Viewer |
|---|---|---|---|
| GET /vessel, /assets, /assets/{asset}, /plans, /plans/{plan} | ✓ | ✓ | ✓ |
| GET/POST /assets/create, edit, update | ✓ | 403 | 403 |
| GET/POST /plans/create, edit, update | ✓ | 403 | 403 |
| POST/DELETE /plans/{plan}/activate | ✓ | 403 | 403 |

Unauthorised direct HTTP requests return 403 and leave the database unchanged.

## 4. User interface

Blade + Tailwind, consistent with Phase 1. Shared layout navigation now links Vessel, Assets and Plans. Forms use `@include` partials with field-level errors and `old()` repopulation. Confirmation-free simple buttons; flash messages for success.

## 5. HTTP verification evidence

- Chief, Technician and Viewer all receive 200 on `/vessel`, `/assets`, `/plans`.
- Technician and Viewer receive 403 on `/assets/create` and `/plans/create`.
- Guests are redirected to `/login`.
- Chief successfully created an asset (Z99) and a plan (ZP1), activated the plan, then the test data was removed; final counts 10 assets / 8 plans.
- Meter-applicability rule verified: a running-hour plan against a non-metered asset returns a validation error and no row is created.

## 6. Test results

Command: `docker compose exec app php artisan test --compact`

```
Tests:    57 passed (166 assertions)
Duration: 9.63s
```

- Phase 1 regression: 21/21
- Phase 2: 29/29
- Phase 3 (Phase3AccessTest): 7 new (access, RBAC 403, asset creation, meter applicability, search)

Pint: passes on all touched files.

## 7. Database safety and isolation

- No schema changes; existing migrations, constraints and seed data reused.
- Dev DB `cmms` and test DB `cmms_test` unchanged in structure; isolation guards intact.
- Test data created during HTTP checks was removed; final counts 10 assets / 8 plans.

## 8. Files created and modified

Created: `VesselController`, `AssetController`, `MaintenancePlanController`, `PlanActivationController`; `Store/UpdateAssetRequest`, `Store/UpdateMaintenancePlanRequest`; `AssetPolicy`, `MaintenancePlanPolicy`; views `vessel/show`, `assets/*`, `plans/*`; `tests/Feature/Phase3AccessTest.php`; `docs/phase-3-progress.md`.

Modified: `routes/web.php`, `layouts/app.blade.php`, `Controller.php` (added `AuthorizesRequests`), `AppServiceProvider.php` (policy registration).

## 9. Technical decisions and deviations

- Route ordering: `/assets/create` and `/plans/create` registered before the `{asset}`/`{plan}` wildcards to avoid shadowing.
- `Controller` base class gained `AuthorizesRequests` (was empty in the skeleton) so `$this->authorize()` works.
- Plan activation checks baselines in the controller and relies on the DB CHECK as the final defence.
- `StoreMaintenancePlanRequest` enforces meter applicability and activation baselines via `withValidator`.

## 10. Remaining limitations

- No due-status calculation (Phase 4).
- No meter-reading workflow (Phase 5).
- No work-order lifecycle (Phase 6).
- No spare-parts management (Phase 7).
- No dashboards/reports/exports (Phase 8+).

## 11. Phase 3 exit criteria and final status

| Criterion | Status |
|---|---|
| Vessel info page | PASS |
| Asset list/detail/create/edit with search/filter/pagination | PASS |
| Maintenance plans CRUD + activation with trigger validation | PASS |
| Server-side RBAC (403 on direct requests) | PASS |
| Phase 1+2 regression (50 tests) | PASS |
| Pint clean | PASS |

**Phase 3 verdict: PASS.** No commit or push performed. Phase 4 requires explicit authorisation. <|end|><|start|>write to=assistant<|channel|>analysis<|message|>Created file successfully: docs/phase-3-completion-report.md