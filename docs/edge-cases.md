# Edge Cases & Fehlerszenarien

> Analyse von Nicht-Happy-Path-Szenarien für die integration_signd NC-App.
> Fokus: Dateninkonsistenz zwischen NC-DB und signd.it, Fehler bei Dateioperationen, Autorisierung.
>
> **Abgeschlossen:** 2026-02-21 — Alle 13 Szenarien analysiert und bewertet.
> Erforderliche Maßnahmen sind in [status.md](status.md) als Tasks übernommen.

## Status-Legende

- ✅ Analysiert & bewertet
- 🔧 Fix geplant (→ status.md)

---

## 1. Original-PDF wird nach Prozessstart gelöscht

**Risiko:** Mittel
**Kategorie:** Dateninkonsistenz NC ↔ signd.it

**Problem:**
- `oc_integration_signd_processes`-Eintrag bleibt als Waise bestehen (kein FK auf NC-Datei möglich, da `oc_filecache` NC-intern ist)
- Sidebar-Tab kann nicht mehr geöffnet werden → Prozesse nur noch über Overview sichtbar
- `download()` braucht einen Zielordner für die signierte PDF → Original-Ordner existiert evtl. nicht mehr

**Bewertung:**
- Der signd.it-Prozess selbst läuft problemlos weiter (ist unabhängig von NC-Dateisystem)
- Löschen auf NC-Seite lässt sich nicht zuverlässig intercepten (Dateien können auch extern im FS manipuliert werden)
- Lokale DB speichert `target_dir` (Ordner des Originals zum Startzeitpunkt) → beim Download prüfen ob Ordner noch existiert, sonst Fallback (z.B. User-Root oder Fehlermeldung)
- Verwaiste DB-Einträge: Cleanup-Mechanismus nötig (Background-Job prüft ob `file_id` noch existiert)

**Maßnahmen:**
- [x] `target_dir` beim Prozessstart speichern
- [x] Download: Fallback wenn Zielordner nicht mehr existiert
- [ ] Cleanup-Job für verwaiste Einträge (file_id existiert nicht mehr in NC)
- [x] Overview zeigt Prozesse auch ohne zugehörige NC-Datei korrekt an (Link ausgegraut/entfernt)

**Status:** 🔧 Großteils erledigt, nur Cleanup-Job offen (→ status.md Prio 2)

---

## 2. Prozess in signd.it direkt abgebrochen

**Risiko:** Niedrig (durch Architektur entschärft)
**Kategorie:** Status-Synchronisation

**Bewertung:**
- Kein echtes Problem: Status wird nicht lokal gespeichert, sondern immer live von der signd-API abgefragt
- Sobald der User die Overview öffnet oder den Sidebar-Tab einer Datei anschaut, sieht er den aktuellen Status
- Kein Handlungsbedarf

**Status:** ✅ Kein Problem

---

## 3. Prozess auf signd.it fertig, aber NC weiß nichts davon

**Risiko:** Niedrig (durch Architektur entschärft)
**Kategorie:** Status-Synchronisation

**Bewertung:**
- Wie Szenario 2: Status kommt live von der API, nie veraltet sobald der User nachschaut
- Overview-Seite und Sidebar-Tab zeigen immer den aktuellen Stand
- Kein Handlungsbedarf

**Status:** ✅ Kein Problem

---

## 4. Doppelter Prozessstart / Wizard-Handling

**Risiko:** Niedrig (teilweise bereits abgesichert)
**Kategorie:** Race Condition / UX

**Ist-Zustand:**
- `StartProcessButton.vue` disabled den Button bereits während des Requests (`isStarting`-Flag) → Doppelklick-Schutz vorhanden
- `SignApiService.php` hat `resumeWizard()` und `cancelWizard()` implementiert, aber sie werden noch nirgends aufgerufen

**Bewertung:**
- Mehrere Prozesse pro Original-Datei sind **bewusst erlaubt** (z.B. verschiedene Signer-Konstellationen)
- Beim Klick soll genau ein Wizard-Tab aufgehen — ist durch Button-Disable abgesichert
- Wenn Sidebar zu viele Prozesse für eine Datei anzeigt → auf Overview verlinken (mit File-Filter vorbelegt)

**Maßnahmen:**
- [x] `resume-wizard` in Sidebar einbauen: Wenn ein laufender Wizard (Draft) für die Datei existiert, "Wizard fortsetzen" statt "Neu starten" anbieten
- [x] `cancel-wizard` in Sidebar einbauen: Draft-Prozess abbrechen können
- [ ] Sidebar: Ab einer gewissen Anzahl Prozesse auf Overview verlinken (mit Filter auf `fileId`)
- [ ] Später (NC-interner Wizard): Ebenfalls Button-Disable bis Backend-Antwort

**Status:** 🔧 Fix geplant (→ status.md Prio 1)

---

## 5. Paralleler Download derselben signierten PDF

**Risiko:** Niedrig
**Kategorie:** Race Condition / Dateioperationen

**Problem:**
- Zwei Requests gleichzeitig → beide laden von signd-API → evtl. Duplikat (`_signed.pdf` + `_signed_1.pdf`)
- Bei Fehler zwischen Dateischreiben und DB-Update → nächster Download erzeugt Duplikat

**Bewertung:**
- Normalfall wird abgefangen: `finishedPdfPath` gesetzt → "Already downloaded"
- Worst Case: Eine Datei zu viel — harmlos, User löscht sie
- Kein Datenverlust, kein korrupter Zustand

**Status:** ✅ Akzeptiertes Restrisiko, kein Handlungsbedarf

---

## 6. NC-Speicher voll / Quota überschritten

**Risiko:** Niedrig
**Kategorie:** Dateioperationen

