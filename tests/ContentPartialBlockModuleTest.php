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

    private function getTemplateAreas(): array
    {
        $reflection = new \ReflectionClass($this->service);
        $prop = $reflection->getProperty('templateAreas');
        $prop->setAccessible(true);
        return $prop->getValue($this->service);
    }

    public function testInitAddsBlockTemplateAreaWithoutContext(): void
    {
        $areas = $this->getTemplateAreas();
        $this->assertArrayHasKey('block', $areas);
        $this->assertFalse($areas['block']);
    }
}
