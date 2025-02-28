<?php

namespace Sitchco\Parent\ContentPartialBlock;

use Sitchco\Framework\Core\Module;
use Sitchco\Parent\ContentPartial\ContentPartialModule;
use Sitchco\Parent\ContentPartial\ContentPartialService;

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
        $this->contentService->addModule('block', $this);
    }
}
