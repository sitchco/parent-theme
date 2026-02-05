<?php

namespace Sitchco\Parent\Modules\KadenceBlocks;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;
use Sitchco\Modules\SvgSprite\SvgSprite;
use Sitchco\Parent\Modules\ExtendBlock\ExtendBlockModule;

/**
 * Integration layer for Kadence Blocks plugin.
 *
 * Makes Kadence Blocks work correctly with theme.json as source of truth
 * by providing CSS variable aliases and overriding preset sizes.
 */
class KadenceBlocks extends Module
{
    public const DEPENDENCIES = [ExtendBlockModule::class, SvgSprite::class];

    public function __construct(protected SvgSprite $svgSprite) {}

    public function init(): void
    {
        $this->enqueueGlobalAssets(function (ModuleAssets $assets) {
            $assets->enqueueStyle('kadence-blocks', 'main.css');
        });

        $this->enqueueEditorPreviewAssets(function (ModuleAssets $assets) {
            $assets->enqueueStyle('kadence-blocks-editor', 'admin-editor.css');
        });

        $this->enqueueEditorUIAssets(function (ModuleAssets $assets) {
            $assets->enqueueScript('kadence-blocks-editor-ui', 'editor-ui.js', [
                'wp-blocks',
                'wp-element',
                'wp-hooks',
                'sitchco/extend-block',
            ]);
            $assets->inlineScriptData('kadence-blocks-editor-ui', 'themeSettings', wp_get_global_settings());
            $assets->inlineScriptData('kadence-blocks-editor-ui', 'sitchcoIcons', $this->getIconList());
        }, 1);

        add_filter('kadence_blocks_column_render_block_attributes', [$this, 'injectDefaultColumnGap']);
        add_filter('kadence_blocks_css_spacing_sizes', [$this, 'overrideSpacingSizes']);
        add_filter('kadence_blocks_css_gap_sizes', [$this, 'overrideGapSizes']);
        add_filter('kadence_blocks_css_font_sizes', [$this, 'overrideFontSizes']);
        add_filter('option_kadence_blocks_config_blocks', [$this, 'overrideConfigDefaults']);
        add_filter('kadence_blocks_measure_output_css_variables', [$this, 'enableCssVariablesForPadding'], 10, 5);
        add_filter('render_block_kadence/accordion', [$this, 'replaceAccordionIcons'], 10, 2);
    }

    /**
     * Override Kadence spacing presets to use theme.json spacing presets.
     */
    public function overrideSpacingSizes(array $sizes): array
    {
        return $this->overrideSizes(
            $sizes,
            'spacing',
            fn($settings) => $settings['spacing']['spacingSizes']['theme'] ?? [],
        );
    }

    /**
     * Override Kadence gap presets with theme.json presets plus semantic tokens.
     */
    public function overrideGapSizes(array $sizes): array
    {
        $sizes = $this->overrideSpacingSizes($sizes);
        $sizes['content-flow'] = 'var(--wp--custom--block-gap)';

        return $sizes;
    }

    /**
     * Override Kadence font size presets to use theme.json font size presets.
     */
    public function overrideFontSizes(array $sizes): array
    {
        return $this->overrideSizes(
            $sizes,
            'font-size',
            fn($settings) => $settings['typography']['fontSizes']['theme'] ?? [],
        );
    }

    /**
     * Replace Kadence's default preset slugs with theme.json preset references.
     */
    protected function overrideSizes(array $sizes, string $type, callable $getThemeSizes): array
    {
        $filtered = array_filter($sizes, fn($slug) => in_array($slug, ['ss-auto', '0', 'none']), ARRAY_FILTER_USE_KEY);
        $theme_sizes = collect($getThemeSizes(wp_get_global_settings()))
            ->mapWithKeys(fn($size) => ["{$type}-{$size['slug']}" => "var(--wp--preset--{$type}--{$size['slug']})"])
            ->all();
        return $theme_sizes + $filtered;
    }

