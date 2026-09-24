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
- **Administrators always get the Export / Delete buttons for other users** — compliance requirement: having these
  buttons trumps every other display option. On another user's Options page (`tmpl/options/default.php`, "manage
  another user" block) they depend only on the viewer's privileges (`HtmlView::$canManageExport` / `$canManageWipe`,
  mirroring `OptionsController::assertUserAccess()`). The component's `showexport` / `showwipe` options govern only a
  user's own self-service buttons and must never hide the administrator's. Sole exception: a non Super User gets no
  buttons on a Super User's page, because the controller refuses that action (H2a).
- **Composer autoloader** — loaded in `Extension/DataComplianceComponent.php` for the `akeeba/s3` dependency (vendor dir: `component/backend/vendor/`)

## CLI Commands

Registered via the console plugin, invoked through Joomla CLI:
- `datacompliance:lifecycle:delete` — auto-remove inactive users
- `datacompliance:lifecycle:notify` — notify users before deletion
- `datacompliance:account:delete` — manual account deletion

## Workarounds for core Joomla bugs — re-check on every Joomla release

- **HTML mail escaping** (`Helper\TemplateEmails::isHtmlLayoutUnescaped()` / `escapeForHtml()`): core
  `MailTemplate::send()` renders com_mails' HTML layout and then calls `replaceTags()` without
  `$isHtml = true`, so tags marked with `addUnsafeTags()` are **not** escaped in the HTML body (checked on
  Joomla 6.1.3). We copy core's "will the HTML layout be used" logic (`mail_style`, `disable_htmllayout`,
  `alternative_mailconfig` + per-template params) and pass pre-escaped values for the HTML part only.
  Whenever the supported Joomla range changes, diff core's `MailTemplate::send()` against our copy: if
  core changed how it decides on the layout, update ours; if core fixed the escaping, remove the
  workaround (otherwise values get escaped twice).

## Git: commit and tag outside the sandbox

Commits and tags are always signed, with a key held in 1Password. The 1Password signing agent is reached
over a local socket that agent sandboxes do not expose, so a sandboxed `git commit` or `git tag` **always**
fails (e.g. `error: 1Password: Could not connect to socket. Is the agent running?`).

Run every `git commit` and `git tag` **outside the sandbox from the first attempt** — in Claude Code with
`dangerouslyDisableSandbox: true`, in other harnesses with their equivalent unsandboxed / escalated
execution. Do not try the sandboxed form first, do not diagnose the failure, and never work around it
with `--no-gpg-sign`, `-c commit.gpgsign=false` or unsigned tags.

## Project memory

Project memory lives in `.claude/memory/`, committed with the code, so that it is shared across machines
and across agentic harnesses (Claude Code, Codex, Qwen Code, Kimi Code, Junie, …). Read the relevant file
**before** starting work that matches its trigger:

| Before you… | Read |
|---|---|
| Add, change or translate language strings, or add a language | `.claude/memory/translations.md` |
| Write or extend unit / E2E tests, or deal with a bug they uncover | `.claude/memory/testing.md` |

### Recording new memories

This is the **default and only** place for project memory. Do not write memories for this project to a
harness's private memory store (such as Claude Code's auto-memory under `~/.claude/projects/`); write
them here instead:

- Add to the existing topic file when one fits; otherwise create a new kebab-case `.md` file named after
  the topic, and add a row for it to the table above with a concrete trigger.
- Plain Markdown, no frontmatter. State the rule, then **Why:** (the reason or incident behind it) and
  **How to apply:**. Link related files with relative Markdown links.
- Don't record what the code, Git history or an existing `AGENTS.md` already says — update that
  `AGENTS.md` instead when the rule belongs there. Remove or correct entries that turn out wrong.
- These files are committed: no secrets, credentials, customer data or personal details.
