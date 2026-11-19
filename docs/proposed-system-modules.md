# Shipyard CMMS — Proposed System Modules

**Companion to:** `docs/requirements-analysis.md`
**Status:** Proposal only — no code written. Awaiting approval.

**Legend**
- **[L]** Lecturer-mandated module/feature.
- **[L-I]** Lecturer-implied (necessary to satisfy a rubric level-4 descriptor).
- **[R]** Architect recommendation — optional.
- Priority: **MVP-1** = needed for a defensible submission; **MVP-2** = needed for a strong (level 3–4) result; **OPT** = optional enhancement, no extra marks by itself.

**Scope guard [L]:** one fictional vessel, 10 assets, 8 plans (3 calendar / 3 hours / 2 dual), 6 seed work orders, 6 spare items, three roles. At least 3 ship systems. Do not expand beyond this until the required workflow, tests and deployment are stable (manual pp. 1, 13).

---

## Module map

```
┌────────────────────────────────────────────────────────────────────┐
│  M08  Dashboard, Reports & Exports          (read layer)           │
├────────────────────────────────────────────────────────────────────┤
│  M07  Audit & History                       (cross-cutting)        │
├──────────────┬───────────────┬───────────────┬─────────────────────┤
│  M05 Work    │  M06 Spare    │  M04 Planning │  M03 Meter          │
│  Orders      │  Parts        │  & Due Engine │  Readings           │
├──────────────┴───────────────┴───────────────┴─────────────────────┤
│  M02  Vessel & Asset Registry                                      │
├────────────────────────────────────────────────────────────────────┤
│  M01  Identity, Access & Security            (foundation)          │
└────────────────────────────────────────────────────────────────────┘

Engineering / delivery modules (not user-facing screens):
  M09  Data Foundation (migrations, seeders, constraints)
  M10  Deployment, Backup & Recovery
  M11  Verification & Testing
  M12  AI-Assisted Delivery Workflow & Evidence
  M13  Documentation & Demonstration
```

---

## M01 — Identity, Access & Security

**Priority:** MVP-1
**Source:** [L] manual pp. 5, 8, 16; rubric C6; templates (accounts, private credentials)

### Purpose
Authenticate the three course roles and enforce authorisation on the **server** for every request, including direct HTTP calls that bypass the interface.

### Features
1. Login / logout with framework password hashing and session protection. **[L]**
2. Three fixed roles: `chief_engineer`, `technician`, `viewer`. **[L]**
3. Route + policy/gate enforcement for every mutating action. **[L]**
4. Server-side input validation with useful field errors. **[L]**
5. Role-aware navigation (cosmetic) **plus** hard server checks (authoritative). **[L]**
6. Denied requests return a clear error and make **no** database change. **[L]**
7. Generic public error messages; detailed errors only in private logs. **[L]**
8. Synthetic demo accounts seeded; passwords never committed or printed in reports. **[L]**
9. Optional: user administration screen for the Chief Engineer. **[R]**

### Related roles
| Role | Interaction |
|---|---|
| Chief Engineer | Full access; may administer users |
| Technician | Assigned work + permitted actions |
| Viewer | Read-only |

### Key acceptance checks
- TC11 Viewer direct update → 403/422, record unchanged.
- TC12 Technician close endpoint → denied, baseline unchanged.
- TC14 Open → Closed direct → rejected.
- Password hash never present in audit/API output.

### Depends on
M09 (users table/seed).

---

## M02 — Vessel & Asset Registry

**Priority:** MVP-1
**Source:** [L] manual pp. 1, 5, 6; rubric C1, C2, C3

### Purpose
Provide the maritime context and the register every other module references: one fictional vessel and ten assets across at least three ship systems.

### Features
1. Vessel record: identifier, name, description. **[L]**
2. Asset CRUD with unique code, name, subsystem, location, criticality, service status. **[L]**
3. Hour-meter flag per asset — only assets with meters require readings. **[L]**
4. Withdrawal from service via status change, never deletion. **[L]**
5. Duplicate code rejected at database level. **[L]**
6. Asset detail page combining current hours, plans, work-order history and spare usage. **[L-I]**
7. Filter by subsystem / criticality / service status. **[R]** (manual p. 20 suggestion)

### Related roles
| Role | Permissions |
|---|---|
| Chief Engineer | Create / edit / withdraw assets |
| Technician | Read assets relevant to assigned work |
| Viewer | Read all assets |

