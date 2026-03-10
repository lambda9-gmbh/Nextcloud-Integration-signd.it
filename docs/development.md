# Development

## Prerequisites

- Node.js 20+, npm 10+
- PHP 8.2+, Composer
- Docker + Docker Compose

## Quick Start

```bash
# install dependencies
npm install

# build the application
npm run build

# start the nextcloud container
npm run up

# enable the app (required only once)
npm run enable-app

# allow connection, when running a local signd.it dev server
npm run occ -- config:system:set allow_local_remote_servers --value=true --type=boolean

# OR use another signd.it instance for development (e.g. test.signd.it)
npm run occ -- config:app:set integration_signd api_url --value=https://your-signd-instance.com
```

Nextcloud: **http://localhost:8080** — login `admin` / `admin`

## npm Scripts

| Command                    | Description                                          |
|----------------------------|------------------------------------------------------|
| `npm run up`               | Start Nextcloud + PostgreSQL                         |
| `NC_VERSION=34 npm run up` | Start a specific NC version                          |
| `npm run down`             | Stop containers                                      |
| `npm run build`            | Build frontend                                       |
| `npm run watch`            | Build frontend with hot reload                       |
| `npm run enable-app`       | Enable the app in Nextcloud (once per new container) |
| `npm run occ -- <command>` | Run any `occ` command in the container               |
| `npm test`                 | Run frontend tests (Vitest, single run)              |
| `npm run test:watch`       | Run frontend tests in watch mode                     |
| `npm run test:e2e`         | Run E2E tests (Playwright, headless)                 |
| `npm run test:e2e:headed`  | Run E2E tests with browser window                    |

Each NC version gets its own Docker volumes — no conflicts when switching.

## Versioning

The app version follows [Nextcloud's release process](https://docs.nextcloud.com/server/latest/developer_manual/app_publishing_maintenance/release_process.html). Changes must be documented in `CHANGELOG.md` before releasing.

After updating the app version in `appinfo/info.xml` (using the same container volume):

```bash
npm run occ -- upgrade
```

## signd Server Configuration

The app resolves the signd API URL in this order (see `SignApiService::getApiUrl()`):

1. **App config** — set manually via occ
2. **Env variable** — `SIGND_BASE_URL` (set in `docker-compose.yml`)
3. **Default** — `https://signd.it`

### Using a local signd instance

`docker-compose.yml` sets `SIGND_BASE_URL=http://host.docker.internal:7755` by default, so the container can reach a signd server running on the host.

Nextcloud blocks requests to local addresses by default. To allow them:

```bash
npm run occ -- config:system:set allow_local_remote_servers --value=true --type=boolean
```

### Using a remote signd instance

Override the URL via app config:

```bash
npm run occ -- config:app:set integration_signd api_url --value=https://your-signd-instance.example.com
```

No `allow_local_remote_servers` needed in this case.

## Tests

### Backend (PHPUnit)

```bash
composer install
vendor/bin/phpunit --testsuite Unit
```

No running NC server required — all tests use mocks.

### Frontend (Vitest)

```bash
npm test              # single run
npm run test:watch    # watch mode
```

### E2E (Playwright)

Requires running Docker environment with the app enabled and built.

```bash
npm run test:e2e           # headless
npm run test:e2e:headed    # with browser window
```

## Releasing

Releases are automated via GitHub Actions. Pushing a version tag triggers the workflow (`.github/workflows/release.yml`), which builds the frontend, creates a tarball, and publishes a GitHub Release.

1. Update the version in `appinfo/info.xml`
2. Commit and push to `main`
3. Run the release script:

```bash
./scripts/release.sh
```

The script reads the version from `info.xml`, creates the tag `v<version>`, and pushes it. The GitHub Action validates that the tag matches `info.xml` before building.

## Docker Volumes

Each NC version stores its data in separate named volumes (`integration_signd_nextcloud_<version>`, `integration_signd_db_<version>`). To get a clean state for a specific version:

```bash
npm run down
docker volume rm integration_signd_nextcloud_33 integration_signd_db_33
npm run up
npm run enable-app
```