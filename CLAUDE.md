# signd.it Integration – Nextcloud App

Nextcloud-App (v1, NC 30–32) zur Integration mit signd.it (digitales PDF-Signieren).

## Dokumentation

- **[docs/decisions.md](docs/decisions.md)** — Architektur-Entscheidungen (verbindlich, bei Widersprüchen maßgeblich)
- **[docs/status.md](docs/status.md)** — Was ist fertig, was fehlt (Prio 1/2/3)
- **[docs/edge-cases.md](docs/edge-cases.md)** — Fehlerszenarien & Bewertungen
- **[docs/testplan.md](docs/testplan.md)** — Testplan aller drei Ebenen (PHPUnit, Vitest, Playwright)
- **[docs/research-sign-api.md](docs/research-sign-api.md)** — signd API Analyse
- **[docs/research-nextcloud-app-dev.md](docs/research-nextcloud-app-dev.md)** — NC App-Entwicklung Patterns
- **signd OpenAPI Spec:** `../digisign/src/main/resources/static/api.yaml` (Quelle der Wahrheit)

## Konventionen

- Sprache im Code: Englisch. Docs/Kommentare: Deutsch OK.
- **NICHT** das bestehende sign-plugin anschauen — komplett neuer Ansatz.

## Tech-Stack

| Bereich | Technologie |
|---------|-------------|
| Backend | PHP 8.1+, NC App Framework |
| Frontend | Vue 3, TypeScript, Vite |
| Build | `@nextcloud/vite-config`, drei Entrypoints |
| NC-Pakete | `@nextcloud/vue` v8, `@nextcloud/files` v3, `@nextcloud/axios`, `@nextcloud/router`, `@nextcloud/l10n`, `@nextcloud/initial-state` |
| DB | NC DB-Abstraktionsschicht (QBMapper), Tabelle `oc_integration_signd_processes` |
| Dev | Docker (NC 30–32 + PostgreSQL), Frontend-Build nativ auf Host |

## Projektstruktur

```
appinfo/              info.xml, routes.php
lib/
  Controller/         SettingsController, ProcessController, PageController, OverviewController
  Service/            SignApiService (zentraler HTTP-Client für signd-API)
  Db/                 Process Entity + ProcessMapper
  Settings/           AdminSettings, AdminSection
  Listener/           LoadAdditionalListener (injiziert Frontend in Files-App)
  Migration/          DB-Schema
src/
  settings/           Admin-Settings Vue-Komponenten
  views/              SigndSidebarTab, OverviewApp
  components/         ProcessList, ProcessStatus, StartProcessButton, SignerList
  components/overview/ OverviewToolbar, OverviewTable, OverviewPagination, ProcessDetail
  services/api.ts     Frontend API-Client (Settings + Processes + Overview)
  main-settings.ts    Entrypoint: Admin-Settings
  main-files.ts       Entrypoint: FileAction + Sidebar-Tab (Legacy, NC 30-32)
  main-overview.ts    Entrypoint: Übersichtsseite (Prozessliste + Detail-Sidebar)
tests/
  Unit/               PHPUnit Unit-Tests (spiegelt lib/)
  frontend/           Vitest Frontend-Tests (spiegelt src/)
    setup.ts          Globale Mocks (@nextcloud/axios, router, l10n, initial-state)
e2e/                  Playwright E2E-Tests
  fixtures/           Test-Fixtures (Login etc.)
docs/                 Entscheidungen, Recherche, Status
```

## Wichtige Dateien

| Datei | Zweck |
|-------|-------|
| `lib/Service/SignApiService.php` | Alle signd-API-Aufrufe, API-URL-Auflösung |
| `lib/Controller/ProcessController.php` | Prozess-CRUD, Wizard-Start, PDF-Download |
| `lib/Controller/SettingsController.php` | API-Key-Verwaltung (3 Wege) |
| `lib/Controller/OverviewController.php` | Prozessliste (signd-API Proxy) + Cancel |
| `src/services/api.ts` | Frontend HTTP-Client |
| `src/main-files.ts` | FileAction + Sidebar-Tab-Registrierung |
| `src/views/OverviewApp.vue` | Hauptkomponente Übersichtsseite |
| `appinfo/routes.php` | Alle Backend-Routen |

## Dev-Befehle

```bash
npm install && npm run build   # Frontend bauen
docker compose up -d           # NC + DB starten
npm run enable-app             # App aktivieren (integration_signd)
npm run watch                  # Frontend-Dev mit Watch
npm run logs                   # Container-Logs
```

NC: http://localhost:8080 (admin/admin), signd lokal: localhost:7755

## Tests

Drei Testebenen, alle aktiv:

### PHPUnit (Backend Unit-Tests)

```bash
composer install
vendor/bin/phpunit --testsuite Unit
```

- Config: `phpunit.xml`, Testsuites `Unit` + `Integration`
- Tests in `tests/Unit/` — Struktur spiegelt `lib/`
- Mocking: PHPUnit MockBuilder für NC-Interfaces (`IClientService`, `IConfig`, `LoggerInterface`)
- `tests/bootstrap.php` registriert OCP-Namespace manuell (nextcloud/ocp hat kein eigenes Autoloading)
- Kein laufender NC-Server nötig

### Vitest (Frontend Unit-/Komponenten-Tests)

```bash
npm test              # einmaliger Lauf
npm run test:watch    # Watch-Modus
```

- Config: `vitest.config.ts` (separate Datei, nicht in vite.config.ts — `createAppConfig` nicht erweiterbar)
- Tests in `tests/frontend/` — Struktur spiegelt `src/`
- `tests/frontend/setup.ts` mockt `@nextcloud/axios`, `@nextcloud/router`, `@nextcloud/l10n`, `@nextcloud/initial-state`
- `@nextcloud/vue`-Komponenten (NcButton etc.) als Stubs mit `inheritAttrs: false` in Tests
- happy-dom als Test-Environment, globals aktiv

### Playwright (E2E-Tests)

```bash
# Voraussetzung: Docker-Umgebung + App müssen laufen
npm run test:e2e           # headless
npm run test:e2e:headed    # mit Browser-Fenster
```

- Config: `playwright.config.ts`, nur Chromium, single worker, baseURL `localhost:8080`
- Tests in `e2e/`, Login-Fixture in `e2e/fixtures/auth.ts` (admin/admin)

### Konventionen

- PHP-Tests: `FooTest.php` testet `Foo.php`
- Frontend-Tests: `foo.test.ts` mit `@vue/test-utils` für Komponenten
- Neue Features/Bugfixes: passende Tests mitliefern