### Seed requirement
10 assets spanning ≥3 systems; the manual's suggested A01–A10 set is recommended (7 subsystems).

### Acceptance checks
- Exactly 10 seeded assets; codes unique.
- Withdrawn asset still appears in history and on closed work orders.
- Asset without a meter never triggers an "hours required" error.

---

## M03 — Meter Readings

**Priority:** MVP-1
**Source:** [L] manual pp. 5, 6, 7; TC07

### Purpose
Maintain an authoritative cumulative running-hour reading per metered asset, which drives hours and dual due calculations.

### Features
1. Record reading: asset, cumulative hours, timestamp, recording user. **[L]**
2. Show current reading and reading history per asset. **[L-I]**
3. Reject a reading lower than the accepted previous reading. **[L]**
4. Treat missing readings as **missing**, never zero; expose "hours check incomplete". **[L]**
5. Store numeric values, not strings; explicit unit "hours". **[L]**
6. Document meter replacement/rollover as a limitation unless implemented. **[L]**
7. Optional explicit correction workflow (supersede, with reason + audit). **[R]**

### Related roles
| Role | Permissions |
|---|---|
| Chief Engineer | Record readings; corrections |
| Technician | Record readings for permitted assets |
| Viewer | Read only |

### Acceptance checks
- TC07 decrease rejected, no row written.
- Latest reading is the one used by the due engine.
- Reading survives restart (TC17).

---

## M04 — Maintenance Planning & Due Engine

**Priority:** MVP-1 — **highest-value module (C3)**
**Source:** [L] manual pp. 1, 5, 6, 7; TC01–TC08; rubric C1, C3

### Purpose
Define recurring maintenance and compute, deterministically, whether each plan is Not due / Due / Overdue and why.

### Features
1. Plan CRUD: asset, task, trigger type, interval(s), baseline(s), source label. **[L]**
2. Trigger types exactly: calendar, hours, dual. **[L]**
3. Positive-interval validation. **[L]**
4. Activation blocked unless every applicable baseline is present; no zero substitution. **[L]**
5. Calendar due: `last_completion_date + interval_days`; equality = Due; later = Overdue. **[L]**
6. Hours due: `last_completion_hours + interval_hours`; equality = Due; later = Overdue. **[L]**
7. Dual: **OR** of both triggers; state which caused it. **[L]**
8. Timezone-correct local date in Asia/Kuala_Lumpur. **[L]**
9. Missing meter on hours/dual → incomplete hours check + warning, preserving known calendar status. **[L]**
10. Plan detail shows formula, inputs, computed due date/threshold and current reading. **[R]**
11. Baseline advances **only** on closure, from actual completion values. **[L]**
12. Optional baseline-change history view. **[R]**

### Related roles
| Role | Permissions |
|---|---|
| Chief Engineer | Create / edit / activate / deactivate plans |
| Technician | Read plans for assigned assets |
| Viewer | Read plans |

### Design note
Implement as a **pure service** (`DueStatusCalculator`) receiving `today`, plan baseline, interval and current hours. No framework or DB calls inside the calculation. This lets unit tests freeze the date and prove TC01–TC06 exactly.

### Acceptance checks
All nine boundary rows in manual p. 7; TC01–TC06, TC08.
Dashboard counts each dual plan once, with a stated Due/Overdue definition.

---

## M05 — Work Order Lifecycle

**Priority:** MVP-1 — **core of C3**
**Source:** [L] manual pp. 1, 5, 7, 8; TC09–TC15; rubric C2, C3, C6

### Purpose
Manage one maintenance task instance from creation to approved closure, preserving history and atomically advancing the plan baseline.

### Features
1. Four states only: Open, In progress, Submitted, Closed. **[L]**
2. Create work order against an active plan; assign a Technician. **[L]**
3. **One active work order per plan**, enforced by a partial unique index; concurrent duplicates handled gracefully. **[L]**
4. Start: Open → In progress; record actor + timestamp; only assigned Technician or authorised CE. **[L]**
5. Submit: In progress → Submitted; validate actual completion date/hours and required evidence. **[L]**
6. Return: Submitted → In progress; CE only; reason required; **prior submission preserved**. **[L]**
7. Close: Submitted → Closed; CE only; validate again; update plan baseline atomically. **[L]**
8. Reject invalid transitions (e.g. Open → Closed). **[L-I]**
9. Idempotent closure — repeat requests have no second effect. **[L]**
10. Actual completion values kept separate from submission/approval timestamps. **[L]**
11. Timeline view of every event (created/started/submitted/returned/closed/corrected). **[L-I]**
12. Optional correction workflow for closed orders with reason + audit. **[R]**

