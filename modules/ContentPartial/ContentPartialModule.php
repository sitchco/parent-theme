<?php

namespace Sitchco\Parent\Modules\ContentPartial;

use Sitchco\Framework\Module;

/**
 * class ContentPartialModulenamespace SitchcoParentModulesSiteHeader;
 */
class ContentPartialModule extends Module
{
    public const POST_CLASSES = [
        ContentPartialPost::class,
    ];

    protected ContentPartialService $contentService;
    protected ContentPartialRepository $repository;

    public function __construct(ContentPartialService $contentService, ContentPartialRepository $repository)
    {
        $this->contentService = $contentService;
        $this->repository = $repository;
    }

    public function init(): void
    {
        if (is_admin()) {
            add_action('current_screen', [$this->contentService, 'ensureTaxonomyTermExists']);
            add_action('current_screen', [$this->contentService, 'registerBlockPatterns']);
        }
        add_action('init', [$this->contentService, 'setContext']);
    }
}