<?php
/**
 * Unit tests for the plugin bootstrap functions.
 */

use PHPUnit\Framework\TestCase;

class PluginFunctionsTest extends TestCase
{
    public function testIconValueSanitizationNormalizesTokens(): void
    {
        $this->assertSame('rocket', mudrava_lucide_field_sanitize_icon_value('Rocket'));
        $this->assertSame('arrow-left', mudrava_lucide_field_sanitize_icon_value('Arrow Left'));
        $this->assertSame('arrow-left', mudrava_lucide_field_sanitize_icon_value('arrow--left'));
        $this->assertSame('backstage-casting', mudrava_lucide_field_sanitize_icon_value('backstage_casting'));
    }

    public function testIconValueSanitizationKeepsSourcePrefix(): void
    {
        $this->assertSame('simple:facebook', mudrava_lucide_field_sanitize_icon_value('simple:facebook'));
        $this->assertSame('simple:facebook', mudrava_lucide_field_sanitize_icon_value('SIMPLE:Facebook'));
        $this->assertSame('lucide:rocket', mudrava_lucide_field_sanitize_icon_value('lucide:rocket'));
    }

    public function testIconValueSanitizationRejectsJunk(): void
    {
        $this->assertSame('not-an-icon', mudrava_lucide_field_sanitize_icon_value('not an icon!!'));
        $this->assertSame('', mudrava_lucide_field_sanitize_icon_value('<script>alert(1)</script>'));
        $this->assertSame('', mudrava_lucide_field_sanitize_icon_value('simple:'));
        $this->assertSame('', mudrava_lucide_field_sanitize_icon_value('other:icon'));
    }

    public function testParseIconValue(): void
    {
        $this->assertSame(
            array('source' => 'simple', 'name' => 'facebook'),
            mudrava_lucide_field_parse_icon_value('simple:facebook')
        );
        $this->assertSame(
            array('source' => 'lucide', 'name' => 'rocket'),
            mudrava_lucide_field_parse_icon_value('lucide:rocket')
        );
        $this->assertSame(
            array('source' => 'auto', 'name' => 'rocket'),
            mudrava_lucide_field_parse_icon_value('rocket')
        );
    }

    public function testIconExistsForBothSources(): void
    {
        $this->assertTrue(mudrava_lucide_field_icon_exists('rocket'));
        $this->assertTrue(mudrava_lucide_field_icon_exists('simple:github'));
        $this->assertTrue(mudrava_lucide_field_icon_exists('lucide:rocket'));
        $this->assertFalse(mudrava_lucide_field_icon_exists('not-an-icon'));
        $this->assertFalse(mudrava_lucide_field_icon_exists('simple:not-a-brand'));
        $this->assertFalse(mudrava_lucide_field_icon_exists(''));
    }

    public function testResolveIconPrefersExactLucideMatch(): void
    {
        $this->assertSame(
            array('source' => 'lucide', 'name' => 'rocket'),
            mudrava_lucide_field_resolve_icon('rocket')
        );
    }

    public function testResolveIconFallsBackToBrandAlias(): void
    {
        $resolved = mudrava_lucide_field_resolve_icon('facebook');

        $this->assertSame('simple', $resolved['source']);
        $this->assertSame('facebook', $resolved['name']);
    }

    public function testResolveIconUsesCompatAliasesForRemovedIcons(): void
    {
        $this->assertSame(
            array('source' => 'lucide', 'name' => 'face-slightly-smiling'),
            mudrava_lucide_field_resolve_icon('smile')
        );

        $this->assertSame(
            array('source' => 'lucide', 'name' => 'face-angry'),
            mudrava_lucide_field_resolve_icon('lucide:angry')
        );

        $this->assertSame(
            array('source' => '', 'name' => ''),
            mudrava_lucide_field_resolve_icon('not-an-icon')
        );
    }

    public function testGetSymbolReturnsSanitizedInnerMarkup(): void
    {
        $symbol = mudrava_lucide_field_get_symbol('lucide', 'rocket');

        $this->assertStringStartsWith('<path', $symbol);
        $this->assertStringNotContainsString('<symbol', $symbol);
        $this->assertStringNotContainsString('<script', $symbol);
        $this->assertStringNotContainsString('onerror', $symbol);
    }

    public function testGetSymbolForBrandSource(): void
    {
        $symbol = mudrava_lucide_field_get_symbol('simple', 'github');

        $this->assertStringStartsWith('<path', $symbol);
    }

    public function testGetSymbolEmptyForUnknown(): void
    {
        $this->assertSame('', mudrava_lucide_field_get_symbol('lucide', 'no-such-icon'));
        $this->assertSame('', mudrava_lucide_field_get_symbol('simple', 'no-such-brand'));
    }

    public function testReadSpriteSliceMatchesIndex(): void
    {
        $sprite = MUDRAVA_LUCIDE_FIELD_PATH . 'assets/sprite.svg';
        $contents = (string) file_get_contents($sprite);

        $this->assertStringStartsWith('<svg', $contents);
        $this->assertStringContainsString('id="rocket"', $contents);

        $index = mudrava_lucide_field_data('lucide-index');

        $this->assertArrayHasKey('rocket', $index);

        $slice = mudrava_lucide_field_read_sprite_slice('sprite.svg', (int) $index['rocket'][0], (int) $index['rocket'][1]);

        $this->assertStringStartsWith('<path', $slice);
        $this->assertStringNotContainsString('</symbol>', $slice);
    }

