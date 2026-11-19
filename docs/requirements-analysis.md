# Shipyard CMMS — Requirements Analysis

**Project:** Shipyard: Computerized Maintenance Management System (CMMS)
**Course:** MNA4003 — Individual Final Project, Semester 1 2026/2027
**Assessment reference:** FE-MNA4003-S12026-2027
**Prepared by:** Senior Software Architect / Business Systems Analyst
**Status:** Analysis only — no application code written. Awaiting student/lecturer approval.
**Analysis date:** 19 November 2026

---

## 0. How to read this document

Every requirement carries a **source tag** so that nothing is silently invented:

| Tag | Meaning |
|---|---|
| **[L]** | Explicit **lecturer requirement**, taken from the supplied manual, rubric or templates. The page/criterion is cited. |
| **[L-I]** | **Lecturer-implied** requirement: follows necessarily from an explicit statement or from the rubric's top-level descriptor. |
| **[R]** | **My recommendation** as architect. Not required by the lecturer. Safe to accept, modify or reject. |
| **[?]** | **Unclear / missing** requirement that needs lecturer clarification. |

Where the manual says "suggested", "example" or "you may", it is treated as guidance, **not** as a hard requirement.

---

## 1. Source inventory (what was actually read)

> Note: the task referred to an `attachments/` directory. **No such directory exists.** All materials are in the project root. This is recorded as finding **G-00** in §12.

| # | File | Type | Pages / sheets | Read status | Role |
|---|---|---|---|---|---|
| 1 | `FE-MNA4003-S12026-2027_Student_Manual.pdf` | PDF 1.7 | 52 pages | ✅ Fully extracted (59,781 chars) | Primary assessment brief / development manual |
| 2 | `FE-MNA4003-S12026-2027_Rubric.xlsx` | XLSX | 2 sheets: `Marking`, `Descriptors` | ✅ Fully read | Marking scheme, 9 criteria, levels 0–4 |
| 3 | `FE-MNA4003-S12026-2027_Student_Templates.docx` | DOCX | 7 record forms + D1–D7 manifest | ✅ Fully read | Evidence templates the student must complete |
| 4 | `*_Rubric.xlsx:Zone.Identifier` | Metadata sidecar | — | ✅ Inspected (25 bytes) | Windows download marker; irrelevant |
| 5 | `*_Student_Manual.pdf:Zone.Identifier` | Metadata sidecar | — | ✅ Inspected | Windows download marker; irrelevant |
| 6 | `*_Student_Templates.docx:Zone.Identifier` | Metadata sidecar | — | ✅ Inspected | Windows download marker; irrelevant |

**Images/diagrams:** the only PDF-embedded raster is a repeated header logo. The architecture/deployment figures referenced on pages 3–4 are vector drawings that did not extract as raster images; their content is fully described in the surrounding text. Nothing of substance was lost.

**Files that could not be opened:** none.

**Important absence:** the manual repeatedly refers to *"the approved assessment brief"* as the controlling document (pp. 1, 19) and to *"lecturer-approved budget"*, *"actual dates"* and *"submission arrangements"* — **that brief is not present in the supplied files**. Only the manual, rubric (marked "Draft for course approval") and templates were provided. See **G-01**.

---

## 2. Purpose and objectives of the project

### 2.1 Lecturer-stated purpose **[L]**

> *"Design, build, test and publish a Ship Computerized Maintenance Management System with assistance from AI. Your system will manage a fictional vessel. Your evidence must show what the system does, why its rules are correct, and how you verified the work."*
> — Manual, p. 1

The project is an **educational prototype** for planned maintenance on a fictional vessel. The manual explicitly states it does **not** claim commercial readiness, operational ship approval or classification-society approval (p. 1).

### 2.2 Objectives **[L]**

1. Model a coherent **ship maintenance scenario** — fictional vessel, ship systems, assets, maintenance plans and crew roles (p. 5; rubric C1).
2. Implement a **PostgreSQL-backed CMMS** with calendar, running-hour and dual-trigger maintenance scheduling (pp. 6–7).
3. Implement a controlled **work-order lifecycle** with server-enforced permissions (p. 8).
4. Preserve **data integrity, history and auditability** across approvals, stock and baseline updates (pp. 6, 8).
5. **Deploy publicly** over HTTPS at an authorised hostname containing the student ID, via Cloudflare, with Docker for reproducible local execution (pp. 14–15).
6. **Test and evidence** the work: ≥15 meaningful cases, boundary tests, permission tests, persistence, backup restoration and clean install (pp. 17–18).
7. Demonstrate **responsible AI-assisted development**: one reusable skill, two bounded agent roles, one read-only MCP integration (or approved fallback), six substantive AI decision records (pp. 10–12).
8. Produce a **technical report** and deliver a **15-minute individual demonstration** (p. 19).

### 2.3 What the marker is really assessing (rubric weights) **[L]**

| Criterion | Max | What "level 4" demands |
|---|---:|---|
| C1 Requirements & maritime relevance | 10 | Coherent ship scenario, roles, complete traceable requirements, justified assumptions |
| C2 Database design & integrity | 10 | Coherent PostgreSQL schema, relationships, constraints, repeatable migrations/seed, transactional integrity |
| C3 Core maintenance functions | 25 | **Complete** workflow; calendar/hours/dual correct **at boundaries**; approval, next-due updates, stock, dashboard and exports all consistent |
| C4 AI workflow & development judgement | 10 | Reusable skill + 2 bounded agent roles + read-only MCP + 6 honest AI records linked to reviewed code/tests |
| C5 Deployment & reproducibility | 10 | Clean-checkout Docker + PostgreSQL, Cloudflare HTTPS, student-ID hostname, recovery explained |
| C6 Security & robustness | 10 | Server-side role enforcement incl. direct requests, secrets, validation, safe errors, attributable audit |
| C7 Testing & verification | 10 | Repeatable tests mapped to requirements; normal/boundary/invalid/permission/persistence/backup |
| C8 Usability & documentation | 5 | Clear flows, validation feedback, responsive, accurate install/user docs |
| C9 Individual demonstration | 10 | Accurate explanation of architecture/data/logic/security/AI; trace a requirement through code, data, tests |

Course contribution = project mark × 40%. **C3 alone is 25 marks**, so the scheduling engine and lifecycle must be finished and correct before any optional feature.

---

## 3. Required starting scope (the non-negotiable dataset) **[L]**

Manual p. 1, expanded on pp. 5–8:

| Item | Required quantity / rule | Source |
|---|---|---|
| Fictional vessel | One vessel (example: *Training Vessel Seri Ilmu*) | p. 5 |
| Assets | **10** assets, each with unique ID, name, subsystem, location, criticality, service status; hour meter **only where applicable** | pp. 1, 5 |
| Ship systems | **At least 3** distinct ship systems covered | p. 13 |
| Maintenance plans | **8** total = **3 calendar + 3 running-hour + 2 dual-trigger**; each linked to an asset; task, trigger basis, interval, last verified completion baseline, source label | pp. 1, 5 |
| Work orders (seed) | **6**, spread across useful lifecycle states; closed ones kept as history; never two active for one plan | pp. 1, 5 |
| Spare items (seed) | **6**, unique item code, description, unit, stock quantity, reorder level; enough to demo an issue **and** a shortage | pp. 1, 5 |
| Users/roles | **Chief Engineer, Technician, Viewer** — synthetic demo accounts | pp. 1, 5, 8 |
| Timezone | **Asia/Kuala_Lumpur** for all displayed dates; calendar due date is a date in that timezone | p. 6 |
| Data | **Synthetic only**; invented intervals labelled as such; no real vessel/crew/serial/approval data | pp. 1, 2, 5 |

