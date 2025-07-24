<?php

namespace Sitchco\Parent\Modules\ContentPartial;

use Sitchco\Framework\Module;
use Sitchco\Modules\TimberModule;

class ContentPartialModule extends Module
{
    public const POST_CLASSES = [ContentPartialPost::class];

    public const DEPENDENCIES = [TimberModule::class];

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
        add_action('wp', [$this->contentService, 'setContext']);
    }
}
