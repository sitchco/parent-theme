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
        $this->container->get(UIModal::class)->filterModalPostQuery([
            'post_type' => ContentPartialPost::POST_TYPE,
            'tax_query' => [
                [
                    'taxonomy' => ContentPartialPost::TAXONOMY,
                    'field' => 'slug',
                    'terms' => 'modal',
                ],
            ],
        ]);
    }
}
