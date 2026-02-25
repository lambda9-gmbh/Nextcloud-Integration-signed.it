# Development Status

As of: 2026-02-22 | Version: 0.1.0 (initial)

## Implemented

### Backend (PHP)
- [x] App skeleton (info.xml, Application.php, composer.json)
- [x] `SignApiService` — all signd API endpoints covered
- [x] `SettingsController` — API key manual entry, login, registration, prices, validation
- [x] `ProcessController` — getByFileId, startWizard, refresh, download
- [x] `AdminSettings` + `AdminSection` — NC settings integration
- [x] DB migration `oc_integration_signd_processes` with Entity + Mapper
- [x] `LoadAdditionalListener` — frontend injection into Files app
- [x] API URL resolution: appconfig → ENV → default
- [x] `PageController` — renders overview page with InitialState
- [x] `OverviewController` — process list (signd API `/api/list` proxy with instance scoping) + cancel

### Frontend (Vue 3 / TypeScript)
- [x] Admin settings: ApiKeyForm, LoginForm, RegisterForm (incl. price display, ToS/privacy links)
- [x] FileAction for PDFs ("Digitally sign" in context menu)
- [x] Legacy sidebar tab (OCA.Files.Sidebar.Tab, NC 30-32)
- [x] Sidebar components: ProcessList, ProcessStatus, StartProcessButton
- [x] Manual reload button
- [x] Manual PDF download button
- [x] Frontend API service (`src/services/api.ts`)
- [x] Overview page: process list, filters (status/search/date/only mine), sortable columns, pagination, detail sidebar with refresh/cancel/download
- [x] Shared `SignerList` component (used in sidebar tab + overview page)
- [x] Link "Show all processes" in Files sidebar → overview page
- [x] Translations (l10n): 8 languages (en, de, es, fr, it, pt, da, pl), 91 strings, `.l10nignore`
- [x] Branding: service name consistently as "signd.it" (info.xml, UI texts, admin settings), app logo (`img/app.svg`)

### Infrastructure
- [x] Docker Compose (NC 32 + PostgreSQL)
- [x] npm scripts (occ, enable-app, logs)
- [x] Vite build with three entrypoints (settings, files, overview)
- [x] TypeScript configuration

### Logic
- [x] Start wizard flow (read PDF → signd API → DB entry → wizardUrl)
- [x] apiClientMetaData with NC metadata (fileId, path, user, instance)
- [x] Duplicate prevention on PDF download (`finishedPdfPath` check + filename counter)
- [x] Multiple processes per document (DB query returns array)
- [x] Naming convention: `contract_signed.pdf`, `contract_signed_2.pdf`, ...

## Open

### Priority 1 — Required before first release
- [x] **Docker multi-version:** `NC_VERSION` environment variable (default: 32), isolated volumes per version. `NC_VERSION=30 npm run up` to switch.
- [x] **Repository URLs in info.xml:** Add `<bugs>` and `<repository>` once the repository is established.
- [ ] **CHANGELOG:** Create a CHANGELOG file (e.g. Keep a Changelog format) before first release.
- [x] **Switch instance scoping to `ncInstanceId`:** `apiClientMetaData` and overview filter use stable NC `instanceid` instead of variable URL. See [edge-cases.md#9](edge-cases.md#9-nc-accessible-behind-different-urls).
- [x] **Reduce local DB to mapping data:** Remove `status`, `process_name`, `created_at`, `updated_at` from `oc_signd_processes` — status always comes live from the signd API. Add `target_dir` (directory of the original at the time of start). See [decisions.md](decisions.md#data-ownership--local-db).
- [x] **Download fallback when original file is deleted:** When `target_dir` no longer exists, fall back to user root with warning. See [edge-cases.md#1](edge-cases.md#1-original-pdf-deleted-after-process-start).
- [x] **Error UX for file operations:** Clear error messages on failed downloads (storage full, directory missing, etc.).
- [x] **Wizard lifecycle in sidebar:** Add `resume-wizard` (resume draft) and `cancel-wizard` (cancel draft) to sidebar. Backend methods already exist in `SignApiService`. See [edge-cases.md#4](edge-cases.md#4-duplicate-process-start--wizard-handling).
- [x] **Overview: Graceful file link:** When original file has been deleted, gray out/remove file link instead of error.

### Priority 2 — Planned
- [ ] **Automatic PDF sync-back:** Background job (NC cron) polling `GET /api/new-finished?gt=...` and automatically downloading finished PDFs. Currently manual download only.
- [ ] **Configurable storage location:** Admin setting for target directory of signed PDFs. Currently always next to the original. _Note: Fallback behavior (user root when target_dir is missing) needs to be reviewed during implementation._
- [ ] **Cleanup job for orphaned DB entries:** Background job checks if `file_id` still exists in NC, removes orphans. See [edge-cases.md#1](edge-cases.md#1-original-pdf-deleted-after-process-start).

### Priority 3 — Later
- [ ] **v2 (NC 33+):** Separate app version with web component-based sidebar tab (`getSidebar()`, `defineCustomElement`). See [research-nextcloud-app-dev.md](research-nextcloud-app-dev.md#b-new-api-for-nc-33-getsidebarregistertab-web-components).
- [ ] **Full process creation in NC** (`/api/new`) instead of start-wizard only.
- [ ] **Fine-grained permissions:** Admin configures which users/groups can start processes.
- [ ] **Auto-polling:** Sidebar automatically polls every 30s while tab is visible.

## Related Documents
- [decisions.md](decisions.md) — All architecture decisions
- [research-nextcloud-app-dev.md](research-nextcloud-app-dev.md) — NC app development research
- [research-sign-api.md](research-sign-api.md) — signd API analysis