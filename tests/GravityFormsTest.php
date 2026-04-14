<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Parent\Modules\GravityForms\GravityForms;
use Sitchco\Tests\TestCase;

class GravityFormsTest extends TestCase
{
    protected GravityForms $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = $this->container->get(GravityForms::class);
    }

    // --- hideBlockStyleControls ---

    public function testHideBlockStyleControlsSetsOrbitalDefaultToFalse(): void
    {
        $config = [
            'block_editor' => [
                'gravityforms/form' => [
                    'data' => ['orbitalDefault' => true, 'other' => 'value'],
                ],
            ],
        ];
        $result = $this->module->hideBlockStyleControls($config);
        $this->assertFalse($result['block_editor']['gravityforms/form']['data']['orbitalDefault']);
        $this->assertSame('value', $result['block_editor']['gravityforms/form']['data']['other']);
    }

    public function testHideBlockStyleControlsPassesThroughConfigWithoutGfBlock(): void
    {
        $config = ['block_editor' => ['core/paragraph' => ['data' => ['foo' => 'bar']]]];
        $this->assertSame($config, $this->module->hideBlockStyleControls($config));
    }

    // --- bridgeInlineStyles ---

    /**
     * @dataProvider bridgeInlineStylesProvider
     */
    public function testBridgeInlineStyles(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->module->bridgeInlineStyles($input));
    }

    public static function bridgeInlineStylesProvider(): array
    {
        return [
            'color primary bridged' => [
                '--gf-color-primary: #204ce5;',
                '--gf-color-primary: var(--wp--custom--gf--color-primary, #204ce5);',
            ],
            'border color bridged' => [
                '--gf-ctrl-border-color: #ccc;',
                '--gf-ctrl-border-color: var(--wp--custom--gf--ctrl-border-color, #ccc);',
            ],
            'icon property skipped' => [
                '--gf-icon-chevron: url(data:image/svg+xml;base64,abc);',
                '--gf-icon-chevron: url(data:image/svg+xml;base64,abc);',
            ],
            'no gf vars unchanged' => ['--color-primary: blue;', '--color-primary: blue;'],
            'multiple vars all bridged' => [
                '--gf-color-primary: #204ce5; --gf-color-secondary: #333;',
                '--gf-color-primary: var(--wp--custom--gf--color-primary, #204ce5); --gf-color-secondary: var(--wp--custom--gf--color-secondary, #333);',
            ],
        ];
    }

    // --- replaceSubmitButton ---

    public function testReplaceSubmitButtonConvertsInputToButton(): void
    {
        $input = '<input type="submit" id="gform_submit_button_1" value="Send Message" />';
        $form = ['id' => 1];
        $result = $this->module->replaceSubmitButton($input, $form);
        $this->assertStringContainsString('<button type="submit"', $result);
        $this->assertStringContainsString('wp-block-button__link', $result);
        $this->assertStringContainsString('Send Message', $result);
        $this->assertStringContainsString('id="gform_submit_button_1"', $result);
        $this->assertStringContainsString('<div class=', $result);
    }

    public function testReplaceSubmitButtonPreservesDataAttributes(): void
    {
        $input = '<input type="submit" value="Submit" data-loading="Sending..." tabindex="5" />';
        $form = ['id' => 1];
        $result = $this->module->replaceSubmitButton($input, $form);
        $this->assertStringContainsString('data-loading="Sending..."', $result);
        $this->assertStringContainsString('tabindex="5"', $result);
    }

    public function testReplaceSubmitButtonHandlesButtonElement(): void
    {
        $input =
            '<button type="submit" id="gform_submit_button_1" class="gform_button"><svg><path d="M0 0"/></svg>Submit Now</button>';
        $form = ['id' => 1];
        $result = $this->module->replaceSubmitButton($input, $form);
        $this->assertStringContainsString('<button type="submit"', $result);
        $this->assertStringContainsString('wp-block-button__link', $result);
        $this->assertStringContainsString('Submit Now', $result);
        // SVG should be stripped from the button text
        $this->assertStringNotContainsString('<svg', $result);
    }

    // --- registerBlockAttributes ---

    public function testRegisterBlockAttributesAddsCustomAttrsForGravityFormsBlock(): void
    {
        $args = ['attributes' => []];
        $result = $this->module->registerBlockAttributes($args, 'gravityforms/form');
        $this->assertArrayHasKey('theme', $result['attributes']);
        $this->assertArrayHasKey('icon', $result['attributes']);
        $this->assertArrayHasKey('extendBlockClasses', $result['attributes']);
    }

    public function testRegisterBlockAttributesPassesThroughOtherBlocks(): void
    {
        $args = ['attributes' => ['existing' => true]];
        $result = $this->module->registerBlockAttributes($args, 'core/paragraph');
        $this->assertSame($args, $result);
    }

    // --- prepareButtonThemeClass + applyButtonThemeClass ---

    public function testPrepareAndApplyButtonThemeClassWithThemeAndIcon(): void
    {
        $block = [
            'blockName' => 'gravityforms/form',
            'attrs' => ['theme' => 'arrow', 'icon' => 'chevron'],
        ];
        $this->module->prepareButtonThemeClass($block);
        $result = $this->module->applyButtonThemeClass(['wp-block-button']);

        $this->assertContains('has-theme-arrow', $result);
        $this->assertContains('has-icon', $result);
        $this->assertContains('has-icon-chevron', $result);
        $this->assertContains('wp-block-button', $result);
    }

    public function testPrepareAndApplyButtonThemeClassClearsStateAfterApply(): void
    {
        $block = [
            'blockName' => 'gravityforms/form',
            'attrs' => ['theme' => 'arrow'],
        ];
        $this->module->prepareButtonThemeClass($block);
        $this->module->applyButtonThemeClass([]);

        // Second apply without prepare should return only the existing classes
        $result = $this->module->applyButtonThemeClass(['wp-block-button']);
        $this->assertSame(['wp-block-button'], $result);
    }

    public function testPrepareButtonThemeClassSkipsNonGravityFormsBlocks(): void
    {
        $block = [
            'blockName' => 'core/paragraph',
            'attrs' => ['theme' => 'arrow'],
        ];
        $this->module->prepareButtonThemeClass($block);
        $result = $this->module->applyButtonThemeClass(['wp-block-button']);
        $this->assertSame(['wp-block-button'], $result);
    }

    public function testPrepareButtonThemeClassWithNoThemeOrIcon(): void
    {
        $block = [
            'blockName' => 'gravityforms/form',
            'attrs' => [],
        ];
        $this->module->prepareButtonThemeClass($block);
        $result = $this->module->applyButtonThemeClass(['wp-block-button']);
        $this->assertSame(['wp-block-button'], $result);
    }

    // --- replacePaginationButton ---

    public function testReplacePaginationButtonConvertsInputToButton(): void
    {
        $input =
            '<input type="button" value="Next" class="gform_next_button" id="gform_next_button_1_2" onclick="jQuery(this).closest(\'form\').submit();" />';
        $form = ['id' => 1];
        $result = $this->module->replacePaginationButton($input, $form);
        $this->assertStringContainsString('<button type="button"', $result);
        $this->assertStringContainsString('>Next</button>', $result);
        $this->assertStringContainsString('gform_page_button_wrapper', $result);
        $this->assertStringContainsString('class="gform_next_button"', $result);
        $this->assertStringContainsString('onclick=', $result);
    }

    public function testReplacePaginationButtonPassesThroughExistingButton(): void
    {
        $html = '<button class="gform_next_button" type="button">Next</button>';
        $form = ['id' => 1];
        $this->assertSame($html, $this->module->replacePaginationButton($html, $form));
    }
}
