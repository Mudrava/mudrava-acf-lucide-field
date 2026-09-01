<?php
/**
 * Unit tests for the custom SVG icon library.
 */

use PHPUnit\Framework\TestCase;

class CustomIconsTest extends TestCase
{
    /**
     * Reset option storage between tests.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['mudrava_test_options'] = array();
        $GLOBALS['mudrava_test_can'] = true;

        $dir = mudrava_lucide_field_custom_icons_dir();

        if (is_dir($dir)) {
            foreach (glob($dir . '/*') as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * The sanitizer must keep allowlisted shapes and drop everything else.
     *
     * @return void
     */
    public function testSanitizerKeepsShapesAndDropsEverythingElse(): void
    {
        $raw = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48">'
            . '<g transform="translate(2 2)"><path d="M4 4h8" fill="#123456"/></g>'
            . '<script>alert(1)</script>'
            . '<style>svg{fill:red}</style>'
            . '<image href="https://evil.test/x.png" width="4" height="4"/>'
            . '<circle cx="4" cy="4" r="4" onload="alert(2)"/>'
            . '<text>leak</text>'
            . '</svg>';

        $clean = mudrava_lucide_field_sanitize_custom_svg($raw);

        $this->assertNotNull($clean);
        $this->assertStringContainsString('<g transform="translate(2 2)">', $clean['markup']);
        $this->assertStringContainsString('fill="#123456"', $clean['markup']);
        $this->assertStringContainsString('<circle', $clean['markup']);
        $this->assertStringContainsString('viewBox="0 0 48 48"', $clean['markup']);
        $this->assertStringNotContainsString('script', $clean['markup']);
        $this->assertStringNotContainsString('alert', $clean['markup']);
        $this->assertStringNotContainsString('style', $clean['markup']);
        $this->assertStringNotContainsString('image', $clean['markup']);
        $this->assertStringNotContainsString('onload', $clean['markup']);
        $this->assertStringNotContainsString('href', $clean['markup']);
        $this->assertStringNotContainsString('leak', $clean['markup']);
        $this->assertSame('0 0 48 48', $clean['viewBox']);
    }

    /**
     * A missing or malformed viewBox must not survive sanitization.
     *
     * @return void
     */
    public function testSanitizerFallsBackToDefaultsForViewbox(): void
    {
        $from_size = mudrava_lucide_field_sanitize_custom_svg('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"><path d="M0 0h8v8z"/></svg>');

        $this->assertNotNull($from_size);
        $this->assertSame('0 0 64 64', $from_size['viewBox']);

        $garbage = mudrava_lucide_field_sanitize_custom_svg('<svg xmlns="http://www.w3.org/2000/svg" viewBox="px or javascript"><path d="M0 0h8v8z"/></svg>');

        $this->assertNotNull($garbage);
        $this->assertSame('0 0 24 24', $garbage['viewBox']);
        $this->assertStringNotContainsString('javascript', $garbage['markup']);
    }

    /**
     * Non-SVG, non-well-formed and oversize payloads must be refused.
     *
     * @return void
     */
    public function testSanitizerRejectsBadPayloads(): void
    {
        $this->assertNull(mudrava_lucide_field_sanitize_custom_svg('<html><body>nope</body></html>'));
        $this->assertNull(mudrava_lucide_field_sanitize_custom_svg('<svg><unclosed>'));
        $this->assertNull(mudrava_lucide_field_sanitize_custom_svg('<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h8v8z"/>' . str_repeat('<!-- padding -->', 5000) . '</svg>'));
        $this->assertNull(mudrava_lucide_field_sanitize_custom_svg(''));
    }

