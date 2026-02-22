# signd.it Integration - Nextcloud App - Entscheidungen

> Siehe auch: [status.md](status.md) | [research-sign-api.md](research-sign-api.md) | [research-nextcloud-app-dev.md](research-nextcloud-app-dev.md) | [../README.md](../README.md)

## Rahmenbedingungen

| Thema | Entscheidung |
|-------|-------------|
| **App-ID** | `integration_signd` |
| **App-Name** | signd.it Integration |
| **Autor** | lambda9 GmbH (support@lambda9.de) |
| **Lizenz** | AGPL-3.0-or-later |
| **NC-Versionen (v1)** | min 30, max 32 |
| **NC-Versionen (v2)** | exklusiv 33 (wegen Breaking API Changes im Sidebar-Tab-API) |
| **NC-Versionen (danach)** | 33-34, 33-35, 34-36, ... rollierend |
| **Sprachen** | de, en, es, fr, it, pt, da, pl (wie signd selbst) |

## Architektur-Entscheidungen

### API-Key Verwaltung
- **Ein Key pro NC-Instanz** (Admin-Einstellung)
- Admin kann den Key setzen via:
  - Manuell eingeben
  - Login mit E-Mail/Passwort (nutzt `/api/v2/api-login` → liefert API-Key)
  - Neue Registrierung (nutzt `/api/register-account` → liefert API-Key + Account-ID)
    - Vorab Preise laden via `/api/prices` (premium/enterprise Pläne)
    - Registrierung erfordert: Produktplan, Organisation, Adresse, Name, E-Mail, Passwort, AGB/DSB-Akzeptanz
    - AGB: `{server-url}/terms-and-conditions` (PDF-Download)
    - Datenschutzerklärung: `{server-url}/privacy-policy` (HTML-Seite)

### Datenhoheit & lokale DB
- **Prinzip:** Die App ist primär ein **View auf die signd.it-API**. Die signd-API ist Single Source of Truth für alle Prozessdaten (Status, Signer, Timestamps, etc.)
- **Lokale DB (`oc_signd_processes`) speichert nur Zuordnungsdaten**, die signd.it nicht kennt:
  - `file_id` — welche NC-Datei gehört zum Prozess
  - `process_id` — Verbindung zu signd.it
  - `user_id` — welcher NC-User hat den Prozess gestartet
  - `target_dir` — Zielordner für das fertige File (Ordner des Originals zum Zeitpunkt des Starts)
  - `finished_pdf_path` — wo wurde die signierte PDF in NC abgelegt (nullable, erst beim Download gesetzt)
- **Nicht lokal gespeichert:** Status, Prozessname, Timestamps — diese kommen immer live von der signd-API
- **Kein FK auf NC-Dateien:** NC-Apps verwenden keine FKs auf `oc_filecache` (intern, ändert sich bei Rescans). Stattdessen Cleanup-Mechanismus für verwaiste Einträge.
- **Lokale DB wird benötigt für:**
  - **Sidebar** (Files-App): Datei → zugehörige Prozesse
  - **Overview**: Prozess → Link zur Original-Datei / signierten PDF in NC

### Prozess-Datei-Zuordnung
- **Kombination aus:**
  - `apiClientMetaData` beim Erstellen setzen (NC File-ID, Pfad, User, etc.)
  - Eigene NC-Datenbank-Tabelle: File-ID ↔ sign-Prozess-ID Mapping

### Prozess-Erstellung & Wizard-Handling
- **Erstmal:** start-wizard (Redirect zu sign-UI) via `/api/start-wizard`
- **Später:** Ggf. vollständige Erstellung in NC (`/api/new`)
- **Mehrere Prozesse pro Datei:** Bewusst erlaubt
- **Wizard-Lifecycle in Sidebar:**
  - Laufender Draft vorhanden → "Wizard fortsetzen" anbieten (nutzt `/api/resume-wizard`)
  - Draft abbrechen → `/api/cancel-wizard`
- **Doppelklick-Schutz:** Button wird disabled bis Backend-Antwort kommt (bereits umgesetzt)
- **Sidebar bei vielen Prozessen:** Ab gewisser Anzahl auf Overview verlinken, mit Filter auf `fileId` vorbelegt

### Status-Aktualisierung
- **Polling** + **manueller Reload-Button** (kein Webhook vorerst)
- Je nach Kontext (Übersicht vs. Sidebar) unterschiedliche Strategien

### Sidebar-Tab API
- **NC 30-32:** Legacy Vue-basiertes Sidebar-Tab-API (`OCA.Files.Sidebar.registerTab`)
- **NC 33+:** Neues Web-Component-basiertes API (`@nextcloud/files` getSidebar())
- Separate App-Versionen nötig!

