# Translations

## Keep all languages in parity

Supported languages besides en-GB: el-GR, fr-FR, de-DE, es-ES, it-IT, pt-PT. Use the
`machine-translation` skill; consult and extend the per-language glossaries in `build/glossaries/`;
process files with more than about 50 keys in chunks of roughly 10–12 KiB; add manifest `<language>`
entries whenever a new language is added.

**Why:** the operator wants the translations always in parity with en-GB.

**How to apply:** any change that touches language strings also updates all six translations in the same
piece of work. After editing, check every changed INI file with `parse_ini_file()`.

## Apostrophes are written as a plain `'` — never `''`, never `\'`

Inside a `KEY="…"` value, write an apostrophe as a single `'`. Joomla shows `''` as two apostrophes
and `\'` with its backslash; neither is an escape. A double quote is `\"` (Joomla turns it into `"`);
`"_QQ_"` is shown literally.

**Why:** settled with evidence on 2026-09-24. Core loads language files and overrides through
`LanguageHelper::parseIniFile()` (`INI_SCANNER_RAW`, then only `\"` → `"`), and `Text::_()` only
interprets `\\`, `\t` and `\n`. `UnitTest/Language/IniQuoteEscapingTest.php` and
`tests/integration/src/Tests/IniQuoteEscapingTest.php` pin it down on every supported PHP and Joomla
version. `''` is SQL's escape, not INI's — yet the en-GB, fr-FR, it-IT and el-GR files were found
shipping it.

**How to apply:** when translating or editing any `.ini` file, grep it for `''` and `\'` before
finishing; both are always wrong.
