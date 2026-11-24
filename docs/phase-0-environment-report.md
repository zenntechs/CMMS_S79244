# Phase 0 — Environment Readiness Report

**Project:** Shipyard CMMS — MNA4003 (FE-MNA4003-S12026-2027)
**Student ID:** **S79244**
**Phase:** 0 — environment inspection and preparation only
**Date:** 19–20 November 2026
**Status:** **COMPLETE — 20 November 2026.** Docker Engine and Docker Compose are installed, accessible and verified. All required images execute correctly. A Laravel skeleton was discovered in the workspace on 20 November that was **not created by this session**; it is outside Phase 0 scope and is recorded in §11. No Phase 1 work was performed by this session.

**Documents preserved unchanged:** `docs/requirements-analysis.md`, `docs/proposed-system-modules.md`, `docs/implementation-plan.md`.

---

## 1. Executive summary

| Item | Result |
|---|---|
| Host OS | Ubuntu 26.04.1 LTS on WSL2, kernel 6.18.58.1, systemd enabled |
| Docker Engine | **Installed, running and accessible** — 29.1.3, API 1.52, overlayfs, systemd cgroup |
| Docker Compose | **Installed and verified** — 2.40.3+ds1-0ubuntu1 |
| Docker socket access | **Granted** — `zensworth` is a member of the `docker` group (gid 106) |
| PHP / Composer | Host PHP 8.5.4 and Composer 2.9.5 now present (dev-only); authoritative runtime is PHP 8.4 in Docker |
| Node / npm | Host Node 22.22.1 / npm 9.2.0 now present (dev-only); build image is `node:22` |
| PostgreSQL client (`psql`, `pg_dump`) | Host client not installed; use `docker compose exec db` for dump/restore |
| Cloudflare Tunnel (`cloudflared`) | Not installed (not needed until Phase 11) |
| `unzip` | Installed during Docker setup verification |
| Git repository | Healthy; branch `main`; remote reachable. A large set of Laravel skeleton files is untracked (see §11) |
| Network | GitHub and Docker Hub reachable |
| Resources | 926 GB free, 7.4 GB RAM, 16 vCPU — sufficient |
| Version compatibility | **Verified** — Laravel 13.43 + PHP 8.4.27 + PostgreSQL 18.7 + Docker 29.1.3 + Compose 2.40.3 + Node 22 |
| Phase 0 verdict | **PASSED.** Environment gate satisfied. Phase 1 is **not** authorised and was not started by this session. |

---

## 2. Host and repository state

### 2.1 Operating environment

| Property | Value |
|---|---|
| OS | Ubuntu 26.04.1 LTS (Resolute Raccoon) |
| Platform | WSL2 (`microsoft-standard-WSL2`), systemd enabled via `/etc/wsl.conf` |
| Kernel | 6.18.58.1 |
| User | `zensworth` (uid 1000), member of `sudo` |
| Sudo | Requires interactive password (`sudo -n` fails) |
| CPU / RAM | 16 vCPU / 7.4 GB total (5.8 GB free) |
| Disk | 930 GB available on `/` |
| Swap | 2.0 GB |
| Ports 80 / 8080 / 5432 / 8000 | Free |
| Timezone | Host not yet configured for the app; app will force `Asia/Kuala_Lumpur` |

### 2.2 Project structure

```
CMMS_S79244/
├── .git/
├── docs/
│   ├── requirements-analysis.md      (tracked, unchanged)
│   ├── proposed-system-modules.md    (tracked, unchanged)
│   └── implementation-plan.md        (untracked — created in previous step)
├── FE-MNA4003-S12026-2027_Student_Manual.pdf
├── FE-MNA4003-S12026-2027_Rubric.xlsx
└── FE-MNA4003-S12026-2027_Student_Templates.docx
```

- No Laravel application exists yet (correct for Phase 0).
- No `.env`, credentials, database dumps or backup files exist.
- `Zone.Identifier` sidecar files are tracked; they are harmless Windows metadata.
- No symlinks, no special files, no existing `vendor/` or `node_modules/`.

