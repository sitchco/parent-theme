<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Parent\ContentPartial\ContentPartialPost;
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
        $this->repository = new ContentPartialRepository();
        $this->service = new ContentPartialService($this->repository);
        $this->standardHeaderId = self::factory()->post->create([
            'post_title' => 'Standard Header',
            'post_type' => 'content-partial',
            'meta_input' => ['is_default' => '1']
        ]);
        wp_set_object_terms($this->standardHeaderId, 'header', ContentPartialPost::TAXONOMY);
    }

    public function testFindDefault(): void
    {
        $result = $this->service->findDefault('header');
        $this->assertInstanceOf(ContentPartialPost::class, $result);
        $this->assertEquals($this->standardHeaderId, $result->ID);
    }

    public function testFindOverrideFromPage(): void
    {
        $noHeaderId = self::factory()->post->create([
            'post_title' => 'No Header',
            'post_type' => 'content-partial'
        ]);
        wp_set_object_terms($this->standardHeaderId, 'header', ContentPartialPost::TAXONOMY);
        $pageId = self::factory()->post->create([
            'post_title' => 'Page',
            'post_type' => 'page',
            'meta_input' => ['header_partial' => $noHeaderId]
        ]);
        $result = $this->service->findOverrideFromPage('header', $pageId);
        $this->assertInstanceOf(ContentPartialPost::class, $result);
        $this->assertEquals($noHeaderId, $result->ID);
    }

    public function testInsertTaxonomyTerm(): void
    {
        // Ensure taxonomy exists
        $taxonomy = ContentPartialPost::TAXONOMY;
        $this->assertTrue(taxonomy_exists($taxonomy));

        // Test Scenario 1: Term does not exist and should be created
        $termSlug = 'sidebar';
        $this->assertNull(term_exists($termSlug, $taxonomy));

        // Call the method to insert the term directly
        $this->service->insertTaxonomyTerm($termSlug);

        // Assert that the term was created
        $term = term_exists($termSlug, $taxonomy);
        $this->assertIsArray($term);
        $this->assertArrayHasKey('term_id', $term);

        $termData = get_term($term['term_id']);
        $this->assertEquals(ucfirst($termSlug), $termData->name);

        // Test Scenario 2: Term exists and should not be recreated
        $existingTermSlug = 'existing-term-slug';
        wp_insert_term('Existing Term', $taxonomy, ['slug' => $existingTermSlug]);

        // Ensure the term exists before calling the method
        $existingTerm = term_exists($existingTermSlug, $taxonomy);
        $this->assertIsArray($existingTerm, "Existing term '$existingTermSlug' should exist before reinsertion.");

        // Call the method again to ensure no new term is created
        $this->service->insertTaxonomyTerm($existingTermSlug, 'New Term');

        // Assert that the term was not recreated (name should stay as "Existing Term")
        $existingTermData = get_term($existingTerm['term_id']);
        $this->assertEquals('Existing Term', $existingTermData->name, "Existing term name should not change.");
    }
}