Suggested asset seed (p. 5) — **example, not compulsory**:

| ID | Asset | Subsystem |
|---|---|---|
| A01 / A02 | Port / Starboard main engine | Propulsion |
| A03 / A04 | Port / Starboard gearbox | Transmission |
| A05 | Generator set | Electrical power |
| A06 | Bilge pump | Bilge system |
| A07 | Fire pump | Fire main |
| A08 | Steering hydraulic unit | Steering |
| A09 | Sea-water cooling pump | Cooling |
| A10 | Air compressor | Compressed air |

**[R]** Adopt this exact set. It gives 7 subsystems (satisfies "≥3 systems"), pairs naturally for calendar/hour/dual plan assignment, and is easy to defend in the demo.

---

## 4. Functional requirements

Requirements are stable IDs. **[L]** = lecturer, **[L-I]** = lecturer-implied, **[R]** = recommendation. "Priority" uses M = mandatory for a defensible submission.

### 4.1 Vessel and asset management

| ID | Requirement | Source | Priority |
|---|---|---|---|
| FR-01 | Store a fictional vessel record: identifier, name, description; vessel has many assets. | [L] p. 6 | M |
| FR-02 | Store assets with unique code, vessel reference, name, subsystem, location, criticality, service status. | [L] p. 5 | M |
| FR-03 | Record a running-hour meter only for assets that use one; assets without a meter must not require hours. | [L] p. 5 | M |
| FR-04 | Seed exactly 10 assets spanning ≥3 ship systems. | [L] pp. 1, 13 | M |
| FR-05 | Allow an asset to be withdrawn from service **without deleting it**; service status preserves history. | [L] p. 6 | M |
| FR-06 | Reject duplicate asset codes (database-level uniqueness). | [L] p. 6 | M |

### 4.2 Maintenance plans and due calculation

| ID | Requirement | Source | Priority |
|---|---|---|---|
| FR-07 | Store 8 plans: 3 calendar, 3 running-hour, 2 dual-trigger. Each references one asset. | [L] p. 1 | M |
| FR-08 | Each plan stores task description, trigger type, interval, last verified completion baseline (date and/or hours) and a source label (e.g. "Synthetic teaching scenario"). | [L] p. 5 | M |
| FR-09 | Intervals must be **positive integers** (days) / positive numbers (hours). Reject zero or negative. | [L] pp. 6–7 | M |
| FR-10 | A plan cannot be **activated** unless every baseline applicable to its trigger type exists; a missing value must never be substituted with zero. | [L] p. 7 | M |
| FR-11 | Calendar due date = `last_completion_date + interval_days`. Due when `today >= due_date`; **Overdue when today > due_date**. | [L] p. 7 | M |
| FR-12 | Hours due at = `last_completion_hours + interval_hours`. Due when `current_hours >= due_at`; **Overdue when current_hours > due_at**. | [L] p. 7 | M |
| FR-13 | Dual plan is due when **calendar OR hours** threshold is reached — **never AND**. | [L] p. 7 | M |
| FR-14 | At exact equality the status is **Due**, not "not due" and not "overdue". | [L] p. 7 | M |
| FR-15 | The result must state **which trigger** caused it (calendar / hours / both) and whether it is Not due / Due / Overdue. | [L] p. 7 | M |
| FR-16 | If the current meter reading is missing, show an **incomplete hours check**; if calendar status is already Due/Overdue, preserve it and display a missing-meter warning. | [L] p. 7 | M |
| FR-17 | Opening/submitting a work order must **not** move the plan baseline. Only Chief Engineer closure advances it. | [L] p. 7 | M |
| FR-18 | On closure, the new baseline uses the **actual completion date and actual completion hours** recorded for the job — **not** the approval timestamp. | [L] pp. 7, 8 | M |

**Boundary matrix that must hold (manual p. 7):**

| Baseline | Observation | Expected |
|---|---|---|
| Calendar 1 Nov 2026, +30 d | 30 Nov | Not due (due 1 Dec) |
| Same | 1 Dec | **Due** |
| Same | 2 Dec | **Overdue** by 1 day |
| Hours 1200, +250 | 1449 h | Not due (due 1450) |
| Same | 1450 h | **Due** |
| Same | 1451 h | **Overdue** |
| Dual (both baselines above) | 20 Nov @ 1450 h | Due via **hours**, despite earlier date |
| Dual | 1 Dec @ 1400 h | Due via **date**, despite lower hours |
| Complete 5 Nov @ 1500 h; approve 7 Nov | next thresholds | **5 Dec** and **1750 h** |

### 4.3 Meter readings

| ID | Requirement | Source | Priority |
|---|---|---|---|
| FR-19 | Record cumulative running-hour readings: asset reference, cumulative hours, reading timestamp, recording user. | [L] p. 6 | M |
| FR-20 | Reject a reading **lower than the accepted previous reading**; no silent decrease. Meter replacement/rollover may be documented as a limitation. | [L] pp. 6–7 | M |
| FR-21 | Store hours as numeric values, not formatted strings; distinguish "missing" from zero. | [L] p. 6 | M |

### 4.4 Work-order lifecycle

| ID | Requirement | Source | Priority |
|---|---|---|---|
| FR-22 | Lifecycle is exactly **Open → In progress → Submitted → Closed**. A work order is one instance; the plan is the recurring definition. | [L] p. 8 | M |
| FR-23 | "Active" = any of Open, In progress, Submitted. Closed orders are retained history. | [L] p. 8 | M |
| FR-24 | Open → In progress: only the **assigned Technician** or an authorised **Chief Engineer**; record who started and when. | [L] p. 8 | M |
| FR-25 | In progress → Submitted: assigned Technician or authorised Chief Engineer; validate actual completion details and required evidence. | [L] p. 8 | M |
| FR-26 | Submitted → In progress (return): **Chief Engineer only**, requires a **reason**, and **preserves the earlier submission record**. | [L] p. 8 | M |
| FR-27 | Submitted → Closed: **Chief Engineer only**; approve evidence and **atomically** advance applicable plan baselines. | [L] p. 8 | M |
| FR-28 | Reject invalid transitions, notably **Open → Closed** and any direct jump. | [L-I] p. 8; TC14 | M |
| FR-29 | Reject submission when required completion details are missing; preserve previous valid state. | [L-I] p. 8; TC15 | M |
| FR-30 | **At most one active work order per plan**, enforced so it stays correct under **concurrent requests** (not just in the UI). | [L] p. 8 | M |
| FR-31 | A repeated closure request must not reset the baseline twice or duplicate stock deductions (idempotency). | [L] p. 8 | M |
| FR-32 | Chief Engineer can manage plans and assignments and review completion/history. | [L] p. 8 | M |
| FR-33 | Technician can read **assigned** work, record progress, submit completion and record permitted spare usage. | [L] p. 8 | M |
| FR-34 | Viewer can read authorised screens/reports and **cannot** create, edit, submit, return or close. | [L] p. 8 | M |
| FR-35 | Permissions are enforced **server-side on every request**; hiding a button is not enforcement. A Viewer editing the URL or a Technician calling the close endpoint must still be denied. | [L] pp. 8, 16 | M |
| FR-36 | A denied action must leave the database unchanged. | [L-I] p. 18; TC11/TC12 | M |

