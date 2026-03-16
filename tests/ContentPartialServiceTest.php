<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Parent\Modules\ContentPartial\ContentPartialPost;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialRepository;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialService;
use Sitchco\Parent\Tests\Support\ModuleTester;
use Sitchco\Tests\TestCase;

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
            'post_type' => ContentPartialPost::POST_TYPE,
            'meta_input' => ['is_default' => '1'],
        ]);

        wp_set_object_terms($this->standardHeaderId, 'header', ContentPartialPost::TAXONOMY);
    }

    public function testFindDefaultPartial(): void
    {
        $termId = $this->service->getTermId('header');
        $result = $this->repository->findDefaultPartial($termId);

        $this->assertInstanceOf(ContentPartialPost::class, $result);
        $this->assertEquals($this->standardHeaderId, $result->ID);
    }

    public function testRegisterAcfFieldFilter(): void
    {
        $this->service->addTemplateArea('header', true, ['field_test_key']);
        $args = apply_filters('acf/fields/post_object/query/key=field_test_key', []);

        $this->assertEquals(ContentPartialPost::POST_TYPE, $args['post_type']);
        $this->assertArrayHasKey('tax_query', $args);
        $this->assertEquals(ContentPartialPost::TAXONOMY, $args['tax_query'][0]['taxonomy']);
        $this->assertEquals('term_id', $args['tax_query'][0]['field']);
        $this->assertEquals($this->service->getTermId('header'), $args['tax_query'][0]['terms']);
    }

    public function testRegisterAcfFieldFilterNullTermId(): void
    {
        $this->service->addTemplateArea('nonexistent', true, ['field_null_test']);
        $args = apply_filters('acf/fields/post_object/query/key=field_null_test', []);

        $this->assertEquals(ContentPartialPost::POST_TYPE, $args['post_type']);
        $this->assertEquals(0, $args['tax_query'][0]['terms']);
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
