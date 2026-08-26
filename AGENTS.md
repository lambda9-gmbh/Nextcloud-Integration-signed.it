# signd.it Integration – Nextcloud App

Nextcloud app v2.1.0 for integrating signd.it digital PDF signing.
The released app supports Nextcloud 33 and 34. Nextcloud 35 support is prepared
but must not be declared or released before the final Docker and staging checks
in `docs/nextcloud-35-preparation.md` have passed.

## Documentation

- `docs/development.md` — development setup, tests, and release process
- `docs/research-sign-api.md` — signd API analysis
- `docs/research-nextcloud-app-dev.md` — Nextcloud app development patterns
- signd OpenAPI specification: `https://signd.it/static/api.yaml` (source of truth)

## Conventions

- Use English in code.
- Do not inspect or copy from the existing sibling `sign-plugin`; this app uses
  an independent implementation.
- Treat `appinfo/info.xml` as the source of truth for the released app version
  and supported Nextcloud/PHP versions. The private npm package version is not
  the app release version.
- Preserve unrelated user changes in the working tree.

### Translations (l10n)

Every new or changed user-facing string must be added to all translation files.

- Update `l10n/{en,de,da,fr,es,it,pl,pt}.json` and the matching `.js` files.
- Keep entries sorted alphabetically by source string.
- In code, use `t('integration_signd', 'My string')` from `@nextcloud/l10n`.
- When UI text changes, verify every new `t()` call exists in all eight locales.

## Technology

| Area | Technology |
| --- | --- |
| Backend | PHP 8.2+ runtime, Nextcloud App Framework |
| Development | PHP 8.3+ because the development OCP dependency targets NC 35 |
| Frontend | Vue 3, TypeScript, Vite |
| Build | `@nextcloud/vite-config`, three entry points |
| Nextcloud packages | `@nextcloud/vue` v9, `@nextcloud/files` v4, Axios, router, l10n, initial-state |
| Database | Nextcloud DB abstraction with QBMapper; table `oc_integration_signd_processes` |
| Local stack | Docker, Nextcloud 34 by default, PostgreSQL 16 |

## Project structure

```text
appinfo/               App metadata and routes
lib/
  Controller/          Settings, process, page, and overview controllers
  Service/             SignApiService, the central signd HTTP client
  Db/                  Process entity and mapper
  Settings/            Admin settings and section
  Listener/            Files app frontend registration
  Migration/           Database schema
src/
  settings/            Admin settings Vue components
  views/               Sidebar and overview apps
  components/          Shared and overview Vue components
  services/            Frontend API clients
  main-settings.ts     Admin settings entry point
  main-files.ts        File action and sidebar entry point
  main-overview.ts     Process overview entry point
tests/Unit/            PHPUnit tests mirroring `lib/`
tests/frontend/        Vitest tests mirroring `src/`
e2e/                   Playwright end-to-end tests
docs/                  Decisions, research, and release preparation
```

## Key files

| File | Purpose |
| --- | --- |
| `lib/Service/SignApiService.php` | signd API calls and API URL resolution |
| `lib/Controller/ProcessController.php` | Process CRUD, wizard start, and PDF download |
| `lib/Controller/SettingsController.php` | API key management |
| `lib/Controller/OverviewController.php` | Process list proxy and cancellation |
| `src/services/api.ts` | Frontend HTTP client |
| `src/main-files.ts` | File action and sidebar registration |
| `src/views/OverviewApp.vue` | Process overview application |
| `appinfo/routes.php` | Backend routes |

## MCP configuration

- `.mcp.json` configures MCP servers for Claude Code and must be preserved.
- `.codex/config.toml` configures the equivalent project MCP servers for Codex.
- Keep shared MCP definitions aligned when changing them.
- The `openapi` MCP uses `scripts/openapi-mcp.sh` to fetch the current
  specification from `https://signd.it/static/api.yaml` with a browser user
  agent, then invokes the local development API at `http://localhost:7755`.
- The `postgres` MCP connects only to the local Docker development database.
  Do not modify database data unless the task explicitly requires it.

## Development commands

```bash
npm ci                              # Install frontend dependencies
composer install                    # Install backend test dependencies
npm run build                       # Production frontend build
NC_VERSION=34 npm run up            # Start Nextcloud and PostgreSQL
npm run enable-app                  # Enable integration_signd
npm run watch                       # Rebuild frontend on changes
npm run logs                        # Follow container logs
```

Local services:

- Nextcloud: `http://localhost:8080` (`admin` / `admin`)
- signd development API: `http://localhost:7755`

## Verification

### Backend

```bash
vendor/bin/phpunit --testsuite Unit
```

There is currently one PHPUnit suite, `Unit`. It needs no running Nextcloud
server; `tests/bootstrap.php` loads the public OCP package and necessary stubs.

### Frontend

```bash
npm test
npm run build
```

Vitest uses `happy-dom` and `tests/frontend/setup.ts`. Run the frontend tests
after TypeScript or Vue changes and run the production build for changes that
can affect bundling.

`npm run lint` is currently unavailable because ESLint and its configuration
are not declared in the project. Do not report that existing tooling gap as a
regression.

### End to end

```bash
npm run test:e2e
npm run test:e2e:headed
```

Playwright requires the Docker environment, a built and enabled app, and uses
Chromium with a single worker against `http://localhost:8080`.

New features and bug fixes must include matching tests. Run checks in
proportion to the affected layer and report any check that could not run.

## Releases

- Follow the release process in `docs/development.md`.
- Update `CHANGELOG.md` before releasing.
- Do not create a version tag or publish a release unless explicitly requested.
- For Nextcloud 35, complete every gate in
  `docs/nextcloud-35-preparation.md` before changing `max-version` to 35.
