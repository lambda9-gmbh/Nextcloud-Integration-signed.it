# Nextcloud App-Entwicklung - Recherche

> Siehe auch: [decisions.md](decisions.md) | [research-sign-api.md](research-sign-api.md) | [status.md](status.md)

## 1. App-Struktur & Scaffolding

### Offizielles App-Template
- **Repository:** [github.com/nextcloud/app-template](https://github.com/nextcloud/app-template)
- **Generator:** [apps.nextcloud.com/developer/apps/generate](https://apps.nextcloud.com/developer/apps/generate) - erzeugt ein App-Gerüst mit allen Platzhaltern vorausgefüllt
- Es gibt **keinen** `occ`-Befehl zum Erzeugen neuer Apps

### Standard-Verzeichnisstruktur
```
myapp/
  appinfo/
    info.xml          # App-Metadaten (Pflicht)
    routes.php        # API-Routen
  css/
  img/
  js/                 # Build-Output (Vite)
  lib/
    AppInfo/
      Application.php # Bootstrap-Klasse
    Controller/
    Service/
    Db/
  src/                # Vue/TypeScript-Quellcode
    main.ts
    App.vue
  templates/
    main.php          # PHP-Template das Vue-App mounted
  tests/
  composer.json
  package.json
  vite.config.js
  tsconfig.json
```

## 2. Tech-Stack

### Vorgegeben / Empfohlen
| Bereich | Technologie | Status |
|---------|-------------|--------|
| Backend | **PHP 8.1+** | Pflicht |
| Frontend | **Vue 3** | Empfohlen (Standard) |
| Build | **Vite** (`@nextcloud/vite-config`) | Empfohlen (neu) |
| CSS | SCSS | Optional |
| TypeScript | Ja | Empfohlen |
| HTTP (Frontend) | `@nextcloud/axios` + `@nextcloud/router` | Standard |
| HTTP (Backend) | `OCP\Http\Client\IClientService` | Pflicht für externe Requests |
| UI-Komponenten | `@nextcloud/vue` (v8.x, ~99 Komponenten) | Standard |
| Build (Legacy) | Webpack (`@nextcloud/webpack-vue-config`) | Nur für bestehende Apps |

### Vite-Konfiguration (Minimal)
```javascript
import { createAppConfig } from '@nextcloud/vite-config'

export default createAppConfig({
  main: 'src/main.js',
  settings: 'src/settings.js',
})
```

## 3. Sidebar-Tabs (Files App)

### ACHTUNG: Zwei verschiedene APIs je nach NC-Version!

---

### A) Legacy API für NC 30-32: `OCA.Files.Sidebar.Tab`

Verwendet `OCA.Files.Sidebar` mit framework-agnostischem mount/update/destroy Pattern.
`@nextcloud/files` v3.x ist die passende Library-Version.

#### Registrierung
```javascript
import Vue from 'vue'
import MyTab from './views/MySidebarTab.vue'

const View = Vue.extend(MyTab)
let tabInstance = null

window.addEventListener('DOMContentLoaded', function() {
  if (OCA.Files && OCA.Files.Sidebar) {
    const myTab = new OCA.Files.Sidebar.Tab({
      id: 'signd',
      name: t('signd', 'signd'),
      icon: 'icon-rename',

      // fileInfo enthält: id, name, path, mountType, mimetype, size, etc.
      async mount(el, fileInfo, context) {
        if (tabInstance) {
          tabInstance.$destroy()
        }
        tabInstance = new View({ parent: context })
        tabInstance.setFileInfo(fileInfo)
        tabInstance.$mount(el)
      },

      update(fileInfo) {
        if (tabInstance) {
          tabInstance.setFileInfo(fileInfo)
        }
      },

      destroy() {
        if (tabInstance) {
          tabInstance.$destroy()
          tabInstance = null
        }
      },

      // Optional: Tab nur für bestimmte Dateitypen anzeigen
      enabled(fileInfo) {
        return fileInfo && fileInfo.mimetype === 'application/pdf'
      },
    })

    OCA.Files.Sidebar.registerTab(myTab)
  }
})
```

#### Vue-Komponente (NC 30-32)
```vue
<template>
  <div class="signd-sidebar-tab">
    <h3>{{ fileInfo?.name }}</h3>
    <!-- Tab-Inhalt -->
  </div>
</template>

<script>
export default {
  name: 'SigndSidebarTab',
  data() {
    return {
      fileInfo: null,
    }
  },
  methods: {
    setFileInfo(fileInfo) {
      this.fileInfo = fileInfo
      // Hier Daten laden wenn fileInfo sich ändert
    },
  },
}
</script>
```

#### fileInfo-Objekt (Legacy)
Das `fileInfo`-Objekt enthält u.a.:
- `id` - File-ID
- `name` - Dateiname
- `path` - Pfad relativ zum User-Root
- `mimetype` - MIME-Type
- `size` - Dateigröße
- `mountType` - Mount-Typ (z.B. "external")

---

### B) Neues API für NC 33+: `getSidebar().registerTab()` (Web Components)

Seit **NC 33** (18.02.2026) wurde `OCA.Files.Sidebar` entfernt. Neues API aus `@nextcloud/files` v4.x+.

#### Registrierung
```typescript
import { getSidebar } from '@nextcloud/files'
import { defineAsyncComponent, defineCustomElement } from 'vue'
import { t } from '@nextcloud/l10n'
import svgIcon from '../img/icon.svg?raw'

getSidebar().registerTab({
  id: 'signd-tab',
  displayName: t('signd', 'signd'),
  iconSvgInline: svgIcon,
  order: 50,
  tagName: 'signd-sidebar-tab',

  enabled(node) {
    return node.type === 'file' && node.mime === 'application/pdf'
  },

  async onInit() {
    const MyTab = defineAsyncComponent(
      () => import('./views/SigndSidebarTab.vue')
    )
    const MyTabWC = defineCustomElement(MyTab, { shadowRoot: false })
    customElements.define('signd-sidebar-tab', MyTabWC)
  },
})
```

#### Vue-Komponente (NC 33+)
```vue
<template>
  <div class="signd-sidebar-tab">
    <h3>{{ node?.basename }}</h3>
  </div>
</template>

<script>
export default {
  props: {
    node: { type: Object, default: null },    // INode
    folder: { type: Object, default: null },   // IFolder
    view: { type: Object, default: null },     // IView
    active: { type: Boolean, default: false },
  },
}
</script>
```

**Wichtig:** `shadowRoot: false` verwenden, damit Nextcloud-Theming greift.

---

### Konsequenz für unser Projekt
v1 (NC 30-32) nutzt das Legacy-API (A). v2 (NC 33+) muss auf das neue API (B) migriert werden.
Die Vue-Komponente selbst kann weitgehend identisch bleiben - nur die Registrierung und das Daten-Interface unterscheiden sich.

## 4. File Actions (Kontextmenü)

### API (ab NC 28 - `@nextcloud/files`)
```typescript
import { FileAction, registerFileAction } from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import SvgIcon from '../img/icon.svg?raw'

registerFileAction(
  new FileAction({
    id: 'myapp-sign-file',
    displayName: () => t('myapp', 'Digitally Sign'),
    iconSvgInline: () => SvgIcon,
    order: 25,

    enabled(nodes) {
      return nodes.every((node) => node.mime === 'application/pdf')
    },

    async exec(node, view, dir) {
      // Sidebar öffnen und Tab aktivieren
      return true
    },
  })
)
```

### Skript-Laden via Event-Listener
```php
// lib/Listener/LoadAdditionalListener.php
use OCA\Files\Event\LoadAdditionalScriptsEvent;

class LoadAdditionalListener implements IEventListener {
    public function handle(Event $event): void {
        if (!($event instanceof LoadAdditionalScriptsEvent)) return;
        Util::addInitScript('myapp', 'init');
    }
}
```

## 5. Backend: Externe API-Aufrufe

### IClientService (Pflicht für externe HTTP-Requests)
```php
use OCP\Http\Client\IClientService;

class SignApiService {
    public function __construct(private IClientService $clientService) {}

    public function callSignApi(string $apiKey, string $endpoint, array $data): array {
        $client = $this->clientService->newClient();
        $response = $client->post($endpoint, [
            'headers' => [
                'X-API-KEY' => $apiKey,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($data),
        ]);
        return json_decode($response->getBody(), true);
    }
}
```

### Initial State (PHP → JS ohne API-Call)
```php
// PHP
$initialStateService->provideInitialState('myapp', 'config', ['apiUrl' => $url]);
```
```typescript
// JS
import { loadState } from '@nextcloud/initial-state'
const config = loadState('myapp', 'config')
```

## 6. Einstellungen (Settings)

### Zwei Ansätze:

**a) Klassisch (ISettings):**
- PHP-Klasse implementiert `OCP\Settings\ISettings`
- Vue-Frontend für die Settings-Seite
- Registrierung in `info.xml` unter `<settings>`

**b) Deklarativ (ab NC 29):**
- PHP-Klasse implementiert `IDeclarativeSettingsForm`
- Kein Frontend-Code nötig
- Feldtypen: TEXT, PASSWORD, EMAIL, URL, NUMBER, CHECKBOX, SELECT, etc.
- Automatische Speicherung in `appconfig` (admin) / `preferences` (personal)

