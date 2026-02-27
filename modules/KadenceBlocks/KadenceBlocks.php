<?php

namespace Sitchco\Parent\Modules\KadenceBlocks;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;
use Sitchco\Parent\Modules\ExtendBlock\ExtendBlockModule;

/**
 * Integration layer for Kadence Blocks plugin.
 *
 * Makes Kadence Blocks work correctly with theme.json as source of truth
 * by providing CSS variable aliases and overriding preset sizes.
 */
class KadenceBlocks extends Module
{
    public const DEPENDENCIES = [ExtendBlockModule::class];
    public const HOOK_SUFFIX = 'kadence-blocks';

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
                'wp-components',
                'wp-element',
                'wp-hooks',
                'sitchco/extend-block',
            ]);
            $assets->inlineScriptData('kadence-blocks-editor-ui', 'themeSettings', wp_get_global_settings());
            $assets->inlineScriptData(
                'kadence-blocks-editor-ui',
                'contentWidthPresets',
                $this->getContentWidthPresets(),
            );
        }, 1);

        add_filter('kadence_blocks_column_render_block_attributes', [$this, 'injectDefaultColumnGap']);
        add_filter('kadence_blocks_css_spacing_sizes', [$this, 'overrideSpacingSizes']);
        add_filter('kadence_blocks_css_gap_sizes', [$this, 'overrideGapSizes']);
        add_filter('kadence_blocks_css_font_sizes', [$this, 'overrideFontSizes']);
        add_filter('option_kadence_blocks_config_blocks', [$this, 'overrideConfigDefaults']);
        add_filter('kadence_blocks_measure_output_css_variables', [$this, 'enableCssVariablesForPadding'], 10, 5);
        add_filter('kadence_blocks_rowlayout_skip_max_width_css', [$this, 'handleRowCssMaxWidth'], 10, 4);
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

    // Exposes --kb-padding-top (and siblings); consumed by SiteHeader overlay push-down calc.
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

    public function getContentWidthPresets(): array
    {
        $presets = [
            'theme' => ['label' => 'Theme Content'],
            'wide' => ['label' => 'Wide'],
        ];

        $settings = wp_get_global_settings();
        if (!empty($settings['custom']['extraWideSize'])) {
            $presets['xwide'] = ['label' => 'Extra Wide'];
        }

        return apply_filters(static::hookName('content-width-presets'), $presets);
    }

    /**
     * Skip fork's inline max-width CSS when a named content width preset is active.
     * The theme handles max-width via per-preset CSS class rules instead.
     */
    public function handleRowCssMaxWidth(bool $skip, array $attributes, string $unique_id, string $inner_selector): bool
    {
        $inner_content_width = $attributes['innerContentWidth'] ?? '';
        if ($inner_content_width && $inner_content_width !== 'custom') {
            $presets = $this->getContentWidthPresets();
            if (isset($presets[$inner_content_width])) {
                return true;
            }
        }
        return $skip;
    }
}