### 4.5 Spare parts

| ID | Requirement | Source | Priority |
|---|---|---|---|
| FR-37 | Store 6 spare items: unique code, description, stock unit, quantity on hand, reorder level. | [L] p. 5 | M |
| FR-38 | Record spare use as a **traceable movement linked to the work order**. | [L] p. 8 | M |
| FR-39 | Decide **when** stock is deducted (e.g. at issue) and apply the rule exactly once. | [L] p. 8 | M |
| FR-40 | Reject insufficient stock and negative issue quantities; no negative balance. | [L] p. 8; TC16 | M |
| FR-41 | Record corrections as **separate traceable actions**, never by silently editing history. | [L] p. 8 | M |
| FR-42 | Seed data must include enough examples to demonstrate a successful issue **and** a shortage. | [L] p. 5 | M |
| FR-43 | Show current stock and reorder status, and movement history. | [L-I] p. 8; rubric C3 | M |

### 4.6 Audit, history and correction

| ID | Requirement | Source | Priority |
|---|---|---|---|
| FR-44 | Audit event stores actor, action, record reference, timestamp and relevant change/reason. | [L] p. 6 | M |
| FR-45 | Never store passwords or tokens in audit records. | [L] p. 8 | M |
| FR-46 | Retain closed work orders and their completion evidence. | [L] pp. 6, 8 | M |
| FR-47 | Only an approved workflow may change a closed record; document any correction mechanism. | [L] p. 8 | M |
| FR-48 | Record actual completion separately from submission and approval timestamps. | [L] pp. 6, 7 | M |

### 4.7 Dashboard, reports and exports

| ID | Requirement | Source | Priority |
|---|---|---|---|
| FR-49 | Provide a dashboard whose counts reconcile with stored records. | [L] rubric C3 L4; p. 18 | M |
| FR-50 | Clearly define whether "Due" includes "Overdue" and never double-count a dual plan when both triggers are reached. | [L] p. 18 | M |
| FR-51 | Provide exports (CSV at minimum) that match the filtered on-screen data. | [L] rubric C3 L4; p. 20 | M |
| FR-52 | Provide maintenance due/overdue reporting filtered by at least subsystem, criticality, assignee and due status. | [L] p. 20 | Should |
| FR-53 | Provide work-order history, spare stock/movement and audit views. | [L-I] pp. 8, 18 | M |

### 4.8 Platform, deployment and recovery

| ID | Requirement | Source | Priority |
|---|---|---|---|
| FR-54 | Persist all real application records in **PostgreSQL**. | [L] p. 3 | M |
| FR-55 | Keep schema changes in **migrations** and seed data in a **repeatable script**; commit both. | [L] p. 6 | M |
| FR-56 | Provide a working **Dockerfile + Docker Compose** for at least local execution (web + PostgreSQL, persistent volume). | [L] pp. 3, 14 | M |
| FR-57 | Publish over **HTTPS** on an authorised hostname **containing the student ID**, via **Cloudflare Tunnel** (or documented approved alternative). | [L] pp. 3, 15 | M |
| FR-58 | Keep PostgreSQL on a private network; expose only the web app. | [L] p. 3 | M |
| FR-59 | Separate secrets from the repository; provide `.env.example` with placeholders only. | [L] pp. 14, 16 | M |
| FR-60 | Provide a tested **backup and restore** procedure using `pg_dump`/`pg_restore` or an equivalent Docker method; verify restored records. | [L] p. 16 | M |
| FR-61 | Data must survive a service restart (named volume). | [L] pp. 14, 18 TC17 | M |
| FR-62 | Clean checkout must reproduce: build → migrate → seed → login → workflow. | [L] p. 18 TC19 | M |

### 4.9 AI-assisted development evidence (course requirement, not an app feature)

| ID | Requirement | Source | Priority |
|---|---|---|---|
| FR-63 | One project instruction file (e.g. `AGENTS.md` / `CLAUDE.md` as supported by the harness). | [L] p. 10 | M |
| FR-64 | **One reusable skill** with a real, evidenced invocation. | [L] pp. 1, 10 | M |
| FR-65 | **Two bounded AI agent roles** (e.g. builder + reviewer) with defined inputs, tools, outputs and review points. | [L] p. 11 | M |
| FR-66 | **One read-only MCP integration** (or course-approved documented fallback) with redacted evidence. | [L] p. 12 | M |
| FR-67 | **Six substantive AI decision records**, each linking prompt → output → judgement → code/commit → test result. | [L] pp. 1, 11 | M |
| FR-68 | AI *inside the finished application* is **optional** and must not silently mutate operational records. | [L] pp. 1, 13 | Optional |

---

## 5. Non-functional requirements

| ID | Requirement | Source |
|---|---|---|
| NFR-01 **Integrity** | Related changes (close work order + update baseline + audit + stock) occur in **one transaction**; any failure leaves previous consistent state. | [L] p. 8 |
| NFR-02 **Concurrency** | Duplicate active work orders prevented at the database level under simultaneous requests. | [L] p. 8 |
| NFR-03 **Idempotency** | Repeated submission/closure requests produce one effect. | [L] p. 8 |
| NFR-04 **Security** | Hashed passwords, framework session protection, server-side authorisation, input validation, parameterised queries, generic public errors, least-privilege DB user. | [L] p. 16 |
| NFR-05 **Auditability** | Every privileged action attributable to actor + time + reason. | [L] pp. 6, 8 |
| NFR-06 **Reproducibility** | Pinned versions, lock files, tested Docker, documented commands; identical release on host and in repo. | [L] pp. 14, 19 |
| NFR-07 **Persistence & recovery** | Data survives restart; verified restore into a separate empty database; backup kept outside the only DB volume. | [L] pp. 14, 16 |
| NFR-08 **Timezone correctness** | Timestamps consistent; dates displayed in Asia/Kuala_Lumpur; calendar boundary evaluated on local date. | [L] p. 6 |
| NFR-09 **Usability** | Clear task flows, useful validation messages, keyboard-usable, works at suitable screen sizes (mobile/desktop). | [L] p. 20; rubric C8 |
| NFR-10 **Explainability** | Student can explain inputs, validation, DB changes, error handling and tests for any important function. | [L] p. 11 |
| NFR-11 **Synthetic data / privacy** | No real vessel, crew, serial numbers, credentials or production data anywhere in repo/report/screenshots. | [L] pp. 1, 2, 5, 16 |
| NFR-12 **Maintainability** | Conventional, modest architecture (one coherent app); framework the student can explain. | [L] p. 3 |
| NFR-13 **Scope control** | Freeze feature expansion when testing/deployment starts; a complete modest system scores better than disconnected screens. | [L] p. 13 |
| NFR-14 **Cost control** | Record expected costs; stay within lecturer-approved budget; no bonus for paid tools. | [L] pp. 2, 15 |

**Explicitly out of scope / future work [L]:** offline operation, condition monitoring, predictive maintenance, reliable failure prediction. Also the system is **not** commercially/operationally/classification approved (p. 1).

---

## 6. User roles and permission matrix

Source: manual p. 8 (role boundaries), pp. 5, 11; rubric C6.

