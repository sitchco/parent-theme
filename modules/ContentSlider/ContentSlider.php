<?php

namespace Sitchco\Parent\Modules\ContentSlider;

use Sitchco\Framework\Module;

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

        // Block registration and asset loading is handled automatically by the framework
        // via BlockRegistrationModuleExtension. No manual asset enqueuing needed.
    }
}
