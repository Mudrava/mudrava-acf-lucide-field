# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
