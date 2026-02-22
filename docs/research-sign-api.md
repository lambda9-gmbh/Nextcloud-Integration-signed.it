# sign API - Analyse

> Siehe auch: [decisions.md](decisions.md) | [research-nextcloud-app-dev.md](research-nextcloud-app-dev.md) | [status.md](status.md)
>
> **OpenAPI Spec (Quelle der Wahrheit):** `../digisign/src/main/resources/static/api.yaml`

**Basis-URL:** (konfigurierbar, z.B. `https://signd.it`)
**API-Spezifikation:** OpenAPI 3.0.3
**Authentifizierung:** `X-API-KEY` Header (pro Account)

## Endpunkte

### Authentifizierung & Account
| Methode | Pfad | Beschreibung |
|---------|------|-------------|
| POST | `/api/v2/api-login` | Login mit E-Mail/Passwort → API-Key + User-Daten + Rechte |
| POST | `/api/register-account` | Neuen sign-Account registrieren → accountId + userId + API-Key |
| POST | `/api/prices` | Preisinfos für Produktpläne (premium/enterprise) abrufen |

### Prozess-Erstellung
| Methode | Pfad | Beschreibung |
|---------|------|-------------|
| POST | `/api/new` | Neuen Signatur-Prozess erstellen (vollständig per API) |
| POST | `/api/start-wizard` | Draft erstellen → Wizard-URL zum Fertigstellen in sign-UI |
| POST | `/api/resume-wizard` | Draft-Wizard fortsetzen → URL |
| POST | `/api/cancel-wizard` | Draft löschen |

### Prozess-Verwaltung
| Methode | Pfad | Beschreibung |
|---------|------|-------------|
| POST | `/api/cancel-process` | Prozess abbrechen (mit Begründung) |
| POST | `/api/resume-process` | Unterbrochenen Prozess fortsetzen |
| POST | `/api/update-client-metadata` | Metadaten eines Prozesses aktualisieren |

### Prozess-Abfrage
| Methode | Pfad | Beschreibung |
|---------|------|-------------|
| GET | `/api/get-meta?id=...` | Metadaten eines Prozesses/Drafts per ID |
| GET | `/api/list` | Prozesse suchen/filtern (paginiert, viele Filter) |
| GET | `/api/list-status` | Prozess-Status-Infos (kompakt) |
| GET | `/api/new-finished?gt=...` | IDs fertiggestellter Prozesse nach Zeitstempel |
| POST | `/api/find-by-original` | Prozess anhand der Original-PDF finden |

### Dokument-Download
| Methode | Pfad | Beschreibung |
|---------|------|-------------|
| GET | `/api/finished?id=...` | Fertiggestelltes PDF herunterladen |

### Hilfsfunktionen
| Methode | Pfad | Beschreibung |
|---------|------|-------------|
| POST | `/api/upload-check` | PDF auf Signatur-Integrität prüfen → Redirect-URL |
| POST | `/api/base64-encode` | Binärdaten → Base64 |
| POST | `/api/base64-decode` | Base64 → Binärdaten |
| GET | `/api/user-info` | Info über API-User |

### Callback / Notification
| Methode | Pfad | Beschreibung |
|---------|------|-------------|
| POST | (konfigurierbare URL) | Webhook-Notification bei Events |

## Wichtige Datenmodelle

### NewDocumentRequest (Prozess erstellen)
**Pflichtfelder:**
- `pdfFilename` - Dateiname der PDF
- `pdfData` - PDF als Base64-String
- `signersMode` - `SEQUENCE` (ein Dokument, nacheinander) oder `SPREAD` (jeder Unterzeichner eigene Kopie)

**Optionale Felder:**
- `initiatorName`, `initiatorEmail` - Absenderinfo
- `name` - Prozessname
- `emailRecipients[]` / `mobileRecipients[]` - Unterzeichner (per E-Mail/SMS)
- `individualInvitation/Reminder/Cancellation/Completion` - Individuelle Texte
- `pdfPassword` / `generatePassword` - PDF-Passwortschutz
- `callbackUrl` - Webhook für Status-Updates
- `protocolPlacement` - Protokoll am Anfang/Ende/gar nicht
- `deadline` - Globale Frist
- `apiClientMetaData` - Anwendungsspezifische Metadaten (JSON)
- `individualEmailSenderAddress/Name` - Eigene Absenderadresse (bei Custom-SMTP)

### RecipientData (Unterzeichner)
- `sequence` (Pflicht) - Reihenfolge/Grupppierung
- `clearName` - Name
- `accessCode` - Optionaler Zugangs-Code
- `inlineSignaturePlacements[]` - Positionierung der Signatur im PDF
- `stampMode` - `required` / `optional` / `none`
- `additionalInputs[]` - Zusätzliche Eingabefelder

### EmailRecipient / MobileRecipient
- Erbt von RecipientData
- + `email` bzw. `mobile`

