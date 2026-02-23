# signd.it Integration - Nextcloud App - Decisions

> See also: [status.md](status.md) | [research-sign-api.md](research-sign-api.md) | [research-nextcloud-app-dev.md](research-nextcloud-app-dev.md) | [../README.md](../README.md)

## Framework Conditions

| Topic | Decision |
|-------|----------|
| **App ID** | `integration_signd` |
| **App name** | signd.it Integration |
| **Author** | lambda9 GmbH (support@lambda9.de) |
| **License** | AGPL-3.0-or-later |
| **NC versions (v1)** | min 30, max 32 |
| **NC versions (v2)** | exclusively 33 (due to breaking API changes in sidebar tab API) |
| **NC versions (after)** | 33-34, 33-35, 34-36, ... rolling |
| **Languages** | de, en, es, fr, it, pt, da, pl (same as signd itself) |

## Architecture Decisions

### API Key Management
- **One key per NC instance** (admin setting)
- Admin can set the key via:
  - Manual entry
  - Login with email/password (uses `/api/v2/api-login` → returns API key)
  - New registration (uses `/api/register-account` → returns API key + account ID)
    - Prices loaded upfront via `/api/prices` (premium/enterprise plans)
    - Registration requires: product plan, organization, address, name, email, password, ToS/privacy policy acceptance
    - ToS: `{server-url}/terms-and-conditions` (PDF download)
    - Privacy policy: `{server-url}/privacy-policy` (HTML page)

### Data Ownership & Local DB
- **Principle:** The app is primarily a **view on the signd.it API**. The signd API is the single source of truth for all process data (status, signers, timestamps, etc.)
- **Local DB (`oc_signd_processes`) stores only mapping data** that signd.it doesn't know:
  - `file_id` — which NC file belongs to the process
  - `process_id` — connection to signd.it
  - `user_id` — which NC user started the process
  - `target_dir` — target directory for the finished file (directory of the original at the time of start)
  - `finished_pdf_path` — where the signed PDF was stored in NC (nullable, set on download)
- **Not stored locally:** Status, process name, timestamps — these always come live from the signd API
- **No FK on NC files:** NC apps don't use FKs on `oc_filecache` (internal, changes on rescans). Instead, a cleanup mechanism for orphaned entries.
- **Local DB is needed for:**
  - **Sidebar** (Files app): file → associated processes
  - **Overview**: process → link to original file / signed PDF in NC

### Process-File Mapping
- **Combination of:**
  - Setting `apiClientMetaData` on creation (NC file ID, path, user, etc.)
  - Own NC database table: file ID ↔ signd process ID mapping

### Process Creation & Wizard Handling
- **For now:** start-wizard (redirect to signd UI) via `/api/start-wizard`
- **Later:** Possibly full creation in NC (`/api/new`)
- **Multiple processes per file:** Deliberately allowed
- **Wizard lifecycle in sidebar:**
  - Running draft exists → offer "Resume wizard" (uses `/api/resume-wizard`)
  - Cancel draft → `/api/cancel-wizard`
- **Double-click protection:** Button is disabled until backend response arrives (already implemented)
- **Sidebar with many processes:** Link to overview beyond a certain count, with `fileId` filter preset

### Status Updates
- **Polling** + **manual reload button** (no webhook for now)
- Different strategies depending on context (overview vs. sidebar)

### Sidebar Tab API
- **NC 30-32:** Legacy Vue-based sidebar tab API (`OCA.Files.Sidebar.registerTab`)
- **NC 33+:** New web component-based API (`@nextcloud/files` getSidebar())
- Separate app versions required!

### Registration
- **Fully integrated** in admin settings
- Complete form: product plan (with price display via `/api/prices`), organization, address, name, email, password, ToS/privacy policy acceptance, coupon code
- After registration, the received API key is automatically saved

