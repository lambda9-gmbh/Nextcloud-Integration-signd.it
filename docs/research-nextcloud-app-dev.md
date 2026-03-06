# Nextcloud App Development - Research

> See also: [research-sign-api.md](research-sign-api.md)

## 1. App Structure & Scaffolding

### Official App Template
- **Repository:** [github.com/nextcloud/app-template](https://github.com/nextcloud/app-template)
- **Generator:** [apps.nextcloud.com/developer/apps/generate](https://apps.nextcloud.com/developer/apps/generate) - generates an app skeleton with all placeholders pre-filled
- There is **no** `occ` command for creating new apps

### Standard Directory Structure
```
myapp/
  appinfo/
    info.xml          # App metadata (required)
    routes.php        # API routes
  css/
  img/
  js/                 # Build output (Vite)
  lib/
    AppInfo/
      Application.php # Bootstrap class
    Controller/
    Service/
    Db/
  src/                # Vue/TypeScript source code
    main.ts
    App.vue
  templates/
    main.php          # PHP template that mounts Vue app
  tests/
  composer.json
  package.json
  vite.config.js
  tsconfig.json
```

## 2. Tech Stack

### Required / Recommended
| Area | Technology | Status |
|------|------------|--------|
| Backend | **PHP 8.1+** | Required |
| Frontend | **Vue 3** | Recommended (standard) |
| Build | **Vite** (`@nextcloud/vite-config`) | Recommended (new) |
| CSS | SCSS | Optional |
| TypeScript | Yes | Recommended |
| HTTP (frontend) | `@nextcloud/axios` + `@nextcloud/router` | Standard |
| HTTP (backend) | `OCP\Http\Client\IClientService` | Required for external requests |
| UI components | `@nextcloud/vue` (v8.x, ~99 components) | Standard |
| Build (legacy) | Webpack (`@nextcloud/webpack-vue-config`) | Only for existing apps |

### Vite Configuration (Minimal)
```javascript
import { createAppConfig } from '@nextcloud/vite-config'

export default createAppConfig({
  main: 'src/main.js',
  settings: 'src/settings.js',
})
```

## 3. Sidebar Tabs (Files App)

### NOTE: Two different APIs depending on NC version!

---

### A) Legacy API for NC 30-32: `OCA.Files.Sidebar.Tab`

Uses `OCA.Files.Sidebar` with a framework-agnostic mount/update/destroy pattern.
`@nextcloud/files` v3.x is the matching library version.

#### Registration
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

      // fileInfo contains: id, name, path, mountType, mimetype, size, etc.
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

      // Optional: Only show tab for certain file types
      enabled(fileInfo) {
        return fileInfo && fileInfo.mimetype === 'application/pdf'
      },
    })

    OCA.Files.Sidebar.registerTab(myTab)
  }
})
```

#### Vue Component (NC 30-32)
```vue
<template>
  <div class="signd-sidebar-tab">
    <h3>{{ fileInfo?.name }}</h3>
    <!-- Tab content -->
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
      // Load data here when fileInfo changes
    },
  },
}
</script>
```

#### fileInfo Object (Legacy)
The `fileInfo` object contains among others:
- `id` - File ID
- `name` - Filename
- `path` - Path relative to user root
- `mimetype` - MIME type
- `size` - File size
- `mountType` - Mount type (e.g. "external")

---

### B) New API for NC 33+: `getSidebar().registerTab()` (Web Components)

Since **NC 33** (2026-02-18), `OCA.Files.Sidebar` has been removed. New API from `@nextcloud/files` v4.x+.

#### Registration
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

#### Vue Component (NC 33+)
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

**Important:** Use `shadowRoot: false` so that Nextcloud theming applies.

---

### Consequence for Our Project
v1 (NC 30-32) uses the legacy API (A). v2 (NC 33+) must migrate to the new API (B).
The Vue component itself can remain largely identical — only the registration and the data interface differ.

## 4. File Actions (Context Menu)

### API (from NC 28 - `@nextcloud/files`)
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
      // Open sidebar and activate tab
      return true
    },
  })
)
```