### FoundProcess (Prozess-Ergebnis)
- `documentId`, `multiProcessKey`, `secretKey`
- `name`, `created`, `initiator`, `filename`
- `signersCompleted[]`, `signersRejected[]`, `signersPending[]`
- `apiClientMetaData`
- `lastSignerAction`, `interrupted`, `cancelled`

### CreatedDocument (Antwort auf `/api/new`)
- `id` - Dokument-ID
- `hash` - SHA-512 des Originals (Base64)
- `signerUrls[]` - Sign-URLs pro Unterzeichner
- `password` (optional, wenn generiert)
- `warnings[]`
- `additionalIdsCreated[]` (bei SPREAD-Modus)

### Notification (Webhook)
- `trigger` - `SIGNED` | `REJECTED` | `FINISHED` | `CANCELLED`
- `process` - FoundProcess-Objekt
- `signer` - UUID des auslösenden Unterzeichners (bei SIGNED/REJECTED)

## Prozess-Modi

### SEQUENCE
Alle Teilnehmer unterschreiben **dasselbe** Dokument. Die `sequence`-Nummer bestimmt die Reihenfolge. Nächster Unterzeichner wird erst eingeladen, wenn vorherige fertig sind.

### SPREAD
Jeder Teilnehmer (oder Gruppe mit gleicher Sequence-Nr.) bekommt eine **eigene Kopie** des Original-Dokuments.

## API-Features für die NC-App relevant

### Für die Übersichtsseite:
- `GET /api/list` - Paginierte Liste mit vielen Filtern (Status, Datum, Name, Metadaten-Suche)
- `GET /api/list-status` - Kompakte Status-Info
- Filter: `status`, `processName`, `fileName`, `initiatorName`, `signerName`, `searchQuery`, `dateFrom/To`, `metadataSearch`
- Sortierung: `PROCESS_NAME`, `INITIATOR`, `FILENAME`, `CREATED`, `LAST_SIGNER_ACTION` + ASC/DESC

### Für den Sidebar-Tab:
- `POST /api/find-by-original` - PDF-Datei senden → passende Prozesse finden
- `GET /api/get-meta?id=...` - Detailinfos zu bekanntem Prozess
- `GET /api/finished?id=...` - Fertiges PDF herunterladen

### Für Prozess-Erstellung:
- `POST /api/new` - Vollständiger Prozess per API
- `POST /api/start-wizard` - Draft + Redirect zu sign-Wizard-UI

### Für Metadaten-Tracking:
- `apiClientMetaData` bei Prozesserstellung setzen (z.B. NC File-ID, Pfad, User)
- `metadataSearch` bei `/api/list` zum Filtern nach NC-spezifischen Metadaten
- `POST /api/update-client-metadata` zum Aktualisieren

### Für Statusänderungen:
- `callbackUrl` bei Erstellung setzen → Webhook an NC-App
- Alternativ: Polling via `GET /api/list-status` oder `GET /api/new-finished?gt=...`

## Authentifizierung & Account-Verwaltung
- Jeder Request (außer `/api/prices` und `/api/register-account`) braucht `X-API-KEY` im Header
- API-Key erhält man via:
  - Login (`/api/v2/api-login`) mit E-Mail/Passwort
  - Registrierung (`/api/register-account`) - erstellt neuen Account + liefert API-Key
  - Über das sign-UI (manuell)
- Key ist account-gebunden

## Pricing-Modell

### Endpunkt: `POST /api/prices`
- Kein API-Key nötig
- Liefert Preise für `premium` und `enterprise` Plan

### PriceInfo-Struktur
- `perProcess` - Preis pro Signaturprozess (nach Aufbrauch des Inklusiv-Kontingents)
- `perMonthAndUser` - Monatspreis pro User
- `includedProcessesPerMonth` - Inklusiv-Prozesse pro Monat
- `sms` - Preis pro SMS-Einladung
- `qes` - Preis pro qualifizierter elektronischer Signatur

## Account-Registrierung

### Endpunkt: `POST /api/register-account`
- Kein API-Key nötig (erstellt ja erst den Account)

### RegisterAccountRequest
**Felder:**
- `productPlan` - `premium` oder `enterprise`
- `organisation` - Organisationsname
- `street`, `houseNumber`, `zipCode`, `city` - Adresse
- `country` - Ländercode (default: `DE`)
- `clearName` - Name des Kontoinhabers
- `email` - E-Mail-Adresse
- `password` - Passwort
- `vatId` - USt-ID (optional)
- `couponCode` - Gutschein-Code (optional)
- `agbAccepted` - AGB akzeptiert (boolean)
- `dsbAccepted` - Datenschutzerklärung akzeptiert (boolean)
- `dlvSend` - AVV verschickt (boolean)

### RegisterAccountResponse
- `accountId` - UUID des erstellten Accounts
- `userId` - Interne User-ID (int64)
- `apiKey` - Generierter API-Key für weitere Requests
