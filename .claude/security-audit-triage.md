# Security audit triage — Akeeba Data Compliance

Knowledge collected from security audits of this repository, with the operator's decisions. Read this before
running any security audit, security review or `audit-*` skill, and before reporting any finding. It saves
re-reporting settled questions and keeps severities consistent.

First compiled from the full audit of 2026-09-21 (12 `audit-*` skills; 48 findings: 34 fixed, 14 ruled invalid).

## Scope and threat model

- **In scope:** guests, logged-in frontend users, backend users with limited ACL (e.g. Managers, groups denied
  `core.manage` on the component), and users holding only the component's custom `export` / `wipe` actions.
  Privilege escalation between these roles is the main risk class for this extension.
- **Out of scope (collapses to "I can hack myself"):**
  - Super Users, and anyone who can edit plugin or component options. That includes S3 endpoint and credentials,
    policy article choice, mail templates.
  - Anyone with database or filesystem access.
  - Anything that only happens when `AKEEBADEBUG` is defined or site debug is enabled. The extension never defines
    `AKEEBADEBUG`; only the site owner or server access can.
- **Local development tools** (e.g. `assets/email/0_make_lang_strings.php`, `build.xml`) run outside Joomla and are
  not shipped. Joomla-specific controls such as the `_JEXEC` guard do not apply to them.

## Permission model (as fixed in the 2026-09-21 audit)

- Backend access to any view other than `options` requires `core.manage` on `com_datacompliance`. The dispatcher
  exemption for `options` applies only when **both** the view and the resolved controller are `options`.
  `Dispatcher::checkAccess()` relies on `applyViewAndController()` having already set a non-empty `controller`
  input, and throws `LogicException` otherwise.
- Acting on **another** user (`OptionsController::assertUserAccess()`):
  - `export`: the `export` action, or `core.admin` on the component, or Super User. Only Super Users may export
    Super Users.
  - `wipe`: the `wipe` action, or `core.admin` on the component, or Super User. Only Super Users may wipe Super
    Users. Protection of other backend accounts is delegated to `plg_datacompliance_joomla` (see "By design").
  - `consent` on behalf of another user: **only** Super User or `core.admin` on the component. `export` and `wipe`
    are the wrong privileges for this.
  - "DataCompliance administrator" means `core.admin` on `com_datacompliance`, **never** `core.manage`. That
    mix-up was a real bug (M1).
- `Options\HtmlView::populateBasicViewParameters()` must mirror `assertUserAccess()`. When changing one, change
  the other.

## Controls already in place — do not re-report

- **Layout/template inclusion:** `ViewLoadAnyTemplateTrait::loadTemplate()` cleans file, sub-template and template
  names. That is the authoritative guard against path traversal. The layout filter in
  `ControllerReusableModelsTrait::getView()` is defence in depth only; it does not run for normal page loads.
  Every HtmlView uses the trait. Keep it that way for new views.
- **Anti-CSRF:** every state-changing task needs a POST token (`checkToken('post')`). The wipe confirmation page
  (a `wipe` task without `phrase`) is deliberately token-less because it changes nothing. The phrase is read from
  POST only. No URL carries the form token.
- **Export response:** `no-store, private` cache headers and `nosniff`. Fixed filename.
- **Wipe errors:** only `WipeRefusedException` messages reach the user. Any other exception is logged to
  `administrator/logs/com_datacompliance_errors.php`. CLI commands intentionally show raw errors to the operator.
- **User changes audit trail:** never logs `password`, `password_clear`, `otpKey`/`otep`, the `activation` value,
  or `joomlatoken.token` (the Joomla API token, which core `plg_user_token` posts back with the profile form).
  Output in the trail views is escaped, including array values.
- **HTML email:** every template tag is marked unsafe (escaped) except `actions`, which is built from trusted
  language strings.
- **Output escaping:** the shared user layout (`layouts/akeeba/datacompliance/common/user.php`) escapes all user
  fields. Do not rely on Joomla's input filters: users can be created by SSO, bridges, imports or the API.
