# Phase 1 — Completion Report

**Project:** Shipyard CMMS — MNA4003 (FE-MNA4003-S12026-2027)
**Student ID:** **S79244** (lowercase `s79244` in host and volume names)
**Phase:** 1 — Docker platform, PostgreSQL, timezone, authentication, roles, server-side RBAC
**Date:** 24 November 2026
**Verdict:** **PASS** — all Phase 1 exit criteria met, 21 automated tests pass on an isolated test database, and the running application was verified over HTTP for every role.

**Documents preserved unchanged (verified by diff):** `docs/requirements-analysis.md`, `docs/proposed-system-modules.md`, `docs/implementation-plan.md`.
**Not done and not attempted:** no commit, no push, no Phase 2 entity or module work.

---

## 1. Executive summary

| Item | Result |
|---|---|
| Phase 1 exit criterion (`P1` in `docs/implementation-plan.md` §1458: "working login for 4 seeded roles; 403 on wrong role", evidence "Login/logout; role gate test") | **Met.** Five seeded accounts (three active roles + one inactive), server-side role gate returns 403 for Technician and Viewer, login/logout verified end to end |
| Containers | `cmms_s79244-app-1` and `cmms_s79244-db-1` both **healthy** |
| Application URL | **http://localhost:8080** (bound to `127.0.0.1` only) |
| Runtime | Laravel **13.43.0**, PHP **8.4.28**, PostgreSQL **18.7**, Node **22** (build image), Docker Compose **2.40.3** |
| Database | PostgreSQL 18 inside Compose; **no host port published**; test database `cmms_test` separate from development database `cmms` |
| Timezone | `Asia/Kuala_Lumpur` enforced by configuration and confirmed at runtime |
| Automated tests | **21 passed, 74 assertions** on `cmms_test`; development database provably unchanged by the suite |
| Incident | One development-database reset occurred during Phase 1 testing; root cause identified, data restored, and two independent guards added. Fully documented in §7 |
| Verdict | **PASS** |

---

## 2. Scope delivered

Phase 1 delivers the platform and access-control foundation only. No CMMS business entities (assets, maintenance plans, work orders, spares, audit) exist yet — those begin in Phase 2.

1. Reproducible Docker Compose platform (`app` + `db`) with multi-stage image.
2. PostgreSQL 18 as the only database, non-exposed.
3. Forced `Asia/Kuala_Lumpur` timezone.
4. Session authentication (login, logout, throttling, inactive-account rejection).
5. Three application roles and a native PostgreSQL `user_role` enum.
6. Server-side role enforcement through middleware, independent of the UI.
7. Blade login screen, authenticated dashboard, 403 page, and a Chief-Engineer-only user list used as a live RBAC check.
8. Automated tests plus an isolated test database with hard safety gates.

---

## 3. Files delivered

### 3.1 Platform and containers

| File | Purpose |
|---|---|
| `Dockerfile` | Multi-stage build: `vendor-development`, `vendor-production`, `assets` (Node 22), then `base` (`php:8.4-apache` with `pdo_pgsql`, `pgsql`, `intl`, `bcmath`, `zip`), `development` and `production` targets |
| `compose.yaml` | `app` (bound `127.0.0.1:8080`) and `db` (`postgres:18-alpine`, **no published port**), healthchecks, named volumes (`pgdata`, `vendor`, `assets`, `storage`, `bootstrap-cache`) |
| `docker/entrypoint.sh` | Waits for PostgreSQL via PDO, ensures writable directories, `package:discover`, optional `RUN_MIGRATIONS` |
| `docker/apache/000-default.conf` | Apache vhost pinned to `public/` with `AllowOverride All`, no directory listing |
| `docker/postgres/init/01-create-test-database.sh` | Creates `cmms_test` on first volume initialisation |
| `.dockerignore`, `.gitignore`, `package-lock.json` | Build context hygiene, secret exclusion, reproducible npm install |
| `.mcp.json`, `opencode.json` | Laravel Boost MCP launched **inside** the app container (`docker compose exec -T app php artisan boost:mcp`) so the agent sees PHP 8.4, not the host runtime |

