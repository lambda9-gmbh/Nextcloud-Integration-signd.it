# signd.it Integration – Nextcloud App

Nextcloud app (v2, NC 33+) for integration with signd.it (digital PDF signing).

## Documentation

- **[docs/research-sign-api.md](docs/research-sign-api.md)** — signd API analysis
- **[docs/research-nextcloud-app-dev.md](docs/research-nextcloud-app-dev.md)** — NC app development patterns
- **signd OpenAPI Spec:** `../digisign/src/main/resources/static/api.yaml` (source of truth)

## Conventions

- Language in code: English.
- Do **NOT** look at the existing sign-plugin — completely new approach.

### Translations (l10n)

**Every new or changed user-facing string must be added to all translation files.**

- Files: `l10n/{en,de,da,fr,es,it,pl,pt}.json` + matching `.js` files (both must be updated)
- Format `.json`: `"Key": "Translated value"` inside `"translations": { ... }`
- Format `.js`: `"Key": "Translated value",` inside `OC.L10N.register(...)`
- Entries are **sorted alphabetically** by key
- In code, use `t('integration_signd', 'My string')` (imported from `@nextcloud/l10n`)
- When adding a feature or fixing a bug that touches UI text, check **all** new `t()` calls and ensure every string exists in all 8 language files

## Tech Stack

| Area | Technology |
|------|------------|
| Backend | PHP 8.2+, NC App Framework |
| Frontend | Vue 3, TypeScript, Vite |
| Build | `@nextcloud/vite-config`, three entrypoints |
| NC packages | `@nextcloud/vue` v9, `@nextcloud/files` v4, `@nextcloud/axios`, `@nextcloud/router`, `@nextcloud/l10n`, `@nextcloud/initial-state` |
| DB | NC DB abstraction layer (QBMapper), table `oc_integration_signd_processes` |
| Dev | Docker (NC 33 + PostgreSQL), frontend build natively on host |

## Project Structure

```
appinfo/              info.xml, routes.php
lib/
  Controller/         SettingsController, ProcessController, PageController, OverviewController
  Service/            SignApiService (central HTTP client for signd API)
  Db/                 Process Entity + ProcessMapper
  Settings/           AdminSettings, AdminSection
  Listener/           LoadAdditionalListener (injects frontend into Files app)
  Migration/          DB schema
src/
  settings/           Admin settings Vue components
  views/              SigndSidebarTab, OverviewApp
  components/         ProcessList, ProcessStatus, StartProcessButton, SignerList
  components/overview/ OverviewToolbar, OverviewTable, OverviewPagination, ProcessDetail
  services/api.ts     Frontend API client (Settings + Processes + Overview)
  main-settings.ts    Entrypoint: Admin settings
  main-files.ts       Entrypoint: FileAction + Sidebar tab (Web Component, NC 33+)
  main-overview.ts    Entrypoint: Overview page (process list + detail sidebar)
tests/
  Unit/               PHPUnit unit tests (mirrors lib/)
  frontend/           Vitest frontend tests (mirrors src/)
    setup.ts          Global mocks (@nextcloud/axios, router, l10n, initial-state)
e2e/                  Playwright E2E tests
  fixtures/           Test fixtures (login etc.)
docs/                 Decisions, research, status
```

## Key Files

| File | Purpose |
|------|---------|
| `lib/Service/SignApiService.php` | All signd API calls, API URL resolution |
| `lib/Controller/ProcessController.php` | Process CRUD, wizard start, PDF download |
| `lib/Controller/SettingsController.php` | API key management (3 methods) |
| `lib/Controller/OverviewController.php` | Process list (signd API proxy) + cancel |
| `src/services/api.ts` | Frontend HTTP client |
| `src/main-files.ts` | FileAction + sidebar tab registration |
| `src/views/OverviewApp.vue` | Main component for overview page |
| `appinfo/routes.php` | All backend routes |

## Dev Commands

```bash
npm install && npm run build   # Build frontend
docker compose up -d           # Start NC 33 + DB
npm run enable-app             # Enable app (integration_signd)
npm run watch                  # Frontend dev with watch
npm run logs                   # Container logs
```

NC: http://localhost:8080 (admin/admin), signd local: localhost:7755

## Tests

Three test levels, all active:

### PHPUnit (Backend Unit Tests)

```bash
composer install
vendor/bin/phpunit --testsuite Unit
```

- Config: `phpunit.xml`, test suites `Unit` + `Integration`
- Tests in `tests/Unit/` — structure mirrors `lib/`
- Mocking: PHPUnit MockBuilder for NC interfaces (`IClientService`, `IConfig`, `LoggerInterface`)
- `tests/bootstrap.php` manually registers OCP namespace (nextcloud/ocp has no autoloading)
- No running NC server required

### Vitest (Frontend Unit/Component Tests)

```bash
npm test              # single run
npm run test:watch    # watch mode
```

- Config: `vitest.config.ts` (separate file, not in vite.config.ts — `createAppConfig` not extensible)
- Tests in `tests/frontend/` — structure mirrors `src/`
- `tests/frontend/setup.ts` mocks `@nextcloud/axios`, `@nextcloud/router`, `@nextcloud/l10n`, `@nextcloud/initial-state`
- `@nextcloud/vue` components (NcButton etc.) as stubs with `inheritAttrs: false` in tests
- happy-dom as test environment, globals enabled

### Playwright (E2E Tests)

```bash
# Prerequisite: Docker environment + app must be running
npm run test:e2e           # headless
npm run test:e2e:headed    # with browser window
```

- Config: `playwright.config.ts`, Chromium only, single worker, baseURL `localhost:8080`
- Tests in `e2e/`, login fixture in `e2e/fixtures/auth.ts` (admin/admin)

### Conventions

- PHP tests: `FooTest.php` tests `Foo.php`
- Frontend tests: `foo.test.ts` with `@vue/test-utils` for components
- New features/bugfixes: include matching tests