    /**
     * Ensure Kadence blocks config has valid defaults.
     */
    public function overrideConfigDefaults(mixed $config): mixed
    {
        return $config === '{}'
            ? json_encode(['kadence/tabs' => (object) [], 'kadence/accordion' => (object) []])
            : $config;
    }

    /**
     * Inject default row gap for Kadence columns when none is specified.
     *
     * When rowGapVariable is empty, Kadence outputs no row-gap. This filter
     * injects 'content-flow' as the default, which maps to the semantic
     * --wp--custom--block-gap variable via overrideGapSizes.
     */
    public function injectDefaultColumnGap(array $attributes): array
    {
        if (empty($attributes['rowGapVariable'][0])) {
            if (!isset($attributes['rowGapVariable']) || !is_array($attributes['rowGapVariable'])) {
                $attributes['rowGapVariable'] = ['', '', ''];
            }
            $attributes['rowGapVariable'][0] = 'content-flow';
        }

        return $attributes;
    }

    /**
     * Enable CSS custom property output for padding on Kadence blocks.
     *
     * Instead of outputting:
     *   padding-top: var(--wp--preset--spacing--70);
     *
     * This outputs:
     *   --kb-padding-top: var(--wp--preset--spacing--70);
     *   padding-top: var(--kb-padding-top);
     *
     * This provides an intermediate variable that can be overridden in CSS
     * without needing to know the original preset value.
     */
    public function enableCssVariablesForPadding(
        bool $use_variables,
        string $property,
        string $name,
        string $selector,
        array $attributes,
    ): bool {
        if ($property === 'padding') {
            if (str_contains($selector, '.kadence-column') || str_contains($selector, '.kb-row-layout')) {
                return true;
            }
        }

        return $use_variables;
    }

    /**
     * Get list of available icons for editor UI.
     *
     * @return array<array{label: string, value: string}>
     */
    protected function getIconList(): array
    {
        $iconList = apply_filters(SvgSprite::hookName('icon-list'), []);
        $icons = collect($iconList)
            ->flatMap(fn($item) => $item['icons'])
            ->sort()
            ->map(fn($name) => [
                'label' => ucfirst(str_replace('-', ' ', $name)),
                'value' => $name,
            ])
            ->prepend(['label' => 'Default', 'value' => ''])
            ->values()
            ->all();

        return $icons;
    }

    /**
     * Replace Kadence accordion icon trigger with sitchco icons.
     *
     * Replaces empty spans used for CSS-based icons with actual SVG icons
     * (collapsed icon shown by default, expanded icon shown when active).
     */
    public function replaceAccordionIcons(string $block_content, array $block): string
    {
        // Get icon names from accordion block attributes (with defaults)
        $collapsedIcon = $block['attrs']['accordionIconCollapsed'] ?? 'plus';
        $expandedIcon = $block['attrs']['accordionIconExpanded'] ?? 'minus';

        // Skip replacement if using default empty values (use CSS icons instead)
        if (empty($collapsedIcon) && empty($expandedIcon)) {
            return $block_content;
        }

        // Use defaults if only one is set
        $collapsedIcon = $collapsedIcon ?: 'plus';
        $expandedIcon = $expandedIcon ?: 'minus';

        $collapsedIconHtml = $this->svgSprite->renderIcon($collapsedIcon, null, ['kt-accordion-icon-collapsed']);
        $expandedIconHtml = $this->svgSprite->renderIcon($expandedIcon, null, ['kt-accordion-icon-expanded']);

        // Use regex to match icon trigger spans more flexibly
        // Matches: <span class="...kt-blocks-accordion-icon-trigger..."></span>
        // Allows for varying attribute order, additional classes, or whitespace
        return preg_replace(
            '/<span\s+[^>]*\bclass=["\'][^"\']*\bkt-blocks-accordion-icon-trigger\b[^"\']*["\'][^>]*>\s*<\/span>/i',
            '<span class="kt-blocks-accordion-icon-trigger kt-blocks-accordion-icon-trigger--sitchco">'
            . $collapsedIconHtml
            . $expandedIconHtml
            . '</span>',
            $block_content
        );
    }
}
