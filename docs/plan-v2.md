# v2.0.0 Plan — Nextcloud 33 Support

## Motivation

NC 33 (released Feb 2026) bringt Breaking Changes in der Files-App API.
Die Sidebar-Tab- und FileAction-APIs aus `@nextcloud/files` v3 sind in v4 entfernt/ersetzt.
v1.x (NC 30–32) und v2.x (NC 33+) lassen sich nicht sinnvoll in einer Codebasis abbilden → **eigenständiger v2 Release**.

## NC 34 Status

NC 34 (Hub 26 Spring) ist für **9. Juni 2026** geplant, Beta 1 ab 28. April 2026.
API Freeze am 24. April — aktuell noch zu früh, um NC 34 konkret einzuplanen.
→ **v2.0.0 zielt auf NC 33, NC 34 wird nachgezogen sobald Beta verfügbar.**

## Versioning-Strategie

| Version | NC Support | Status |
|---------|-----------|--------|
| v1.x | NC 30–32 | Aktiv (bis alle Ziel-Instanzen migriert) |
| v2.0.0 | NC 33+ | Geplant |

v1.x bleibt als Maintenance-Branch für Bugfixes erhalten.
v2.0.0 wird auf `main` entwickelt (oder `v2`-Branch, je nach Workflow).

## Breaking Changes NC 33

### P0 — Files-App APIs (Hauptaufwand)

#### 1. Sidebar Tab API (komplett neu)

**Entfernt:** `OCA.Files.Sidebar` Legacy-API (Tab-Objekt mit `mount`/`update`/`destroy`).

**Neu:** `@nextcloud/files` v4 → `getSidebar().registerTab()` mit **Web Components**.

Betrifft: `src/main-files.ts` Zeilen 28–89 (komplett).

Neues Interface `ISidebarTab`:
- `id`, `displayName`, `iconSvgInline`, `order`, `tagName`
- `enabled({ node, folder, view })` — optional
- Tab-Inhalt als Custom Element (`customElements.define()`)
- Web Component muss `node`, `folder`, `view`, `active` Properties exponieren
- Vue-Komponente wrappen mit `defineCustomElement()` + `shadowRoot: false`

**TODO:**
- [ ] `SigndSidebarTab.vue` als Web Component wrappen
- [ ] Neuen Tab via `getSidebar().registerTab()` registrieren
- [ ] Alten Legacy-Code entfernen

#### 2. FileAction API (Signatur geändert)

**Entfernt:** `FileAction` Klasse.

**Neu:** Plain objects mit `IFileAction` Interface.

Betrifft: `src/main-files.ts` Zeilen 1–26.

Änderungen:
- `new FileAction({...})` → Plain object `{ id, displayName, ... }`
- `enabled(nodes)` → `enabled({ nodes, folder, view })`
- `exec(node)` → `exec({ node, folder, view, filelist })`
- `OCA.Files.Sidebar.open()` / `setActiveTab()` → neue Sidebar-API

**TODO:**
- [ ] FileAction als Plain Object umschreiben
- [ ] `exec` Handler auf neue Sidebar-API umstellen
- [ ] Import `FileAction` entfernen, `registerFileAction` bleibt

### P0 — Package Upgrades

| Package | v1 (aktuell) | v2 (Ziel) | Breaking? |
|---------|-------------|-----------|-----------|
| `@nextcloud/files` | `^3.9.0` | `^4.0.0` | Ja (s.o.) |
| `@nextcloud/vue` | `^9.5.0` | `^9.5.0` | Nein |
| `@nextcloud/axios` | `^2.5.0` | `^2.5.0` | Nein |
| `@nextcloud/router` | `^3.0.1` | `^3.0.1` | Nein |
| `@nextcloud/l10n` | `^3.1.0` | `^3.1.0` | Nein |
| `@nextcloud/initial-state` | `^2.2.0` | `^2.2.0` | Nein |

### P0 — info.xml & PHP

```xml
<!-- v1 (aktuell) -->
<php min-version="8.1" max-version="8.4"/>
<nextcloud min-version="30" max-version="32"/>

<!-- v2 -->
<php min-version="8.2" max-version="8.5"/>
<nextcloud min-version="33" max-version="33"/>
```

`composer.json`: `"php": ">=8.2"` (NC 33 erfordert mindestens PHP 8.2).

### P1 — Backend

**Kein Handlungsbedarf erwartet.** Die Backend-APIs (Controller, QBMapper, IConfig, IUserSession, IRootFolder, IClientService) haben sich in NC 33 nicht gebrochen.

Geprüft:
- `IQueryBuilder::execute()` → nicht direkt verwendet (QBMapper abstrahiert)
- Keine entfernten OCP-Interfaces in Nutzung

### P2 — Sonstiges

- Entfernte Globals (`OC.AppConfig`, `OC.redirect` etc.) — nicht verwendet
- `@nextcloud/vite-config` `^2.3.0` — kompatibel, kein Update nötig

## Aufwand-Schätzung

| Aufgabe | Größe |
|---------|-------|
| Sidebar Tab als Web Component | Groß (Hauptaufwand) |
| FileAction Umschreibung | Klein–Mittel |
| Package Upgrades + info.xml | Klein |
| Tests anpassen | Mittel |
| E2E auf NC 33 Docker | Klein |

## Migration Checkliste

- [ ] Branch `v2` (oder `main`) vorbereiten, v1 als `v1`-Branch taggen
- [ ] `@nextcloud/files` auf v4 upgraden
- [ ] `info.xml` Version + Ranges aktualisieren
- [ ] `composer.json` PHP Range aktualisieren
- [ ] `main-files.ts`: FileAction umschreiben (Plain Object)
- [ ] `main-files.ts`: Sidebar Tab als Web Component (defineCustomElement)
- [ ] Neue Sidebar open/activate API statt `OCA.Files.Sidebar`
- [ ] Frontend-Tests für neue Registrierung anpassen
- [ ] Docker-Setup auf NC 33 Image umstellen
- [ ] E2E Tests auf NC 33 laufen lassen
- [ ] PHPUnit mit PHP 8.2+ prüfen

## Quellen

- [NC 33 Upgrade Guide](https://docs.nextcloud.com/server/latest/developer_manual/app_publishing_maintenance/app_upgrade_guide/upgrade_to_33.html)
- [Breaking Files APIs Update for 33](https://help.nextcloud.com/t/breaking-files-apis-update-for-33/237283)
- [NC Maintenance & Release Schedule](https://github.com/nextcloud/server/wiki/Maintenance-and-Release-Schedule)
- [@nextcloud/files Changelog](https://github.com/nextcloud-libraries/nextcloud-files/blob/main/CHANGELOG.md)