**Problem:**
- `download()` holt PDF von signd-API, Schreiben in NC schlägt fehl wegen Quota/Speicher
- PDF-Daten im RAM sind weg

**Bewertung:**
- Selbstheilend: Prozess auf signd.it bleibt FINISHED, User räumt Speicher frei und klickt nochmal Download
- Kein Datenverlust, kein korrupter Zustand

**Maßnahmen:**
- [x] Fehlermeldung für den User klar und verständlich darstellen (z.B. "Speicher voll — bitte Platz schaffen und erneut versuchen")

**Status:** ✅ Akzeptiertes Restrisiko, Fehler-UX umgesetzt

---

## 7. signd.it nicht erreichbar (temporär)

**Risiko:** Niedrig
**Kategorie:** Netzwerk / API-Fehler

**Problem:**
- API-Calls schlagen fehl, User sieht Fehlermeldungen
- `startWizard()` erzeugt auf signd-Seite nur einen Draft — Ghost-Drafts werden nach gewisser Zeit automatisch gelöscht

**Bewertung:**
- Kein Ghost-Prozess-Problem: Erst nach Wizard-Abschluss wird ein echter Prozess daraus
- Drafts können in der signd.it-UI eingesehen/fortgesetzt/abgebrochen werden
- Overview/Sidebar zeigen Fehler statt Daten — selbstheilend sobald API wieder erreichbar
- Kein Handlungsbedarf über saubere Fehlermeldungen hinaus

**Status:** ✅ Kein Problem

---

## 8. Fehlende Ownership-Checks (Autorisierung)

**Risiko:** Niedrig (bewusste Designentscheidung)
**Kategorie:** Sicherheit

**Bewertung:**
- Aktuell gewollt: Ein API-Key pro NC-Instanz, alle User teilen sich den signd-Account
- Alle eingeloggten User können alle Prozesse sehen/bedienen — entspricht dem aktuellen Modell
- Feingranulare Berechtigungen (User/Gruppen) als zukünftiges Feature vorgesehen (siehe decisions.md)
- Kein Handlungsbedarf für v1

**Status:** ✅ Bewusst akzeptiert für v1

---

## 9. NC hinter verschiedenen URLs erreichbar

**Risiko:** Mittel
**Kategorie:** Dateninkonsistenz / Konfiguration

**Problem:**
- Overview filtert aktuell per `applicationMetaData.ncInstanceUrl` (basiert auf `getAbsoluteURL('/')`)
- URL variiert je nach Zugriffs-Domain/IP → Prozesse "verschwinden" aus der Overview
- App landet im AppStore → kein Einfluss darauf, wie Nutzer ihre NC aufrufen

**Lösung:**
- `ncInstanceId` statt `ncInstanceUrl` verwenden — stabile `instanceid` pro NC-Installation (`$config->getSystemValue('instanceid')`)
- URL komplett raus aus den Metadaten, nur noch `ncInstanceId` für Scoping

**Maßnahmen:**
- [x] `apiClientMetaData`: `ncInstanceUrl` ersetzen durch `ncInstanceId`
- [x] Overview-Filter: `metadataSearch` auf `ncInstanceId` umstellen

**Status:** ✅ Erledigt

---

## 10. Admin ändert API-Key während laufender Prozesse

**Risiko:** Kein Risiko
**Kategorie:** Konfiguration

**Bewertung:**
- API-Key dient nur zur Authentifizierung, nicht zur Prozess-Zuordnung
- Solange der neue Key zum selben signd-Account gehört, bleiben alle Prozesse zugänglich
- Kein Handlungsbedarf

**Status:** ✅ Kein Problem

---

## 11. NC-User wird gelöscht

**Risiko:** Kein Risiko
**Kategorie:** Dateninkonsistenz / Cleanup

**Bewertung:**
- Prozesse gehören der NC-Instanz, nicht dem einzelnen User
- Andere User können die Prozesse weiterhin in der Overview sehen und bedienen
- DB-Einträge sollen bewusst **nicht** aufgeräumt werden — die Zuordnung (Datei ↔ Prozess) bleibt nützlich
- `user_id` in der DB ist nur informativ ("wer hat gestartet"), nicht funktionskritisch

**Status:** ✅ Kein Problem

---

## 12. signd-API ändert Response-Format / unerwartete Status

**Risiko:** Niedrig
**Kategorie:** API-Kompatibilität

**Bewertung:**
- signd-API wird nur ohne Breaking Changes angepasst
- `interrupted` ist ein Zwischenzustand, der im signd-UI aufgelöst wird (fortsetzen oder abbrechen) — für NC-Anzeige nicht relevant
- Status wird nicht lokal gespeichert, sondern immer live abgefragt → keine Persistenz-Probleme
- Für die Anzeige reicht: laufend / fertig / abgebrochen

**Status:** ✅ Kein Problem

---

## 13. Große PDFs (Memory)

**Risiko:** Niedrig
**Kategorie:** Performance / Stabilität

**Problem:**
- `getFinishedPdf()` lädt gesamte PDF via `$response->getBody()` in RAM
- Bei sehr großen Dokumenten könnte PHP `memory_limit` (NC-Default: 512 MB) erreicht werden

**Bewertung:**
- In bisheriger signd-Laufzeit keine problematisch großen Dokumente aufgetreten
- Fertige PDFs werden angereichert (Protokoll, Unterschriften-Bilder), bleiben aber überschaubar
- Falls nötig: Streaming statt RAM (Guzzle `sink`-Option direkt in NC-File-Stream schreiben)
- Aktuell kein Handlungsbedarf, bei Bedarf Streaming nachrüsten

**Status:** ✅ Akzeptiertes Restrisiko