### 3.2 Application code

| File | Purpose |
|---|---|
| `app/Enums/UserRole.php` | `ChiefEngineer`/`Technician`/`Viewer` backed enum with `label()` |
| `app/Models/User.php` | UUID keys (`HasUuids`), enum + boolean casts, `hasRole()`, `isChiefEngineer()`, `isTechnician()`, `isViewer()` |
| `app/Http/Controllers/Auth/LoginController.php` | Login form, throttled `Auth::attempt` (active accounts only), session regeneration, logout with session invalidation |
| `app/Http/Requests/Auth/LoginRequest.php` | Credential validation |
| `app/Http/Controllers/DashboardController.php` | Authenticated landing page |
| `app/Http/Controllers/UserController.php` | Chief-Engineer-only user list |
| `app/Http/Middleware/EnsureRole.php` | `role:` middleware — `abort(403)` when the role does not match, never relying on view-level hiding |
| `app/Http/Middleware/EnsureUserIsActive.php` | Terminates the session of a deactivated account on its next request |
| `app/Policies/UserPolicy.php` | Policy-level authorisation for user records |
| `bootstrap/app.php` | Registers `active` and `role` middleware aliases; `/up` health endpoint |
| `routes/web.php` | `/` → `/dashboard`; guest `login`; `auth` + `active` dashboard; `auth` + `active` + `role:chief_engineer` `/admin/users`; `auth` logout |
| `resources/views/**` | `layouts/app`, `layouts/guest`, `auth/login`, `dashboard/index`, `admin/users/index`, `errors/403` (Tailwind) |

### 3.3 Database

| File | Purpose |
|---|---|
| `database/migrations/0001_01_01_000000_create_users_table.php` | Native `user_role` enum created idempotently (`DO $$ … pg_type … $$`), UUID `users` table with enum `role` and `is_active`, plus `password_reset_tokens` and `sessions` |
| `database/factories/UserFactory.php` | Role states (`chiefEngineer()`, `technician()`, `viewer()`, `inactive()`) |
| `database/seeders/UserSeeder.php` | Five synthetic accounts; refuses to run with a placeholder seed password |
| `database/seeders/DatabaseSeeder.php` | Calls `UserSeeder` |

### 3.4 Configuration and test infrastructure

| File | Purpose |
|---|---|
| `.env.example` | Non-secret template on `pgsql`, `DB_HOST=db`, `DB_DATABASE=cmms`, `DB_TEST_DATABASE=cmms_test`, `APP_TIMEZONE=Asia/Kuala_Lumpur`, `APP_URL=http://localhost:8080`, empty `CMMS_SEED_PASSWORD` |
| `config/cmms.php` | `student_id`, `test_date`, `seed_password` (secret always comes from untracked `.env`) |
| `phpunit.xml` | Unit + Feature suites, testing environment values, PostgreSQL connection |
| `tests/bootstrap.php` | Forces the testing environment **before** Laravel boots and refuses to run when isolation cannot be proven |
| `tests/TestCase.php` | Second guard executed before `parent::setUp()` (i.e. before `RefreshDatabase` could act) |
| `tests/Feature/*`, `tests/Unit/*` | Authentication, authorisation, seeder, database, enum and root-redirect tests |

### 3.5 Documentation

`docs/phase-0-environment-report.md`, `docs/decision-log.md` (Phase 0 outputs), and this report.

---

## 4. Running environment and how to operate it

### 4.1 Current status

| Check | Evidence |
|---|---|
| `docker compose ps` | `cmms_s79244-app-1` — Up (healthy) — `127.0.0.1:8080->80/tcp`; `cmms_s79244-db-1` — Up (healthy) — `5432/tcp` |
| Health endpoint | `GET http://127.0.0.1:8080/up` → **200** |
| Runtime | PHP 8.4.28, Laravel 13.43.0, timezone `Asia/Kuala_Lumpur`, URL `localhost:8080` |
| Database | PostgreSQL 18.7, database `cmms`, 5 seeded users, 3 migrations applied |
| Host PostgreSQL listener | none — `ss -ltn` shows no `:5432`; `docker compose port db 5432` returns `:0` |
| Config cache | absent (`bootstrap/cache/config.php` not present), so `.env` values are live |