### Script Loading via Event Listener
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

## 5. Backend: External API Calls

### IClientService (Required for External HTTP Requests)
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

### Initial State (PHP → JS Without API Call)
```php
// PHP
$initialStateService->provideInitialState('myapp', 'config', ['apiUrl' => $url]);
```
```typescript
// JS
import { loadState } from '@nextcloud/initial-state'
const config = loadState('myapp', 'config')
```

## 6. Settings

### Two Approaches:

**a) Classic (ISettings):**
- PHP class implements `OCP\Settings\ISettings`
- Vue frontend for the settings page
- Registration in `info.xml` under `<settings>`

**b) Declarative (from NC 29):**
- PHP class implements `IDeclarativeSettingsForm`
- No frontend code needed
- Field types: TEXT, PASSWORD, EMAIL, URL, NUMBER, CHECKBOX, SELECT, etc.
- Automatic storage in `appconfig` (admin) / `preferences` (personal)

## 7. Docker Development Environment

### Recommended: Standalone Container
```bash
# Simple NC instance with mounted app
docker run --rm -p 8080:80 \
  -v ~/code/myapp:/var/www/html/apps-extra/myapp \
  ghcr.io/juliusknorr/nextcloud-dev-php81:latest

# Specific server version
docker run --rm -p 8080:80 \
  -e SERVER_BRANCH=stable32 \
  -v ~/code/myapp:/var/www/html/apps-extra/myapp \
  ghcr.io/juliusknorr/nextcloud-dev-php81:latest
```

### Minimal docker-compose
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

### Full Dev Environment
[github.com/juliusknorr/nextcloud-docker-dev](https://github.com/juliusknorr/nextcloud-docker-dev)

## 8. App Store Publication

### License
- **AGPL-3.0-or-later** (recommended) or compatible (Apache-2.0, GPL-3.0+, MIT, MPL-2.0)

### Code Signing
1. Generate CSR: `openssl req -nodes -newkey rsa:4096 -keyout app.key -out app.csr -subj "/CN=appid"`
2. Submit CSR as PR at [github.com/nextcloud/app-certificate-requests](https://github.com/nextcloud/app-certificate-requests)
3. Sign app: `occ integrity:sign-app --privateKey=... --certificate=... --path=...`

### info.xml Required Fields
- `<id>`, `<name>`, `<summary>`, `<description>`, `<version>`, `<licence>`
- `<author>`, `<namespace>`, `<category>`, `<dependencies>`
- `<bugs>` (issue tracker URL), `<repository>`

### Important Rules
- "Nextcloud" must **not** appear in the app name
- Only use **public** NC APIs
- Set compatibility to current NC version + 1 at most
- App must clean up on uninstall
- External data transmission must be clearly communicated

### Valid Categories
`customization`, `files`, `games`, `integration`, `monitoring`, `multimedia`, `office`, `organization`, `security`, `social`, `tools`

## 9. Nextcloud Versions (as of Feb. 2026)

| Version | Release | End of Life | Status |
|---------|---------|-------------|--------|
| **33** | 2026-02-18 | 2027-02 | Current |
| **32** | 2025-09-27 | 2026-09 | Supported |
| **31** | 2025-02-25 | 2026-02 | Phasing out |
| 30 | 2024-09-11 | Oct 2025 | EOL |
| 29 | March 2024 | March 2025 | EOL |

### Recommendation for New App
```xml
<dependencies>
    <php min-version="8.1"/>
    <nextcloud min-version="30" max-version="33"/>
</dependencies>
```

## 10. Key @nextcloud Packages

| Package | Purpose |
|---------|---------|
| `@nextcloud/vue` | UI components (NcAppContent, NcButton, NcDialog, ...) |
| `@nextcloud/files` | FileAction, sidebar tab registration |
| `@nextcloud/axios` | HTTP client (with CSRF token) |
| `@nextcloud/router` | `generateUrl()` for API paths |
| `@nextcloud/l10n` | `t()` and `n()` for translations |
| `@nextcloud/initial-state` | `loadState()` for PHP→JS data |
| `@nextcloud/vite-config` | Vite build configuration |