| Capability | Chief Engineer | Technician | Viewer | Notes |
|---|:--:|:--:|:--:|---|
| Sign in | ✅ | ✅ | ✅ | **[L]** |
| View dashboard / assets / plans / reports | ✅ | ✅ (assigned/authorised) | ✅ (authorised) | **[L]** "authorised screens" |
| Create / edit maintenance plans | ✅ | ❌ | ❌ | **[L]** "manage plans" |
| Activate / deactivate plans | ✅ | ❌ | ❌ | **[L-I]** |
| Assign work orders | ✅ | ❌ | ❌ | **[L]** "manage … assignments" |
| Create work order | ✅ | **[?]** | ❌ | Manual does not say who creates. **G-06** |
| Start work (Open → In progress) | ✅ (authorised) | ✅ (assigned only) | ❌ | **[L]** |
| Record progress / meter readings | ✅ | ✅ | ❌ | **[L]** |
| Submit completion (→ Submitted) | ✅ (authorised) | ✅ (assigned only) | ❌ | **[L]** |
| Return submission (→ In progress, reason) | ✅ | ❌ | ❌ | **[L]** |
| Close / approve (→ Closed) | ✅ | ❌ | ❌ | **[L]** |
| Record spare usage | ✅ | ✅ (permitted) | ❌ | **[L]** |
| Manage spare master data / corrections | ✅ | **[?]** | ❌ | Not specified. **G-07** |
| View audit / history | ✅ | **[?]** (own/assigned) | **[?]** | Not specified. **G-08** |
| Export CSV | ✅ | ✅ | ✅ (read-only) | **[L]** exports required; permitted per role not stated |
| Create/edit users | ✅ (admin) | ❌ | ❌ | Implied by account management |
| Change/delete closed records | ❌ (approved correction workflow only) | ❌ | ❌ | **[L]** |

**Critical enforcement rule [L]:** every cell above must be checked on the **server**, including direct HTTP requests, not merely by hiding UI. Test cases TC11/TC12 require proving no DB change occurred.

**[R]** Model roles as a fixed enum (`chief_engineer`, `technician`, `viewer`) rather than a full RBAC table. Three fixed roles are simpler to explain and audit; extend later if needed.

---

## 7. Required workflows

### 7.1 Due-status evaluation (pure, deterministic)

```
today_local        = current date in Asia/Kuala_Lumpur
current_hours      = latest accepted meter reading for the asset (nullable)

calendar_known     = plan.interval_days IS NOT NULL AND plan.last_completion_date IS NOT NULL
hours_known        = plan.interval_hours IS NOT NULL AND plan.last_completion_hours IS NOT NULL

calendar_due_date  = last_completion_date + interval_days
hours_due_at       = last_completion_hours + interval_hours

calendar_due       = calendar_known AND today_local >= calendar_due_date
calendar_overdue   = calendar_known AND today_local >  calendar_due_date
hours_due          = hours_known AND current_hours IS NOT NULL AND current_hours >= hours_due_at
hours_overdue      = hours_known AND current_hours IS NOT NULL AND current_hours >  hours_due_at

dual_due           = calendar_due OR hours_due          # NEVER AND
status             = Overdue if (calendar_overdue OR hours_overdue)
                     else Due if (calendar_due OR hours_due)
                     else Not due
trigger_cause      = CALENDAR / HOURS / BOTH / NONE
```

Rules:
- Equality ⇒ **Due** (not overdue).
- Missing applicable baseline ⇒ plan cannot be active.
- Missing current hours on an hours/dual plan ⇒ **hours check incomplete**; calendar status still shown; warning displayed.
- One row per plan in the dashboard; a dual plan counts **once**.

**[R]** Implement this as a **pure domain service** with injected "today" and readings, so tests can freeze time and hours without touching the UI. This directly enables TC01–TC06 automation and C7 marks.

### 7.2 Work-order lifecycle

```
                 ┌──────────┐
   create ──────▶│   Open   │
                 └────┬─────┘
      assigned Tech / authorised CE
                      ▼
                 ┌──────────────┐
       return    │ In progress  │◀────────────┐
      + reason   └──────┬───────┘             │
      (CE only)         │ assigned Tech / CE  │
                        ▼                     │
                 ┌──────────────┐   return    │
                 │  Submitted   │─────────────┘
                 └──────┬───────┘  (reason required,
                        │           prior submission preserved)
             CE approve │
                        ▼
                 ┌──────────────┐
                 │    Closed    │  (history, immutable except
                 └──────────────┘   approved correction workflow)
```

**Closure transaction [L]:**
1. Re-validate state = `Submitted` **inside** the transaction (`UPDATE … WHERE id = ? AND state = 'submitted' RETURNING …`).
2. Validate completion data again.
3. Write work order → Closed, `closed_at`, `approved_by`.
4. Advance baseline: `last_completion_date = actual_completion_date`; `last_completion_hours = actual_completion_hours` (only fields applicable to trigger type).
5. Insert audit event.
6. Commit. If any step fails → rollback to prior consistent state.
7. A second request finds no `submitted` row → no-op with clear message; no double baseline reset.

**Duplicate prevention [L]:** partial unique index on active states (see §8.4). Catch the unique violation and return a clear message.

### 7.3 Spare issue workflow

1. Technician selects spare item(s) and quantity for a work order.
2. Server validates quantity > 0, work order active, item active.
3. `SELECT … FOR UPDATE` the spare row.
4. Check `quantity_on_hand - requested >= 0`; else reject with no changes.
5. Insert signed `spare_movement` (negative for issue), update `quantity_on_hand`, write audit event — one transaction.
6. Display resulting balance; reject repeated submission via idempotency key/unique constraint **[R]**.

**[?]** Manual says "decide when stock is deducted, then apply that rule once" — deduction point is a student decision, not prescribed. Recommend **at issue**, with returns as positive movements.

### 7.4 Meter reading workflow

1. User records cumulative hours + timestamp (default now, Asia/Kuala_Lumpur).
2. Server reads latest accepted reading; if new < previous → reject with clear error (or route through an explicit correction workflow if implemented).
3. Insert reading + audit event. Reading becomes the "current hours" for due calculations.

### 7.5 Backup / restore verification

`pg_dump --format=custom` → restore into a **separate empty** database with `pg_restore --no-owner --no-acl --exit-on-error` → compare record counts, a closed work order + its baseline, spare balances → run a login and a representative workflow → record evidence. Backup stored outside the DB's only volume.

---

## 8. Database analysis and proposed PostgreSQL design

The manual gives a **suggested** entity list (p. 6) and explicitly says it is *"not a compulsory table naming scheme"* — entities may be combined or separated if behaviour, integrity and audit remain clear. The design below adopts the suggested entities and adds two justified extras.

### 8.1 Entity–relationship overview

```
vessels 1───* assets 1───* meter_readings
                 │
                 └──* maintenance_plans 1───* work_orders 1───* spare_movements *───1 spare_items
                                                    │
                                                    └──* work_order_events
users 1───* (meter_readings, work_orders.created_by, work_orders.assigned_to,
             work_orders.approved_by, work_order_events.actor, spare_movements.actor,
             audit_events.actor)

maintenance_plans.last_completion_work_order_id ──▶ work_orders.id (nullable, provenance)
audit_events ── polymorphic reference to any entity (entity_type + entity_id)
```

**Added beyond the suggested list [R]:**
- `work_order_events` — needed to satisfy "preserve the earlier submission and reason" and to prove lifecycle history; also supports C9 traceability.
- `plan_baseline_history` — optional but strongly recommended to audit every baseline advance; satisfies "silent changes make the audit trail unreliable".

### 8.2 Table specifications

