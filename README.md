# signd.it Integration – Nextcloud App

Nextcloud-App zur Integration mit [signd.it](https://signd.it) (digitales Signieren von PDF-Dokumenten).

**NC 30–32** | PHP 8.1+ | Vue 3 | TypeScript | Vite

## Voraussetzungen

- Node.js 20+, npm 10+
- Docker + Docker Compose
- signd-Instanz (lokal: `localhost:7755` oder `signd.it`)

## Setup

```bash
# Frontend-Dependencies installieren + bauen
npm install
npm run build

# Nextcloud + PostgreSQL starten (Default: NC 32)
npm run up

# App aktivieren + lokale API-Requests erlauben
npm run enable-app
npm run occ -- config:system:set allow_local_remote_servers --value=true --type=boolean
```

Nextcloud läuft unter **http://localhost:8080** (Login: `admin` / `admin`).

## Entwicklung

```bash
npm run watch          # Frontend mit Hot-Reload
npm run logs           # NC Container-Logs
npm run occ -- app:list  # beliebiger occ-Befehl im Container
npm run down           # Container stoppen
npm run restart        # Container neu starten
```

### Multi-Version Testing (NC 30, 31, 32)

Die NC-Version wird über die Environment-Variable `NC_VERSION` gesteuert (Default: `32`). Jede Version hat eigene Docker-Volumes — Daten bleiben beim Wechsel erhalten, kein Konflikt.

```bash
# NC 30 starten
NC_VERSION=30 npm run up
npm run enable-app

# Wechsel auf NC 31
npm run down
NC_VERSION=31 npm run up
npm run enable-app

# Zurück auf NC 32 (Default)
npm run down
npm run up
```

`npm run enable-app` ist nur beim ersten Start einer neuen Version nötig.

### signd-Server-URL

Standardmäßig `https://signd.it`. Für lokale Entwicklung setzt `docker-compose.yml` die Env-Variable `SIGND_BASE_URL=http://host.docker.internal:7755` im Container – damit erreicht PHP den signd-Server auf dem Host.

Auflösungs-Reihenfolge (siehe `SignApiService::getApiUrl()`):
1. App-Config (`occ config:app:set`)
2. Env-Variable `SIGND_BASE_URL` (im Container gesetzt via `docker-compose.yml`)
3. Default: `https://signd.it`

Falls die URL manuell geändert werden soll:
```bash
npm run occ -- config:app:set integration_signd api_url --value=http://host.docker.internal:7755
```

## Architektur

```
integration_signd/
  appinfo/           info.xml, routes.php
  lib/
    AppInfo/          Application Bootstrap
    Controller/       SettingsController, ProcessController
    Db/               Process Entity + Mapper (oc_integration_signd_processes)
    Listener/         LoadAdditionalScriptsEvent → lädt Frontend in Files-App
    Migration/        DB-Schema
    Service/          SignApiService (alle sign-API-Aufrufe)
    Settings/         AdminSettings + AdminSection (NC Settings-Seite)
  src/
    settings/         Admin-Settings Vue-Komponenten (ApiKey, Login, Register)
    views/            SigndSidebarTab
    components/       ProcessList, ProcessStatus, StartProcessButton
    services/api.ts   Frontend HTTP-Client
    main-settings.ts  Entrypoint Admin-Settings
    main-files.ts     Entrypoint Files-App (FileAction + Sidebar-Tab)
  templates/          PHP-Templates
```

## Dokumentation

- [docs/status.md](docs/status.md) — Entwicklungsstand + offene Punkte
- [docs/decisions.md](docs/decisions.md) — Architektur-Entscheidungen
- [docs/research-sign-api.md](docs/research-sign-api.md) — sign API Analyse
- [docs/research-nextcloud-app-dev.md](docs/research-nextcloud-app-dev.md) — Nextcloud App-Entwicklung Recherche