### 4.2 Commands

```bash
# Start / stop / restart the platform
docker compose up -d
docker compose stop
docker compose restart

# Status and logs
docker compose ps
docker compose logs -f app

# Shell and Artisan inside the container (never on the host)
docker compose exec app bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --class=UserSeeder --force

# Test suite (isolated test database, forced by tests/bootstrap.php)
docker compose exec app php artisan test --compact

# Formatting
docker compose exec app vendor/bin/pint --dirty --format agent

# Database backup (optional, outside the app)
docker compose exec -T db pg_dump -U cmms -d cmms -Fc > backups/cmms-$(date +%F).dump
```

> `RUN_MIGRATIONS=true` in the environment lets the entrypoint migrate on start; it is left unset locally so that migrations are always an explicit action.

### 4.3 Application URL

**http://localhost:8080** — reached through the loopback binding on the host. `/` redirects to `/dashboard`, which redirects unauthenticated visitors to `/login`.

---

## 5. Authentication and role-based access control

### 5.1 Role matrix

| Role | Enum value | Dashboard | `/admin/users` | Notes |
|---|---|---|---|---|
| Chief Engineer | `chief_engineer` | 200 | **200** | Full Phase 1 visibility; future approver/creator role |
| Technician | `technician` | 200 | **403** | Field execution role |
| Viewer | `viewer` | 200 | **403** | Read-only role |
| Inactive account | any role, `is_active = false` | redirect to `/login` | redirect to `/login` | Login refused and existing sessions terminated |

### 5.2 Enforcement design

- **Two independent server-side checks.** `EnsureRole` (`role:` alias) and `EnsureUserIsActive` (`active` alias) run as route middleware; the 403 page is rendered by Laravel's exception handler, not by hiding navigation.
- **No client-side trust.** The users page is reachable only through `auth` → `active` → `role:chief_engineer`; hiding the menu link is cosmetic only.
- **Authentication hardening.** `Auth::attempt` includes `is_active => true`, so deactivated accounts cannot authenticate; the login request is rate limited to 5 attempts per email+IP per 60 seconds and the limiter is cleared on success; the session ID is regenerated on login and invalidated plus CSRF-token regenerated on logout.
- **Generic failure message.** Both a wrong password and an inactive account return the same message, preventing account-state disclosure.
- **Schema-level guarantee.** `role` is a native PostgreSQL `user_role` enum with `NOT NULL DEFAULT 'viewer'`, so an invalid role cannot exist in the database.

### 5.3 Demo accounts

| Account | Role | State |
|---|---|---|
| `chief@cmms.test` | Chief Engineer | active |
| `tech1@cmms.test` | Technician | active |
| `tech2@cmms.test` | Technician | active |
| `viewer@cmms.test` | Viewer | active |
| `inactive@cmms.test` | Viewer | inactive |

**Password:** never stored in the repository or in this report. It is generated by the owner into the untracked `.env` as `CMMS_SEED_PASSWORD`, and `UserSeeder` refuses to run if it is empty or still `change-me`. See §9.

---

## 6. Verification evidence

### 6.1 Automated test suite

Command: `docker compose exec app php artisan test --compact`

```
Tests:    21 passed (74 assertions)
Duration: 2.78s
```

