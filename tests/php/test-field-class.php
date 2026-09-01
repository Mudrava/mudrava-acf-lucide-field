<?php
/**
 * Unit tests for the ACF field class.
 */

use PHPUnit\Framework\TestCase;

class FieldClassTest extends TestCase
{
    /**
     * Field instance.
     *
     * @var Mudrava_ACF_Field_Lucide_Icon
     */
    private $field;

    protected function setUp(): void
    {
        parent::setUp();

        $this->field = new Mudrava_ACF_Field_Lucide_Icon();

        $GLOBALS['mudrava_test_transients'] = array();
        $GLOBALS['mudrava_test_field_settings'] = array();
        $GLOBALS['mudrava_test_enqueued'] = array();
    }

    private function make_field(array $overrides = array()): array
    {
        return array_merge(
            array(
                'key' => 'field_test',
                'id' => 'field_test',
                'name' => 'icon',
                'label' => 'Icon',
                'type' => 'lucide_icon',
                'value' => '',
                'allow_null' => 0,
                'on_unknown' => 'warn',
                'default_value' => '',
                'return_format' => 'name',
                'placeholder' => '',
            ),
            $overrides
        );
    }

    public function testInitializeSetsRegistrationData(): void
    {
        $this->assertSame('lucide_icon', $this->field->name);
        $this->assertSame('choice', $this->field->category);
        $this->assertSame('warn', $this->field->defaults['on_unknown']);
    }

    public function testLoadValueAppliesDefaultWhenEmpty(): void
    {
        $field = $this->make_field(array('default_value' => 'Rocket'));

        $this->assertSame('', $this->field->load_value('', 1, $field));
        $this->assertSame('rocket', $this->field->load_value(null, 1, $field));
        $this->assertSame('simple:facebook', $this->field->load_value('simple:facebook', 1, $field));
    }

    public function testLoadValueKeepsEmptyWithoutDefault(): void
    {
        $field = $this->make_field();

        $this->assertSame('', $this->field->load_value('', 1, $field));
        $this->assertNull($this->field->load_value(null, 1, $field));
    }

    public function testUpdateValueSanitizes(): void
    {
        $field = $this->make_field();

        $this->assertSame('rocket', $this->field->update_value('Rocket', 1, $field));
        $this->assertSame('not-an-icon', $this->field->update_value('not an icon!!', 1, $field));
        $this->assertSame('', $this->field->update_value('', 1, $field));
        $this->assertSame('', $this->field->update_value('<script>alert(1)</script>', 1, $field));
    }

    public function testFormatValueNameMode(): void
    {
        $field = $this->make_field(array('return_format' => 'name'));

        $this->assertSame('rocket', $this->field->format_value('rocket', 1, $field));
        $this->assertSame('', $this->field->format_value('', 1, $field));
    }

    public function testFormatValueSvgMode(): void
    {
        $field = $this->make_field(array('return_format' => 'svg'));

        $value = $this->field->format_value('rocket', 1, $field);

        $this->assertStringStartsWith('<svg', $value);
        $this->assertStringNotContainsString('<symbol', $value);
    }

    public function testValidateRejectsEmptyWhenNotAllowed(): void
    {
        $field = $this->make_field(array('allow_null' => 0));

        $result = $this->field->validate_value(true, '', $field, 'input');

        $this->assertIsString($result);
    }

    public function testValidateAcceptsEmptyWhenAllowed(): void
    {
        $field = $this->make_field(array('allow_null' => 1));

        $this->assertTrue($this->field->validate_value(true, '', $field, 'input'));
    }

    public function testValidateWarnsOnUnknownByDefault(): void
    {
        $field = $this->make_field();

        $result = $this->field->validate_value(true, 'totally-unknown-icon', $field, 'input');

        $this->assertTrue($result);

        $unknown = get_transient(Mudrava_ACF_Field_Lucide_Icon::UNKNOWN_TRANSIENT);

        $this->assertIsArray($unknown);
        $this->assertContains('totally-unknown-icon', $unknown);
    }