### 2.3 Git state

| Property | Value |
|---|---|
| Branch | `main`, tracking `origin/main` |
| Remote | `git@github.com:zenntechs/CMMS_S79244.git` (SSH, reachable) |
| Last commit | `52d9d0e Initial commit: Add CMMS assignment materials` |
| Working tree | Clean except `docs/implementation-plan.md` untracked |
| Tags | none |
| `.gitignore` | **absent** — created in Phase 0 (see §7) |
| Git identity | configured (`zenntechs <lqzzen@gmail.com>`) |
| Repository health | `git fsck` clean |
| `.git` size | 628 KB |

The SSH remote was verified with `git ls-remote`; the `main` head matches the local commit.

---

## 3. Toolchain inventory

### 3.1 Installed

| Tool | Version | Notes |
|---|---|---|
| Docker CLI / Engine | 29.1.3 (`docker.io` Ubuntu package) | daemon running as root via systemd |
| containerd | 2.2.2 | running |
| Git | 2.53.0 | |
| curl | 8.18.0 | |
| wget | 1.25.0 | |
| make | 4.4.1 | |
| gcc | 15.2.0 | not needed for the chosen stack |
| OpenSSL | 3.5.5 | |
| Python 3 | present | used only for inspection |
| snapd | 2.76.3 | not needed |

### 3.2 Resolved and outstanding items (20 November 2026)

| Tool | Status | Resolution |
|---|---|---|
| Docker Compose v2 | **Resolved** | 2.40.3+ds1-0ubuntu1 installed |
| Docker socket permission | **Resolved** | `zensworth` added to `docker` group (gid 106) |
| `unzip` | **Resolved** | installed |
| PHP 8.4 CLI | **Verified in container** | `php:8.4-apache` runs PHP 8.4.27 |
| Composer | **Available** | `composer:2` image; host Composer 2.9.5 also present |
| Node / npm | **Available** | `node:22` image (Node 22.23.5 / npm 10.9.9); host Node 22.22.1 / npm 9.2.0 |
| `psql`, `pg_dump`, `pg_restore` | Optional, host absent | Use `docker compose exec db ...` or install `postgresql-client-18` |
| `cloudflared` | Deferred | Install from official Cloudflare source in Phase 11 |
| PHP 8.4 host packages | Not required | Runtime is the container image |

**Runtime policy:** the authoritative stack remains **container-first** — PHP 8.4 in `php:8.4-apache` and PostgreSQL 18 in `postgres:18-alpine`. Host PHP 8.5.4, Composer and Node are development conveniences only. They must not be used to produce the assessed release, and `composer.json` should pin `config.platform.php` to 8.4 so dependencies resolve against the runtime version.

> Note: the base `php:8.4-apache` image does **not** include `pdo_pgsql`, `pgsql`, `bcmath`, `intl` or `zip`. The project Dockerfile must install these before Phase 1 migrations can run against PostgreSQL.

---

## 4. Docker assessment

### 4.1 Verified components (20 November 2026)

```
docker --version          → Docker version 29.1.3, build 29.1.3-0ubuntu4.1
docker compose version    → Docker Compose version 2.40.3+ds1-0ubuntu1
id -nG                    → zensworth adm cdrom sudo dip video plugdev users docker ollama
getent group docker       → docker:x:106:zensworth
/var/run/docker.sock      → srw-rw---- root:docker
docker info               → ServerVersion=29.1.3 | Driver=overlayfs
                            Root=/var/lib/docker | Cgroup=systemd | API=1.52
docker run --rm hello-world → "Hello from Docker!" (success)
```

The daemon (`dockerd`) and `containerd` run under systemd. Docker Desktop is not involved; this is native Docker inside WSL2. The user is now a member of the `docker` group and the daemon is reachable without `sudo`.

### 4.2 Compose package verification

