<?php

namespace Sitchco\Parent\Modules\ContentSlider;

use Sitchco\Framework\Module;
use Sitchco\Utils\Cache;
use Sitchco\Utils\Logger;

/**
 * Content Slider Module
 *
 * Provides a generic slider container block that uses Kadence Section blocks (kadence/column)
 * as slides. All assets are managed at the block level via block.json and .asset.php files.
 *
 * This module ensures Kadence Splide dependencies are registered for block.json dependencies.
 *
 * Requirements:
 * - Kadence Blocks plugin (provides kadence/column blocks and Splide library)
 * - ACF Pro (for slider settings fields)
 */
class ContentSlider extends Module
{
    public const HOOK_SUFFIX = 'content-slider';
    /**
     * Module initialization
     *
     * The block assets are self-contained and loaded via block.json.
     * Dependencies on Kadence Splide are declared in the block's .asset.php files.
     */
    public function init(): void
    {
        // Register Kadence Splide scripts early for our block.json dependencies
        if (class_exists('Kadence_Blocks_Testimonials_Block')) {
            add_action(
                'init',
                function () {
                    $instance = \Kadence_Blocks_Testimonials_Block::get_instance();
                    // Explicitly call register_scripts to ensure Splide dependencies are registered
                    // (the Kadence abstract block only registers scripts lazily on enqueue)
                    $instance->register_scripts();
                },
                5,
            );
        }

        // Register block style variations and hook the variations filter
        // Priority 15 ensures sitchco/content-slider block type exists (registered at init:10)
        add_action(
            'init',
            function () {
                $variations = $this->scanVariations();
                foreach ($variations as $slug => $config) {
                    register_block_style('sitchco/content-slider', [
                        'name' => $slug,
                        'label' => $config['title'],
                    ]);
                }
                add_filter(static::hookName('variations'), fn(array $v) => array_merge($v, $variations), 5);
            },
            15,
        );
    }

    /**
     * Build the Splide config array for a Content Slider block.
     *
     * Pure assembly of $sliderConfig from ACF field values, kept here (rather than
     * inline in block.php) so the mode-derivation and variation precedence are
     * unit-testable. The runtime auto-downgrade in script.js remains the final
     * authority on `type`.
     *
     * `type`/`rewind` derive from the `slider_mode` field. A block with no saved
     * mode falls back to the `default_mode` filter: the platform default is the
     * neutral 'slide'; a child theme (e.g. Roundabout) can return 'loop'.
     *
     * @param array $fields    ACF field values (keyed by field name).
     * @param array $blockData The block context array (uses `className` for variations).
     */
    public static function buildSliderConfig(array $fields, array $blockData = []): array
    {
        // Parent default is the platform-neutral 'slide'; a child theme overrides via filter.
        $mode = $fields['slider_mode'] ?? apply_filters(static::hookName('default_mode'), 'slide');

        $sliderConfig = [
            'type' => $mode === 'loop' ? 'loop' : 'slide',
            'rewind' => $mode === 'rewind',
            'autoplay' => !empty($fields['autoplay']),
            'interval' => (int) ($fields['autoplay_speed'] ?? 5000),
            'arrows' => !empty($fields['arrows']),
            'pagination' => !empty($fields['dots']),
            'gap' => 'var(--wp--custom--carousel-gap)',
            'perPage' => (int) ($fields['per_view_desktop'] ?? 3),
            'perMove' => 1,
            'keyboard' => true,
            'accessibility' => true,
            'ariaLabel' => 'Content slider',
            'breakpoints' => [
                '1024' => ['perPage' => (int) ($fields['per_view_desktop'] ?? 3)],
                '768' => ['perPage' => (int) ($fields['per_view_tablet'] ?? 2)],
                '480' => ['perPage' => (int) ($fields['per_view_mobile'] ?? 1)],
            ],
        ];

        // Merge variation overrides from block style selection
        $variationNames = wp_get_block_style_variation_name_from_class($blockData['className'] ?? '');
        if (!empty($variationNames)) {
            $variationSlug = $variationNames[0];
            $variations = apply_filters(static::hookName('variations'), []);
            if (!empty($variations[$variationSlug]['splide'])) {
                $sliderConfig = array_replace_recursive($sliderConfig, $variations[$variationSlug]['splide']);
            }
        }

        return $sliderConfig;
    }

    private function scanVariations(): array
    {
        return Cache::remember('content_slider_variations', function () {
            $variations = [];
            $dirs = array_unique(
                array_filter(
                    [
                        get_template_directory() . '/modules/ContentSlider/variations',
                        get_stylesheet_directory() . '/modules/ContentSlider/variations',
                    ],
                    'is_dir',
                ),
            );

            foreach ($dirs as $dir) {
                foreach (glob($dir . '/*.json') as $file) {
                    $slug = basename($file, '.json');
                    $data = json_decode(file_get_contents($file), true);
                    if (!is_array($data) || empty($data['title']) || !isset($data['splide'])) {
                        Logger::warning("ContentSlider: Invalid variation file skipped: {$file}");
                        continue;
                    }
                    $variations[$slug] = $data;
                }
            }

            return $variations;
        });
    }
}
