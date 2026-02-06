<?php

namespace Sitchco\Parent\Modules\Patterns;

use Sitchco\Framework\ConfigRegistry;
use Sitchco\Framework\Module;

class PatternsModule extends Module
{
    const FEATURES = ['registerPatternCategories'];

    public function __construct(private readonly ConfigRegistry $configRegistry) {}

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
}