| Test class | Tests | What is proven |
|---|---|---|
| `Feature/AuthenticationTest` | 7 | Login screen renders; active user authenticates; wrong password rejected; inactive user rejected; logout; authenticated user redirected away from `/login`; throttling after five failures |
| `Feature/AuthorizationTest` | 6 | Guest redirected from dashboard and role-protected page; Chief Engineer sees users; Technician and Viewer get **403**; deactivated user's live session is terminated; every active role reaches the dashboard |
| `Feature/UserSeederTest` | 2 | Seeder creates exactly 5 accounts covering every role plus one inactive; seeder refuses a placeholder password |
| `Feature/DatabaseConnectionTest` | 2 | Suite runs on `pgsql` against database **`cmms_test`**; server version starts with `18.` |
| `Unit/UserRoleTest` | 1 | Role values and labels are stable |
| `Feature/ExampleTest` | 1 | `/` redirects to `/dashboard` |

`vendor/bin/pint --test` reports `passed` for `app database routes tests config bootstrap/app.php`.

### 6.2 HTTP verification against the running containers

Performed with `curl` against `http://127.0.0.1:8080`, using CSRF-protected form posts and per-user cookie jars.

| Scenario | Result |
|---|---|
| `GET /up` | 200 |
| `GET /dashboard` (guest) | 302 → `/login` |
| `GET /admin/users` (guest) | 302 → `/login` |
| `POST /login` Chief Engineer | 302 → dashboard; dashboard 200 showing "Chief Engineer" |
| `GET /admin/users` Chief Engineer | **200**, lists all seeded accounts |
| `POST /login` Technician | 302; dashboard 200 showing "Technician" |
| `GET /admin/users` Technician | **403**, "Permission denied" page |
| `POST /login` Viewer | 302; dashboard 200 showing "Viewer" |
| `GET /admin/users` Viewer | **403**, "Permission denied" page |
| `POST /logout` then `GET /dashboard` | 302 → `/login`; session no longer authenticated |
| `POST /login` inactive account | 302 → `/login` with generic credential error; still guest |
| `POST /login` wrong password | 302 → `/login` with the same generic error; still guest |

No cookies, tokens or passwords were printed or recorded.

### 6.3 Database isolation verification

The Phase 1 incident in §7 makes this the most important control. Three independent proofs:

1. **Positive proof.** `DatabaseConnectionTest` asserts `DB::connection()->getDatabaseName() === 'cmms_test'` on every run, and all 21 tests pass while using `RefreshDatabase`.
2. **Negative proof (snapshot diff).** An ordered snapshot of the development database (database name, server version, migration list, all five users with role and state, public table count) was taken before and after a full test run:

   ```
   === DIFF ===
   DEVELOPMENT DATABASE UNCHANGED BY TEST SUITE
   ```

   Development database after the run: `cmms`, PostgreSQL 18.7, 3 migrations, 5 users, 9 tables.

3. **Guard proof.** Both safety gates were deliberately triggered:
   - `docker compose exec -e DB_TEST_DATABASE=cmms app php artisan test` → `Refusing to run tests: DB_TEST_DATABASE must end with '_test' (got [cmms]).`
   - `php artisan config:cache` followed by the suite → `Refusing to run tests: configuration is cached at bootstrap/cache/config.php.` After `php artisan config:clear`, the suite passed again.

### 6.4 Security and repository hygiene

| Check | Result |
|---|---|
| Secret scan of `DB_PASSWORD`, `CMMS_SEED_PASSWORD`, `APP_KEY` values across the working tree (excluding `.env`, `vendor`, `node_modules`, `.git`) | **no leaks outside `.env`** |
| Pattern scan for `base64:` keys, AWS keys, private-key headers in tracked files | none |
| `.env` status | ignored by `.gitignore` (`/.env`); not tracked |
| Tracked files | 8 — the lecturer's `Rubric.xlsx`, `Student_Manual.pdf`, `Student_Templates.docx` (with their `Zone.Identifier` sidecars) and `docs/requirements-analysis.md`, `docs/proposed-system-modules.md`. All Phase 1 work is uncommitted |
| `vendor/`, `node_modules/`, SQLite files | not tracked |
| PostgreSQL exposure | `docker compose port db 5432` → `:0`; no host listener on 5432 |
| Web exposure | `127.0.0.1:8080` only (loopback), not `0.0.0.0` |

