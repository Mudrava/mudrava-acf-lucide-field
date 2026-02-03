# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-02-03

### Added

- Initial release of Mudrava Lucide Field
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
- CDN-based icon loading for performance
- Bundled icon metadata for instant search
- Transient caching for SVG requests
- Full compatibility with ACF Repeater and Flexible Content fields

### Technical Details

- Requires WordPress 6.0+
- Requires PHP 8.0+
- Requires ACF Pro 6.0+
- Uses Lucide CDN (unpkg.com) for SVG delivery
- Icon metadata from lucide-static package
