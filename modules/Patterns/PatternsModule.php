<?php

namespace Sitchco\Parent\Modules\Patterns;

use Sitchco\Framework\ConfigRegistry;
use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;

class PatternsModule extends Module
{
    const FEATURES = ['registerPatternCategories', 'saveToTheme'];

    public function __construct(
        private readonly ConfigRegistry $configRegistry,
        private readonly SavePatternsToTheme $savePatterns,
    ) {}

    public function registerPatternCategories(): void
    {
        $categories = $this->configRegistry->load('patternCategories');
        if (empty($categories)) {
            return;
        }
        add_action('init', function () use ($categories) {
            foreach ($categories as $slug => $label) {
                register_block_pattern_category($slug, ['label' => $label]);
            }
        });
    }

    public function saveToTheme(): void
    {
        if (wp_get_environment_type() !== 'local') {
            return;
        }

        $this->enqueueAdminAssets(function (ModuleAssets $assets) {
            $screen = get_current_screen();
            $validScreens = ['site-editor', 'appearance_page_gutenberg-edit-site'];
            if (!$screen || !in_array($screen->id, $validScreens, true)) {
                return;
            }
            $assets->enqueueScript('save-patterns-to-theme', 'save-patterns.js', [
                'wp-dom-ready',
                'wp-api-fetch',
                'wp-data',
                'wp-core-data',
            ]);
        });

        add_action('rest_api_init', [$this->savePatterns, 'registerRestRoute']);
    }
}
