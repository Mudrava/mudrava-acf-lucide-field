# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
