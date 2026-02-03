# Lucide ACF Field Free

A professional ACF (Advanced Custom Fields) custom field type for selecting [Lucide](https://lucide.dev/) icons with a visual picker interface.

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-green.svg)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)
![ACF Pro](https://img.shields.io/badge/ACF%20Pro-6.0%2B-orange.svg)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-lightgrey.svg)

## Description

Lucide ACF Field Free adds a new field type to ACF Pro that allows users to select icons from the Lucide icon library through an intuitive visual picker. The selected icon name is stored in the database, making it lightweight and flexible for frontend rendering.

### Features

- **Visual Icon Picker** — Browse and select from 1,500+ Lucide icons
- **Smart Search** — Filter icons by name or tags instantly
- **Performant** — Local sprite file, paginated grid (100 icons per page)
- **Native ACF Look** — Seamlessly integrates with ACF's design language
- **Responsive** — Works on all screen sizes
- **Accessible** — Keyboard navigation support
- **Flexible Output** — Return icon name or full SVG markup
- **Auto Updates** — Receive updates directly from GitHub

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
- ACF Pro 6.0 or higher

## Installation

### Manual Installation

1. Download the plugin zip file from [GitHub Releases](https://github.com/Mudrava/Lucide-ACF-Field-Free/releases)
2. Go to **Plugins > Add New > Upload Plugin**
3. Upload the zip file and click **Install Now**
4. Activate the plugin

### Via Git

```bash
cd wp-content/plugins
git clone https://github.com/Mudrava/Lucide-ACF-Field-Free.git mudrava-lucide-field
```

## Usage

### Creating a Lucide Icon Field

1. Go to **Custom Fields > Add New**
2. Add a new field and select **Lucide Icon** as the field type
3. Configure the field settings:
   - **Default Value** — Pre-selected icon name (e.g., `rocket`)
   - **Return Format** — Choose between icon name or SVG markup
   - **Allow Null** — Allow the field to have no selection
4. Save your field group

### Retrieving the Value

#### Get Icon Name

```php
<?php
$icon_name = get_field('your_field_name');
// Returns: 'rocket'
?>
```

#### Get SVG Markup (with return_format = 'svg')

```php
<?php
$icon_svg = get_field('your_field_name');
// Returns: '<svg>...</svg>'

echo $icon_svg;
?>
```

#### Using the Helper Function

The plugin provides a helper function for rendering icons with custom attributes:

```php
<?php
// Basic usage
echo mudrava_get_lucide_icon('rocket');

// With custom attributes
echo mudrava_get_lucide_icon('rocket', [
    'class'  => 'my-custom-class',
    'width'  => 32,
    'height' => 32,
    'stroke' => '#ff0000',
]);
?>
```

### Template Examples

#### Display Icon in a Block

```php
<?php
$icon = get_field('icon');
if ($icon) {
    echo mudrava_get_lucide_icon($icon, ['class' => 'feature-icon']);
}
?>
```

#### Icon with Link

```php
<?php
$icon = get_field('social_icon');
$url = get_field('social_url');

if ($icon && $url) : ?>
    <a href="<?php echo esc_url($url); ?>" class="social-link">
        <?php echo mudrava_get_lucide_icon($icon, ['width' => 20, 'height' => 20]); ?>
    </a>
<?php endif; ?>
```

#### Using in Repeater Fields

```php
<?php if (have_rows('features')) : ?>
    <div class="features-grid">
        <?php while (have_rows('features')) : the_row(); ?>
            <div class="feature">
                <?php
                $icon = get_sub_field('icon');
                if ($icon) {
                    echo mudrava_get_lucide_icon($icon, ['class' => 'feature-icon']);
                }
                ?>
                <h3><?php the_sub_field('title'); ?></h3>
                <p><?php the_sub_field('description'); ?></p>
            </div>
        <?php endwhile; ?>
    </div>
<?php endif; ?>
```

## API Reference

### `mudrava_get_lucide_icon( string $icon_name, array $args = [] ): string`

Retrieves the SVG markup for a Lucide icon.

#### Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `$icon_name` | string | The name of the Lucide icon (e.g., 'rocket') |
| `$args` | array | Optional. Customization arguments |

#### Arguments

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `class` | string | `''` | Additional CSS classes |
| `width` | int | `24` | Icon width in pixels |
| `height` | int | `24` | Icon height in pixels |
| `stroke` | string | `'currentColor'` | Stroke color |

#### Returns

`string` — The SVG markup or empty string on failure.

## Field Settings

| Setting | Description |
|---------|-------------|
| **Default Value** | Icon name to pre-select when field is empty |
| **Return Format** | `name` returns icon name, `svg` returns full SVG markup |
| **Placeholder** | Custom placeholder text for the search input |
| **Allow Null** | When enabled, allows clearing the selection |

## Frequently Asked Questions

### Can I use this with the free version of ACF?

No, this plugin requires ACF Pro 6.0 or higher due to the use of advanced field type APIs.

### Are the icons bundled with the plugin?

Yes! The Lucide sprite file is bundled locally for optimal performance. No external CDN requests are made.

### How often is the icon library updated?

The bundled sprite can be updated by replacing the `assets/sprite.svg` file with the latest version from [Lucide's repository](https://github.com/lucide-icons/lucide).

### Does it support RTL languages?

Yes, the plugin includes RTL (right-to-left) support.

### How do updates work?

The plugin checks for updates directly from GitHub releases. When a new version is available, you'll see the update notification in WordPress admin just like any other plugin.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a complete version history.

## Contributing

Contributions are welcome! Please read our contributing guidelines before submitting a pull request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Links

- **Plugin Page**: [mudrava.com/en/projects/lucide-acf-field-free](https://mudrava.com/en/projects/lucide-acf-field-free)
- **GitHub**: [github.com/Mudrava/Lucide-ACF-Field-Free](https://github.com/Mudrava/Lucide-ACF-Field-Free)
- **Lucide Icons**: [lucide.dev](https://lucide.dev/)

## Credits

- [Lucide Icons](https://lucide.dev/) — The icon library
- [Advanced Custom Fields](https://www.advancedcustomfields.com/) — The ACF framework

## License

This project is licensed under the GPL-2.0-or-later License. See the [LICENSE](LICENSE) file for details.

## Author

**Mudrava**  
[https://mudrava.com](https://mudrava.com)
