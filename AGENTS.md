# AGENTS.md

This file provides guidance to coding agents when working with code in this repository.

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

## Always-on rules

- **Administrators always get the Export / Delete buttons for other users** — compliance requirement: having these
  buttons trumps every other display option. On another user's Options page (`tmpl/options/default.php`, "manage
  another user" block) they depend only on the viewer's privileges (`HtmlView::$canManageExport` / `$canManageWipe`,
  mirroring `OptionsController::assertUserAccess()`). The component's `showexport` / `showwipe` options govern only a
  user's own self-service buttons and must never hide the administrator's. Sole exception: a non Super User gets no
  buttons on a Super User's page, because the controller refuses that action (H2a).

- Commits and tags are signed through 1Password, which sandboxes cannot reach: run every `git commit` / `git tag` outside the sandbox from the first attempt; never use `--no-gpg-sign`. Details: `.claude/memory/git-signing.md`.

## Project memory

Project memory lives in `.claude/memory/`. Read the matching file before starting that kind of work.

| File | Read it when… |
|------|---------------|
| [`translations.md`](.claude/memory/translations.md) | Before you add, change or translate language strings, or add a language |
| [`testing.md`](.claude/memory/testing.md) | Before you write or extend unit / E2E tests, or deal with a bug they uncover |
| [`architecture.md`](.claude/memory/architecture.md) | Before you change namespaces, entry points, datacompliance plugins, the `Mixin/` traits or the CLI commands |
| [`joomla-workarounds.md`](.claude/memory/joomla-workarounds.md) | On every Joomla release, and before you touch `Helper\TemplateEmails` / `sendMail()` |
| [`git-signing.md`](.claude/memory/git-signing.md) | Before you run `git commit` or `git tag` |
| [`recording-memories.md`](.claude/memory/recording-memories.md) | Before you add or change a project memory file |
