# Phase 5 — Progress

**Project:** Shipyard CMMS — MNA4003
**Student ID:** S79244
**Phase:** 5 — Meter reading management
**Phase 4 checkpoint:** 8dfe4738e4dd1666e7c60c634e308a30443afb5c
**Status:** IN PROGRESS

## Scope (approved)

- Meter-reading history and latest accepted reading for hour-metered assets.
- Submission form (hours, timestamp, note) with server-side RBAC (CE + Technician).
- Monotonicity rejection with transaction + asset row lock; DB trigger retained.
- Phase 4 integration via the existing `Asset::latestMeterReading` ordering.
- No work-order lifecycle, baseline advancement, spares, dashboards or deployment work.

## Decisions taken (see decision log ADR-019…ADR-022)

1. Monotonicity floor is the highest accepted reading for the asset (matches the existing
   `max(reading_hours)` DB trigger); equal readings are accepted.
2. Future-dated readings are rejected. Backdated readings are accepted only when they do not
   break the chronological sequence, which in practice means `reading_hours` must equal the
   current maximum and the timestamp must be before the latest accepted reading.
   Otherwise they are rejected with a clear message. Rationale: the latest reading drives due
   status, so accepting a backdated higher value would make the chronological sequence
   decrease and contradict the trigger's floor.
3. Withdrawn/standby assets may still receive readings (history/record-keeping); only
   `has_hour_meter` gates submissions. Service status remains visible on the asset page.
4. No correction/deletion workflow is added (not authorised by the planning documents).

## Milestones

- [ ] Service, request validation, policy and routes
- [ ] Asset page meter panel and readings history page
- [ ] Automated + HTTP tests
- [ ] Full regression, Pint, DB isolation evidence
- [ ] Completion report