---

## 7. Incident record — development database reset

This incident is documented because it materially affected Phase 1 and because its controls are part of the deliverable.

### 7.1 What happened

While Phase 1 tests were being developed, an early `php artisan test` run executed `migrate:fresh` against the **development** database `cmms` instead of the test database, dropping the `users`, `sessions`, `cache` and `jobs` tables and the seeded accounts. Retrying migrations then failed with:

- `SQLSTATE[42710]: Duplicate object: 7 ERROR: type "user_role" already exists`
- `SQLSTATE[42P01]: Undefined table: 7 ERROR: relation "sessions" does not exist`

### 7.2 Root cause

Two contributing causes, in order of importance:

1. **PHPUnit `<env>` values did not override the container environment.** The `app` service loads runtime variables from `env_file: .env` (`DB_DATABASE=cmms`). Because those variables already exist in the process environment, PHPUnit's `<env name="DB_DATABASE" value="cmms_test">` could not replace them, so `RefreshDatabase` operated on `cmms`. The `force="true"` attributes in `phpunit.xml` were added in response, but on their own they are environment-dependent and insufficient as a guarantee.
2. **The enum creation was not idempotent.** The original migration ran a bare `CREATE TYPE user_role`, so any partially applied or re-run migration aborted with `42710`, leaving the schema in an inconsistent state and blocking recovery.

### 7.3 Immediate recovery

- Re-applied migrations to `cmms` (`php artisan migrate --force`).
- Re-seeded `UserSeeder` from the untracked `CMMS_SEED_PASSWORD`.
- Confirmed the five expected accounts, their roles and their active/inactive state.

No backup existed at the time; the database contained only synthetic seed data, so no real data was lost. This is why a backup procedure now appears in §4.2.

### 7.4 Corrective actions (implemented and verified)

