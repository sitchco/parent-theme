<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Parent\Modules\ContentPartial\ContentPartialService;
use Sitchco\Parent\Modules\SiteHeader\SiteHeaderModule;
use Sitchco\Tests\TestCase;
use Sitchco\Utils\Hooks;

class SiteHeaderModuleTest extends TestCase
{
    protected SiteHeaderModule $module;
    protected ContentPartialService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = $this->container->get(SiteHeaderModule::class);
        $this->service = $this->container->get(ContentPartialService::class);
    }

    public function testInitAddsHeaderTemplateAreaWithContext(): void
    {
        $areas = $this->service->getTemplateAreas();
        $this->assertArrayHasKey('header', $areas);
        $this->assertTrue($areas['header']);
    }

    public function testInitRegistersTemplateContextFilter(): void
    {
        $hookName = Hooks::name('template-context/partials/site-header');
        $this->assertGreaterThan(0, has_filter($hookName, [$this->module, 'addPageContextToSiteHeader']));
    }

    public function testAddPageContextReturnsEarlyWithoutSiteHeader(): void
    {
        $context = ['other_key' => 'value'];
        $result = $this->module->addPageContextToSiteHeader($context);
        $this->assertSame($context, $result);
    }

    public function testAddPageContextSetsOverlaidFalseByDefault(): void
    {
        $header = new \stdClass();
        $context = ['site_header' => $header];
        $result = $this->module->addPageContextToSiteHeader($context);
        $this->assertFalse($result['site_header']->is_overlaid);
    }

    public function testAddPageContextSetsOverlaidTrueWhenSingularWithOverlay(): void
    {
        $pageId = $this->factory()->post->create(['post_type' => 'page']);
        $this->go_to(get_permalink($pageId));
        $this->assertTrue(is_singular(), 'Expected singular context');

        update_field('header_overlay', true, $pageId);

        $header = new \stdClass();
        $context = ['site_header' => $header];
        $result = $this->module->addPageContextToSiteHeader($context);
        $this->assertTrue($result['site_header']->is_overlaid);
    }
}
