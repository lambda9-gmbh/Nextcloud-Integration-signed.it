# Entwicklungsstand

Stand: 2026-02-22 | Version: 0.1.0 (initial)

## Umgesetzt

### Backend (PHP)
- [x] App-Skeleton (info.xml, Application.php, composer.json)
- [x] `SignApiService` — alle sign-API-Endpunkte abgedeckt
- [x] `SettingsController` — API-Key manuell, Login, Registrierung, Preise, Validierung
- [x] `ProcessController` — getByFileId, startWizard, refresh, download
- [x] `AdminSettings` + `AdminSection` — NC-Settings-Integration
- [x] DB-Migration `oc_integration_signd_processes` mit Entity + Mapper
- [x] `LoadAdditionalListener` — Frontend-Injection in Files-App
- [x] API-URL Auflösung: appconfig → ENV → Default
- [x] `PageController` — Rendert Übersichtsseite mit InitialState
- [x] `OverviewController` — Prozessliste (sign-API `/api/list` Proxy mit Instance-Scoping) + Cancel

### Frontend (Vue 3 / TypeScript)
- [x] Admin-Settings: ApiKeyForm, LoginForm, RegisterForm (inkl. Preisanzeige, AGB/DSB-Links)
- [x] FileAction für PDFs ("Digitally sign" im Kontextmenü)
- [x] Legacy Sidebar-Tab (OCA.Files.Sidebar.Tab, NC 30-32)
- [x] Sidebar-Komponenten: ProcessList, ProcessStatus, StartProcessButton
- [x] Manueller Reload-Button
- [x] Manueller PDF-Download-Button
- [x] Frontend API-Service (`src/services/api.ts`)
- [x] Übersichtsseite (Overview): Prozessliste, Filter (Status/Suche/Datum/Nur meine), sortierbare Spalten, Pagination, Detail-Sidebar mit Refresh/Cancel/Download
- [x] Shared `SignerList`-Komponente (verwendet in Sidebar-Tab + Übersichtsseite)
- [x] Link "Alle Prozesse anzeigen" in Files-Sidebar → Übersichtsseite
- [x] Übersetzungen (l10n): 8 Sprachen (en, de, es, fr, it, pt, da, pl), 91 Strings, `.l10nignore`
- [x] Branding: Service-Name durchgängig als „signd.it" (info.xml, UI-Texte, Admin-Settings), App-Logo (`img/app.svg`)

### Infrastruktur
- [x] Docker-Compose (NC 32 + PostgreSQL)
- [x] npm-Scripts (occ, enable-app, logs)
- [x] Vite Build mit drei Entrypoints (settings, files, overview)
- [x] TypeScript-Konfiguration

### Logik
- [x] Start-Wizard-Flow (PDF lesen → sign-API → DB-Eintrag → wizardUrl)
- [x] apiClientMetaData mit NC-Metadaten (fileId, path, user, instance)
- [x] Duplikat-Vermeidung beim PDF-Download (`finishedPdfPath`-Check + Dateiname-Counter)
- [x] Mehrere Prozesse pro Dokument (DB-Abfrage liefert Array)
- [x] Namenskonvention: `vertrag_signed.pdf`, `vertrag_signed_2.pdf`, ...

## Offen

### Priorität 1 — Vor erstem Release nötig
- [x] **Docker Multi-Version:** `NC_VERSION` Environment-Variable (Default: 32), isolierte Volumes pro Version. `NC_VERSION=30 npm run up` zum Wechseln.
- [ ] **Repository-URLs in info.xml:** `<bugs>` und `<repository>` nachtragen, sobald das Repository feststeht.
- [x] **Instance-Scoping auf `ncInstanceId` umstellen:** `apiClientMetaData` und Overview-Filter verwenden stabile NC `instanceid` statt variabler URL. Siehe [edge-cases.md#9](edge-cases.md#9-nc-hinter-verschiedenen-urls-erreichbar).
- [x] **Lokale DB auf Zuordnungsdaten reduzieren:** `status`, `process_name`, `created_at`, `updated_at` aus `oc_signd_processes` entfernen — Status kommt immer live von der signd-API. `target_dir` (Ordner des Originals zum Startzeitpunkt) ergänzen. Siehe [decisions.md](decisions.md#datenhoheit--lokale-db).
- [x] **Download-Fallback bei gelöschter Originaldatei:** Wenn `target_dir` nicht mehr existiert, Fallback auf User-Root mit Warnung. Siehe [edge-cases.md#1](edge-cases.md#1-original-pdf-wird-nach-prozessstart-gelöscht).
- [x] **Fehler-UX bei Dateioperationen:** Verständliche Fehlermeldungen bei fehlgeschlagenem Download (Speicher voll, Ordner fehlt, etc.).
- [x] **Wizard-Lifecycle in Sidebar:** `resume-wizard` (Draft fortsetzen) und `cancel-wizard` (Draft abbrechen) in Sidebar einbauen. Backend-Methoden existieren bereits in `SignApiService`. Siehe [edge-cases.md#4](edge-cases.md#4-doppelter-prozessstart--wizard-handling).
- [x] **Overview: Link zur Datei graceful:** Wenn Original-Datei gelöscht wurde, Datei-Link ausgrauen/entfernen statt Fehler.

### Priorität 2 — Geplant
- [ ] **Automatischer PDF-Rücksync:** Background-Job (NC Cron) der `GET /api/new-finished?gt=...` pollt und fertige PDFs automatisch herunterlädt. Aktuell nur manueller Download.
- [ ] **Konfigurierbarer Speicherort:** Admin-Setting für Zielordner signierter PDFs. Aktuell immer neben dem Original. _Hinweis: Fallback-Verhalten (User-Root bei fehlendem target_dir) muss bei Implementierung überprüft werden._
- [ ] **Cleanup-Job für verwaiste DB-Einträge:** Background-Job prüft ob `file_id` noch in NC existiert, entfernt Waisen. Siehe [edge-cases.md#1](edge-cases.md#1-original-pdf-wird-nach-prozessstart-gelöscht).

### Priorität 3 — Später
- [ ] **v2 (NC 33+):** Separate App-Version mit Web-Component-basiertem Sidebar-Tab (`getSidebar()`, `defineCustomElement`). Siehe [research-nextcloud-app-dev.md](research-nextcloud-app-dev.md#b-neues-api-für-nc-33-getsidebarregistertab-web-components).
- [ ] **Vollständige Prozess-Erstellung in NC** (`/api/new`) statt nur start-wizard.
- [ ] **Feingranulare Berechtigungen:** Admin konfiguriert welche User/Gruppen Prozesse starten dürfen.
- [ ] **Auto-Polling:** Sidebar pollt automatisch alle 30s solange Tab sichtbar.

## Verwandte Dokumente
- [decisions.md](decisions.md) — Alle Architektur-Entscheidungen
- [research-nextcloud-app-dev.md](research-nextcloud-app-dev.md) — NC App-Entwicklung Recherche
- [research-sign-api.md](research-sign-api.md) — sign API Analyse
