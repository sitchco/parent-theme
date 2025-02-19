<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Parent\ContentPartial\ContentPartialPost;
use Sitchco\Parent\Tests\Support\ModuleTester;
use Sitchco\Tests\Support\TestCase;
use Sitchco\Parent\ContentPartial\ContentPartialService;
use Sitchco\Parent\ContentPartial\ContentPartialRepository;

/**
 * class ContentPartialServiceTest
 * @package Sitchco\Parent\Tests
 */
class ContentPartialServiceTest extends TestCase
{
    protected ContentPartialService $service;
    protected ContentPartialRepository $repository;
    protected string $standardHeaderId;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = new ContentPartialService();
        $this->repository = new ContentPartialRepository();

        // Create a default content partial with a taxonomy term
        $this->standardHeaderId = self::factory()->post->create([
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

        // Register a new module, which should create the term
        $this->service->addModule($termSlug, $Module);

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