`apt-cache policy docker-compose-v2` → **2.40.3+ds1-0ubuntu1** (Ubuntu 26.04 universe).

The package is now installed. `docker compose version` returns **2.40.3+ds1-0ubuntu1**, and the plugin is available at `/usr/libexec/docker/cli-plugins/docker-compose`.

### 4.3 Access status

**Resolved.** The socket remains `root:docker 660`, but `zensworth` is now a member of group `docker` (gid 106), and Docker commands succeed without `sudo`. This is a host permission change only; no engine reconfiguration was required.

**Security note:** membership of the `docker` group is effectively root-equivalent on the host. On a personal WSL2 instance used for this assignment this is the standard approach. A rootless Docker setup is more restrictive but adds complexity and is not recommended for this project.

### 4.4 Required images — verified

All required images are present and execute successfully:

| Image | Digest (repo@sha256) | Verification |
|---|---|---|
| `php:8.4-apache` | `php@sha256:884f64b47174…` | `php -v` → **PHP 8.4.27** |
| `postgres:18-alpine` | `postgres@sha256:ff64c9e3bdf8…` | `postgres --version` → **PostgreSQL 18.7** |
| `composer:2` | `composer@sha256:0c8e663f14c9…` | `composer --version` → Composer 2 (runs on PHP 8.5.12) |
| `node:22` | `node@sha256:e13726ca6041…` | Node **22.23.5**, npm **10.9.9** |

**Base PHP image module check:** `php:8.4-apache` provides `PDO`, `pdo_sqlite`, `mbstring`, `openssl`, `curl`, `xml`, `tokenizer`, but **not** `pdo_pgsql`, `pgsql`, `bcmath`, `intl` or `zip`. The project Dockerfile must install these. This is an expected Phase 1 build step, not a Phase 0 failure.

**Composer platform note:** the `composer:2` image currently runs PHP 8.5.12. To guarantee dependencies resolve for the PHP 8.4 runtime, `composer.json` must set `config.platform.php` to `8.4.27` (or use a PHP 8.4-based Composer stage). This will be applied in Phase 1.

Disk after image pulls: **31 GB used / 926 GB available**. Docker reports 5 images, 3.1 GB, no containers or volumes.

---

## 5. Compatibility verification (condition 5)

Verification performed **before** any Laravel install, using official sources on 19 November 2026.

| Component | Selected version | Verification | Source |
|---|---|---|---|
| Laravel | **13.x — 13.43.0 current**, skeleton requires `php ^8.3` | Laravel 13 released 17 Mar 2026; supports PHP 8.3–8.5; security fixes to 17 Mar 2028 | laravel.com/docs/releases |
| PHP | **8.4.x** (`php:8.4-apache`) | Laravel 13 supports 8.3–8.5. PHP 8.4: active support to 31 Dec 2026, security to 31 Dec 2028 | laravel.com/docs/releases; php.net/supported-versions |
| PostgreSQL | **18.x — 18.7 current** | Laravel supports PostgreSQL 10.0+. PG 18 released 25 Sep 2025, supported to 14 Nov 2030 | laravel.com/docs/13.x/database; postgresql.org/support/versioning |
| Docker Engine | **29.1.3** | Installed and running; Compose plugin format compatible | local `docker --version` |
| Docker Compose | **2.40.3** | Ubuntu package verified; plugin path verified | apt metadata + package contents |
| Node.js | **22.22.1** (or `node:22` image) | Vite 8.3.8 requires `^20.19.0 || >=22.12.0` | npm registry |
| Vite / Tailwind | Vite 8.x / Tailwind 4.x | Laravel 13 skeleton `package.json` | GitHub `laravel/laravel:13.x` |
| Composer | **2.9.5** (Ubuntu) / `composer:2` | Laravel 13 project installs with Composer 2 | apt metadata |
| PHPUnit | **12.5.12** | Laravel 13 skeleton dev dependency | skeleton `composer.json` |
| PHP extensions | `pdo_pgsql`, `pgsql`, `bcmath`, `mbstring`, `xml/dom`, `curl`, `zip`, `intl`, `openssl`, `tokenizer`, `ctype`, `fileinfo` | Required by Laravel/PostgreSQL and by the bcmath due-engine design | Laravel install requirements + plan |

