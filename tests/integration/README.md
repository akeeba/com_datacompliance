# Data Compliance end-to-end tests

These tests drive a **real, disposable Joomla site over real HTTP** — and, for the console commands, a
real `cli/joomla.php` — exactly as a browser, a cron job or an attacker would. The whole stack is stood
up in Docker, provisioned from nothing, tested, and thrown away:

| Service | What it is |
|---|---|
| `db` | MySQL |
| `php` | PHP-FPM running Joomla, and the Joomla CLI |
| `web` | Apache, proxying to `php` over FastCGI |
| `mailpit` | SMTP sink with a REST API: the wipe and lifecycle notifications really go out over SMTP |
| `minio` | S3-compatible storage for `plg_datacompliance_s3` (no published port) |
| `mc` | One-shot MinIO client (profile `tools`), how the tests look inside the bucket |

Nothing boots Joomla inside the PHPUnit process. You never configure a site by hand, and a run that
fails or crashes mid-way never leaves anything behind in an unknown state.

The unit tests are a separate suite; see [`UnitTest/README.md`](../../UnitTest/README.md).

## Requirements

* **Docker** with the Compose plugin.
* **PHP CLI** (with `curl`, `pdo_mysql`, `dom`, `simplexml`) and **PHPUnit 11**, installed globally
  (`composer global require phpunit/phpunit ^11`) and on your `PATH`. The test runner is a host-side
  process that talks to the site over its published ports.
* **Phing** on your `PATH`, plus `node` and `sass`, to build the packages (`phing git`). Skip with
  `--skip-build` if the `release/` folders already hold packages.
* The sibling working copies **`../ats`** and **`../ars`** (Akeeba Ticket System and Akeeba Release
  System), next to this repository. `run.sh` builds and installs both, so `plg_datacompliance_ats` and
  `plg_datacompliance_ars` act on the real tables. Only the directory *names* are assumed; everything is
  resolved relative to this repository. Use `--no-siblings` to run without them (their tests skip).
* `unzip`, `curl`, `gunzip` and `jq`, used to resolve, fetch and extract Joomla.

## Quick start

```
tests/integration/docker/run.sh
```

That one command scrubs, brings the stack up, installs Joomla, points its mailer at Mailpit, builds and
installs Data Compliance, ATS Pro and ARS, provisions the fixtures, runs the suite, and tears the stack
down.

To iterate, keep the stack up and re-run PHPUnit directly — provisioning is the slow part, the tests
themselves take seconds:

```
tests/integration/docker/run.sh --keep-containers
phpunit -c phpunit-integration.xml
phpunit -c phpunit-integration.xml --filter WipeTest
php tests/integration/provision.php      # put the fixtures back
tests/integration/docker/run.sh --down   # tear it all down
```

While the stack is up: the site is on <http://localhost:8120> (`admin` / `test`), and Mailpit's web UI
is on <http://localhost:8145>.

## `run.sh` options

| Option | Effect |
|---|---|
| `-j`, `--joomla=V` | Override `JOOMLA_VERSION` for this run (`6`, `6.1`, `6.1.3`) |
| `-p`, `--php=V` | Override `PHP_VERSION` for this run (`8.1`, `8.3`, `8.5`) |
| `--matrix` | Run every Joomla/PHP pair in `JOOMLA_MATRIX`, then exit |
| `-f`, `--filter=NAME` | Passed through to PHPUnit |
| `--skip-build` | Don't run `phing git` anywhere; install the newest packages already in `release/`, `../ats/release/`, `../ars/release/` |
| `--no-siblings` | Don't build or install ATS and ARS |
| `--no-tests` | Provision the site but don't run the suite (leaves it up) |
| `--keep-containers` | Leave the stack running afterwards |
| `--down` | Tear everything down and exit |
| `-- <args>` | Everything after `--` goes to PHPUnit |

Invoke `run.sh --matrix` by absolute path or from any directory: it re-invokes itself by its absolute
path, so either works.

## The version matrix

```
tests/integration/docker/run.sh --matrix
```

Runs the suite once per pair in `JOOMLA_MATRIX` — by default Joomla **5.4 on PHP 8.1 and 8.5, 6.0 on
PHP 8.3 and 8.5, 6.1 on PHP 8.3 and 8.5**. Where each bound comes from:

* **Joomla ≥ 5.4.0, < 6.3** — `$minimumJoomla` / `$maximumJoomla` in `component/script.datacompliance.php`
  (the same values as `composer.json` `extra.akcompat.limit` and `Helper\VersionLimits`). `run.sh` reads
  them from the installer script and refuses anything outside, before touching Docker. 6.1 is the latest
  stable release today (6.2 is not out), so it is the day-to-day default and the ceiling.
