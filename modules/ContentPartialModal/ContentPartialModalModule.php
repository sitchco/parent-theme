<?php

namespace Sitchco\Parent\Modules\ContentPartialModal;

use Psr\Container\ContainerInterface;
use Sitchco\Modules\UIModal\UIModal;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialModule;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialPost;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialService;
use Sitchco\Framework\Module;

class ContentPartialModalModule extends Module
{
    const DEPENDENCIES = [ContentPartialModule::class, UIModal::class];

    public function __construct(
        protected ContentPartialService $contentService,
        protected ContainerInterface $container,
    ) {}

    public function init(): void
    {
        $this->contentService->addTemplateArea('modal');
        $this->container->get(UIModal::class)->filterModalPostQuery(fn() => $this->buildModalQuery());
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
