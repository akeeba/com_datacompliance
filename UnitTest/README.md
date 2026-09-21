# Unit tests

Pure PHPUnit unit tests for Akeeba Data Compliance. They cover what needs no Joomla at all: the
export format helper, the version limits, the mail template definitions against the language
strings, and the shape of what the package ships. They run in milliseconds, without Docker.

Everything that needs a booted Joomla — controllers, the ACL, plugins, the database, mail, the CLI
commands — is tested end-to-end instead; see [`tests/integration/README.md`](../tests/integration/README.md).

## Requirements

- PHP 8.5 (the highest version `composer.json`'s `require.php`, `>=8.1.0 <8.7`, allows today), with
  `dom` and `simplexml`.
- PHPUnit 11, installed **globally** with Composer — never as a project dependency:
  `composer global require phpunit/phpunit ^11`. Make sure Composer's global `vendor/bin` is on your
  `PATH`, so plain `phpunit` resolves.
- The component's Composer dependencies (`component/backend/vendor/autoload.php`, which `phing git`
  or `composer install` produces). The bootstrap loads that autoloader.

## Running

From the repository root:

```bash
phpunit                                   # the whole unit suite
phpunit --testdox                         # human-readable output
phpunit --filter ExportTest               # one class
phpunit UnitTest/Build/SqlSchemaTest.php  # one file
```

Configuration is `phpunit.xml` at the repository root; the bootstrap is `UnitTest/bootstrap.php`. It
defines `_JEXEC` and a stand-in `JVERSION`, registers the component's PSR-4 prefixes, and loads
`UnitTest/Stubs/PrivacyExport.php` — verbatim copies of the three value classes of core's
`com_privacy` export API, which `Helper\Export` consumes.

## What is here

| Test | Covers |
|---|---|
| `Helper/ExportTest` | The XML export helper: round-tripping every awkward character, the merge, Joomla privacy domains, Maximalist Export filtering |
| `Helper/VersionLimitsTest` | The runtime version guard, and its agreement with `composer.json`, `composer.lock` and the installer script |
| `Helper/TemplateEmailsTest` | Every mail template's language keys exist, and its `{TAGS}` are declared |
| `Table/GetPropertiesAwareTraitTest` | What the tables expose as their properties (it ends up in audit records) |
| `Build/SqlSchemaTest` | Every column an update SQL adds is also in the fresh-install SQL |
| `Build/LanguageFilesTest` | No language key is defined twice (Joomla keeps the last one, silently); every line parses |
| `Build/PackageSurfaceTest` | `_JEXEC` guards, the vendor folder's deny-all files and build exclusions, manifests vs. the files on disk |

## Conventions

- Namespace `Akeeba\DataCompliance\UnitTest\...`, mirroring the directory under `UnitTest/`.
- PHPUnit attributes (`#[CoversClass]`, `#[DataProvider]`), not annotations.
- Where the product is currently wrong, the test skips with `Known issue #N (see known-issues.md)`
  instead of failing or asserting the wrong behaviour; it passes once the issue is fixed.