### Related roles
| Role | Permissions |
|---|---|
| Chief Engineer | Create, assign, start (authorised), review, return, close, view all |
| Technician | View assigned, start, record progress, submit |
| Viewer | Read only |

### Acceptance checks
- TC09 exactly one active row persists under concurrency.
- TC10 baseline = 5 Dec / 1750 h; repeat close is a no-op.
- TC13 return without reason rejected; with reason sets In progress and keeps history.
- TC14/TC15 rejected with prior state intact.

### Transaction sketch
```
BEGIN
  UPDATE work_orders SET state='closed', closed_at=now(), approved_by=:ce
   WHERE id=:id AND state='submitted' RETURNING *;     -- 0 rows → abort, no-op
  UPDATE maintenance_plans SET last_completion_date=:actual_date,
         last_completion_hours=:actual_hours,
         last_completion_work_order_id=:id
   WHERE id=:plan_id;
  INSERT INTO plan_baseline_history ...;
  INSERT INTO audit_events ...;
COMMIT
```

---

## M06 — Spare Parts & Inventory

**Priority:** MVP-1
**Source:** [L] manual pp. 1, 5, 8; TC16; rubric C3

### Purpose
Track six spare items and every stock movement, so that issuing parts to a job is traceable and stock can never go negative.

### Features
1. Spare master: unique code, description, unit, quantity, reorder level. **[L]**
2. Issue spare against a work order as a signed movement. **[L]**
3. Reject insufficient stock and negative issue quantities. **[L]**
4. Reject zero/negative results; no negative balance. **[L]**
5. Record corrections as separate traceable movements, never silent edits. **[L]**
6. Balance updated transactionally with the movement (row lock). **[L]**
7. Low-stock / below-reorder indicator and reorder report. **[L-I]**
8. Optional idempotency key to stop duplicate issue on retry. **[R]**

### Related roles
| Role | Permissions |
|---|---|
| Chief Engineer | Manage items; issue/return/receive/adjust; corrections |
| Technician | Issue permitted spares to assigned work |
| Viewer | Read stock and ledger |

### Seed requirement
Six items; include one below reorder level and one deliberately short for the shortage demo.

### Acceptance checks
- TC16: issuing 3 with 2 in stock rejected; stock remains 2; no movement row.
- Ledger sums reconcile to `quantity_on_hand`.
- Demo shows one successful issue and one shortage.

---

## M07 — Audit & History

**Priority:** MVP-1 (cross-cutting)
**Source:** [L] manual pp. 6, 8, 16; rubric C6

### Purpose
Make every consequential action attributable and every historical record reconstructable.

### Features
1. Audit event: actor, action, entity, before/after (or relevant change), reason, timestamp. **[L]**
2. Write audit entries inside the same transaction as the action. **[L-I]**
3. Never store passwords/tokens. **[L]**
4. Immutable history: closed work orders and their evidence retained. **[L]**
5. Submission history preserved on return, with reason. **[L]**
6. Baseline-change history. **[R]**
7. Read-only audit viewer (CE; optionally Viewer). **[L-I]**

### Related roles
| Role | Permissions |
|---|---|
| Chief Engineer | View all audit/history |
| Technician | View own/assigned history |
| Viewer | Read-only where authorised |
| System | Writes audit rows |

---

## M08 — Dashboard, Reports & Exports

**Priority:** MVP-1 (dashboard + CSV); MVP-2 (richer filters)
**Source:** [L] rubric C3 L4; manual p. 18 (reconciliation), p. 20 (filters/export suggestions)

### Purpose
Give each role an accurate operational picture built from the **same** due engine and records — no separate calculation logic.

### Features
1. Dashboard tiles: Not due / Due / Overdue plans, active WOs by state, low stock, recent activity. **[L]**
2. Explicit "Due includes Overdue?" definition, displayed. **[L]**
3. Dual plan counted once even when both triggers fire. **[L]**
4. Due & overdue register with trigger cause and current reading. **[L]**
5. Asset register and asset maintenance history. **[L]**
6. Work-order register/history. **[L]**
7. Spare stock + movement ledger. **[L]**
8. Audit log. **[L]**
9. CSV export matching the current filters/columns. **[L]**
10. Filters: subsystem, criticality, assignee, due status. **[R]**
11. "As at" timestamp + timezone on due screens. **[R]**