### Overview Page
- **Own top-level entry** in the NC sidebar (visible for all users, route `integration_signd.page.index`)
- **Data source:** signd API `GET /api/list` with `metadataSearch` filtered by `ncInstanceId` (stable NC instance ID via `$config->getSystemValue('instanceid')`, independent of access URL)
- **Visibility:** All account processes for this NC instance; "Only mine" toggle filters by own (`ncUserId`)
- **Filters:** Status (ALL/RUNNING/FINISHED), free-text search (LIKE), date range, sortable columns
- **Detail sidebar:** Click on process opens NC app sidebar with details, refresh, cancel, download
- **File link:** Per row link to Files app (`/apps/files/?fileid=...`), conversely link from Files sidebar to overview page
- **Third Vite entrypoint:** `main-overview.ts` alongside `main-settings.ts` and `main-files.ts`

### signd Server URL
- **Default:** `https://signd.it`
- **Configurable** but NOT in the admin UI
- Settable via `occ config:app:set integration_signd api_url --value=...` (highest priority)
- Alternatively via **environment variable** `SIGND_BASE_URL` (e.g. via `docker-compose.yml`)
  - Settable in Docker container via `ENV` / `docker-compose` environment → no manual configuration needed for dev
- Use cases: Local development (`http://localhost:7755`), staging environment

### Signed PDF Sync-Back
- **Primary:** Automatic download into NC when process is finished (detected during polling)
- **Fallback:** Manual download button if automatic sync fails
- **Check:** Whether finished PDF has already been downloaded (duplicate prevention)
- **Complexity:** A document can have multiple processes → must be handled correctly
- **Storage location:** Configurable directory (admin setting), fallback: next to the original (e.g. `contract.pdf` → `contract_signed.pdf`)

## Development Environment

### Docker Setup
- **Official NC image** (`nextcloud:XX`) + **docker-compose**
- **Multi-version support:** docker-compose profiles or separate configs for NC 30, 31, 32 (later 33+)
  - e.g. `docker compose --profile nc30 up` or `NC_VERSION=30 docker compose up`
- **Database:** PostgreSQL (dev preference; app itself is DB-agnostic via NC DB abstraction layer)
- **App mount:** App directory mounted as volume into the container

### signd Instance (Local)
- Runs **natively/separately** on the host at `localhost:7755`
- NC container accesses it via `host.docker.internal:7755` or `extra_hosts`
- Environment variable `SIGND_BASE_URL=http://host.docker.internal:7755` in the NC container

### Frontend Build
- **Natively on the host:** `npm install` / `npm run dev` / `npm run watch`
- Build output (`js/`, `css/`) is directly visible in the container via the mounted app volume

### Permissions
- **For now:** All NC users can start signature processes
- **Later:** Fine-grained permissions (admin configures users/groups) as a future feature

## Open Research Items (to clarify during implementation)

1. **Signed PDF naming convention:** Exact naming when multiple processes exist for one document (e.g. `contract_signed.pdf`, `contract_signed_2.pdf`?)

## Testing Strategy

Three test levels, all active from v1:

### PHPUnit (Backend Unit Tests)
- **Config:** `phpunit.xml`, test suites `Unit` + `Integration`
- **Directory:** `tests/Unit/`, `tests/Integration/`
- **Conventions:** One test file per class (`FooTest.php` → `Foo.php`), mocking via PHPUnit MockBuilder for NC interfaces (`IClientService`, `IConfig`, `LoggerInterface`)
- **Execution:** `vendor/bin/phpunit --testsuite Unit`
- No running NC server required for unit tests

### Vitest (Frontend Unit/Component Tests)
- **Config:** `vitest.config.ts` (separate config, not in `vite.config.ts`)
- **Directory:** `tests/frontend/`, structure mirrors `src/`
- **Setup:** `tests/frontend/setup.ts` mocks `@nextcloud/axios`, `@nextcloud/router`, `@nextcloud/l10n`, `@nextcloud/initial-state`
- **Conventions:** `*.test.ts`, `@vue/test-utils` for components, happy-dom as environment
- **Execution:** `npm test` (single run) / `npm run test:watch` (dev)

### Playwright (E2E Tests)
- **Config:** `playwright.config.ts`, Chromium only, single worker
- **Directory:** `e2e/`, fixtures in `e2e/fixtures/`
- **Prerequisite:** Docker environment running (`docker compose up -d` + `npm run build` + `npm run enable-app`)
- **Execution:** `npm run test:e2e` / `npm run test:e2e:headed`