**Image tags verified on Docker Hub:**

| Image | Tag | Last updated |
|---|---|---|
| `php` | `8.4-apache` | 2026-11-06 |
| `php` | `8.5-apache` | 2026-11-06 (alternative) |
| `postgres` | `18-alpine` | 2026-11-18 |
| `composer` | `2` | 2026-11-16 |
| `node` | `22` | 2026-11-11 |

**Conclusion:** the approved stack is compatible. No unsupported combination was found.

### 5.1 Required adjustment: Laravel 12 → Laravel 13

`docs/implementation-plan.md` §0.3 selected Laravel 12.x. Compatibility review shows:

- Laravel 12 bug fixes ended **13 Aug 2026**; security fixes end **24 Feb 2027**.
- Laravel 13 is the current release (17 Mar 2026), requires PHP 8.3+, supports PHP 8.4/8.5, and is security-supported to **17 Mar 2028**.

For a greenfield project starting November 2026, **Laravel 13.43.x** is the supported choice under condition 5. This is recorded as **ADR-002** in `docs/decision-log.md`. The implementation plan itself is preserved unchanged; this report and the decision log carry the correction.

### 5.2 PHP 8.4 vs 8.5

- **PHP 8.4** is retained to match the approved plan. It is fully supported by Laravel 13 and security-supported until December 2028.
- **PHP 8.5** is also supported and is the Ubuntu 26.04 default (8.5.4). It is a valid fallback if host-native PHP is ever needed, but the container-only approach makes it unnecessary.
- Both Docker tags exist and were verified.

**Recommendation:** pin `php:8.4-apache` and record its digest after the first pull for reproducibility. Do not float on `latest`.

---

## 6. Recommended installation approach

### 6.1 Preferred path — Docker-only toolchain (recommended)

**Why:** no host PHP/Node version drift; the same image runs locally and on the origin host; fewer packages; directly satisfies the Docker requirement; PHP 8.4 is available even though Ubuntu 26.04 only packages 8.5.

The following block required the student's **interactive sudo password**. It **has now been executed by the student and verified** (see §4):

```bash
# 1. Install Compose v2 and unzip (no removals expected)
sudo apt update
sudo apt install -y docker-compose-v2 unzip

# 2. Grant this user access to the Docker socket
sudo usermod -aG docker "$USER"

# 3. Refresh group membership
#    Either close and reopen the WSL terminal, or:
newgrp docker

# 4. Verify
docker compose version          # expect v2.40.3
docker run --rm hello-world     # daemon + networking check
```

If `newgrp` is awkward in the agent session, close the WSL shell and reopen it (from Windows: `wsl --terminate Ubuntu`, then start Ubuntu again). Group membership is only applied to new sessions.

Then pre-pull the pinned images:

```bash
docker pull php:8.4-apache
docker pull postgres:18-alpine
docker pull composer:2
docker pull node:22
```

Verify inside the PHP image:

```bash
docker run --rm php:8.4-apache php -v
docker run --rm php:8.4-apache php -m | grep -Ei 'pdo|pgsql|bcmath|mbstring|xml|curl|zip|intl|openssl'
```

The base `php:8.4-apache` image does not include every extension; the project `Dockerfile` will install `pdo_pgsql`, `pgsql`, `bcmath`, `intl`, `zip` in Phase 1. Phase 0 only verifies the image and Docker health.

### 6.2 Alternative path — host-native PHP (not recommended)

Ubuntu 26.04 packages PHP **8.5.4**, not 8.4. Installing PHP 8.4 on the host would require a third-party PPA (ondrej), adding supply-chain and version-drift risk. If host tooling is ever required, install:

```bash
sudo apt install -y php8.5-cli php8.5-pgsql php8.5-mbstring php8.5-xml \
  php8.5-curl php8.5-bcmath php8.5-intl php8.5-zip composer unzip nodejs npm
```

This is compatible with Laravel 13 but does **not** match the approved PHP 8.4 target and is not the deployment runtime. Use only as a fallback.

### 6.3 Optional host conveniences (not required)

```bash
sudo apt install -y postgresql-client-18   # psql / pg_dump / pg_restore on host
```

Cloudflare Tunnel is deliberately deferred to Phase 11 and will be installed from the official Cloudflare repository at that point. No budget or domain assumptions are made.

---

## 7. Phase 0 changes made to the repository

Only non-destructive additions:

| File | Purpose |
|---|---|
| `docs/phase-0-environment-report.md` | This report |
| `docs/decision-log.md` | Documented defaults and the verified stack (condition 3) |
| `.gitignore` | Secret/hygiene baseline: `.env`, backups, dumps, vendor, node_modules, build output, keys |

Nothing was deleted, moved or overwritten. The three existing documents are byte-for-byte unchanged (the two tracked analysis documents remain tracked; the plan remains untracked).

The `.gitignore` includes:

- `.env` and `.env.*` (with `.env.example` allowed)
- `vendor/`, `node_modules/`, `public/build/`, `public/hot/`
- `backups/`, `*.dump`, `*.sql`, `*.sql.gz`
- `storage/logs/*`, `storage/*.key`, private storage
- `*.pem`, `*.key`, `credentials.json`, `.cloudflared/`
- editor/OS noise and PHPUnit caches

This directly supports condition 9. It does not remove any already-tracked file.

---

## 8. Condition-by-condition compliance check

| Condition | Status | Evidence |
|---|---|---|
| 1. Use S79244 | ✅ | Decision log ADR-001; plan already uses S79244 |
| 2. Lecturer requirements first; distinguish assumptions | ✅ | Existing [L]/[L-I]/[R]/[?] tagging retained; decision log separates mandatory vs provisional |
| 3. Use defaults; document them | ✅ | `docs/decision-log.md` |
| 4. Do not invent brief/domain/host/budget | ✅ | No domain, host, budget or brief content invented; all remain open |
| 5. Verify compatibility before Laravel install | ✅ | §5; Laravel 13.43 + PHP 8.4 + PG 18.7 verified |
| 6. Core functions/tests/backup/deploy before optional | ✅ | Roadmap P1–P11 unchanged; no optional work started |
| 7. Do not expose PostgreSQL publicly | ✅ | Plan: no published DB port; firewall/Cloudflare only to app |
| 8. Backups outside PG data volume | ✅ | Plan: `backups/` on host, never inside `pgdata` |
| 9. No secrets in Git | ✅ | `.gitignore` created; no secrets exist |
| 10. Preserve analysis/plan | ✅ | No edits to the three documents |

---

## 9. Phase 0 exit checklist

| Check | Status |
|---|---|
| Inspect existing local toolchain | ✅ Complete |
| Verify Docker availability | ✅ Engine 29.1.3 installed, running, accessible |
| Verify Docker Compose availability | ✅ Compose 2.40.3 installed and verified |
| Identify missing dependencies | ✅ Complete |
| Recommend safest installation approach | ✅ §6 |
| Check project structure | ✅ Complete |
| Check Git status | ✅ Complete |
| Prepare environment without deleting files | ✅ Additive only; privileged install completed by student |
| Required images present and executable | ✅ §4.4 — PHP 8.4.27, PostgreSQL 18.7, Composer 2, Node 22.23.5 |
| Compatibility verified before Laravel install | ✅ §5 |
| No Laravel app created by this session | ✅ (skeleton found in workspace predates Phase 0 closure — §11) |
| No Phase 1 work performed by this session | ✅ |

### Gate to Phase 1

Environment gate requirements:

