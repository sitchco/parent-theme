<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Parent\Modules\ExtendBlock\ExtendBlockModule;
use Sitchco\Tests\TestCase;
use Sitchco\Utils\Logger;
use Sitchco\Utils\LogLevel;

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

    public function testClassesSkipKadencesLeadingInlineStyle(): void
    {
        $html = '<style>.kb-row-layout-id1{}</style><div class="kb-row-layout-wrap">content</div>';
        $block = [
            'attrs' => ['extendBlockClasses' => ['ns1' => 'added']],
            'blockName' => 'kadence/rowlayout',
        ];
        $this->assertSame(
            '<style>.kb-row-layout-id1{}</style><div class="kb-row-layout-wrap added">content</div>',
            $this->module->injectExtendBlockClasses($html, $block),
        );
    }

    public function testContentOpeningWithTextGetsNoClasses(): void
    {
        $html = 'text first <div class="wp-block-group">content</div>';
        $block = [
            'attrs' => ['extendBlockClasses' => ['ns1' => 'added']],
            'blockName' => 'core/group',
        ];
        $this->assertSame($html, $this->module->injectExtendBlockClasses($html, $block));
    }

    /**
     * Run injectWrapperProps() with $props contributed through the filter, and the Logger
     * threshold lowered so dropped-name warnings can be asserted.
     *
     * @return array{0: string, 1: ?array} The rendered HTML and the last log entry
     */
    private function renderWithProps(string $html, array|callable $props, string $blockName = 'core/group'): array
    {
        $hookName = ExtendBlockModule::hookName('wrapper-props');
        $callback = is_callable($props) ? $props : fn(array $current) => array_replace_recursive($current, $props);
        add_filter($hookName, $callback, 10, 2);

        $resolved = new \ReflectionProperty(Logger::class, 'resolvedLevel');
        $lastEntry = new \ReflectionProperty(Logger::class, 'lastEntry');
        $previous = $resolved->getValue();
        $resolved->setValue(null, LogLevel::WARNING);
        $lastEntry->setValue(null, null);
        Logger::silenceErrorLog(true);
        try {
            $result = $this->module->injectWrapperProps($html, ['blockName' => $blockName, 'attrs' => []]);
        } finally {
            Logger::silenceErrorLog(false);
            $resolved->setValue(null, $previous);
            remove_filter($hookName, $callback, 10);
        }

        return [$result, Logger::getLastEntry()];
    }

    public function testWrapperPropsLeaveContentUntouchedWhenNothingIsContributed(): void
    {
        $html = "<div class=\"wp-block-group\" style=\"color: red\">content</div>\n";
        [$result] = $this->renderWithProps($html, []);
        $this->assertSame($html, $result);
    }

    public function testWrapperPropsTreatNullAndEmptyAsUnset(): void
    {
        $html = '<div class="wp-block-group">content</div>';
        [$result] = $this->renderWithProps($html, [
            'attributes' => ['data-animation' => null, 'data-empty' => ''],
            'style' => ['--x' => null, '--y' => '', '--z' => false],
        ]);
        $this->assertSame($html, $result);
    }

    public function testWrapperPropsWriteAttributesAndStyle(): void
    {
        [$result] = $this->renderWithProps('<div class="wp-block-group">content</div>', [
            'attributes' => ['data-animation' => 'letter', 'data-count' => 3, 'aria-hidden' => true],
            'style' => ['--letter-animation-color' => 'var(--wp--preset--color--purple)'],
        ]);
        $this->assertSame(
            '<div aria-hidden="true" data-animation="letter" data-count="3" style="--letter-animation-color:var(--wp--preset--color--purple);" class="wp-block-group">content</div>',
            $result,
        );
    }

    /**
     * @dataProvider existingStyleProvider
     */
    public function testWrapperPropsAppendToAnExistingStyle(string $existing, string $expected): void
    {
        [$result] = $this->renderWithProps("<div style=\"{$existing}\">content</div>", [
            'style' => ['--a' => '1', '--b' => '2'],
        ]);
        $this->assertSame("<div style=\"{$expected}\">content</div>", $result);
    }

    public static function existingStyleProvider(): array
    {
        return [
            'no trailing semicolon' => ['color:red', 'color:red;--a:1;--b:2;'],
            'trailing semicolon' => ['color:red;', 'color:red;--a:1;--b:2;'],
            'padded' => [' color: red ; ', 'color: red;--a:1;--b:2;'],
            'empty' => ['', '--a:1;--b:2;'],
        ];
    }

    public function testWrapperPropsPreserveLeadingComments(): void
    {
        [$result] = $this->renderWithProps("<!-- note -->\n<div>content</div>", [
            'attributes' => ['data-animation' => 'letter'],
        ]);
        $this->assertSame("<!-- note -->\n<div data-animation=\"letter\">content</div>", $result);
    }

    /**
     * @dataProvider leadingRawTextProvider
     */
    public function testWrapperPropsSkipALeadingStyleOrScript(string $leading): void
    {
        [$result] = $this->renderWithProps("{$leading}<div class=\"kb-row-layout-wrap\">content</div>", [
            'attributes' => ['data-animation' => 'letter'],
        ]);
        $this->assertSame(
            "{$leading}<div data-animation=\"letter\" class=\"kb-row-layout-wrap\">content</div>",
            $result,
        );
    }

    public static function leadingRawTextProvider(): array
    {
        return [
            'kadence inline style' => ['<style>.kb-row-layout-id1{}</style>'],
            'script' => ['<script>var x = "<div>";</script>'],
            'style after a comment' => ["<!-- note -->\n<style>.a{}</style>\n"],
        ];
    }

    public function testWrapperPropsSkipContentWithNoWrapper(): void
    {
        $html = 'just text';
        [$result] = $this->renderWithProps($html, ['attributes' => ['data-animation' => 'letter']]);
        $this->assertSame($html, $result);
    }

    public function testWrapperPropsEscapeAttributeValues(): void
    {
        [$result] = $this->renderWithProps('<div>content</div>', [
            'attributes' => ['data-x' => '" onclick="alert(1)'],
        ]);
        $this->assertStringNotContainsString('onclick="alert', $result);
        $this->assertStringContainsString('&quot;', $result);
    }

    /**
     * @dataProvider droppedPropProvider
     */
    public function testWrapperPropsDropWhatMustNotBeEmitted(array $props, string $warning): void
    {
        $html = '<div>content</div>';
        [$result, $entry] = $this->renderWithProps($html, $props);
        $this->assertSame($html, $result);
        $this->assertSame(LogLevel::WARNING, $entry['level'] ?? null);
        $this->assertStringContainsString($warning, $entry['value']);
    }

    public static function droppedPropProvider(): array
    {
        return [
            'event handler' => [['attributes' => ['onclick' => 'alert(1)']], "'onclick' attribute"],
            'class' => [['attributes' => ['class' => 'x']], "'class' attribute"],
            'plain style property' => [['style' => ['color' => 'red']], "'color' style"],
            'declaration break' => [['style' => ['--x' => 'red; background: url(x)']], "'--x' style"],
            'brace' => [['style' => ['--y' => 'a}b']], "'--y' style"],
            'angle bracket' => [['style' => ['--z' => '</style>']], "'--z' style"],
            'backslash' => [['style' => ['--w' => '\\66']], "'--w' style"],
        ];
    }

    public function testWrapperPropsFromTwoContributorsBothLand(): void
    {
        $hookName = ExtendBlockModule::hookName('wrapper-props');
        $first = function (array $props) {
            $props['attributes']['data-first'] = 'a';
            $props['style']['--first'] = '1';
            return $props;
        };
        add_filter($hookName, $first);
        try {
            [$result] = $this->renderWithProps('<div>content</div>', function (array $props) {
                $props['attributes']['data-second'] = 'b';
                $props['style']['--second'] = '2';
                return $props;
            });
        } finally {
            remove_filter($hookName, $first);
        }

        $this->assertSame('<div data-first="a" data-second="b" style="--first:1;--second:2;">content</div>', $result);
    }

    public function testWrapperPropsSeeTheBlock(): void
    {
        $seen = null;
        $this->renderWithProps(
            '<div>content</div>',
            function (array $props, array $block) use (&$seen) {
                $seen = $block['blockName'];
                return $props;
            },
            'kadence/rowlayout',
        );
        $this->assertSame('kadence/rowlayout', $seen);
    }
}
