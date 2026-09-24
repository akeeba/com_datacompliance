# Testing

## Product bugs found while writing tests go in `known-issues.md`

When creating or extending the test suites turns up a real product bug, record it, numbered, in the
git-ignored `known-issues.md` at the repository root for a follow-up session — do not fix it in the same
session. The tests assert the correct behaviour and skip with "Known issue #N (see known-issues.md)"
until the bug is fixed; the E2E base class (`tests/integration/src/AbstractE2ETestCase.php`) has
`assertOrKnownIssue()` / `assertOrKnownIssues()` for this.

**Why:** requested by the operator on 2026-09-21 while the E2E and unit suites were being built, so that
bug fixes happen as a separate, reviewable step.

**How to apply:** keep `/known-issues.md` in `.gitignore`. Give each entry where / what / fix / which
test. Verify a diagnosis by patching the installed copy in `tests/integration/docker/www` and then
restoring it.
