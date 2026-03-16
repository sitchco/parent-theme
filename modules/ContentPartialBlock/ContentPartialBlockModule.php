<?php

namespace Sitchco\Parent\Modules\ContentPartialBlock;

use Sitchco\Parent\Modules\ContentPartial\ContentPartialModule;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialService;
use Sitchco\Framework\Module;

/**
 * class ContentPartialBlockModulenamespace SitchcoParentModulesSiteHeader;
 */
class ContentPartialBlockModule extends Module
{
    const DEPENDENCIES = [ContentPartialModule::class];

    protected ContentPartialService $contentService;

    public function __construct(ContentPartialService $contentService)
    {
        $this->contentService = $contentService;
    }

    public function init(): void
    {
        $this->contentService->addTemplateArea('block', false);
    }
}
