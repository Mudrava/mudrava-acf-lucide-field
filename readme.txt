=== Lucide ACF Field Free ===
Contributors: mudrava
Tags: acf, icons, lucide, custom fields, icon picker
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A professional ACF custom field type for selecting Lucide icons with a visual picker interface.

== Description ==

**Lucide ACF Field Free** adds a new field type to Advanced Custom Fields (ACF) that allows users to select icons from the Lucide icon library through an intuitive visual picker. The selected icon name is stored in the database, making it lightweight and flexible for frontend rendering.

**Requirements**: This plugin requires [Advanced Custom Fields (ACF) Pro](https://www.advancedcustomfields.com/) version 6.0 or higher to function.

= Features =

* **Visual Icon Picker** - Browse and select from 1,500+ Lucide icons
* **Smart Search** - Filter icons by name or tags instantly
* **Performant** - Local sprite file, paginated grid (100 icons per page)
* **Native ACF Look** - Seamlessly integrates with ACF's design language
* **Responsive** - Works on all screen sizes
* **Accessible** - Keyboard navigation support
* **Flexible Output** - Return icon name or full SVG markup
* **No External Requests** - All icon data is stored locally

= Usage =

After installing both ACF Pro and this plugin:

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
]);
?>`

= About Lucide Icons =

[Lucide](https://lucide.dev/) is a modern, open-source icon library with 1,500+ carefully crafted icons. All icons are licensed under ISC, making them free to use in commercial and personal projects.

== Installation ==

= Requirements =

Before installing this plugin, ensure you have:

* WordPress 6.0 or higher
* PHP 7.4 or higher
* **Advanced Custom Fields (ACF) Pro 6.0 or higher** (required)

= Installation Steps =

1. Install and activate ACF Pro (if not already installed)
2. Upload the `Lucide-ACF-Field-Free` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. The new "Lucide Icon" field type will appear in ACF field type selection

= Via WordPress Admin =

1. Go to **Plugins > Add New > Upload Plugin**
2. Upload the plugin zip file
3. Click **Install Now** and then **Activate**

== Frequently Asked Questions ==

= Do I need ACF Pro for this plugin to work? =

Yes, this plugin is an extension for Advanced Custom Fields Pro and requires ACF Pro version 6.0 or higher to function. It will not work with the free version of ACF.

= Will the plugin work if I deactivate ACF Pro? =

No, the plugin will show an admin notice and the field type will not be available. You must keep ACF Pro active.

= Does this plugin make external requests? =

No. All icon data is stored locally in the plugin files (sprite.svg and icons.json). No external API calls or CDN requests are made.

= Where are the icons stored? =

All icon SVG data is bundled with the plugin in the `assets/sprite.svg` file. Icon metadata (names and tags) is stored in `data/icons.json`. Everything runs locally on your server.

= Can I use this with ACF Repeater or Flexible Content fields? =

Yes, the Lucide Icon field works perfectly within Repeater fields, Flexible Content fields, and any other ACF field groups.

= What format does the field return? =

You can configure the return format in the field settings:
* **Icon Name** - Returns the icon name as a string (e.g., "rocket")
* **SVG Markup** - Returns the complete SVG HTML code

= How do I customize the icon appearance? =

Use the `mudrava_get_lucide_icon()` helper function with custom attributes for width, height, stroke color, and CSS classes.

= Is the plugin translation-ready? =

Yes, the plugin is fully translation-ready with text domain `mudrava-lucide-field`. Translation files should be placed in the `/languages/` directory.

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

= 1.0.0 =
Initial release of Lucide ACF Field Free.
