# Translations

This plugin ships 27 complete bundled translations and is also set up for
community translations on translate.wordpress.org.

## Bundled locales

| Locale file suffix | Language               |
|--------------------|------------------------|
| `ca`               | Catalan                |
| `cs`               | Czech                  |
| `da`               | Danish                 |
| `de_DE`            | German                 |
| `el`               | Greek                  |
| `es_ES`            | Spanish (Spain)        |
| `fr_FR`            | French (France)        |
| `gl`               | Galician               |
| `hr`               | Croatian               |
| `hu`               | Hungarian              |
| `id`               | Indonesian             |
| `it_IT`            | Italian                |
| `ja`               | Japanese               |
| `nb`               | Norwegian Bokmål       |
| `nl_NL`            | Dutch                  |
| `pl_PL`            | Polish                 |
| `pt_BR`            | Portuguese (Brazil)    |
| `pt_PT`            | Portuguese (Portugal)  |
| `ro`               | Romanian               |
| `ru`               | Russian                |
| `sk`               | Slovak                 |
| `sl`               | Slovenian              |
| `sr`               | Serbian                |
| `sv`               | Swedish                |
| `tr_TR`            | Turkish                |
| `uk`               | Ukrainian              |
| `zh_CN`            | Chinese (China)        |

Because the text domain (`mudrava-acf-lucide-field`) equals the plugin folder
name, WordPress 4.6+ loads `languages/<locale>.mo` automatically; no
`load_plugin_textdomain()` call is needed.

## Community translations (translate.wordpress.org)

Project: https://translate.wordpress.org/projects/wp-plugins/mudrava-acf-lucide-field/

Both the `development` (trunk) and `stable` branches are open for translation.
Approved strings are distributed by WordPress as language packs and take
precedence over the bundled `.mo` files for sites in that locale, so the
bundled catalogs act as the baseline and community improvements apply on top.

Maintainer workflow:

- `php scripts/make-pot.php` regenerates the `.pot` catalog from PHP sources.
- `python3 scripts/merge-po.py` merges catalogs against the current `.pot` and
  recompiles the `.mo` files.
- Translations are written by hand per locale, one language at a time, matching
  the existing tone and terminology of each catalog. Machine-translated bulk
  text is not accepted.

## Code-side notes

- Every user-facing string goes through `__()` / `esc_html__()` / `esc_attr_e()`
  with the `mudrava-acf-lucide-field` text domain.
- Strings rendered by the picker JS are translated server-side and passed via
  the inline config in `class-mudrava-acf-field-lucide-icon.php`.
- Translator hints for placeholder strings use `/* Translators: ... */` comments.