#### `vessels`
| Column | Type | Constraints |
|---|---|---|
| id | `uuid` | PK, `gen_random_uuid()` |
| code | `varchar(30)` | UNIQUE, NOT NULL |
| name | `varchar(120)` | NOT NULL |
| description | `text` | NULL |
| created_at / updated_at | `timestamptz` | NOT NULL, default now |

#### `assets`
| Column | Type | Constraints |
|---|---|---|
| id | `uuid` | PK |
| vessel_id | `uuid` | FK → vessels(id), NOT NULL, ON DELETE RESTRICT |
| code | `varchar(20)` | NOT NULL, UNIQUE (A01…A10) |
| name | `varchar(120)` | NOT NULL |
| subsystem | `varchar(80)` | NOT NULL |
| location | `varchar(120)` | NULL |
| criticality | `criticality_enum` | NOT NULL (`low`,`medium`,`high`,`critical`) |
| service_status | `service_status_enum` | NOT NULL (`in_service`,`standby`,`withdrawn`) |
| has_hour_meter | `boolean` | NOT NULL default false |
| meter_unit | `varchar(10)` | NOT NULL default `'hours'` |
| created_at / updated_at | `timestamptz` | NOT NULL |

Constraints: `UNIQUE(code)`; `CHECK (meter_unit = 'hours')` for this scope.

#### `meter_readings`
| Column | Type | Constraints |
|---|---|---|
| id | `uuid` | PK |
| asset_id | `uuid` | FK → assets, NOT NULL, RESTRICT |
| reading_hours | `numeric(12,2)` | NOT NULL, `CHECK (reading_hours >= 0)` |
| reading_at | `timestamptz` | NOT NULL, default now |
| recorded_by | `uuid` | FK → users, NOT NULL, RESTRICT |
| note | `text` | NULL |
| created_at | `timestamptz` | NOT NULL |

Indexes: `(asset_id, reading_at DESC)`. Monotonicity validated in a transaction + optional trigger; a DB trigger is recommended for integrity independent of the UI.

#### `maintenance_plans`
| Column | Type | Constraints |
|---|---|---|
| id | `uuid` | PK |
| asset_id | `uuid` | FK → assets, NOT NULL, RESTRICT |
| code | `varchar(20)` | UNIQUE, NOT NULL |
| task_description | `text` | NOT NULL |
| trigger_type | `plan_trigger_enum` | NOT NULL (`calendar`,`hours`,`dual`) |
| interval_days | `integer` | NULL, `CHECK (interval_days > 0)` |
| interval_hours | `numeric(12,2)` | NULL, `CHECK (interval_hours > 0)` |
| last_completion_date | `date` | NULL |
| last_completion_hours | `numeric(12,2)` | NULL, `CHECK >= 0` |
| last_completion_work_order_id | `uuid` | FK → work_orders, NULL, ON DELETE SET NULL |
| source_label | `varchar(120)` | NOT NULL default `'Synthetic teaching scenario'` |
| is_active | `boolean` | NOT NULL default false |
| created_at / updated_at | `timestamptz` | NOT NULL |

**Critical CHECK (activation completeness):**
```sql
CHECK (
  NOT is_active OR (
    (trigger_type = 'calendar' AND interval_days  IS NOT NULL AND last_completion_date  IS NOT NULL)
 OR (trigger_type = 'hours'    AND interval_hours IS NOT NULL AND last_completion_hours IS NOT NULL)
 OR (trigger_type = 'dual'     AND interval_days  IS NOT NULL AND last_completion_date  IS NOT NULL
                               AND interval_hours IS NOT NULL AND last_completion_hours IS NOT NULL)
  )
)
```
Draft/inactive plans may be incomplete; **activation** is what requires every applicable baseline.

#### `work_orders`
| Column | Type | Constraints |
|---|---|---|
| id | `uuid` | PK |
| reference | `varchar(20)` | UNIQUE, NOT NULL |
| plan_id | `uuid` | FK → maintenance_plans, NOT NULL, RESTRICT |
| state | `work_order_state_enum` | NOT NULL (`open`,`in_progress`,`submitted`,`closed`) |
| assigned_to | `uuid` | FK → users, NULL, RESTRICT |
| created_by | `uuid` | FK → users, NOT NULL |
| created_at | `timestamptz` | NOT NULL |
| started_at | `timestamptz` | NULL |
| submitted_at | `timestamptz` | NULL |
| closed_at | `timestamptz` | NULL |
| actual_completion_date | `date` | NULL |
| actual_completion_hours | `numeric(12,2)` | NULL, `CHECK >= 0` |
| completion_notes | `text` | NULL |
| evidence_ref | `varchar(255)` | NULL |
| approved_by | `uuid` | FK → users, NULL |

**Active-work-order uniqueness (the key integrity mechanism):**
```sql
CREATE UNIQUE INDEX one_active_work_order_per_plan
  ON work_orders (plan_id)
  WHERE state IN ('open', 'in_progress', 'submitted');
```
This is atomic and concurrency-safe — unlike an application-level "check then insert".

#### `work_order_events`
| Column | Type | Constraints |
|---|---|---|
| id | `uuid` | PK |
| work_order_id | `uuid` | FK → work_orders, NOT NULL, CASCADE |
| event_type | `varchar(20)` | NOT NULL (`created`,`started`,`submitted`,`returned`,`closed`,`corrected`) |
| from_state / to_state | `varchar(20)` | NULL |
| actor_id | `uuid` | FK → users, NOT NULL |
| reason | `text` | NULL — **required for `returned`** via app validation / CHECK |
| snapshot | `jsonb` | NULL — completion fields at submission time |
| occurred_at | `timestamptz` | NOT NULL default now |

Index: `(work_order_id, occurred_at)`. A partial unique index can prevent two consecutive `submitted` events with the same snapshot if desired **[R]**.

#### `spare_items`
| Column | Type | Constraints |
|---|---|---|
| id | `uuid` | PK |
| code | `varchar(30)` | UNIQUE, NOT NULL |
| description | `varchar(160)` | NOT NULL |
| unit | `varchar(20)` | NOT NULL |
| quantity_on_hand | `numeric(12,2)` | NOT NULL default 0, `CHECK (quantity_on_hand >= 0)` |
| reorder_level | `numeric(12,2)` | NOT NULL default 0, `CHECK (reorder_level >= 0)` |
| is_active | `boolean` | NOT NULL default true |

#### `spare_movements`
| Column | Type | Constraints |
|---|---|---|
| id | `uuid` | PK |
| spare_item_id | `uuid` | FK → spare_items, NOT NULL, RESTRICT |
| work_order_id | `uuid` | FK → work_orders, NULL, RESTRICT |
| movement_type | `varchar(20)` | NOT NULL (`issue`,`return`,`receipt`,`adjustment`) |
| quantity_delta | `numeric(12,2)` | NOT NULL, `CHECK (quantity_delta <> 0)` |
| balance_after | `numeric(12,2)` | NOT NULL, `CHECK >= 0` |
| actor_id | `uuid` | FK → users, NOT NULL |
| reason | `text` | NULL |
| occurred_at | `timestamptz` | NOT NULL default now |

Issues must be negative (`CHECK (movement_type <> 'issue' OR quantity_delta < 0)`); receipts/returns positive.