## 7. Docker-Entwicklungsumgebung

### Empfohlen: Standalone-Container
```bash
# Einfache NC-Instanz mit gemounteter App
docker run --rm -p 8080:80 \
  -v ~/code/myapp:/var/www/html/apps-extra/myapp \
  ghcr.io/juliusknorr/nextcloud-dev-php81:latest

# Bestimmte Server-Version
docker run --rm -p 8080:80 \
  -e SERVER_BRANCH=stable32 \
  -v ~/code/myapp:/var/www/html/apps-extra/myapp \
  ghcr.io/juliusknorr/nextcloud-dev-php81:latest
```

### Minimale docker-compose
```yaml
version: '3'
services:
  nextcloud:
    image: nextcloud:latest
    ports:
      - "8080:80"
    volumes:
      - nextcloud_data:/var/www/html
      - ./myapp:/var/www/html/custom_apps/myapp
    environment:
      - SQLITE_DATABASE=nextcloud
      - NEXTCLOUD_ADMIN_USER=admin
      - NEXTCLOUD_ADMIN_PASSWORD=admin

  db:
    image: mariadb:10.11
    environment:
      - MYSQL_ROOT_PASSWORD=nextcloud
      - MYSQL_DATABASE=nextcloud
      - MYSQL_USER=nextcloud
      - MYSQL_PASSWORD=nextcloud
    volumes:
      - db_data:/var/lib/mysql

volumes:
  nextcloud_data:
  db_data:
```

