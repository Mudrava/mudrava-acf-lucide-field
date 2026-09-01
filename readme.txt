=== Mudrava Icon Field for ACF with Lucide ===
Contributors: mudrava
Tags: acf, icons, lucide, brands, icon picker
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A professional ACF custom field type for selecting Lucide icons and Simple Icons brand logos with a visual picker.

== Description ==

**Mudrava Icon Field for ACF with Lucide** adds a new field type to Advanced Custom Fields (ACF) that allows users to select icons from the Lucide icon library and brand logos from Simple Icons through an intuitive visual picker. The selected icon value is stored in the database, making it lightweight and flexible for frontend rendering.

**Requirements**: This plugin requires [Advanced Custom Fields (ACF)](https://www.advancedcustomfields.com/) version 6.0 or higher to function.

= Features =

* **Visual Icon Picker** - Browse and select from 1,791 Lucide icons and 3,457 brand logos
* **Brand Icons** - Brand logos use Simple Icons values such as `simple:facebook`
* **Custom Icon Library** - Upload your own SVG icons under Settings, sanitized on upload and addressable as `custom:<name>`
* **Shortcode & Helper** - `[lucide_icon]` shortcode plus `mudrava_get_lucide_icon()` helper for templates
* **Smart Search** - Filter icons by name or tags instantly
* **Performant** - Lazy icon catalog over a local REST endpoint, byte-offset sprite lookups, inline frontend SVG output, paginated grid (100 icons per page)
* **Native ACF Look** - Seamlessly integrates with ACF's design language
* **Responsive** - Works on all screen sizes
* **Accessible** - Keyboard navigation support
* **Flexible Output** - Return icon value or full SVG markup
* **No External Requests** - All icon data is stored locally

= Usage =

After installing both ACF and this plugin:

1. Go to **Custom Fields > Add New**
2. Add a new field and select **Lucide Icon** as the field type
3. Configure the field settings (default value, return format, etc.)
4. Save your field group

= Template Examples =

**Get Icon Name:**

`<?php
$icon_name = get_field('your_field_name');
echo esc_html($icon_name); // Returns: 'rocket'
?>`

**Get SVG Markup (with return_format = 'svg'):**

`<?php
$icon_svg = get_field('your_field_name');
echo $icon_svg; // Returns: <svg>...</svg>
?>`

**Using the Helper Function:**

`<?php
// Basic usage
echo mudrava_get_lucide_icon('rocket');

// With custom attributes
echo mudrava_get_lucide_icon('rocket', [
    'class'  => 'my-custom-class',
    'width'  => 32,
    'height' => 32,
    'stroke' => '#ff0000',
    'title'  => 'Launch', // accessible title
    'mode'   => 'sprite', // 'inline' (default) or 'sprite'
]);
?>`

= Shortcode =

`[lucide_icon name="rocket" size="32" title="Launch"]`

Custom icons uploaded under Settings → Custom Icons are addressed as `custom:<name>`,
for example `[lucide_icon name="custom:my-logo" title="My Logo"]` or
`mudrava_get_lucide_icon( 'custom:my-logo' )`.

= About Custom SVG Icons =

Site owners can upload plain SVG icons under **Settings → Custom Icons**.
Uploads are parsed with `DOMDocument` (no network access) and reduced to the
same element and attribute allowlist as the bundled sets via `wp_kses()`, so
scripts, styles, embedded images and unsupported elements never reach storage.
Custom icons are stored as standalone files in the uploads directory, appear
in the picker search, and render with their own colors instead of a forced
currentColor tint.

= About Lucide Icons =

[Lucide](https://lucide.dev/) is a modern, open-source icon library with 1,700+ carefully crafted icons. The bundled Lucide assets are from `lucide-static` 1.38.0 and are licensed under ISC.

= About Brand Icons =

Brand logos are bundled separately from [Simple Icons](https://simpleicons.org/) 16.29.0 and are saved with the `simple:` prefix, for example `simple:facebook`.

Simple Icons is distributed under CC0-1.0, but individual brand logos and trademarks may still be governed by each brand owner's guidelines and permissions. Check the relevant brand guidelines before using a logo in production. Source and guideline URLs from Simple Icons are bundled in `data/brand-icons-meta.json`.

== Installation ==

= Requirements =

Before installing this plugin, ensure you have:

* WordPress 6.0 or higher
* PHP 7.4 or higher
* **Advanced Custom Fields (ACF) 6.0 or higher** (required)

= Installation Steps =

1. Install and activate ACF (if not already installed)
2. Upload the `mudrava-acf-lucide-field` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. The new "Lucide Icon" field type will appear in ACF field type selection

= Via WordPress Admin =

1. Go to **Plugins > Add New > Upload Plugin**
2. Upload the plugin zip file
3. Click **Install Now** and then **Activate**

== Frequently Asked Questions ==

= Do I need ACF Pro for this plugin to work? =

No. The field type works with Advanced Custom Fields 6.0 or higher. It also works inside ACF Pro field groups such as Repeaters and Flexible Content.

= Will the plugin work if I deactivate ACF? =

No, the plugin will show an admin notice and the field type will not be available. You must keep ACF active.

= Does this plugin make external requests? =

No. All icon data is stored locally in the plugin files. No external API calls or CDN requests are made.

= Where are the icons stored? =

Lucide SVG data is bundled in `assets/sprite.svg`. Brand logo SVG data is bundled in `assets/brand-sprite.svg`. Search metadata is built into `data/icons.json`, `data/brand-icons.json`, `data/lucide-tags.php`, `data/simple-tags.php` and byte-offset lookups in `data/lucide-index.php` and `data/simple-index.php`. The admin picker loads the catalog lazily through a local REST endpoint (`wp-json/mudrava-lucide/v1/icons`, logged in by default). Everything runs locally on your server.

= Why did social or brand icons disappear after updating Lucide assets? =

Lucide v1 removed most brand icons from the current packages. Version 1.1.0 updates Lucide to `lucide-static` 1.14.0 and adds a separate Simple Icons brand bundle, which is the recommended direction for brand logos. New brand values are saved as `simple:<slug>`, and legacy unprefixed social values such as `facebook`, `instagram`, `twitter`, or `youtube` are resolved against Simple Icons when they no longer exist in Lucide.

= What happens if an icon name was removed in a library update? =

Saved values are never rewritten. Icons removed in `lucide-static` 1.38.0 (`angry`, `annoyed`, `frown`, `history`, `laugh`, `meh`, `podcast`, `smile`, `smile-plus`) are mapped to canonical replacements in `data/compat-aliases.json`, for example `smile` renders as `face-slightly-smiling`. Unknown values that have no alias keep saving and surface as a dismissible admin notice; set the field setting "Unknown Icon Values" to "error" to make validation strict instead.

= Can I use this with ACF Repeater or Flexible Content fields? =

Yes, the Lucide Icon field works perfectly within Repeater fields, Flexible Content fields, and any other ACF field groups.

= What format does the field return? =

You can configure the return format in the field settings:
* **Icon Name** - Returns the icon value as a string (e.g., "rocket" or "simple:facebook")
* **SVG Markup** - Returns the complete SVG HTML code

= How do I customize the icon appearance? =

Use the `mudrava_get_lucide_icon()` helper function with custom attributes for width, height, stroke color, fill color, and CSS classes.

= Is the plugin translation-ready? =

Yes, the plugin is fully translation-ready with text domain `mudrava-acf-lucide-field`. Translation files should be placed in the `/languages/` directory.

== Privacy ==

**Data Collection:**

This plugin does not collect, store, or transmit any user data or personal information.

**External Requests:**

This plugin does not make any external HTTP requests. All icon data is stored locally and served from your WordPress installation.

**Cookies:**

This plugin does not use cookies.

**Third-Party Services:**

This plugin does not integrate with or send data to any third-party services.

== Changelog ==

= 1.2.0 - 2026-08-31 =

* Update bundled assets to `lucide-static` 1.38.0 and `simple-icons` 16.29.0.
* Replace the 600 KB admin payload with a lazy REST icon catalog.
* Replace regex sprite extraction with byte-offset index lookups (no full-file scans per render).
* Render icons as inline SVG by default and support a `sprite` mode with a footer sprite sheet.
* Add a compatibility alias layer (`data/compat-aliases.json`) for icon names removed upstream; saved values are never rewritten.
* Unknown icon values no longer block saving by default; they show a dismissible admin notice. A per-field "Unknown Icon Values" setting (`warn`/`error`) controls strict mode.
* Harden SVG handling with a build-time allowlist (`data/allowed-svg.json`) and runtime `wp_kses` sanitization.
* Add a custom SVG icon library: upload icons under Settings, sanitized against the same allowlist and stored as standalone files; addressable as `custom:<name>` in the field, picker, shortcode and helper.
* Add the `[lucide_icon]` shortcode with `mudrava_lucide_icon_svg_args` and `mudrava_lucide_icon_svg` filters.
* Bundle minified sprite assets and add scripted asset pipeline checks.
* Add PHPUnit unit tests and PHPCS/ESLint configs.

= 1.1.0 - 2026-04-30 =

* Update bundled Lucide assets to `lucide-static` 1.14.0.
* Add a separate Simple Icons 16.18.0 brand icon bundle with 3,400+ brand logos.
* Save new brand icon selections as `simple:<slug>`.
* Resolve legacy unprefixed social icon values against Simple Icons when Lucide no longer contains them.
* Add third-party notices and brand-logo usage guidance.

= 1.0.1 - 2026-04-30 =

* Render frontend helper output as inline SVG instead of external sprite references.
* Avoid stale transient icon lists after sprite updates.
* Improve keyboard focus and dropdown stacking in ACF Repeater/Flexible Content contexts.
* Validate saved icon names against the bundled local sprite.
* Clarify ACF 6.0+ requirement and Lucide v1 brand icon behavior.

= 1.0.0 - 2026-03-07 =

* Initial release
* Visual icon picker with 1,500+ Lucide icons
* Real-time search and filter functionality
* Icon name or SVG return format options
* Helper function for template use
* Allow null option for optional icon selection
* Default value configuration
* Custom placeholder text setting
* Keyboard navigation support
* RTL language support
* Responsive grid layout
* Native ACF styling integration
* Local sprite file (no external requests)
* Bundled icon metadata for instant search
* Full compatibility with ACF Repeater and Flexible Content fields

== Upgrade Notice ==

= 1.2.0 =
Updates bundled icon libraries to `lucide-static` 1.38.0 and `simple-icons` 16.29.0, reworks the admin picker to load lazily over REST, and makes unknown icon values non-blocking by default. Saved values are never rewritten; removed upstream icons are aliased to their canonical replacements.

= 1.1.0 =
Updates Lucide to 1.14.0 and moves brand logos to a separate Simple Icons bundle. New brand values use `simple:<slug>`; legacy social values are resolved automatically where possible.

= 1.0.1 =
Fixes frontend icon rendering reliability, especially on sites using CDN, HTTPS/domain rewrites, CSP, or optimization plugins. Also documents Lucide v1 brand icon changes.

= 1.0.0 =
Initial release of Mudrava Icon Field for ACF with Lucide.