    public function testSvgWhitelistShape(): void
    {
        $allowed = mudrava_lucide_field_allowed_svg_children();

        foreach (array('path', 'circle', 'rect', 'line', 'polyline', 'polygon', 'ellipse') as $tag) {
            $this->assertArrayHasKey($tag, $allowed, sprintf('Whitelist should contain "%s"', $tag));
        }

        foreach (array('script', 'foreignobject', 'a', 'text', 'image', 'use', 'iframe') as $tag) {
            $this->assertArrayNotHasKey($tag, $allowed, sprintf('Whitelist must never contain "%s"', $tag));
        }

        foreach ($allowed as $tag => $attributes) {
            $this->assertIsArray($attributes);
            $this->assertArrayNotHasKey('href', $attributes);
            $this->assertArrayNotHasKey('xlink:href', $attributes);
            $this->assertArrayNotHasKey('onclick', $attributes);
        }
    }

    public function testSanitizePaint(): void
    {
        $this->assertSame('#fff', mudrava_lucide_field_sanitize_paint('#fff'));
        $this->assertSame('#ff0000', mudrava_lucide_field_sanitize_paint('#FF0000'));
        $this->assertSame('currentColor', mudrava_lucide_field_sanitize_paint('currentColor'));
        $this->assertSame('red', mudrava_lucide_field_sanitize_paint('red'));
        $this->assertSame('rgb(1, 2, 3)', mudrava_lucide_field_sanitize_paint('rgb(1, 2, 3)'));
    }

    public function testSanitizePaintRejectsCssInjection(): void
    {
        $this->assertSame('currentColor', mudrava_lucide_field_sanitize_paint('red; position:absolute'));
        $this->assertSame('currentColor', mudrava_lucide_field_sanitize_paint('url(javascript:alert(1))'));
        $this->assertSame('currentColor', mudrava_lucide_field_sanitize_paint('expression(alert(1))'));
        $this->assertSame('currentColor', mudrava_lucide_field_sanitize_paint('var(--brand)'));
    }

    public function testIconHelperReturnsInlineSvg(): void
    {
        $svg = mudrava_get_lucide_icon('rocket', array('class' => 'my-icon'));

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('my-icon', $svg);
        $this->assertStringContainsString('mudrava-lucide-icon-svg--lucide', $svg);
        $this->assertStringContainsString('<path', $svg);
        $this->assertStringNotContainsString('onerror', $svg);
    }

    public function testIconHelperUnknownReturnsEmpty(): void
    {
        $this->assertSame('', mudrava_get_lucide_icon('not-an-icon'));
    }

    public function testIconHelperAppliesFilters(): void
    {
        $filter = static function ($args) {
            $args['width'] = 48;
            $args['height'] = 48;
            $args['stroke'] = '#123456';

            return $args;
        };

        add_filter('mudrava_lucide_icon_svg_args', $filter);

        $svg = mudrava_get_lucide_icon('rocket');

        remove_filter('mudrava_lucide_icon_svg_args', $filter);

        $this->assertStringContainsString('width="48"', $svg);
        $this->assertStringContainsString('height="48"', $svg);
        $this->assertStringContainsString('stroke="#123456"', $svg);
    }

    public function testIconHelperBrandUsesFill(): void
    {
        $svg = mudrava_get_lucide_icon('simple:github');

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('fill="currentColor"', $svg);
        $this->assertStringContainsString('stroke="none"', $svg);
    }

    public function testRestPayloadShapeAndEscaping(): void
    {
        $response = mudrava_lucide_field_rest_icons();
        $data = $response->get_data();

        $this->assertSame(200, $response->get_status());
        $this->assertArrayHasKey('allowedElements', $data);
        $this->assertContains('path', $data['allowedElements']);
        $this->assertArrayHasKey('compatAliases', $data);
        $this->assertNotEmpty($data['compatAliases']);
        $this->assertNotEmpty($data['lucide']['icons']);
        $this->assertNotEmpty($data['simple']['icons']);

        $encoded = wp_json_encode($data);

        $this->assertNotFalse($encoded);
        $this->assertStringNotContainsString('<script', (string) $encoded);
    }

    public function testRestPermissionRequiresLoggedInUser(): void
    {
        $GLOBALS['mudrava_test_logged_in'] = true;
        $this->assertTrue(mudrava_lucide_field_icons_permission());

        $GLOBALS['mudrava_test_logged_in'] = false;
        $this->assertFalse(mudrava_lucide_field_icons_permission());

        $GLOBALS['mudrava_test_logged_in'] = true;
    }

    public function testPluginLinksUseEscapedUrls(): void
    {
        $links = mudrava_lucide_field_plugin_links(array(), 'mudrava-acf-lucide-field/mudrava-acf-lucide-field.php');

        $joined = implode(' ', $links);

        $this->assertStringNotContainsString('plugins.trac.wordpress.org', $joined);
        $this->assertMatchesRegularExpression('/href="https?:\/\/[^"]+"/', $joined);
    }

    public function testShortcodeRegistered(): void
    {
        $this->assertArrayHasKey('lucide_icon', $GLOBALS['mudrava_test_shortcodes']);

        $html = call_user_func($GLOBALS['mudrava_test_shortcodes']['lucide_icon'], array('name' => 'rocket'));

        $this->assertStringContainsString('<svg', $html);
    }
}