    public function testValidateErrorsOnUnknownInStrictMode(): void
    {
        $field = $this->make_field(array('on_unknown' => 'error'));

        $result = $this->field->validate_value(true, 'totally-unknown-icon', $field, 'input');

        $this->assertIsString($result);
        $this->assertFalse(get_transient(Mudrava_ACF_Field_Lucide_Icon::UNKNOWN_TRANSIENT));
    }

    public function testValidateAcceptsKnownAndLegacyAliasedValues(): void
    {
        $field = $this->make_field();

        $this->assertTrue($this->field->validate_value(true, 'rocket', $field, 'input'));
        $this->assertTrue($this->field->validate_value(true, 'simple:facebook', $field, 'input'));
        $this->assertTrue($this->field->validate_value(true, 'smile', $field, 'input'));
    }

    public function testFieldGroupDefaultValidationCollectsInvalid(): void
    {
        $field_group = array(
            'fields' => array(
                array(
                    'type' => 'lucide_icon',
                    'name' => 'icon',
                    'label' => 'Icon',
                    'default_value' => 'not-an-icon',
                ),
                array(
                    'type' => 'lucide_icon',
                    'name' => 'other',
                    'label' => 'Other',
                    'default_value' => 'rocket',
                ),
            ),
        );

        mudrava_lucide_field_validate_field_group_defaults($field_group);

        $invalid = get_transient('mudrava_lucide_invalid_defaults');

        $this->assertSame(array('Icon'), $invalid);
    }

    public function testEnqueueGuardRunsOnceAndConfigIsJsonEncoded(): void
    {
        $this->field->input_admin_enqueue_scripts();
        $this->field->input_admin_enqueue_scripts();

        $this->assertCount(1, $GLOBALS['mudrava_test_enqueued']['script']);

        $inline = $GLOBALS['mudrava_test_enqueued']['inline'][0][1];

        $this->assertStringStartsWith('var mudravaLucideField = ', $inline);

        $payload = json_decode(substr($inline, strlen('var mudravaLucideField = '), -1), true);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('dataUrl', $payload);
        $this->assertArrayHasKey('nonce', $payload);
        $this->assertStringContainsString('wp-json', $payload['dataUrl']);
    }

    public function testRenderFieldEscapesOutput(): void
    {
        $field = $this->make_field(
            array(
                'value' => '"><script>alert(1)</script>',
                'default_value' => 'rocket',
            )
        );

        ob_start();
        $this->field->render_field($field);
        $html = (string) ob_get_clean();

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('name="icon"', $html);
        $this->assertStringContainsString('role="combobox"', $html);
    }

    public function testFieldGroupNoticeEscapesValues(): void
    {
        $GLOBALS['mudrava_test_can'] = true;

        set_transient(
            Mudrava_ACF_Field_Lucide_Icon::UNKNOWN_TRANSIENT,
            array('"><script>alert(1)</script>')
        );

        ob_start();
        mudrava_lucide_field_invalid_defaults_notice();
        $html = (string) ob_get_clean();

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('&quot;&gt;', $html);
        $this->assertFalse(get_transient(Mudrava_ACF_Field_Lucide_Icon::UNKNOWN_TRANSIENT));
        unset($GLOBALS['mudrava_test_can']);
    }

    public function testNoticeSkippedWithoutCapability(): void
    {
        $GLOBALS['mudrava_test_can'] = false;
        set_transient(
            Mudrava_ACF_Field_Lucide_Icon::UNKNOWN_TRANSIENT,
            array('gone-forever')
        );

        ob_start();
        mudrava_lucide_field_invalid_defaults_notice();
        $html = (string) ob_get_clean();

        $this->assertSame('', $html);
        $this->assertNotFalse(get_transient(Mudrava_ACF_Field_Lucide_Icon::UNKNOWN_TRANSIENT));
        unset($GLOBALS['mudrava_test_can']);
    }
}