#### `users`
| Column | Type | Constraints |
|---|---|---|
| id | `uuid` | PK |
| name | `varchar(120)` | NOT NULL |
| email | `varchar(160)` | UNIQUE, NOT NULL |
| password_hash | `varchar(255)` | NOT NULL |
| role | `user_role_enum` | NOT NULL (`chief_engineer`,`technician`,`viewer`) |
| is_active | `boolean` | NOT NULL default true |
| created_at / updated_at | `timestamptz` | NOT NULL |

#### `audit_events`
| Column | Type | Constraints |
|---|---|---|
| id | `bigserial` | PK |
| actor_id | `uuid` | FK → users, NULL (system actions), RESTRICT |
| action | `varchar(50)` | NOT NULL (`work_order.closed`, `plan.baseline_advanced`, …) |
| entity_type | `varchar(50)` | NOT NULL |
| entity_id | `uuid` | NOT NULL |
| before / after | `jsonb` | NULL |
| reason | `text` | NULL |
| occurred_at | `timestamptz` | NOT NULL default now |

Index: `(entity_type, entity_id, occurred_at)`.

#### `plan_baseline_history` **[R] — recommended**
| Column | Type | Constraints |
|---|---|---|
| id | `bigserial` | PK |
| plan_id | `uuid` | FK → maintenance_plans, NOT NULL, RESTRICT |
| work_order_id | `uuid` | FK → work_orders, NOT NULL |
| previous_date / new_date | `date` | NULL |
| previous_hours / new_hours | `numeric(12,2)` | NULL |
| changed_by | `uuid` | FK → users, NOT NULL |
| changed_at | `timestamptz` | NOT NULL default now |

Makes baseline changes provable rather than inferred from the work order.

### 8.3 Relationships summary

| Relationship | Cardinality | FK / enforcement |
|---|---|---|
| Vessel → Assets | 1 : N | `assets.vessel_id` |
| Asset → Meter readings | 1 : N | `meter_readings.asset_id` |
| Asset → Maintenance plans | 1 : N | `maintenance_plans.asset_id` |
| Maintenance plan → Work orders | 1 : N | `work_orders.plan_id` (≤1 active via partial unique index) |
| Work order → Work-order events | 1 : N | `work_order_events.work_order_id` |
| Work order ↔ Spare items | M : N via `spare_movements` | two FKs, `work_order_id` nullable for receipts |
| User → many records | 1 : N | `recorded_by`, `actor_id`, `assigned_to`, `approved_by`, `created_by` |
| Work order → next plan baseline | 1 : 0..1 | `maintenance_plans.last_completion_work_order_id` |
| Any entity → Audit events | 1 : N (polymorphic) | `entity_type` + `entity_id` |

### 8.4 Constraints checklist

| Concern | Mechanism |
|---|---|
| Duplicate asset/spare/plan/WO codes | `UNIQUE` constraints |
| Positive calendar/day intervals | `CHECK (interval_days > 0)` |
| Positive hour intervals | `CHECK (interval_hours > 0)` |
| Missing ≠ zero | `NULL` allowed for unset baselines; `NOT NULL` only at activation |
| Trigger/baseline coherence | table `CHECK` shown in §8.2 |
| One active WO per plan | **partial unique index** |
| No negative stock | `CHECK (quantity_on_hand >= 0)` + `balance_after >= 0` |
| Issue is negative | `CHECK (movement_type <> 'issue' OR quantity_delta < 0)` |
| Valid states | PostgreSQL enums |
| No meter rollback | transactional check + optional trigger |
| Required return reason | app validation + `CHECK` trigger (cross-row) |
| History retention | `ON DELETE RESTRICT`; assets use `service_status`, never delete |
| Zeitgeist/timezone | `timestamptz` everywhere; date math in Asia/Kuala_Lumpur |
| Units explicit | `interval_days` vs `interval_hours`; `spare_items.unit` |
| Closure idempotency | state-guarded `UPDATE` inside transaction |
| Audit immutability | no UPDATE/DELETE grant on `audit_events` **[R]** |

### 8.5 Data types rationale

- **UUID PKs** — safe for public URLs, no enumeration, simple seeding.
- **`numeric(12,2)` for hours/stock** — never floats for operational quantities.
- **`timestamptz` for events**, **`date` for calendar due dates** — matches the manual's distinction between a local calendar date and an instant.
- **Enums** for trigger type, state, role, criticality — validity enforced by the database and self-documenting.
- **`jsonb` snapshots** for audit and submission history — preserves "what it looked like then" without schema churn.
- **`ON DELETE RESTRICT`** almost everywhere — history preservation is an assessed requirement.

### 8.6 Seed data plan **[R]** (within lecturer rules)

- 1 vessel, 10 assets (p. 5 set), 7 subsystems.
- 8 plans:
  - Calendar: A05 generator (30 d), A07 fire pump (90 d), A08 steering (180 d).
  - Hours: A01 port main engine (250 h), A03 port gearbox (500 h), A10 air compressor (200 h).
  - Dual: A02 starboard main engine (30 d OR 250 h), A09 sea-water cooling pump (60 d OR 400 h).
- 6 work orders: 1 Open, 1 In progress, 2 Submitted (one with a returned prior submission to demo history **[R]**), 2 Closed with baselines advanced.
- 6 spares: incl. one below reorder level and one with stock too low for a demo issue (shortage case).
- 3 demo accounts (+ optional 2 technicians to demonstrate "assigned only").
- All intervals labelled `Synthetic teaching scenario`; dates chosen near a fixed test date so TC01–TC10 are reproducible **[R]**.

---

## 9. Reports and dashboards

| Report / view | Content | Reconciles to | Source |
|---|---|---|---|
| **Maintenance dashboard** | Counts: Not due / Due / Overdue plans; active WOs by state; low-stock items; recent activity. Dual plans counted once. | Stored plans + latest readings | [L] C3, p. 18 |
| **Due & overdue register** | Plan, asset, subsystem, criticality, trigger, due date/hours, current reading, status, cause | Due engine | [L] pp. 7, 20 |
| **Asset register** | All asset fields, current hours, plan count, service status | `assets`, `meter_readings` | [L] p. 5 |
| **Asset maintenance history** | Plans, WOs, completion evidence, baseline changes | `work_orders`, `work_order_events` | [L-I] p. 6 |
| **Work-order register/history** | Reference, plan, asset, state, assignee, actual completion, approval, age | `work_orders` | [L] p. 8 |
| **Spare stock report** | Code, description, unit, on hand, reorder level, below-reorder flag | `spare_items` | [L] p. 5 |
| **Spare movement ledger** | Date, item, ±qty, work order, actor, reason, balance after | `spare_movements` | [L] p. 8 |
| **Audit trail** | Actor, action, entity, time, reason | `audit_events` | [L] p. 6 |
| **CSV exports** | Same columns/filters as the on-screen list | Same query | [L] C3, p. 20 |

**Dashboard definition [L]:** state explicitly whether "Due" includes "Overdue"; if Due and Overdue are separate tiles, define Overdue ⊆ Due or disjoint, and be consistent everywhere. Never count a dual plan twice.

**[R]** Show a "computed as at" timestamp + timezone on every due output, so markers can reproduce results.

---

## 10. Testing requirements (from lecturer)

Minimum **15 meaningful cases** selected from the 20 provided (p. 17). Coverage must include: scheduling boundaries, both dual-trigger paths, server permissions, invalid data, stock integrity, persistence, **actual backup restoration**, clean installation, deployment.

**Lecturer's cases (verbatim intent):**

