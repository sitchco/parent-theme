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

    public function setUp(): void
    {
        parent::setUp();
        $this->repository = new ContentPartialRepository();
        $this->service = new ContentPartialService($this->repository);
    }

    public function test_find_default(): void
    {
        $standard_header_id = self::factory()->post->create([
            'post_title' => 'Standard Header',
            'post_type' => 'content-partial'
        ]);
        wp_set_object_terms($standard_header_id, 'header', ContentPartialPost::TAXONOMY);
        add_post_meta($standard_header_id, 'is_default', 1);
        wp_set_object_terms($standard_header_id, 'header', ContentPartialPost::TAXONOMY);
        $result = $this->service->findDefault('header');
        $this->assertInstanceOf(ContentPartialPost::class, $result);
        $this->assertEquals($standard_header_id, $result->ID);
    }

    public function test_find_override_from_page(): void
    {
        $standard_header_id = self::factory()->post->create([
            'post_title' => 'Standard Header',
            'post_type' => 'content-partial'
        ]);
        wp_set_object_terms($standard_header_id, 'header', ContentPartialPost::TAXONOMY);
        add_post_meta($standard_header_id, 'is_default', true);
        $no_header_id = self::factory()->post->create([
            'post_title' => 'No Header',
            'post_type' => 'content-partial'
        ]);
        wp_set_object_terms($standard_header_id, 'header', ContentPartialPost::TAXONOMY);
        $page_id = self::factory()->post->create([
            'post_title' => 'Page',
            'post_type' => 'page'
        ]);
        add_post_meta($page_id, 'header_partial', $no_header_id);
        $result = $this->service->findOverrideFromPage('header', $page_id);
        $this->assertInstanceOf(ContentPartialPost::class, $result);
        $this->assertEquals($no_header_id, $result->ID);
    }
}