### Related roles
| Role | Permissions |
|---|---|
| Chief Engineer | All reports and exports |
| Technician | Assigned work + operational reads |
| Viewer | Read-only authorised reports; may export |

### Acceptance checks
- Dashboard counts reconcile with SQL counts.
- Export row count/values match the filtered page.
- No report recalculates status differently from M04.

---

## M09 — Data Foundation (migrations, seeders, constraints)

**Priority:** MVP-1 (engineering)
**Source:** [L] manual pp. 6, 14; rubric C2

### Purpose
Make the database reproducible: empty DB → migrate → seed → working system.

### Features
1. Versioned migrations for all entities. **[L]**
2. Repeatable seeder: 1 vessel, 10 assets, 8 plans, 6 work orders, 6 spares, demo users. **[L]**
3. Constraints: PK/FK, unique codes, positive intervals, activation completeness, non-negative stock, partial unique active-WO index. **[L]**
4. Enum types for role, trigger, state, criticality. **[L]**
5. `timestamptz` + local-date handling for Asia/Kuala_Lumpur. **[L]**
6. Synthetic-only data with `Synthetic teaching scenario` source labels. **[L]**
7. Deterministic/fixed test dates for reproducible boundary demos. **[R]**

### Acceptance checks
- Fresh DB: migrate + seed succeeds without manual SQL.
- TC19 clean install.

---

## M10 — Deployment, Backup & Recovery

**Priority:** MVP-1
**Source:** [L] manual pp. 3, 14, 15, 16; rubric C5; TC17–TC20

### Purpose
Publish the tested release reproducibly and prove it can be rebuilt and recovered.

### Features
1. Dockerfile + Compose: web + PostgreSQL with named volume, health checks. **[L]**
2. `.env.example` with placeholders; real secrets excluded. **[L]**
3. Cloudflare Tunnel → HTTPS student-ID hostname (e.g. `s79244…`). **[L]**
4. PostgreSQL private; only web exposed. **[L]**
5. Persistent data verified across restart. **[L]**
6. `pg_dump` custom-format backup + `pg_restore` into a separate empty DB; compare records. **[L]**
7. Documented clean-start, migration, seed and account-creation commands. **[L]**
8. Pinned versions recorded in README/lock files. **[L]**
9. Release tag/commit matching the hosted release. **[L]**
10. Optional separate least-privilege DB role for the app. **[R]**

### Related roles
Operational: student/administrator. End users: all three roles via HTTPS.

### Acceptance checks
TC17, TC18, TC19, TC20.

---

## M11 — Verification & Testing

**Priority:** MVP-1
**Source:** [L] manual pp. 17–18; rubric C7

### Purpose
Produce repeatable evidence that requirements, boundaries, permissions, persistence and recovery work.

### Features
1. ≥15 cases selected from TC01–TC20. **[L]**
2. Automated unit tests for the due engine (calendar, hours, dual, equality, overdue, missing data). **[L-I]**
3. Feature/HTTP tests for lifecycle, permissions, invalid transitions, stock. **[L-I]**
4. Test register: ID, requirement, precondition, action, expected, observed, pass/fail, evidence, commit. **[L]**
5. Kept failures + corrections, not just green results. **[L]**
6. Dashboard/export reconciliation test. **[L]**
7. Backup restore verification. **[L]**
8. Optional CI job running the suite on push. **[R]**

### Related roles
Student/developer; evidence reviewed by examiner.

---

## M12 — AI-Assisted Delivery Workflow & Evidence

**Priority:** MVP-1 (course requirement; **not** an application screen)
**Source:** [L] manual pp. 2, 10, 11, 12; rubric C4

### Purpose
Demonstrate genuine, reviewed, bounded AI use — separate from any optional AI feature inside the app.

### Features
1. Project instruction file (`AGENTS.md` or harness equivalent), actually used. **[L]**
2. One reusable skill, e.g. `cmms-schedule-review`, with real invocation evidence. **[L]**
3. Two bounded agent roles: **Builder** (feature + tests, no deploy/account changes) and **Reviewer** (read-only inspection, findings). **[L]**
4. One **read-only MCP** integration (e.g. Cloudflare docs MCP) or approved documented fallback. **[L]**
5. Six substantive AI decision records using the supplied template. **[L]**
6. Redaction of secrets/personal data in all evidence. **[L]**
7. Record accepted / modified / rejected suggestions with reasons. **[L]**
8. Optional in-app AI assistant that only proposes text, never mutates records. **[L] optional**

