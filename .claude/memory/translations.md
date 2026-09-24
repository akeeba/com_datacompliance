# Translations

## Keep all languages in parity

Supported languages besides en-GB: el-GR, fr-FR, de-DE, es-ES, it-IT, pt-PT. Use the
`machine-translation` skill; consult and extend the per-language glossaries in `build/glossaries/`;
process files with more than about 50 keys in chunks of roughly 10–12 KiB; add manifest `<language>`
entries whenever a new language is added.

**Why:** the operator wants the translations always in parity with en-GB.

**How to apply:** any change that touches language strings also updates all six translations in the same
piece of work. After editing, check every changed INI file with `parse_ini_file()`.