- **SQL:** prepared statements and bound parameters throughout, including the datacompliance plugins. Ordering
  columns are whitelisted (`filter_fields` holds only real columns) and quoted with `quoteName()`.
- **Bundled JS libraries** (Chart.js, its moment adapter, moment) ship locally in `media/js/vendor`. No CDN.
- **Vendor folder:** deny-all `.htaccess`/`web.config`. Dev files of Composer libraries are excluded from the build.

## Ruled invalid — by design (do not report)

- **Data export completeness (GDPR portability):**
  - Exporting the **entire** ATS ticket, including staff and other users' replies and unpublished posts, is
    required. The export must match what the wipe deletes, and replies may contain data the user submitted.
  - With **Maximalist export** (default Yes) the export includes authentication material (MFA configuration,
    remember-me tokens, API token seed, download IDs). This mirrors core `plg_privacy_user`. The password hash is
    **never** exported. With the option off, authentication material is removed or masked at the source; the
    option reaches plugins as the second argument of `onDataComplianceExportUser`.
  - As a result, the `export` privilege is effectively Super-User-equivalent for non-Super-User targets. This is
    an accepted consequence.
- **Wipe behaviour:** user and admin wipes delete whole tickets, public ones included. The lifecycle wipe
  anonymises public tickets instead. The difference is intentional: support staff make identifying tickets private.
- **Plugin-based architecture:** `plg_datacompliance_joomla` is meant to be always enabled. Protections living in it
  (e.g. refusing to wipe backend users) are not "bypassable by disabling the plugin".
- **Task-driven events:** request task names selecting `onBefore<Task>`/`onAfter<Task>` handlers and task-named
  plugin events are a standard Akeeba architecture choice.
- **List model state:** `getUserStateFromRequest()` followed by `parent::populateState()`, with raw `filter[]`
  overriding typed state (and a possible HTTP 500 on malformed arrays), is exactly how core Joomla works.
- **`getDbo()` fallback** in `ConsenttrailsTable` (`method_exists($this, 'getDatabase') ? … : getDbo()`) is kept
  for compatibility across the supported Joomla range.
- **Privacy policy article** is shown regardless of its publish state or access level. Choosing a valid article is
  the administrator's responsibility.
- **S3 audit-trail plugin:** SigV2 default, optional plain HTTP, arbitrary custom endpoints and following
  `Location` redirects are all required for S3-compatible non-Amazon storage services.
- **MailTemplateHotFix** (runtime-rewritten core `MailTemplate` through a stream wrapper) is intentional. Do not
  touch it.
- **Packaging:** SCSS sources and source maps ship on purpose, so users can troubleshoot and customise styles.
  Dependency pinning (e.g. `akeeba/s3` on `dev-development`) is done by the release workflow, not in the repo.
- **akeeba/s3 internals** (e.g. `unserialize()` of self-produced data) are covered by that library's own security
  review.
- **Git history:** the 2018 cookieconsent `api_key` was never a live key.

## Classification guidance

- Rate by the **least-privileged realistic attacker** and whether the result crosses a privilege boundary.
  Examples of real findings: a Manager getting a Super User's API token or password hash, a backend user without
  `core.manage` running component tasks, stored XSS from a frontend-controlled field into a Super User page.
- Reachability "only if the site owner misconfigures or disables something" is at most hardening. It is invalid
  when the configuration is the owner's documented responsibility (policy article, S3 endpoint, disabling the
  core plugin).
- GDPR requirements (portability, full-ticket exports, audit trails) take precedence over data minimisation inside
  exports. Report credential leakage to **other** users, not to the data subject themselves.
- Functional bugs found during an audit (e.g. broken notifications, wrong argument order) are worth reporting as
  Informational. The operator fixes them together with the security findings.
- When a fix mirrors a pattern in core Joomla, check core first (a local Joomla 6 site is at `~/Sites/abcom`).
  "Joomla does the same" is a valid reason to rule a finding invalid.

## Cross-project notes

Decisions that apply to every Akeeba extension (I3 task-driven events, I12 shipped SCSS/maps, I14 core list-model
state handling, I17 `AKEEBADEBUG` output) are also filed in MemPalace, wing `akeeba`, room `security-triage`.