| ID | Case | Expected |
|---|---|---|
| TC01 | Calendar 1 Nov + 30 d observed 30 Nov | Not due; due 1 Dec |
| TC02 | Same observed 1 Dec | Due at equality |
| TC03 | Same observed 2 Dec | Overdue by 1 day |
| TC04 | Hours 1200 + 250; compare 1449/1450/1451 | Not due / Due / Overdue |
| TC05 | Dual: date reached, hours below | Due via date |
| TC06 | Dual: hours reached, date not | Due via hours |
| TC07 | Cumulative reading below previous | Reject (or explicit correction) |
| TC08 | Activate dual with missing baseline | Reject, no zero substitution |
| TC09 | Two concurrent create requests, same plan | Exactly one active order persists |
| TC10 | Complete 5 Nov @ 1500 h, approve 7 Nov | Baseline from 5 Nov/1500 → 5 Dec/1750; repeat close is a no-op |
| TC11 | Viewer direct update request | Server denies; record unchanged |
| TC12 | Technician calls close endpoint | Denied; state and baseline unchanged |
| TC13 | CE returns without reason, then with reason | Reject / In progress + reason preserved |
| TC14 | Direct Open → Closed | Reject; no baseline change |
| TC15 | Submit without required completion details | Reject; prior state preserved |
| TC16 | Issue 3 when 2 in stock | Reject; no negative balance |
| TC17 | Save record, restart services, reload | Persists |
| TC18 | Restore backup to separate empty DB | Counts and key values match |
| TC19 | Follow README in clean environment | Build/migrate/seed/login/workflow succeed |
| TC20 | Final hostname from second device | HTTPS, login, role behaviour correct |

**Evidence per test [L]:** test ID, requirement, precondition, action, expected, observed, pass/fail, evidence reference, final commit. Keep failures and corrections honest.

**[R]** Add: timezone boundary test around midnight Asia/Kuala_Lumpur; idempotency test repeating a spare issue; and a test proving dual plan counted once on the dashboard.

---

## 11. Required deliverables (from templates, p. 2 of DOCX)

| ID | Deliverable | Rubric link |
|---|---|---|
| D1 | Hosted system | C3, C5, C6 |
| D2 | Source and release | C2, C3, C5 |
| D3 | Technical report | C1, C2, C4 |
| D4 | User and deployment guides | C5, C8 |
| D5 | Test and recovery evidence | C6, C7 |
| D6 | AI and provenance records | C4 |
| D7 | Recording and demonstration | C9 |

**Evidence forms to complete [L]:** Project charter; Requirement & test record (one per significant requirement); AI decision record ×6; Agent skill & MCP evidence (role cards ×2, skill record, MCP record); Deployment, recovery & risk record; Final declaration & evidence manifest.

---

## 12. Gaps, ambiguities, conflicts and risks

### 12.1 Gaps and ambiguities (need lecturer confirmation)

| ID | Issue | Why it matters | Recommended handling |
|---|---|---|---|
| **G-00** | **`attachments/` folder does not exist**; files are in the project root. | Task instruction mismatch | Proceed with root files; reported here |
| **G-01** | **The "approved assessment brief" is not supplied.** Manual defers dates, submission, format, budget and institutional rules to it (pp. 1, 19). | Cannot finalise deadline, report format, spend limit, host, access channel | Request the brief. Continue technical work meanwhile |
| **G-02** | Rubric is marked **"Draft for course approval"**; descriptors on `Descriptors` sheet are truncated (English anchors only). | Marks could change | Request final rubric before freezing scope |
| **G-03** | **Asset set is "suggested"** (p. 5), yet starting scope says "ten assets" (p. 1). | Ambiguity over whether the exact 10 are compulsory | Use the suggested set; document choice |
| **G-04** | **Which asset gets which plan type is unspecified.** | Affects seed realism and demo | Architect assigns as in §8.6; defend rationale |
| **G-05** | **Six seed work orders' distribution** across states is unspecified ("useful lifecycle states"). | Need all transitions demonstrable | Use 1/1/2/2 (+ returned history) as §8.6 |
| **G-06** | **Who may create work orders?** Role table only says CE "manage plans and assignments". | Permission matrix incomplete | Lets CE create/assign; optionally Technician? Default CE only |
| **G-07** | **Who may manage spare master data / stock corrections?** | Security/audit design | CE only; corrections as adjustment movements |
| **G-08** | **What exactly may a Viewer / Technician see** ("authorised screens")? | Row-level access scope undefined | Viewer: all read-only; Technician: assigned WOs + assets/plans/spares read-only |
| **G-09** | **Are calendar intervals allowed in months?** Manual only defines positive integer **days** and warns about month end. | Due engine complexity | Use days only; state limitation |
| **G-10** | **Whether "Due" includes "Overdue"** is left to the student to *explain*, not prescribed. | Dashboard consistency | Define explicitly (recommend separate tiles, one plan one row) |
| **G-11** | **Framework choice** is open (Django, Laravel or lecturer-approved). Existing folder is a Laravel workspace. | Architecture decision | Laravel (see final response); confirm no objection |
| **G-12** | **Student ID / hostname / domain / host not supplied.** | Deployment criterion C5 | Use `s79244` (folder ID) as identifier; confirm exact hostname |
| **G-13** | **Whether the 6 AI records must come from distinct tools/episodes** — manual says "at least six substantive episodes". | Evidence planning | Record six real episodes; can be same tool |
| **G-14** | **Export format** is not specified beyond CSV in an optional-improvement list, yet rubric C3 L4 says "exports". | Mandatory vs optional ambiguity | Implement CSV export; low cost |
| **G-15** | **Report length/format, submission channel, demonstration booking** unknown. | Deliverable compliance | From brief (G-01) |
| **G-16** | **Course budget ceiling** unknown ("lecturer-approved budget", "cost ceiling" template). | Tool/domain choice | Default to free/local + free Cloudflare tier; request figure |

### 12.2 Conflicts / tensions

| ID | Observation | Resolution |
|---|---|---|
| **C-01** | Manual calls itself guidance that "does not add hidden marking requirements" (p. 1), yet contains very specific seed counts and rules that the rubric rewards (C1–C3). | **Treat the manual's concrete rules as de facto required**; they are the only detailed specification provided. Flag to lecturer. |
| **C-02** | "Ten assets are a suggested seed dataset… students may select another coherent set" vs "Use one fictional vessel, ten assets…" (required starting scope). | Follow the exact 10; safest. |
| **C-03** | Rubric is draft; descriptors sheet truncated. | Request final; design to the manual which is consistent with rubric level-4 language. |
| **C-04** | Manual says AI-in-app is optional, but rubric C4 requires AI *development process* evidence. | Do not confuse the two: process evidence mandatory, in-app AI optional. |
| **C-05** | "Docker at least for local execution" while deployment uses Cloudflare Tunnel on an always-on host. | Use Docker locally; document host deployment (can also be Docker). |
| **C-06** | Manual says do not claim ownership/availability until DNS confirmed, but C5 requires a live student hostname. | Confirm DNS early (Week 1 risk). |

### 12.3 Technical challenges

