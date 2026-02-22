# Staging-Deployment (ohne App Store)

Nextcloud-Apps können manuell in das `custom_apps`-Verzeichnis kopiert werden — kein App Store oder Tarball nötig.

## Voraussetzungen

- Frontend ist gebaut (`npm run build`)
- SSH-Zugang zum NC-Server
- signd-API muss vom Server erreichbar sein

## Welche Dateien werden gebraucht?

| Braucht man | Braucht man NICHT |
|---|---|
| `appinfo/` | `node_modules/` |
| `lib/` | `src/` (Quellcode, wird kompiliert) |
| `js/` (Build-Output) | `docs/` |
| `css/`, `img/`, `templates/`, `l10n/` (falls vorhanden) | `vite.config.*`, `tsconfig*`, `package*.json` |
| | `.git/`, `docker-compose*`, `.github/` |

## Schritte

### 1. Frontend bauen

```bash
npm install && npm run build
```

### 2. Auf den Server kopieren

```bash
rsync -av \
  --exclude='node_modules' --exclude='src' --exclude='docs' \
  --exclude='.git' --exclude='docker-compose*' --exclude='package*.json' \
  --exclude='vite.config.*' --exclude='tsconfig*' --exclude='.github' \
  ./ user@server:/pfad/zu/nextcloud/custom_apps/integration_signd/
```

Den `custom_apps`-Pfad findet man in `config/config.php` unter `apps_paths`.

### 3. App aktivieren (nur beim ersten Mal)

```bash
sudo -u www-data php /pfad/zu/nextcloud/occ app:enable integration_signd
```

### 4. Bei Updates

- `npm run build` + `rsync` erneut ausführen
- Versionnummer in `appinfo/info.xml` hochsetzen, falls DB-Migrationen dazukamen
- App muss nicht erneut aktiviert werden — Seiten-Reload reicht

## Hinweise

- **Dateiberechtigungen** müssen zum Webserver-User passen (meist `www-data`)
- **Tarball-Packaging** ist nur für den App Store relevant (Signierung + Upload); für eigene Server reicht rsync/scp