### Registrierung
- **Vollständig integriert** in Admin-Settings
- Komplettes Formular: Produktplan (mit Preisanzeige via `/api/prices`), Organisation, Adresse, Name, E-Mail, Passwort, AGB/DSB-Akzeptanz, Coupon-Code
- Nach Registrierung wird der erhaltene API-Key automatisch gespeichert

### Übersichtsseite (Overview)
- **Eigener Top-Level-Eintrag** in der NC-Seitenleiste (sichtbar für alle User, Route `integration_signd.page.index`)
- **Datenquelle:** sign-API `GET /api/list` mit `metadataSearch` auf `ncInstanceId` gefiltert (stabile NC-Instanz-ID via `$config->getSystemValue('instanceid')`, unabhängig von Zugriffs-URL)
- **Sichtbarkeit:** Alle Account-Prozesse dieser NC-Instanz; Toggle "Nur meine" filtert auf eigene (`ncUserId`)
- **Filter:** Status (ALL/RUNNING/FINISHED), Freitext-Suche (LIKE), Datumsbereich, sortierbare Spalten
- **Detail-Sidebar:** Klick auf Prozess öffnet NC-App-Sidebar mit Details, Refresh, Cancel, Download
- **Datei-Link:** Pro Zeile Link zur Files-App (`/apps/files/?fileid=...`), umgekehrt Link aus Files-Sidebar zur Übersichtsseite
- **Dritter Vite-Entrypoint:** `main-overview.ts` neben `main-settings.ts` und `main-files.ts`

### sign-Server-URL
- **Default:** `https://signd.it`
- **Konfigurierbar** aber NICHT im Admin-UI
- Per `occ config:app:set integration_signd api_url --value=...` setzbar (höchste Priorität)
- Alternativ über **Umgebungsvariable** `SIGND_BASE_URL` (z.B. via `docker-compose.yml`)
  - Im Docker-Container per `ENV` / `docker-compose` environment setzbar → kein manuelles Konfigurieren für Dev nötig
- Use Cases: Lokale Entwicklung (`http://localhost:7755`), Staging-Umgebung

### Fertig-signiertes PDF Rücksync
- **Primär:** Automatischer Download in NC wenn Prozess fertig (erkannt beim Polling)
- **Fallback:** Manueller Download-Button wenn Automatik fehlschlägt
- **Prüfung:** Check ob fertiges PDF bereits geladen wurde (Duplikat-Vermeidung)
- **Komplexität:** Ein Dokument kann mehrere Prozesse haben → muss korrekt gehandhabt werden
- **Speicherort:** Konfigurierbarer Ordner (Admin-Setting), Fallback: neben dem Original (z.B. `vertrag.pdf` → `vertrag_signed.pdf`)

## Entwicklungsumgebung

### Docker-Setup
- **Offizielles NC-Image** (`nextcloud:XX`) + **docker-compose**
- **Multi-Version-Support:** docker-compose Profile oder separate Configs für NC 30, 31, 32 (später 33+)
  - z.B. `docker compose --profile nc30 up` oder `NC_VERSION=30 docker compose up`
- **Datenbank:** PostgreSQL (Dev-Präferenz; App selbst ist DB-agnostisch über NC DB-Abstraktionsschicht)
- **App-Mount:** App-Verzeichnis als Volume in den Container gemountet

### sign-Instanz (lokal)
- Läuft **nativ/separat** auf dem Host unter `localhost:7755`
- NC-Container greift darauf zu via `host.docker.internal:7755` oder `extra_hosts`
- Umgebungsvariable `SIGND_BASE_URL=http://host.docker.internal:7755` im NC-Container

### Frontend-Build
- **Nativ auf dem Host:** `npm install` / `npm run dev` / `npm run watch`
- Build-Output (`js/`, `css/`) wird über das gemountete App-Volume direkt im Container sichtbar

### Berechtigungen
- **Erstmal:** Alle NC-User können Signaturprozesse starten
- **Später:** Feingranulare Berechtigungen (Admin konfiguriert User/Gruppen) als zukünftiges Feature

## Offene Recherche-Punkte (beim Implementieren klären)

1. **Namenskonvention signiertes PDF:** Wie genau benennen wenn mehrere Prozesse für ein Dokument existieren (z.B. `vertrag_signed.pdf`, `vertrag_signed_2.pdf`?)

## Testing-Strategie

- **v1 (NC 30-32):** Manuelles Testen gegen alle drei NC-Versionen via Docker Multi-Version-Setup (Profile/Variable). Deckt Sidebar-Tab, FileAction, Tab-Registrierung ab.
- **v2 (NC 33+):** Automatisierte E2E-Integrationstests geplant (zusammen mit dem Wechsel auf das neue Web-Component-basierte Sidebar-API).
