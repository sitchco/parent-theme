<?php

namespace Sitchco\Parent\ContentPartial;

use Sitchco\Framework\Core\Module;

/**
 * class ContentPartialBlockModule
 * @package Sitchco\Parent\SiteHeader
 */
class ContentPartialBlockModule extends Module
{
    const DEPENDENCIES = [
        ContentPartialModule::class
    ];

    protected ContentPartialService $contentService;

    public function __construct(ContentPartialService $contentService)
    {
        $this->contentService = $contentService;
    }

    public function init(): void
    {
        $this->contentService->ensureTaxonomyTermExists('block');
    }
}