    /**
     * Uploading stores files and the manifest; the picker payload follows.
     *
     * @return void
     */
    public function testUploadRoundTrip(): void
    {
        $raw = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><g><path d="M4 4h8" fill="#123456"/></g></svg>';

        $clean = mudrava_lucide_field_sanitize_custom_svg($raw);

        $this->assertNotNull($clean);

        $result = mudrava_lucide_field_store_custom_icon(
            'zeta-test',
            $clean['markup'],
            array(
                'title'    => 'Zeta Test',
                'keywords' => array('logo', 'zeta'),
            ),
            $clean['viewBox']
        );

        $this->assertTrue($result);

        $icons = mudrava_lucide_field_get_custom_icons();

        $this->assertArrayHasKey('zeta-test', $icons);
        $this->assertSame('Zeta Test', $icons['zeta-test']['title']);
        $this->assertSame(array('logo', 'zeta'), $icons['zeta-test']['keywords']);
        $this->assertSame('0 0 48 48', $icons['zeta-test']['viewBox']);

        $stored = file_get_contents(mudrava_lucide_field_custom_icons_dir() . '/zeta-test.svg');

        $this->assertIsString($stored);
        $this->assertStringStartsWith('<svg xmlns=', $stored);
        $this->assertStringNotContainsString('script', $stored);

        $this->assertTrue(mudrava_lucide_field_symbol_exists('custom', 'zeta-test'));
        $this->assertFalse(mudrava_lucide_field_symbol_exists('custom', 'missing-icon'));

        $symbol = mudrava_lucide_field_get_symbol('custom', 'zeta-test');

        $this->assertStringContainsString('<g>', $symbol);
        $this->assertStringContainsString('path', $symbol);
        $this->assertSame('0 0 48 48', mudrava_lucide_field_get_symbol_viewbox('custom', 'zeta-test'));
        $this->assertSame('0 0 24 24', mudrava_lucide_field_get_symbol_viewbox('custom', 'missing-icon'));
        $this->assertSame('', mudrava_lucide_field_get_symbol('custom', 'missing-icon'));
    }

    /**
     * Values and resolution must understand the custom namespace.
     *
     * @return void
     */
    public function testValueResolution(): void
    {
        $this->assertSame('custom:zeta-test', mudrava_lucide_field_sanitize_icon_value('custom:zeta-test'));

        $this->assertSame('', mudrava_lucide_field_resolve_icon('custom:missing-icon')['source']);

        $raw = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><path d="M4 4h8"/></svg>';
        $clean = mudrava_lucide_field_sanitize_custom_svg($raw);

        $this->assertNotNull($clean);
        $this->assertTrue(mudrava_lucide_field_store_custom_icon('omega-test', $clean['markup'], array('title' => 'Omega Test', 'keywords' => array()), $clean['viewBox']));

        $resolved = mudrava_lucide_field_resolve_icon('custom:omega-test');

        $this->assertSame('custom', $resolved['source']);
        $this->assertSame('omega-test', $resolved['name']);

        $auto = mudrava_lucide_field_resolve_icon('omega-test');

        $this->assertSame('custom', $auto['source']);

        $svg = mudrava_get_lucide_icon('custom:omega-test');

        $this->assertStringContainsString('mudrava-lucide-icon-svg--custom', $svg);
        $this->assertStringContainsString('viewBox="0 0 48 48"', $svg);
        $this->assertStringNotContainsString('stroke-width', $svg);
        $this->assertStringNotContainsString('fill="', $svg);
        $this->assertStringContainsString('<path d="M4 4h8"', $svg);

        $sprite = mudrava_get_lucide_icon('custom:omega-test', array('mode' => 'sprite'));

        $this->assertStringContainsString('href="#custom-omega-test"', $sprite);
        $this->assertStringNotContainsString('fill="', $sprite);

        $this->assertSame('', mudrava_get_lucide_icon('custom:missing-icon'));

        $ghost = mudrava_lucide_field_resolve_icon('ghost-icon');

        $this->assertSame('', $ghost['source']);
    }

    /**
     * Missing files must be purged from the manifest on admin render.
     *
     * @return void
     */
    public function testPruneRemovesMissingFiles(): void
    {
        $GLOBALS['mudrava_test_options'][MUDRAVA_LUCIDE_FIELD_CUSTOM_OPTION] = array(
            'gone-icon' => array(
                'title'    => 'Gone',
                'keywords' => array(),
                'viewBox'  => '0 0 24 24',
            ),
        );

        $icons = mudrava_lucide_field_get_custom_icons();

        $this->assertArrayHasKey('gone-icon', $icons);

        mudrava_lucide_field_maybe_prune_custom_icons();

        $icons = mudrava_lucide_field_get_custom_icons();

        $this->assertArrayNotHasKey('gone-icon', $icons);
    }
}