1. ✅ Docker Compose installed and verified (2.40.3+ds1-0ubuntu1).
2. ✅ `docker run --rm hello-world` succeeds.
3. ✅ Pinned images pulled: `php:8.4-apache`, `postgres:18-alpine`, `composer:2`, `node:22`.
4. ✅ Student approved the Laravel 13 adjustment (ADR-002).
5. ⏳ **Student approval of this Phase 0 completion report is required before Phase 1 begins.**

The environment gate is satisfied. Phase 1 is **not** authorised by this report alone.

---

## 10. Residual risks

| # | Risk | Impact | Mitigation |
|---|---|---|---|
| P0-R1 | Student cannot run `sudo` in the agent session | Blocks Docker use | ✅ Resolved — student ran the install block interactively |
| P0-R2 | Group membership not refreshed in current shell | `docker` still denied | ✅ Resolved — membership active; use `sg docker -c '…'` if a stale shell appears |
| P0-R3 | WSL2 Docker networking quirks | Container startup failures | Verify with `hello-world`; inspect `docker info` if needed |
| P0-R4 | Base image lacks required PHP extensions | Build fails later | Dockerfile installs `pdo_pgsql`, `pgsql`, `bcmath`, `intl`, `zip` |
| P0-R5 | Laravel 13 package drift | Non-reproducible installs | Pin `composer.lock`, image digests, record versions |
| P0-R6 | Host storage growth from images/volumes | Disk pressure | 930 GB free; prune unused images only when safe |
| P0-R7 | Missing approved brief/domain/host/budget | Assessment/deployment risk | Remain open; no assumptions invented |

---

## 11. Workspace change discovered during Phase 0 closure (20 November 2026)

While re-checking the project for closure, a **Laravel 13.43.0 application skeleton was found in the project root**. It was **not present at the start of Phase 0 inspection and was not created by this session**. Its origin is unconfirmed, so it is recorded rather than modified.

Observed state (read-only inspection):

| Item | Finding |
|---|---|
| Framework | Laravel 13.43.0; `composer.json` requires `php ^8.3`, `laravel/framework ^13.17`, `phpunit/phpunit ^12.5.12` |
| Application code | Default skeleton only — `app/Models/User.php`, `app/Providers/AppServiceProvider.php`; no CMMS code |
| Routes | Default `GET /` → `welcome` view only |
| Migrations | Standard skeleton migrations; no project tables |
| Database config | `.env` uses `DB_CONNECTION=sqlite` (must become PostgreSQL in Phase 1) |
| Environment | `APP_NAME=Laravel`, `APP_ENV=local`, `APP_DEBUG=true`, `APP_URL=http://localhost:8000` |
| Secret hygiene | `.env` is ignored by Git (`.gitignore` line 2); no secrets tracked |
| AI/dev tooling | `laravel/boost`, `laravel/pail`, `laravel/pao`, Pint; `.mcp.json`, `.agents/`, `.claude/` present |
| Protected documents | `docs/requirements-analysis.md`, `docs/proposed-system-modules.md`, `docs/implementation-plan.md` — **unchanged** (verified via `git diff`) |

**Impact on Phase 0:** none. No application code, migration, Docker Compose project or Phase 1 work was authored by this session.

**Items to reconcile in Phase 1 (not Phase 0):**

1. Confirm whether the skeleton is intended as the project base or should be replaced.
2. Switch `DB_CONNECTION` to `pgsql` and point it at the `db` service.
3. Set `APP_NAME`, `APP_TIMEZONE=Asia/Kuala_Lumpur` and the correct `APP_URL`.
4. Pin `config.platform.php` to `8.4.27` so Composer resolves against the container runtime.
5. Add `pdo_pgsql`, `pgsql`, `bcmath`, `intl` and `zip` to the project Dockerfile.
6. Review the tracked/untracked file set before the first Phase 1 commit.

---

**End of Phase 0 report. The environment gate is satisfied. No Phase 1 work has been performed by this session.**