1. **Concurrency** — two simultaneous "create work order" requests. Application "check then insert" is racy; needs partial unique index + violation handling (C2/C3, TC09).
2. **Idempotent closure** — repeated close must not double-advance baselines or double-deduct stock (TC10).
3. **Timezone/date boundaries** — `timestamptz` vs local `date`; equality at midnight Asia/Kuala_Lumpur (TC01–TC03).
4. **Dual-trigger logic** — OR not AND; count once; state cause (TC05/TC06).
5. **Baseline semantics** — actual completion date/hours, not approval time (TC10).
6. **Stock integrity under concurrent issues** — row locking + non-negative constraint (TC16).
7. **Missing data vs zero** — nullable baselines, incomplete-hours UI state (TC08).
8. **Server-side authorisation on every route**, including direct requests (TC11/TC12).
9. **Reproducible Docker + PostgreSQL volume + migrations + seed** from clean checkout (TC19).
10. **Verified restore**, not just a dump command (TC18).
11. **Always-on public host + Cloudflare Tunnel + HTTPS + student-ID hostname** (TC20).
12. **Evidence discipline** — 15+ traceable tests, 6 AI records, skill/agent/MCP proof, within ~60 hours.

### 12.4 Recommended improvements (NOT lecturer requirements)

| ID | Recommendation | Benefit |
|---|---|---|
| R-01 | Pure `DueStatusCalculator` domain service with `Carbon::setTestNow()` unit tests | Makes C3/C7 boundaries trivial and provable |
| R-02 | `plan_baseline_history` table | Proves baseline advances; strengthens C2/C6 |
| R-03 | `work_order_events` immutable log | Satisfies "preserve earlier submission" and supports C9 traceability |
| R-04 | Idempotency keys on mutation endpoints | Prevents duplicate effects beyond TC10 |
| R-05 | Read-only DB role for reports + separate migration role | Least privilege (C6) |
| R-06 | Feature tests for policies + HTTP-level tests for direct requests | Proves server enforcement (TC11/TC12) |
| R-07 | Export a single "evidence pack" (test log CSV + screenshots + commit SHA) | Eases D5/D7 |
| R-08 | QR code links to asset pages; mobile-friendly forms | Optional manual suggestion (p. 20), no extra marks |
| R-09 | Filters by subsystem/criticality/assignee/status | Matches optional manual improvement; helps dashboard demo |
| R-10 | "As at" timestamp + timezone on due screens | Reproducibility for marker |
| R-11 | Seeder parameterised by a fixed `TEST_DATE` env | Repeatable boundary tests |
| R-12 | Documented correction workflow (reopen → adjust → re-close with reason) | Satisfies "approved workflow may change closed record" |
| R-13 | Do **not** build predictive/condition monitoring/offline features | Manual explicitly scopes them out |

---

## 13. Assumptions register (to be confirmed)

| ID | Assumption | If wrong |
|---|---|---|
| A-01 | The supplied manual + rubric are the controlling requirements | Re-baseline against the missing brief (G-01) |
| A-02 | The suggested 10-asset set is acceptable | Swap dataset; schema unchanged |
| A-03 | Framework may be Laravel | Switch to Django; schema/domain design unchanged |
| A-04 | Calendar intervals in days only | Add month logic + end-of-month rules |
| A-05 | Due and Overdue shown as separate, reconcilable tiles (Overdue a subset) | Adjust dashboard definition |
| A-06 | Student identifier is `79244` (from folder name) | Change hostname/seeds |
| A-07 | CSV export is sufficient for "exports" | Add XLSX/PDF if brief says so |
| A-08 | Stock deducts at **issue**, returns are positive movements | Change single deduction point |
| A-09 | Chief Engineer creates work orders | Adjust matrix + policy |
| A-10 | No real vessel/crew data will ever be entered | N/A — synthetic only |

---

## 14. Traceability: requirement → rubric → test

| Requirement group | Rubric | Lecturer test IDs | Evidence form |
|---|---|---|---|
| Vessel/assets/plans dataset | C1, C2 | TC19 seed check | Charter, requirement records |
| Due engine (calendar/hours/dual) | C3 | TC01–TC06, TC08 | Test register |
| Meter readings | C3 | TC07 | Test register |
| Work-order lifecycle | C3, C6 | TC10, TC13, TC14, TC15 | Lifecycle screenshots + DB |
| Permissions | C6 | TC11, TC12, TC20 | HTTP response + DB before/after |
| Spares | C3 | TC16 | Ledger + balance |
| Concurrency / idempotency | C2, C3 | TC09, TC10 repeat | DB counts |
| Dashboard/exports | C3, C8 | reconcile counts | Screenshot + CSV |
| Persistence/backup | C5, C7 | TC17, TC18 | Restore log |
| Docker/clean install | C5, C7 | TC19 | README + commands |
| Deployment/HTTPS/hostname | C5 | TC20 | URL + second-device proof |
| Audit trail | C6 | TC11–TC13 | Audit rows |
| AI process (skill/agents/MCP/6 records) | C4 | — | 6 AI records + role cards + MCP record |
| Report/demo | C8, C9 | — | Report, 15-min rehearsal |

---

## 15. Open questions for the lecturer

1. **G-01** Please provide the **approved assessment brief** (dates, report format/length, submission channel, demonstration schedule, budget ceiling).
2. **G-02** Is the rubric final, and can the full bilingual descriptors be provided?
3. **G-03** Is the suggested 10-asset set acceptable, or is a different coherent set preferred?
4. **G-06/G-07** May Chief Engineers create work orders? Who may manage spare master data and stock corrections?
5. **G-08** What exactly may Viewers and Technicians read (all records vs assigned only)?
6. **G-09** Are month-based calendar intervals permitted, or days only?
7. **G-11** Is **Laravel** an acceptable framework for this cohort?
8. **G-12** What is the exact approved hostname format and hosting arrangement (student ID `79244`)?
9. **G-14** Is **CSV export** sufficient to satisfy "exports" in C3 level 4?
10. **G-16** What is the **spending ceiling** and is an institutional host/domain provided?

---

## 16. Referenced sources

Supplied materials:
- `FE-MNA4003-S12026-2027_Student_Manual.pdf` — 52 pages.
- `FE-MNA4003-S12026-2027_Rubric.xlsx` — sheets `Marking`, `Descriptors`.
- `FE-MNA4003-S12026-2027_Student_Templates.docx` — 7 evidence forms + D1–D7 manifest.

Secondary references cited inside the manual (not independently required reading): IMO ISM Code [M1]; AMSA Marine Notice 2024/08 on planned maintenance on ships [M2]; PostgreSQL backup/restore and `pg_dump` docs [T12, T13]; Docker Compose and secrets docs [T10, T11]; Cloudflare Tunnel / Workers / Hyperdrive / Containers docs [T14–T20]; MCP spec [T27]; AGENTS.md [T23]; harness-specific skill/MCP docs [T24–T26, T28–T32].

---

## 17. Bottom line

The assignment is **not** "build every CMMS feature". It is:

> Build a **small, correct, reproducible, secure, PostgreSQL-backed CMMS** for **one fictional vessel**, with **8 plans across 3 scheduling modes**, a **4-state work order lifecycle**, **spare stock integrity**, **server-enforced roles**, **audited history**, **dashboard/exports**, and **provable tests + deployment + AI-development evidence**.

The highest-value engineering targets, in mark order, are:
1. **C3 (25 marks)** — boundary-correct due engine + complete lifecycle + consistent stock/baseline.
2. **C2/C6/C7 (30 marks)** — PostgreSQL constraints, server-side security, repeatable tests.
3. **C5 (10)** — Docker + Cloudflare + student hostname + verified restore.
4. **C4 (10)** — genuine skill/agents/MCP/6 records.
5. **C1/C8/C9 (25)** — coherent maritime story, usable docs, confident individual explanation.

Everything else is optional.