| Action | Implementation |
|---|---|
| Force the testing environment before Laravel boots | `tests/bootstrap.php` `putenv()`/`$_ENV`/`$_SERVER` overrides for `APP_ENV=testing`, `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, plus array cache/session and sync queue. Loaded via `bootstrap="tests/bootstrap.php"` |
| Refuse a non-isolated test database | Bootstrap gate: `DB_TEST_DATABASE` must end in `_test` and must differ from the development database read from `.env` |
| Refuse a cached configuration | Bootstrap gate: exits with an explanatory message if `bootstrap/cache/config.php` exists, because a cached config can silently ignore overrides |
| Second gate immediately before destructive work | `tests/TestCase::setUp()` re-checks `APP_ENV === 'testing'` and the `_test` suffix **before** `parent::setUp()`, i.e. before `RefreshDatabase` can run |
| Idempotent enum creation | Migration uses `DO $$ … IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'user_role') … $$`, so a partial state is recoverable |
| Separate test database | `cmms_test` created by `docker/postgres/init/01-create-test-database.sh` (and present and verified on the running instance) |

### 7.5 Verification evidence

- All 21 tests pass with the gates active.
- Deliberate misconfiguration (`DB_TEST_DATABASE=cmms`) is refused with a non-zero exit before any test executes.
- A cached configuration is refused with an actionable message; clearing the cache restores a passing run.
- Before/after development-database snapshots are byte-identical (§6.3).
- `cmms` currently holds its 3 migrations and 5 seeded users.

---

## 8. Phase 1 exit-criteria check

| Requirement | Evidence | Status |
|---|---|---|
| Docker platform reproducible and container-only | `Dockerfile` + `compose.yaml`; both services healthy; all PHP/Artisan/test commands run via `docker compose exec` | PASS |
| PostgreSQL 18, not externally exposed | `DatabaseConnectionTest` asserts `18.`; `docker compose port db 5432` → `:0` | PASS |
| Timezone `Asia/Kuala_Lumpur` | `APP_TIMEZONE` in `.env`/`.env.example`; confirmed by `php artisan about` | PASS |
| Authentication | Login form, throttled login, logout, session regeneration; HTTP and automated evidence | PASS |
| Three roles | `UserRole` enum, native `user_role` PostgreSQL enum, seeded accounts | PASS |
| Server-side RBAC | `EnsureRole` middleware + `UserPolicy`; 403 for Technician and Viewer at HTTP level and in tests | PASS |
| Login UI | Tailwind Blade login screen served at `/login` | PASS |
| Tests | 21 passed / 74 assertions on `cmms_test` | PASS |
| Isolated test database | Two gates plus positive/negative/guard proofs | PASS |
| No secrets in the repository | Value scan and pattern scan clean; `.env` ignored | PASS |
| Lecturer documents preserved | `git diff` empty for the two tracked analysis documents; `implementation-plan.md` unmodified | PASS |
| Scope discipline | No Phase 2 entities, no commit, no push | PASS |

---

## 9. Using the demo accounts securely

The demo password is **not** in Git, not in this report, and not in `.env.example`.

1. The owner sets a strong, unique value in the untracked `.env`:

   ```bash
   # Generate a password without echo and store it only in .env
   python3 - <<'PY'
   import secrets, pathlib, re
   p = pathlib.Path('.env')
   text = p.read_text()
   value = secrets.token_urlsafe(24)
   text = re.sub(r'^CMMS_SEED_PASSWORD=.*$', f'CMMS_SEED_PASSWORD={value}', text, flags=re.M)
   p.write_text(text)
   PY
   ```

2. Re-seed with that value: `docker compose exec app php artisan db:seed --class=UserSeeder --force`. The seeder hashes the value with bcrypt and never stores it in plain text.
3. Before any demonstration or submission: rotate the value as above, confirm `.env` is still ignored (`git check-ignore -v .env`), and re-run the secret scan.
4. Treat the accounts as synthetic test identities. They are not production credentials, and `@cmms.test` addresses are deliberately non-routable.
5. Never paste the password into chat, screenshots, commit messages or the report. Share it with the lecturer only through the agreed secure channel.

---

## 10. Limitations and deferred work

| Limitation | Reason / next step |
|---|---|
| `APP_ENV=local` with `APP_DEBUG=true` | Correct for a development platform; production target image sets `APP_ENV=production` and Phase 11 will add the production environment and `APP_KEY` handling |
| `README.md` is still the stock Laravel readme | Documentation is completed in Phase 12 (P12); not required by Phase 1 |
| No backup has been automated yet | Manual `pg_dump` procedure documented in §4.2; automated backup/restore verification is P10 |
| Login throttle uses the default cache store | Single-node deployment, so the file/array behaviour is adequate; a shared cache is only needed if the app is scaled out |
| No password reset / email verification flow | Accounts are administrator-seeded in Phase 1; not part of the Phase 1 exit criteria |
| `/admin/users` is read-only | Deliberate: it exists to prove the role gate. User administration is Phase 2+ scope |
| No HTTPS / public hostname | Phase 11 (Cloudflare Tunnel, `s79244-cmms.<domain>`) |
| `composer.json` pins `platform.php` to `8.4.27` while the runtime reports 8.4.28 | Intentional floor for lock resolution; both are PHP 8.4, and the image tag is `php:8.4-apache` |
| Dockerfile dependency on a project `.npmrc` was removed this session | `.npmrc` is intentionally gitignored, which would break a fresh-clone build; the assets stage now relies on the explicit `npm ci --ignore-scripts --no-audit --no-fund` flags and was rebuilt successfully with `docker build --target assets` |
| `bootstrap/cache/*.php` regenerated by tooling | These are ignored build artefacts inside a named volume and are regenerated on boot; Pint was re-run only on tracked source paths afterwards |
| Work is uncommitted | Nothing was committed or pushed; awaiting explicit approval |

---

## 11. Recommendation

Phase 1 is complete and meets its exit criteria. The next action is review of this report, followed — only with explicit approval — by committing the Phase 1 baseline and starting Phase 2 (asset register and maintenance-plan entities). No Phase 2 work has begun.
