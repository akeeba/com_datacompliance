# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Akeeba Data Compliance is a Joomla extension (component + plugins) for GDPR compliance. It provides consent management, personal data export (XML), right-to-erasure (account wipe with audit trail), and lifecycle management for stale accounts. Version 4.x supports PHP 8.1 – 8.6 and Joomla! 5.4 – 6.2.

Tests: unit tests in `UnitTest/` (`phpunit`, config `phpunit.xml`; see `UnitTest/README.md`) and end-to-end tests against
a disposable Dockerized Joomla site in `tests/integration/` (`tests/integration/docker/run.sh`, config
`phpunit-integration.xml`; see `tests/integration/README.md`). The E2E harness builds and installs the sibling `../ats`
and `../ars` working copies.

Builds use Phing with the Akeeba Build Tools — see the `phing-build` skill. `build.xml` is at the working copy root, and the build requires a sibling `../buildfiles` checkout.

## Security audits

Before any security audit, security review, or `audit-*` skill run — and before reporting any
finding from one — you MUST read `.claude/security-audit-triage.md` — the actors that are out
of scope, finding classes already ruled invalid, controls already in place, and how to
classify hardening versus vulnerabilities.

## Architecture

### Namespaces

- Backend: `Akeeba\Component\DataCompliance\Administrator\*`
- Frontend: `Akeeba\Component\DataCompliance\Site\*`
- Plugins: `Akeeba\Plugin\{Group}\DataCompliance\*`

### Entry Points

- `component/backend/services/provider.php` — DI service provider, the component bootstrap entry point.
- `component/backend/src/Model/WipeModel.php` — the core of account erasure.

### Plugin Architecture

Datacompliance-group plugins handle data export/deletion for specific subsystems, implementing event handlers invoked by `RunPluginsTrait`. The `ars` and `ats` plugins integrate with Akeeba Release System and Akeeba Ticket System respectively.

### Key Patterns

- **Composition via traits** (`component/backend/src/Mixin/`) — `RunPluginsTrait`, `TriggerEventTrait`, `ControllerEventsTrait`, etc.
- **Composer autoloader** — loaded in `Extension/DataComplianceComponent.php` for the `akeeba/s3` dependency (vendor dir: `component/backend/vendor/`)

## CLI Commands

Registered via the console plugin, invoked through Joomla CLI:
- `datacompliance:lifecycle:delete` — auto-remove inactive users
- `datacompliance:lifecycle:notify` — notify users before deletion
- `datacompliance:account:delete` — manual account deletion
