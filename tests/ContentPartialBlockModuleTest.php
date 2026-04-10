<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Parent\Modules\ContentPartial\ContentPartialService;
use Sitchco\Parent\Modules\ContentPartialBlock\ContentPartialBlockModule;
use Sitchco\Tests\TestCase;

class ContentPartialBlockModuleTest extends TestCase
{
    protected ContentPartialBlockModule $module;
    protected ContentPartialService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = $this->container->get(ContentPartialBlockModule::class);
        $this->service = $this->container->get(ContentPartialService::class);
    }

    public function testInitAddsBlockTemplateAreaWithoutContext(): void
    {
        $areas = $this->service->getTemplateAreas();
        $this->assertArrayHasKey('block', $areas);
        $this->assertFalse($areas['block']);
    }
}
