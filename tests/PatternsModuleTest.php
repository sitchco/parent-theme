<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Framework\ConfigRegistry;
use Sitchco\Parent\Modules\Patterns\PatternsModule;
use Sitchco\Tests\TestCase;
use WP_Block_Pattern_Categories_Registry;

class PatternsModuleTest extends TestCase
{
    protected PatternsModule $module;
    protected ConfigRegistry $configRegistry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = $this->container->get(PatternsModule::class);
        $this->configRegistry = $this->container->get(ConfigRegistry::class);
    }

    private function countInitCallbacks(): int
    {
        global $wp_filter;
        if (!isset($wp_filter['init'])) {
            return 0;
        }
        $count = 0;
        foreach ($wp_filter['init']->callbacks as $priority => $callbacks) {
            $count += count($callbacks);
        }
        return $count;
    }

    public function testRegisterPatternCategoriesWithEmptyConfigAddsNoAction(): void
    {
        $categories = $this->configRegistry->load('patternCategories');
        if (!empty($categories)) {
            $this->markTestSkipped('patternCategories config is not empty in this environment');
        }
        $countBefore = $this->countInitCallbacks();
        $this->module->registerPatternCategories();
        $this->assertSame(
            $countBefore,
            $this->countInitCallbacks(),
            'Expected no init action when patternCategories config is empty',
        );
    }

    public function testRegisterPatternCategoriesRegistersCategories(): void
    {
        $categories = $this->configRegistry->load('patternCategories');
        if (empty($categories)) {
            $this->markTestSkipped('patternCategories config is empty — requires child theme config');
        }
        $countBefore = $this->countInitCallbacks();
        $this->module->registerPatternCategories();
        $this->assertGreaterThan($countBefore, $this->countInitCallbacks(), 'Expected init callback to be registered');
    }
}