* **PHP ≥ 8.1, < 8.7** — `$minimumPhp` / `$maximumPhp`, likewise. Joomla 6 itself needs PHP 8.3; `run.sh`
  reads Joomla's own floor out of the extracted package and refuses an impossible pair. The newest
  published `php:*-fpm` image is 8.5, so that is the ceiling in practice.
* The pairs are the **edges** of the range, not a cross product.

Why more than one Joomla version is not optional thoroughness:

* **Joomla 5.4 still has the `otpKey` / `otep` columns** (legacy TFA secrets); 6.x dropped them. With
  Maximalist Export off, `plg_datacompliance_joomla` must drop them from the export.
  `ExportTest::testMaximalistExportControlsAuthenticationMaterial` asserts they are absent — which only
  proves something on 5.4. On 6.x it passes because the columns do not exist.
* **`MailTemplateHotFix`** (on by default) builds a working copy of core's `MailTemplate` by rewriting
  core's source file at runtime. Whether its replacements still apply is a property of each Joomla
  release's `MailTemplate.php`; the notification tests (`WipeNotificationTest`, `LifecycleTest`) are the
  only thing that exercises it against a given release.
* **HTML mail escaping** relies on core's `MailTemplate::addUnsafeTags()` and on how core renders the
  HTML layout. Known issue #14 was reproduced on 6.1.3; only the matrix tells whether 5.4 and 6.0 behave
  the same.
* **`ConsenttrailsTable`** keeps a `getDatabase()`/`getDbo()` fallback for the declared range, and
  **`Table\User::store()`**'s group handling (known issue #8) is core code the wipe depends on.

The day-to-day run (no flags) covers only Joomla 6.1 on PHP 8.5. **A green single-version run proves
less than it looks** — in particular nothing about Joomla 5.4 or the PHP 8.1 floor. Run the matrix
before a release.

## Configuration

Everything is in `docker/env.dist`, which **is committed**. `run.sh` creates `docker/.env` from it on
first run; edit that for local overrides. Ports (8120 / 33310 / 8145) are deliberately off the sibling
harnesses' (Admin Tools, ATS, Akeeba Backup, ARS, com_compatibility), so any of those stacks can be up at
the same time.

`tests/integration/config.php` is written by `run.sh` to match what it actually provisioned. It is
git-ignored, and `config.dist.php` carries the same defaults, so a fresh clone can run PHPUnit without
configuring anything.

## How it fits together

