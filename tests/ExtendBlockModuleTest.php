<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Parent\Modules\ExtendBlock\ExtendBlockModule;
use Sitchco\Tests\TestCase;

class ExtendBlockModuleTest extends TestCase
{
    protected ExtendBlockModule $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = $this->container->get(ExtendBlockModule::class);
    }

    /**
     * @dataProvider earlyReturnProvider
     */
    public function testReturnsUnchangedWhenNoMeaningfulClasses(array $attrs): void
    {
        $html = '<div class="wp-block-group">content</div>';
        $block = ['attrs' => $attrs, 'blockName' => 'core/group'];
        $this->assertSame($html, $this->module->injectExtendBlockClasses($html, $block));
    }

    public static function earlyReturnProvider(): array
    {
        return [
            'no attrs' => [[]],
            'empty array' => [['extendBlockClasses' => []]],
            'whitespace only' => [['extendBlockClasses' => ['ns1' => '   ']]],
            'empty string legacy' => [['extendBlockClasses' => '']],
        ];
    }

    public function testInjectsClassesFromObjectFormatIntoExistingClassAttribute(): void
    {
        $html = '<div class="wp-block-group">content</div>';
        $block = [
            'attrs' => ['extendBlockClasses' => ['ns1' => 'custom-class']],
            'blockName' => 'core/group',
        ];
        $result = $this->module->injectExtendBlockClasses($html, $block);
        $this->assertSame('<div class="wp-block-group custom-class">content</div>', $result);
    }

    public function testInjectsClassesFromMultipleNamespaces(): void
    {
        $html = '<div class="wp-block-group">content</div>';
        $block = [
            'attrs' => ['extendBlockClasses' => ['ns1' => 'class-a', 'ns2' => 'class-b']],
            'blockName' => 'core/group',
        ];
        $result = $this->module->injectExtendBlockClasses($html, $block);
        $this->assertSame('<div class="wp-block-group class-a class-b">content</div>', $result);
    }

    public function testMixedEmptyAndNonEmptyNamespaces(): void
    {
        $html = '<div class="wp-block-group">content</div>';
        $block = [
            'attrs' => ['extendBlockClasses' => ['ns1' => '', 'ns2' => 'real-class']],
            'blockName' => 'core/group',
        ];
        $result = $this->module->injectExtendBlockClasses($html, $block);
        $this->assertSame('<div class="wp-block-group real-class">content</div>', $result);
    }

    public function testCreatesClassAttributeWhenMissing(): void
    {
        $html = '<div>content</div>';
        $block = [
            'attrs' => ['extendBlockClasses' => ['ns1' => 'injected-class']],
            'blockName' => 'core/group',
        ];
        $result = $this->module->injectExtendBlockClasses($html, $block);
        $this->assertStringContainsString('class="injected-class"', $result);
    }

    public function testInjectsClassesFromLegacyStringFormat(): void
    {
        $html = '<div class="wp-block-group">content</div>';
        $block = [
            'attrs' => ['extendBlockClasses' => 'legacy-class'],
            'blockName' => 'core/group',
        ];
        $result = $this->module->injectExtendBlockClasses($html, $block);
        $this->assertSame('<div class="wp-block-group legacy-class">content</div>', $result);
    }

    public function testFilterCanExcludeNamespace(): void
    {
        $hookName = ExtendBlockModule::hookName('inject-classes');
        $callback = function (array $classes) {
            unset($classes['excluded-ns']);
            return $classes;
        };
        add_filter($hookName, $callback);

        $html = '<div class="wp-block-group">content</div>';
        $block = [
            'attrs' => ['extendBlockClasses' => ['kept-ns' => 'keep-me', 'excluded-ns' => 'remove-me']],
            'blockName' => 'core/group',
        ];
        $result = $this->module->injectExtendBlockClasses($html, $block);

        $this->assertSame('<div class="wp-block-group keep-me">content</div>', $result);

        remove_filter($hookName, $callback);
    }

    public function testClassesAreSanitizedViaEscAttr(): void
    {
        $html = '<div class="wp-block-group">content</div>';
        $block = [
            'attrs' => ['extendBlockClasses' => ['ns1' => '" onclick="alert(1)']],
            'blockName' => 'core/group',
        ];
        $result = $this->module->injectExtendBlockClasses($html, $block);
        // esc_attr converts quotes to &quot;, preventing attribute injection
        $this->assertStringContainsString('&quot;', $result);
        $this->assertStringNotContainsString('onclick="alert', $result);
    }

    public function testHtmlCommentsBeforeElementArePreserved(): void
    {
        $html = '<!-- wp:group --><div class="wp-block-group">content</div>';
        $block = [
            'attrs' => ['extendBlockClasses' => ['ns1' => 'added']],
            'blockName' => 'core/group',
        ];
        $result = $this->module->injectExtendBlockClasses($html, $block);
        $this->assertSame('<!-- wp:group --><div class="wp-block-group added">content</div>', $result);
    }
}
