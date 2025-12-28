<?php

namespace Sitchco\Parent\Modules\KadenceBlocks;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;

/**
 * Integration layer for Kadence Blocks plugin.
 *
 * Makes Kadence Blocks work correctly with theme.json as source of truth
 * by providing CSS variable aliases and overriding preset sizes.
 */
class KadenceBlocks extends Module
{
    public function init(): void
    {
        $this->enqueueGlobalAssets(function (ModuleAssets $assets) {
            $assets->enqueueStyle('kadence-blocks', 'kadence-blocks.css');
        });

        $this->enqueueEditorUIAssets(function (ModuleAssets $assets) {
            $assets->enqueueScript('kadence-blocks-editor-ui', 'editor-ui.js', ['wp-blocks', 'wp-element', 'wp-hooks']);
        });

        add_filter('render_block_kadence/column', [$this, 'addColumnBackgroundClass'], 10, 2);
        add_filter('kadence_blocks_css_spacing_sizes', [$this, 'overrideSpacingSizes']);
        add_filter('kadence_blocks_css_gap_sizes', [$this, 'overrideSpacingSizes']);
        add_filter('kadence_blocks_css_font_sizes', [$this, 'overrideFontSizes']);
        add_filter('option_kadence_blocks_config_blocks', [$this, 'overrideConfigDefaults']);
    }

    /**
     * Override Kadence spacing/gap presets to use theme.json spacing presets.
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
     * Detect columns with backgrounds and add a CSS class for styling hooks.
     *
     * Checks for background color, gradient, or image in block attributes
     * and adds 'kt-column-has-bg' class when found.
     */
    public function addColumnBackgroundClass(string $block_content, array $block): string
    {
        $attrs = $block['attrs'] ?? [];
        $has_background = false;

        foreach ($attrs as $key => $value) {
            if (str_contains($key, 'background') || str_contains($key, 'bgColor')) {
                if (!empty($value)) {
                    $has_background = true;
                    break;
                }
            }
        }

        if (!$has_background && !empty($attrs['style']['color']['background'])) {
            $has_background = true;
        }

        if ($has_background) {
            $p = new \WP_HTML_Tag_Processor($block_content);
            if ($p->next_tag(['tag_name' => 'DIV', 'class_name' => 'wp-block-kadence-column'])) {
                $p->add_class('kt-column-has-bg');
                return $p->get_updated_html();
            }
        }

        return $block_content;
    }
}
