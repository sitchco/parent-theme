<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Parent\Modules\KadenceBlocks\KadenceBlocks;
use Sitchco\Tests\TestCase;

class KadenceBlocksTest extends TestCase
{
    private const TAB_HTML = '<div class="kt-tab-inner-content"><div class="kt-tab-inner-content-inner">content</div></div>';

    private const SPACING_INPUT = [
        'ss-auto' => 'auto',
        '0' => '0',
        'none' => '',
        'xxs' => '0.25rem',
        'xs' => '0.5rem',
        'sm' => '1rem',
        'md' => '2rem',
    ];

    protected KadenceBlocks $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = $this->container->get(KadenceBlocks::class);
    }

    // --- overrideSpacingSizes ---

    public function testOverrideSpacingSizesPreservesDefaultsAndDropsKadenceSlugs(): void
    {
        $result = $this->module->overrideSpacingSizes(self::SPACING_INPUT);
        $this->assertArrayHasKey('ss-auto', $result);
        $this->assertArrayHasKey('0', $result);
        $this->assertArrayHasKey('none', $result);
        $this->assertArrayNotHasKey('xxs', $result);
        $this->assertArrayNotHasKey('xs', $result);
        $this->assertArrayNotHasKey('sm', $result);
        $this->assertArrayNotHasKey('md', $result);
    }

    public function testOverrideSpacingSizesOutputKeysUseVarFormat(): void
    {
        $result = $this->module->overrideSpacingSizes(['xxs' => '0.25rem', 'ss-auto' => 'auto']);
        foreach ($result as $key => $value) {
            if (in_array($key, ['ss-auto', '0', 'none'])) {
                continue;
            }
            $this->assertStringStartsWith('spacing-', $key);
            $this->assertStringStartsWith('var(--wp--preset--spacing--', $value);
        }
    }

    // --- overrideGapSizes ---

    public function testOverrideGapSizesIncludesContentFlowToken(): void
    {
        $result = $this->module->overrideGapSizes([]);
        $this->assertArrayHasKey('content-flow', $result);
        $this->assertSame('var(--wp--custom--block-gap)', $result['content-flow']);
    }

    // --- overrideFontSizes ---

    public function testOverrideFontSizesDropsNonDefaultAndMapsThemePresets(): void
    {
        $result = $this->module->overrideFontSizes(['xxs' => '10px', 'ss-auto' => 'auto']);
        $this->assertArrayNotHasKey('xxs', $result);
        foreach ($result as $key => $value) {
            if (in_array($key, ['ss-auto', '0', 'none'])) {
                continue;
            }
            $this->assertStringStartsWith('font-size-', $key);
            $this->assertStringStartsWith('var(--wp--preset--font-size--', $value);
        }
    }

    // --- overrideConfigDefaults ---

    /**
     * @dataProvider configDefaultsProvider
     */
    public function testOverrideConfigDefaults(mixed $input, mixed $expected): void
    {
        $result = $this->module->overrideConfigDefaults($input);
        $this->assertSame($expected, $result);
    }

    public static function configDefaultsProvider(): array
    {
        return [
            'empty JSON string becomes valid config' => ['{}', '{"kadence\/tabs":{},"kadence\/accordion":{}}'],
            'existing config passed through' => ['{"kadence/tabs":{}}', '{"kadence/tabs":{}}'],
            'false passed through' => [false, false],
        ];
    }

    // --- injectDefaultColumnGap ---

    /**
     * @dataProvider columnGapProvider
     */
    public function testInjectDefaultColumnGap(array $input, string $expectedFirstValue): void
    {
        $result = $this->module->injectDefaultColumnGap($input);
        $this->assertSame($expectedFirstValue, $result['rowGapVariable'][0]);
    }

    public static function columnGapProvider(): array
    {
        return [
            'no rowGapVariable key' => [[], 'content-flow'],
            'empty first value' => [['rowGapVariable' => ['', '', '']], 'content-flow'],
            'null first value' => [['rowGapVariable' => [null, '', '']], 'content-flow'],
            'existing value preserved' => [['rowGapVariable' => ['custom-value', '', '']], 'custom-value'],
        ];
    }

    // --- enableCssVariablesForPadding ---

    /**
     * @dataProvider cssVariablesForPaddingProvider
     */
    public function testEnableCssVariablesForPadding(
        bool $useVariables,
        string $property,
        string $selector,
        bool $expected,
    ): void {
        $result = $this->module->enableCssVariablesForPadding($useVariables, $property, '', $selector, []);
        $this->assertSame($expected, $result);
    }

    public static function cssVariablesForPaddingProvider(): array
    {
        return [
            'padding + kadence-column' => [false, 'padding', '.kadence-column .inner', true],
            'padding + kb-row-layout' => [false, 'padding', '.kb-row-layout', true],
            'padding + wp-block-kadence-tab' => [false, 'padding', '.wp-block-kadence-tab', true],
            'padding + other selector' => [false, 'padding', '.some-other-class', false],
            'margin + kadence-column' => [false, 'margin', '.kadence-column', false],
            'padding + kadence-column with useVariables true' => [true, 'padding', '.kadence-column', true],
            'non-matching passes through useVariables' => [true, 'margin', '.some-class', true],
        ];
    }

    // --- addTabFullWidthContentClasses ---

    public function testAddTabFullWidthContentClassesAddsLayoutClass(): void
    {
        $block = ['attrs' => ['fullWidthContent' => true]];
        $result = $this->module->addTabFullWidthContentClasses(self::TAB_HTML, $block);
        $this->assertStringContainsString('is-layout-constrained', $result);
    }

    public function testAddTabFullWidthContentClassesSkipsWhenDisabled(): void
    {
        $block = ['attrs' => []];
        $result = $this->module->addTabFullWidthContentClasses(self::TAB_HTML, $block);
        $this->assertStringNotContainsString('is-layout-constrained', $result);
    }

    // --- handleRowCssMaxWidth ---

    /**
     * @dataProvider handleRowCssMaxWidthProvider
     */
    public function testHandleRowCssMaxWidth(bool $skip, string $innerContentWidth, bool $expected): void
    {
        $result = $this->module->handleRowCssMaxWidth(
            $skip,
            ['innerContentWidth' => $innerContentWidth],
            'uid',
            '.selector',
        );
        $this->assertSame($expected, $result);
    }

    public static function handleRowCssMaxWidthProvider(): array
    {
        return [
            'preset width skips' => [false, 'theme', true],
            'wide preset skips' => [false, 'wide', true],
            'custom does not skip' => [false, 'custom', false],
            'empty does not skip' => [false, '', false],
            'unknown preset does not skip' => [false, 'bogus', false],
            'passthrough preserves existing skip=true' => [true, '', true],
        ];
    }

    // --- getContentWidthPresets ---

    public function testGetContentWidthPresetsReturnsExpectedStructure(): void
    {
        $result = $this->module->getContentWidthPresets();
        $this->assertArrayHasKey('theme', $result);
        $this->assertArrayHasKey('wide', $result);
        $this->assertArrayHasKey('label', $result['theme']);
        $this->assertArrayHasKey('label', $result['wide']);
    }
}