### Vollständige Dev-Umgebung
[github.com/juliusknorr/nextcloud-docker-dev](https://github.com/juliusknorr/nextcloud-docker-dev)

## 8. App Store Veröffentlichung

### Lizenz
- **AGPL-3.0-or-later** (empfohlen) oder kompatible (Apache-2.0, GPL-3.0+, MIT, MPL-2.0)

### Code-Signierung
1. CSR generieren: `openssl req -nodes -newkey rsa:4096 -keyout app.key -out app.csr -subj "/CN=appid"`
2. CSR als PR einreichen bei [github.com/nextcloud/app-certificate-requests](https://github.com/nextcloud/app-certificate-requests)
3. App signieren: `occ integrity:sign-app --privateKey=... --certificate=... --path=...`

### info.xml Pflichtfelder
- `<id>`, `<name>`, `<summary>`, `<description>`, `<version>`, `<licence>`
- `<author>`, `<namespace>`, `<category>`, `<dependencies>`
- `<bugs>` (Issue-Tracker URL), `<repository>`

### Wichtige Regeln
- "Nextcloud" darf **nicht** im App-Namen vorkommen
- Nur **öffentliche** NC-APIs verwenden
- Kompatibilität max. auf aktuelle NC-Version + 1 setzen
- App muss bei Deinstallation aufräumen
- Externe Datenübertragung muss klar kommuniziert werden

### Gültige Kategorien
`customization`, `files`, `games`, `integration`, `monitoring`, `multimedia`, `office`, `organization`, `security`, `social`, `tools`

## 9. Nextcloud-Versionen (Stand Feb. 2026)

| Version | Release | End of Life | Status |
|---------|---------|-------------|--------|
| **33** | 18.02.2026 | 2027-02 | Aktuell |
| **32** | 27.09.2025 | 2026-09 | Supported |
| **31** | 25.02.2025 | 2026-02 | Am Auslaufen |
| 30 | 11.09.2024 | Okt 2025 | EOL |
| 29 | März 2024 | März 2025 | EOL |

### Empfehlung für neue App
```xml
<dependencies>
    <php min-version="8.1"/>
    <nextcloud min-version="30" max-version="33"/>
</dependencies>
```

## 10. Wichtige @nextcloud Pakete

| Paket | Zweck |
|-------|-------|
| `@nextcloud/vue` | UI-Komponenten (NcAppContent, NcButton, NcDialog, ...) |
| `@nextcloud/files` | FileAction, Sidebar-Tab-Registrierung |
| `@nextcloud/axios` | HTTP-Client (mit CSRF-Token) |
| `@nextcloud/router` | `generateUrl()` für API-Pfade |
| `@nextcloud/l10n` | `t()` und `n()` für Übersetzungen |
| `@nextcloud/initial-state` | `loadState()` für PHP→JS Daten |
| `@nextcloud/vite-config` | Vite Build-Konfiguration |
