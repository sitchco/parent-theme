<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Parent\Modules\ContentPartial\ContentPartialPost;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialRepository;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialService;
use Sitchco\Parent\Tests\Support\ModuleTester;
use Sitchco\Tests\Support\TestCase;

/**
 * class ContentPartialServiceTestnamespace SitchcoParentModulesSiteHeader;
 */
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

        // Create a default content partial with a taxonomy term
        $this->standardHeaderId = $this->factory()->post->create([
            'post_title' => 'Standard Header',
            'post_type'  => ContentPartialPost::POST_TYPE,
            'meta_input' => ['is_default' => '1']
        ]);

        wp_set_object_terms($this->standardHeaderId, 'header', ContentPartialPost::TAXONOMY);
    }

    public function testFindDefaultPartial(): void
    {
        $result = $this->repository->findDefaultPartial('header');

        $this->assertInstanceOf(ContentPartialPost::class, $result);
        $this->assertEquals($this->standardHeaderId, $result->ID);
    }

    public function testEnsureTaxonomyTermExists(): void
    {
        $taxonomy = ContentPartialPost::TAXONOMY;
        $termSlug = 'sidebar';

        // Ensure taxonomy exists
        $this->assertTrue(taxonomy_exists($taxonomy));

        // Ensure the term does not exist before running the function
        $this->assertNull(term_exists($termSlug, $taxonomy));

        $Module = $this->container->get(ModuleTester::class);
        // Register the new module, which should create the term
        $Module->init();

        // Simulate running ensureTaxonomyTermExists in a real admin context
        global $current_screen;
        $current_screen = convert_to_screen(ContentPartialPost::POST_TYPE);
        $this->service->ensureTaxonomyTermExists($current_screen);

        // Verify the term now exists
        $term = term_exists($termSlug, $taxonomy);
        $this->assertIsArray($term);
        $this->assertArrayHasKey('term_id', $term);

        // Ensure the term's name is properly capitalized
        $termData = get_term($term['term_id']);
        $this->assertEquals('Sidebar', $termData->name);
    }
}
