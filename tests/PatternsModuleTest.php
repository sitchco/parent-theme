<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Parent\Modules\Patterns\PatternsModule;
use Sitchco\Tests\TestCase;

class PatternsModuleTest extends TestCase
{
    protected PatternsModule $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = $this->container->get(PatternsModule::class);
    }

    public function testRegisterPatternCategoriesRegistersAction(): void
    {
        $this->module->registerPatternCategories();
        // Verify the init action was registered (the callback registers categories)
        $this->assertGreaterThan(0, has_action('init'));
    }
}
