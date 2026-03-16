<?php

namespace Sitchco\Parent\Modules\ContentPartialModal;

use Psr\Container\ContainerInterface;
use Sitchco\Modules\UIModal\ModalData;
use Sitchco\Modules\UIModal\UIModal;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialModule;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialPost;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialRepository;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialService;
use Sitchco\Framework\Module;

class ContentPartialModalModule extends Module
{
    const DEPENDENCIES = [ContentPartialModule::class, UIModal::class];

    public function __construct(
        protected ContentPartialService $contentService,
        protected ContentPartialRepository $repository,
        protected ContainerInterface $container,
    ) {}

    public function init(): void
    {
        $this->contentService->addTemplateArea('modal', false);
        $uiModal = $this->container->get(UIModal::class);
        $uiModal->filterModalPostQuery(fn() => $this->buildModalQuery());
        add_action('wp_footer', [$this, 'injectGlobalModals'], 9);
        add_filter('acf/prepare_field/key=field_69b86d0ff1f97', [$uiModal, 'typeFieldChoices']);
    }

    public function injectGlobalModals(): void
    {
        $termId = $this->contentService->getTermId('modal');
        if (!$termId) {
            return;
        }
        $posts = $this->repository->findAll([
            'no_found_rows' => true,
            'tax_query' => [
                [
                    'taxonomy' => ContentPartialPost::TAXONOMY,
                    'field' => 'term_id',
                    'terms' => $termId,
                ],
            ],
            'meta_query' => [
                [
                    'key' => 'is_global',
                    'value' => '1',
                    'compare' => '=',
                ],
            ],
        ]);
        $uiModal = $this->container->get(UIModal::class);
        foreach ($posts as $post) {
            $slug = sanitize_title($post->post_name);
            if ($uiModal->isLoaded($slug)) {
                continue;
            }
            $type = (string) get_field('modal_type', $post->ID) ?: 'box';
            if (!$uiModal->isRegistered($type)) {
                $type = 'box';
            }
            $uiModal->loadModal(ModalData::fromPost($post, $type));
        }
    }

    private function buildModalQuery(): array
    {
        $query = ['post_type' => ContentPartialPost::POST_TYPE];
        $termId = $this->contentService->getTermId('modal');
        if ($termId) {
            $query['tax_query'] = [
                [
                    'taxonomy' => ContentPartialPost::TAXONOMY,
                    'field' => 'term_id',
                    'terms' => $termId,
                ],
            ];
        }
        return $query;
    }
}
