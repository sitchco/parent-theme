<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Parent\Modules\ContentPartial\ContentPartialPost;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialRepository;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialService;
use Sitchco\Parent\Tests\Support\ModuleTester;
use Sitchco\Tests\TestCase;
use Sitchco\Utils\Cache;

class ContentPartialServiceTest extends TestCase
{
    protected ContentPartialService $service;
    protected ContentPartialRepository $repository;
    protected string $standardHeaderId;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = $this->container->get(ContentPartialService::class);
        $this->repository = $this->container->get(ContentPartialRepository::class);

        $this->standardHeaderId = $this->createContentPartial('header', [
            'post_title' => 'Standard Header',
            'meta_input' => ['is_default' => '1'],
        ]);
    }

    private function createContentPartial(string $term, array $overrides = []): int
    {
        $postId = $this->factory()->post->create(
            array_merge(
                [
                    'post_type' => ContentPartialPost::POST_TYPE,
                ],
                $overrides,
            ),
        );
        wp_set_object_terms($postId, $term, ContentPartialPost::TAXONOMY);
        return $postId;
    }

    private function createPageWithPartialOverride(string $area, int $partialId): int
    {
        $pageId = $this->factory()->post->create([
            'post_type' => 'page',
            'post_title' => 'Test Page',
        ]);
        update_field("{$area}_partial", $partialId, $pageId);
        return $pageId;
    }

    private function makeFreshService(): ContentPartialService
    {
        return new ContentPartialService($this->repository);
    }

    public function testFindDefaultPartial(): void
    {
        $termId = $this->service->getTermId('header');
        $result = $this->repository->findDefaultPartial($termId);

        $this->assertInstanceOf(ContentPartialPost::class, $result);
        $this->assertEquals($this->standardHeaderId, $result->ID);
    }

    public function testEnsureTaxonomyTermExists(): void
    {
        $taxonomy = ContentPartialPost::TAXONOMY;
        $termSlug = 'sidebar';

        $this->assertTrue(taxonomy_exists($taxonomy));
        $this->assertNull(term_exists($termSlug, $taxonomy));

        $Module = $this->container->get(ModuleTester::class);
        $Module->init();

        global $current_screen;
        $current_screen = convert_to_screen(ContentPartialPost::POST_TYPE);
        $this->service->ensureTaxonomyTermExists($current_screen);

        $term = term_exists($termSlug, $taxonomy);
        $this->assertIsArray($term);
        $this->assertArrayHasKey('term_id', $term);

        $termData = get_term($term['term_id']);
        $this->assertEquals('Sidebar', $termData->name);
    }

    public function testSetContextResolvesDefaultPartial(): void
    {
        $service = $this->makeFreshService();
        $service->addTemplateArea('header');
        $service->setContext();

        $result = $service->getPartial('header');
        $this->assertInstanceOf(ContentPartialPost::class, $result);
        $this->assertEquals($this->standardHeaderId, $result->ID);
    }

    public function testSetContextOverrideTakesPrecedence(): void
    {
        $overrideId = $this->factory()->post->create([
            'post_title' => 'Override Header',
            'post_type' => ContentPartialPost::POST_TYPE,
        ]);

        $pageId = $this->createPageWithPartialOverride('header', $overrideId);

        $service = $this->makeFreshService();
        $service->addTemplateArea('header');

        $GLOBALS['wp_query']->queried_object_id = $pageId;
        $GLOBALS['wp_query']->queried_object = get_post($pageId);

        $service->setContext();

        $result = $service->getPartial('header');
        $this->assertInstanceOf(ContentPartialPost::class, $result);
        $this->assertEquals($overrideId, $result->ID);
    }

    public function testSetContextSkipsAreasWithoutContext(): void
    {
        $service = $this->makeFreshService();
        $service->addTemplateArea('header', false);
        $service->setContext();

        $this->assertNull($service->getPartial('header'));
    }

    public function testSetContextSkipsAreaWithNoMatchingPartial(): void
    {
        $service = $this->makeFreshService();
        $service->addTemplateArea('footer');
        $service->setContext();

        $this->assertNull($service->getPartial('footer'));
    }

    public function testGetTermIdCachesResult(): void
    {
        Cache::forget('content_partial_term_header');

        $firstResult = $this->service->getTermId('header');
        $secondResult = $this->service->getTermId('header');

        $this->assertNotNull($firstResult);
        $this->assertSame($firstResult, $secondResult);
    }

    public function testFindPartialOverrideFromPage(): void
    {
        $partialId = $this->factory()->post->create([
            'post_title' => 'Override Header',
            'post_type' => ContentPartialPost::POST_TYPE,
        ]);

        $pageId = $this->createPageWithPartialOverride('header', $partialId);

        $result = $this->repository->findPartialOverrideFromPage('header', $pageId);
        $this->assertInstanceOf(ContentPartialPost::class, $result);
        $this->assertEquals($partialId, $result->ID);
    }

    public function testFindPartialOverrideReturnsNullWhenNoPage(): void
    {
        $GLOBALS['wp_query']->queried_object_id = 0;

        $result = $this->repository->findPartialOverrideFromPage('header');
        $this->assertNull($result);
    }
}
