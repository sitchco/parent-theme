<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Modules\UIModal\ModalData;
use Sitchco\Parent\Modules\Theme\Theme;
use Sitchco\Tests\TestCase;

class ThemeModuleTest extends TestCase
{
    protected Theme $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = $this->container->get(Theme::class);
    }

    // --- addButtonThemeAttribute ---

    public function testAddButtonThemeAttributeAddsThemeToButton(): void
    {
        $args = ['attributes' => []];
        $result = $this->module->addButtonThemeAttribute($args, 'core/button');
        $this->assertArrayHasKey('theme', $result['attributes']);
        $this->assertSame('string', $result['attributes']['theme']['type']);
        $this->assertSame('', $result['attributes']['theme']['default']);
    }

    public function testAddButtonThemeAttributeIgnoresOtherBlocks(): void
    {
        $args = ['attributes' => ['existing' => true]];
        $result = $this->module->addButtonThemeAttribute($args, 'core/paragraph');
        $this->assertArrayNotHasKey('theme', $result['attributes']);
        $this->assertSame(['existing' => true], $result['attributes']);
    }

    public function testAddButtonThemeAttributePreservesExistingAttributes(): void
    {
        $args = ['attributes' => ['className' => ['type' => 'string']]];
        $result = $this->module->addButtonThemeAttribute($args, 'core/button');
        $this->assertArrayHasKey('className', $result['attributes']);
        $this->assertArrayHasKey('theme', $result['attributes']);
    }

    // --- contentFilterWarning ---

    public function testContentFilterWarningReturnsContentWhenHeadFired(): void
    {
        do_action('wp_head');
        $content = '<p>Hello world</p>';
        $result = $this->module->contentFilterWarning($content);
        $this->assertSame($content, $result);
    }

    public function testContentFilterWarningLogsAndInjectsActionWhenCalledTooEarly(): void
    {
        // Ensure none of the bypass conditions are met
        remove_all_actions('wp_body_open');
        $this->assertFalse(has_action('wp_body_open'), 'Precondition: no wp_body_open actions');
        $GLOBALS['wp_actions']['wp_head'] = 0;
        set_current_screen('front');

        $content = '<p>Early content</p>';
        $result = $this->module->contentFilterWarning($content);

        $this->assertSame($content, $result, 'Content should still be returned unchanged');
        $this->assertNotFalse(has_action('wp_body_open'), 'Expected wp_body_open action to be registered');
    }

    // --- modalContentAttributes ---

    public function testModalContentAttributesSkipsVideoType(): void
    {
        $modalData = new ModalData('test-video', 'Video Modal', '<p>video</p>', 'video');
        $attrs = ['class' => ['existing-class']];
        $result = $this->module->modalContentAttributes($attrs, $modalData);
        $this->assertSame($attrs, $result);
    }

    public function testModalContentAttributesAddsLayoutClasses(): void
    {
        $modalData = new ModalData('test-modal', 'Test Modal', '<p>content</p>', 'full');
        $attrs = ['class' => ['existing-class']];
        $result = $this->module->modalContentAttributes($attrs, $modalData);
        $this->assertContains('existing-class', $result['class']);
        $this->assertContains('is-layout-constrained', $result['class']);
        $this->assertContains('has-global-padding', $result['class']);
    }

    public function testModalContentAttributesHandlesMissingClassKey(): void
    {
        $modalData = new ModalData('test-modal', 'Test Modal', '<p>content</p>', 'full');
        $attrs = [];
        $result = $this->module->modalContentAttributes($attrs, $modalData);
        $this->assertContains('is-layout-constrained', $result['class']);
        $this->assertContains('has-global-padding', $result['class']);
    }

    public function testModalContentAttributesHandlesStringClass(): void
    {
        $modalData = new ModalData('test-modal', 'Test Modal', '<p>content</p>', 'full');
        $attrs = ['class' => 'single-class'];
        $result = $this->module->modalContentAttributes($attrs, $modalData);
        $this->assertContains('single-class', $result['class']);
        $this->assertContains('is-layout-constrained', $result['class']);
    }

    public function testModalContentAttributesPreservesOtherAttrs(): void
    {
        $modalData = new ModalData('test-modal', 'Test Modal', '<p>content</p>', 'content');
        $attrs = ['id' => 'custom-id', 'class' => []];
        $result = $this->module->modalContentAttributes($attrs, $modalData);
        $this->assertSame('custom-id', $result['id']);
    }

    public static function nonVideoTypeProvider(): array
    {
        return [
            'full' => ['full'],
            'content' => ['content'],
            'image' => ['image'],
            'custom' => ['custom-type'],
        ];
    }

    /**
     * @dataProvider nonVideoTypeProvider
     */
    public function testModalContentAttributesAddsClassesForNonVideoTypes(string $type): void
    {
        $modalData = new ModalData('test-modal', 'Test Modal', '<p>content</p>', $type);
        $result = $this->module->modalContentAttributes([], $modalData);
        $this->assertContains('is-layout-constrained', $result['class']);
        $this->assertContains('has-global-padding', $result['class']);
    }
}
