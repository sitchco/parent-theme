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
    const HOOK_SUFFIX = 'content-slider';
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
