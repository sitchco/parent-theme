<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Modules\UIModal\ModalData;
use Sitchco\Modules\UIModal\UIModal;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialPost;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialService;
use Sitchco\Parent\Modules\ContentPartialModal\ContentPartialModalModule;
use Sitchco\Tests\TestCase;

class ContentPartialModalModuleTest extends TestCase
{
    protected ContentPartialModalModule $module;
    protected UIModal $uiModal;
    protected ContentPartialService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = $this->container->get(ContentPartialModalModule::class);
        $this->uiModal = $this->container->get(UIModal::class);
        $this->service = $this->container->get(ContentPartialService::class);
    }

    private function createGlobalModal(string $title = 'Test Modal', string $slug = 'test-modal'): int
    {
        $postId = $this->factory()->post->create([
            'post_title' => $title,
            'post_name' => $slug,
            'post_type' => ContentPartialPost::POST_TYPE,
            'meta_input' => ['is_global' => '1'],
        ]);
        wp_set_object_terms($postId, 'modal', ContentPartialPost::TAXONOMY);
        return $postId;
    }

    public function testGlobalModalInjectedIntoQueue(): void
    {
        $this->createGlobalModal();
        $this->module->injectGlobalModals();
        $this->assertTrue($this->uiModal->isLoaded('test-modal'));
    }

    public function testNonGlobalModalNotInjected(): void
    {
        $postId = $this->factory()->post->create([
            'post_title' => 'Non-Global Modal',
            'post_name' => 'non-global-modal',
            'post_type' => ContentPartialPost::POST_TYPE,
        ]);
        wp_set_object_terms($postId, 'modal', ContentPartialPost::TAXONOMY);

        $this->module->injectGlobalModals();
        $this->assertFalse($this->uiModal->isLoaded('non-global-modal'));
    }

    public function testDuplicateModalNotRenderedTwice(): void
    {
        $postId = $this->createGlobalModal();
        $post = \Timber\Timber::get_post($postId);
        $this->uiModal->loadModal(ModalData::fromPost($post, 'full'));

        $this->module->injectGlobalModals();

        ob_start();
        $this->uiModal->unloadModals();
        $output = ob_get_clean();

        $this->assertEquals(1, substr_count($output, 'id="test-modal"'));
    }

    public function testNoModalsNotInjected(): void
    {
        $postId = $this->factory()->post->create([
            'post_title' => 'Page-Only Modal',
            'post_name' => 'page-only-modal',
            'post_type' => ContentPartialPost::POST_TYPE,
        ]);
        wp_set_object_terms($postId, 'modal', ContentPartialPost::TAXONOMY);

        $this->module->injectGlobalModals();
        $this->assertFalse($this->uiModal->isLoaded('page-only-modal'));
    }
}
