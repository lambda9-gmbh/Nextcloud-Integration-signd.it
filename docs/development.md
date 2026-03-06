# Development

## Prerequisites

- Node.js 20+, npm 10+
- PHP 8.1+, Composer
- Docker + Docker Compose
- signd instance (local: `localhost:7755` or `signd.it`)

## Setup

```bash
# Install frontend dependencies + build
npm install
npm run build

# Start Nextcloud + PostgreSQL (default: NC 32)
npm run up

# Enable app + allow local API requests
npm run enable-app
npm run occ -- config:system:set allow_local_remote_servers --value=true --type=boolean

# Run after app version change in info.xml or when otherwise nextcloud needs to be updated (shown in browser)
npm run occ -- upgrade
```

Nextcloud runs at **http://localhost:8080** (login: `admin` / `admin`).

## Development Commands

```bash
npm run watch          # Frontend with hot reload
npm run logs           # NC container logs
npm run occ -- <command>  # Run any occ command in the container
npm run down           # Stop containers
npm run restart        # Restart containers
```

## Multi-Version Testing (NC 30, 31, 32)

The NC version is controlled via the `NC_VERSION` environment variable (default: `32`). Each version has its own Docker volumes — data persists across switches, no conflicts.

```bash
# Start NC 30
NC_VERSION=30 npm run up
npm run enable-app

# Switch to NC 31
npm run down
NC_VERSION=31 npm run up
npm run enable-app

# Back to NC 32 (default)
npm run down
npm run up
```

`npm run enable-app` is only needed on the first start of a new version.

## signd Server URL

Defaults to `https://signd.it`. For local development, `docker-compose.yml` sets the env variable `SIGND_BASE_URL=http://host.docker.internal:7755` in the container — this allows PHP to reach the signd server on the host.

Resolution order (see `SignApiService::getApiUrl()`):
1. App config (`occ config:app:set`)
2. Env variable `SIGND_BASE_URL` (set in container via `docker-compose.yml`)
3. Default: `https://signd.it`

To manually change the URL:
```bash
npm run occ -- config:app:set integration_signd api_url --value=http://host.docker.internal:7755
```

## Tests

### Backend (PHPUnit)

```bash
composer install
vendor/bin/phpunit --testsuite Unit
```

No running NC server required — all unit tests use mocks for NC interfaces.

### Frontend (Vitest)

```bash
npm install
npm test              # single run
npm run test:watch    # watch mode for development
```

### E2E (Playwright)

Prerequisite: Docker environment + app must be running.

```bash
npm run up && npm run build && npm run enable-app
npm run test:e2e           # headless
npm run test:e2e:headed    # with browser window
```

### Test Structure

```
tests/
  Unit/                 PHPUnit unit tests (mirrors lib/)
  frontend/             Vitest frontend tests (mirrors src/)
e2e/                    Playwright E2E tests
  fixtures/             Test fixtures (login etc.)
```

## Architecture

```
integration_signd/
  appinfo/           info.xml, routes.php
  lib/
    AppInfo/          Application bootstrap
    Controller/       SettingsController, ProcessController, OverviewController
    Db/               Process Entity + Mapper (oc_integration_signd_processes)
    Listener/         LoadAdditionalScriptsEvent → injects frontend into Files app
    Migration/        DB schema
    Service/          SignApiService (all signd API calls)
    Settings/         AdminSettings + AdminSection (NC settings page)
  src/
    settings/         Admin settings Vue components (ApiKey, Login, Register)
    views/            SigndSidebarTab, OverviewApp
    components/       ProcessList, ProcessStatus, StartProcessButton, SignerList
    components/overview/  OverviewToolbar, OverviewTable, OverviewPagination, ProcessDetail
    services/api.ts   Frontend HTTP client
    main-settings.ts  Entrypoint: Admin settings
    main-files.ts     Entrypoint: Files app (FileAction + Sidebar tab)
    main-overview.ts  Entrypoint: Overview page
  templates/          PHP templates
```

## Further Documentation

- [research-sign-api.md](research-sign-api.md) — signd API analysis
- [research-nextcloud-app-dev.md](research-nextcloud-app-dev.md) — Nextcloud app development research