| Piece | What it does |
|---|---|
| `docker/run.sh` | The one-shot orchestrator described above |
| `docker/docker-compose.yml` | The services |
| `assets/e2e-provision.php` | Runs **inside** the container: the nested-set fixtures (user groups, the component's permission rules, the ATS category, a user custom field, the privacy policy article) |
| `assets/e2e-probe.php` | Runs inside the container: reports who a session belongs to and what it may do |
| `src/SiteProvisioner.php` | Host-side: runs the above, then seeds everything flat over PDO (users, consent, parameters, ATS / ARS rows); hands fixtures to tests by name; creates throwaway accounts |
| `src/Engine/Surfer.php` | cURL client with a cookie jar, token extraction, and redirects captured rather than followed |
| `src/Engine/ContainerCli.php` | `cli/joomla.php` and `mc` inside the stack |
| `src/Engine/Mailpit.php` | Reads the mail the site actually sent |
| `src/AbstractE2ETestCase.php` | Base class: logged-in actors, request helpers (export, wipe, consent), refusal and known-issue assertions |

**The provisioner's nested-set half runs inside the container on purpose.** Joomla's user groups,
categories, fields and assets are nested sets with an asset tree hanging off them; building those with
hand-written SQL means reimplementing `lft`/`rgt` bookkeeping and then debugging ACL results that are
wrong for reasons unrelated to Data Compliance. The flat half is PDO because it is faster and keeps the
dates (`registerDate`, `lastvisitDate`) exactly where the lifecycle tests need them.

**The container-side scripts call `$app->createExtensionNamespaceMap()` explicitly.** Nothing in
`libraries/bootstrap.php` or `includes/framework.php` registers the extension namespaces, and neither
script executes the application. Without that line no extension class is loadable, and the failure is
silent (every extension file dies quietly on its `_JEXEC` guard).

## The actors

`loggedIn($role)` (front-end) and `loggedInBackend($role)` give a real session for each:

| Role | What it is | Why it exists |
|---|---|---|
| `alice`, `bob` | Registered, consented | Data subjects; bob is the "someone else" of every cross-user attempt |
| `carol` | Registered, **no** consent | The consent redirect |
| `exporter` | `export` on the component | Export others; must not wipe or record consent |
| `wiper` | `wipe` on the component | Wipe others; must not export or record consent |
| `dcadmin` | `core.admin` on the component, not a Super User | The "DataCompliance administrator" of the permission model |
| `administrator` | Joomla's Administrator group | core.manage on the component **by inheritance** — which must not stand in for export/wipe (M1) |
| `nomanage` | An Administrator explicitly **denied** core.manage on the component | The back-end dispatcher gate (H1) |
| `super2` | A second Super User | The protected target: only Super Users may export or wipe Super Users |
| `exempt` | In `plg_datacompliance_joomla`'s exempt groups | Must never be wiped |
| `admin` | The installer's Super User | |

`HarnessTest::testAclMatrixIsWhatItClaims()` asserts, through the site's own authorisation code, that
each role holds exactly what the table says. A fixture that accidentally granted the wrong thing would
turn the tests that rely on it green while proving nothing.

**Wiping is irreversible, so no test wipes a shared account.** They wipe throwaway ones from
`static::$fixtures->createUser()` (and `seedPersonalData()`, `seedAtsFor()`, `seedArsFor()` to give them
something to lose).

## Writing tests

Test classes go in `src/Tests/`, namespace `Akeeba\DataCompliance\IntegrationTest\Tests`, extending
`AbstractE2ETestCase`.

```php
[$victimId, $victim] = …;                                   // a throwaway account, logged in
$before   = $this->userRow($victimId);
$response = $this->requestWipe($this->loggedIn('alice'), $victimId);

$this->assertRefused($this->loggedIn('alice'), $response, 'alice wiping someone else');
$this->assertUserUntouched($before);
```

Conventions:

- **Assert the refusal AND the absence of its effect.** `assertRefused()` says the response was not a
  success; `assertUserUntouched()`, `exportTrailCount()`, `consentRow()` say nothing changed. A message
  can be reworded, a row cannot.
- **Pass the surfer that made the request to `assertRefused()`.** A bad anti-CSRF token does not throw —
  Joomla enqueues a message and redirects — and the message lives in that session.
- **Make sure the test could fail.** Pair every "must be refused" with a control that succeeds (the
  Super User resetting the mail templates in `BackendAccessTest`, a parameter change alone being
  recorded in `UserTrailTest`), and guard against fixtures that make the test moot ("the form shows no
  API token; the test would prove nothing").
- **A crash is not a refusal.** Use `assertNotServerError()` on anything that should be refused: it
  also fails on a PHP fatal in `php-errors.log`.
- **Surface suspected bugs, don't encode them.** Where the product is wrong, the test asserts the
  correct behaviour through `assertOrKnownIssue($ok, N, 'diagnosis')` / `assertOrKnownIssues([...])`,
  which skip with a pointer to item N of the git-ignored `known-issues.md` while the bug stands, and pass
  once it is fixed. When you fix one, replace the call with a plain assertion.

## Practical notes

- **Prove a test fails on the old code (or passes on the fixed code) without rebuilding:** the installed
  site lives in `docker/www`. Patch the installed copy of a file, run the test, then copy the repository
  file back over it. Every known issue marked "verified" in `known-issues.md` was checked this way.
- **PHP errors** go to `docker/www/php-errors.log` (display is off, so tests observe real error pages);
  `run.sh` prints its tail when the suite fails, and `newPhpErrors()` returns what a test caused.
- **Mail:** clear Mailpit at the start of a test that asserts on mail (`$this->mailpit()->clear()`).
  Joomla's mail templates are plain text by default; set `com_mails`' `mail_style` to `both` to get the
  HTML part (`WipeNotificationTest` does).
- **Administrator notifications crash every wipe** while known issue #11 stands. Tests about something
  else switch them off in `setUp()` (`admins => 0`) and back on in `tearDown()`; the tests about the
  notifications themselves run with the defaults.
- **The lifecycle commands act on every end-of-life account on the site.** `LifecycleTest` resets the
  fixtures when it is done.
- **A transient install failure** — "Joomla\Filesystem\Folder::delete: Could not delete folder … install_…"
  while installing ATS or ARS — has been seen once (Joomla 5.4 / PHP 8.5, Docker Desktop on macOS) and did
  not reproduce on re-running that pair. It is Joomla cleaning up its own temporary extraction folder on
  the bind-mounted web root, not the component. Re-run the pair with `--joomla=… --php=…`.