### Deliverables
Filled templates: charter, requirement/test records, 6 AI records, 2 role cards, skill record, MCP record, deployment/risk record, final declaration + D1–D7 manifest.

---

## M13 — Documentation & Demonstration

**Priority:** MVP-1
**Source:** [L] manual p. 19; rubric C8, C9

### Purpose
Make the system independently usable and the student able to defend it.

### Features
1. README: purpose, stack, versions, local start, migrations, seed, tests, accounts. **[L]**
2. User guide per role (task flows). **[L]**
3. Deployment guide + recovery guide. **[L]**
4. Technical report following the 7-part outline (purpose/scope, design, maintenance behaviour, AI assistance, implementation & verification, deployment & recovery, evaluation & references). **[L]**
5. ER diagram, architecture diagram, permission matrix. **[L]**
6. 15-minute demo rehearsal: 6 min workflow, 6 min Q&A, 3 min replay a boundary test + explain a real correction/tradeoff. **[L]**
7. Frozen release tag + evidence pack. **[L]**

---

## Optional / explicitly out-of-scope

| Idea | Status | Reason |
|---|---|---|
| QR codes linking to asset pages | OPT [R] | Manual p. 20 suggestion; no extra marks |
| Mobile/keyboard usability polish | OPT [R] | Supports C8 |
| Filters + export preview | OPT [R] | Manual p. 20 suggestion |
| In-app document assistant | OPT [R] | Must not change records |
| Notifications/email alerts | OPT [R] | Not required; adds failure modes |
| Condition monitoring / predictive maintenance | **OUT** [L] | Manual p. 20; insufficient data, out of scope |
| Offline operation | **OUT** [L] | Manual p. 20 |
| Multi-vessel / multi-tenant | **OUT** [R] | One fictional vessel required |
| Full RBAC / SSO | **OUT** [R] | Three fixed roles suffice |
| Cloudflare Workers runtime | Alternative [L] | Only if choosing Workers + Hyperdrive; not the recommended path |

---

## Mandatory vs recommended summary

| MUST build (lecturer) | SHOULD build (rubric level 4) | MAY build (recommendation) |
|---|---|---|
| M01 auth + 3 roles | Server-side policy tests | User admin screen |
| M02 10 assets / ≥3 systems | Asset detail/history | QR links |
| M03 readings + reject decrease | Reading history | Correction workflow |
| M04 8 plans / 3 trigger modes / exact boundaries | Pure tested calculator | Baseline history view |
| M05 4-state lifecycle + 1 active WO + atomic close | Event timeline | Reopen/correction workflow |
| M06 6 spares + issue/shortage | Reorder report | Idempotency keys |
| M07 audit of actor/action/time/reason | Baseline history | Audit viewer filters |
| M08 dashboard + exports | Filters + reconciliation | Export preview |
| M09 migrations + repeatable seed | Deterministic test dates | Parameterised seeder |
| M10 Docker + Cloudflare + restore | Least-privilege DB role | CI |
| M11 ≥15 tests incl. boundaries/permissions/restore | Automated suite | Extra edge cases |
| M12 skill + 2 agents + MCP + 6 records | Reviewed, rejected suggestions | — |
| M13 README, report, demo | Polished user guide | — |

---

## Recommended build order (dependency-safe)

1. **M09** schema + seed skeleton → **M02** assets visible from PostgreSQL.
2. **M01** auth + server-side roles (before any write workflow).
3. **M04** due engine with unit tests (TC01–TC06, TC08).
4. **M03** readings (TC07) feeding M04.
5. **M05** lifecycle + partial unique index + atomic closure (TC09–TC15).
6. **M06** spares (TC16).
7. **M07** audit wired into every mutation.
8. **M08** dashboard + exports (reconcile).
9. **M11** full suite, persistence, restore (TC17–TC19).
10. **M10** Cloudflare publish (TC20).
11. **M12** finalise AI evidence; **M13** docs + rehearsal.

Do not start optional features (QR, notifications, AI assistant) until step 10 is stable.
