# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Akeeba Data Compliance is a Joomla extension (component + plugins) for GDPR compliance. It provides consent management, personal data export (XML), right-to-erasure (account wipe with audit trail), and lifecycle management for stale accounts. Version 4.x targets Joomla 4.2+ / 5.x with experimental Joomla 6 support.

## Build Commands

The build system uses Phing with Akeeba Build Tools. Requires a sibling `../buildfiles` directory ([Akeeba Build Tools](https://github.com/akeeba/buildfiles)).

```bash
# Build installable ZIP package (from build/ directory)
cd build && phing git -Dversion=0.0.1.a1

# Transpile + minify JS only (Babel via buildfiles/node_modules)
cd build && phing compile-javascript

# Compile SCSS to minified CSS (requires sass CLI)
cd build && phing compile-css
```

There is no test suite in this repository.

## Architecture

### Extension Structure

This is a Joomla **package** (`pkg_datacompliance.xml`) containing:
- **Component** (`component/`) — the main admin/site MVC component
- **8 plugins** (`plugins/`) — console CLI, 6 datacompliance-group plugins, system, and user

### Component Layout

```
component/
├── backend/          # Administrator (main logic)
│   ├── services/     # provider.php — DI service provider (bootstrap entry point)
│   ├── src/
│   │   ├── Controller/
│   │   ├── Model/        # Business logic (WipeModel is the core — handles account erasure)
│   │   ├── View/
│   │   ├── Table/        # ORM table classes (AbstractTable base)
│   │   ├── Dispatcher/   # Request routing, access control, asset loading
│   │   ├── Extension/    # Component bootstrap (loads Composer autoloader)
│   │   ├── CliCommand/   # CLI commands for lifecycle/account management
│   │   ├── Helper/       # ComponentParams, Export, MailTemplateHotFix
│   │   ├── Mixin/        # Reusable traits (events, cache, plugins, templates)
│   │   ├── Field/        # Custom Joomla form fields
│   │   ├── Service/Html/ # HTML rendering helpers
│   │   └── Router/       # SEF URL routing
│   ├── sql/              # MySQL + PostgreSQL schemas and update migrations
│   └── tmpl/             # View templates (Joomla layout files)
├── frontend/         # Site-facing (minimal — only Options view for user preferences)
└── media/            # JS, CSS/SCSS, fonts, joomla.asset.json (Web Asset Manager)
```

### Namespaces

- Backend: `Akeeba\Component\DataCompliance\Administrator\*`
- Frontend: `Akeeba\Component\DataCompliance\Site\*`
- Plugins: `Akeeba\Plugin\{Group}\DataCompliance\*`

### Plugin Architecture

Datacompliance-group plugins handle data export/deletion for specific subsystems. Each implements event handlers invoked by `RunPluginsTrait`:
- **joomla** — core Joomla user data
- **email** — email address handling
- **ars** — Akeeba Release System integration
- **ats** — Akeeba Ticket System integration
- **loginguard** — two-factor auth data
- **s3** — audit log export to S3

### Database Tables

All prefixed with `#__datacompliance_`: `exporttrails`, `wipetrails`, `consenttrails`, `usertrails`. Schemas in `component/backend/sql/install.mysql.utf8.sql` with update migrations in `sql/updates/{mysql,postgresql}/`.

### Key Patterns

- **Composition via traits** (`Mixin/` directory) — `RunPluginsTrait`, `TriggerEventTrait`, `ControllerEventsTrait`, etc.
- **Event-driven** — dispatches `onBeforeDispatch`/`onAfterDispatch` and plugin events for data operations
- **Joomla Web Asset Manager** — assets registered in `component/media/joomla.asset.json`
- **Composer autoloader** — loaded in `Extension/DataComplianceComponent.php` for `akeeba/s3` dependency (vendor dir: `component/backend/vendor/`)

### CLI Commands

Registered via the console plugin, invoked through Joomla CLI:
- `datacompliance:lifecycle:delete` — auto-remove inactive users
- `datacompliance:lifecycle:notify` — notify users before deletion
- `datacompliance:account:delete` — manual account deletion

## JavaScript / CSS Workflow

- JS source: `component/media/js/*.js` (ES6+) → Babel transpiles to `*.min.js`
- CSS source: `component/media/css/sources/*.scss` → Sass compiles to `*.css`
- `.babelrc.json` ignores `*.min.js` to avoid reprocessing
- External CDN deps: Chart.js, Moment.js (used in control panel dashboard)

## Requirements

- PHP 7.4 – 8.5+
- Joomla 4.2 – 5.x (Joomla 6 experimental on `feature/joomla6` branch)
- MySQL (utf8mb4) or PostgreSQL
