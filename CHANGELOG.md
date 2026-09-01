# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-08-31

### Added

- Updated bundled assets to `lucide-static` `1.38.0` and `simple-icons` `16.29.0`.
- Added a lazy REST icon catalog (`wp-json/mudrava-lucide/v1/icons`) that replaces the ~600 KB inlined admin payload; the catalog is restricted to logged-in users via the `mudrava_lucide_field_icons_permission` filter.
- Added a byte-offset sprite index (`data/lucide-index.php`, `data/simple-index.php`) so each render reads only the matching symbol slice instead of scanning and regex-parsing the whole sprite file.
- Added build-time search tags (`data/lucide-tags.php`, `data/simple-tags.php`) and a canonical SVG allowlist (`data/allowed-svg.json`) enforced at build time and mirrored at runtime through `wp_kses`.
- Added a compatibility alias layer (`data/compat-aliases.json`) that maps icon names removed upstream in Lucide 1.38.0 (`angry`, `annoyed`, `frown`, `history`, `laugh`, `meh`, `podcast`, `smile`, `smile-plus`) to their canonical replacements; stored post values are never rewritten.
- Added a `[lucide_icon]` shortcode and `mudrava_lucide_icon_svg_args` / `mudrava_lucide_icon_svg` filters.
- Added a custom SVG icon library: site owners can upload plain SVG icons under Settings → Custom Icons (capability configurable via the `mudrava_lucide_field_upload_permission` filter). Uploads are parsed with `DOMDocument` (`LIBXML_NONET`), reduced to the same shape allowlist as the bundled sets via `wp_kses()` and stored as standalone sanitized files; the raw upload never reaches disk. Custom icons are addressable as `custom:<name>` in the field, the picker (per-symbol `viewBox` honored in sprites and previews) and the shortcode/helper, and their inline output carries the `mudrava-lucide-icon-svg--custom` variant without forced paint. Manifest entries whose files went missing are pruned automatically.
- Added a `sprite` render mode to the helper/shortcode that queues symbols into a footer sprite sheet; inline SVG remains the default.
- Added minified sprite pipeline scripts (`scripts/build-sprites.mjs`, `scripts/minify-assets.mjs`, `scripts/build-assets.php`) with `--check` modes, plus PHPUnit tests, PHPCS and ESLint configurations.

### Changed

- Sprite and data file reads now go through the WordPress filesystem API (`WP_Filesystem`) instead of direct PHP filesystem calls.
- Unknown icon values no longer block saving by default. They are collected in a short-lived global transient and surfaced as a dismissible admin notice; the new per-field **Unknown Icon Values** setting (`warn`/`error`) controls strict blocking. The previous hard-fail behavior can be restored per field.
- The admin picker now fetches catalog data on demand and renders preview sprites sourced from the REST payload instead of localized option objects.
- Sprite assets are shipped minified via SVGO while the non-minified SVG sources stay untouched for the PHP index.

### Fixed

- Inline frontend rendering no longer embeds `<symbol>` wrappers inside `<svg>`, which made helper and shortcode output invisible.
- Restored the default icon size rule and the `:not()` brand modifier selector in `assets/css/field.css`.
- Active descendant and option element IDs are guaranteed in the picker combobox for screen readers.
- Picker options now apply the `mudrava-lucide-icon-svg--brand` / `--lucide` variant classes with matching `fill`/`stroke` attributes, so brand logos render as filled silhouettes instead of outlines.
- Local test sites install the plugin through `scripts/sync-live.sh` (production-file mirror) instead of linking the whole repository, so admin runtime and Plugin Check evaluate exactly the shipped payload.

### Security

- All stored and rendered icon markup passes through a strict element/attribute allowlist; `<symbol>` wrappers and disallowed elements are unwrapped or dropped, event-handler attributes and external references (`href`, `xlink:href`) are never allowed.

### Deprecated

- `mudrava_lucide_field_get_sprite_symbols()` and `mudrava_lucide_field_get_brand_sprite_symbols()` are superseded by `mudrava_lucide_field_parse_sprite_symbols()`; back-compat wrappers ship in 1.2.0 and will be removed in 1.3.

## [1.1.0] - 2026-04-30

### Added

- Added a separate Simple Icons `16.18.0` brand icon bundle with 3,400+ brand logos.
- Added `simple:<slug>` values for new brand icon selections, for example `simple:facebook`.
- Added legacy fallback resolution for unprefixed social values such as `facebook`, `instagram`, `twitter`, and `youtube` when Lucide no longer contains them.
- Added third-party notices and brand-logo usage guidance.

### Changed

- Updated bundled Lucide assets to `lucide-static` `1.14.0`.
- Updated the admin picker to search Lucide icons and Simple Icons brand logos together while keeping the icon sources separate.
- Improved picker search result ranking and labels so direct brand matches appear before Lucide tag-only matches.
- Render Simple Icons brand logos as fill-based inline SVGs and Lucide icons as stroke-based inline SVGs.

## [1.0.1] - 2026-04-30

### Fixed

- Render frontend helper output as inline SVG instead of external sprite references, improving reliability with CDN domains, HTTPS/domain rewrites, CSP, MIME handling, and optimization plugins.
- Avoid stale transient icon lists after sprite updates by parsing the bundled sprite once per request.
- Preserve open picker stacking above ACF Repeater and Flexible Content rows.

### Changed

- Validate saved icon names against the bundled local sprite.
- Clarify the ACF 6.0+ requirement.
- Document Lucide v1 brand icon behavior and warn against blindly replacing the bundled sprite with `lucide-static@latest`.

## [1.0.0] - 2026-03-07

### Added

- Initial WordPress.org release
- Visual icon picker with 1,500+ Lucide icons
- Real-time search and filter functionality
- Icon name or SVG return format options
- `mudrava_get_lucide_icon()` helper function for template use
- Allow null option for optional icon selection
- Default value configuration
- Custom placeholder text setting
- Keyboard navigation support (Escape to close, Enter to select)
- RTL language support
- Responsive grid layout for all screen sizes
- Native ACF styling integration
- Full compatibility with ACF Repeater and Flexible Content fields
- ACF Pro dependency check with admin notice
- Complete WordPress.org readme.txt with metadata

### Changed

- Lowered PHP requirement from 8.0 to 7.4 for wider compatibility
- Icon rendering now uses local sprite.svg file (no external requests)
- Removed external CDN dependencies (unpkg.com)
- All icon data is now served locally from plugin files

### Removed

- GitHub auto-updater system (not compatible with WordPress.org)
- External HTTP requests to CDN services
- Automatic target="_blank" link modification

### Technical Details

- Requires WordPress 6.0+
- Requires PHP 7.4+
- Requires ACF Pro 6.0+
- All icons loaded from local sprite.svg file
- Icon metadata from bundled icons.json file
- No external requests or third-party services
- Transient caching for sprite symbol list
