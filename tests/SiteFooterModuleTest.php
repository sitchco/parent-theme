<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Parent\Modules\ContentPartial\ContentPartialService;
use Sitchco\Parent\Modules\SiteFooter\SiteFooterModule;
use Sitchco\Tests\TestCase;

class SiteFooterModuleTest extends TestCase
{
    protected SiteFooterModule $module;
    protected ContentPartialService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = $this->container->get(SiteFooterModule::class);
        $this->service = $this->container->get(ContentPartialService::class);
    }

    public function testInitAddsFooterTemplateAreaWithContext(): void
    {
        $areas = $this->service->getTemplateAreas();
        $this->assertArrayHasKey('footer', $areas);
        $this->assertTrue($areas['footer']);
    }
}
