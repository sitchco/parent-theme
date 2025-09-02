<?php

namespace Sitchco\Parent\Modules\SiteHeader;

use Sitchco\Parent\Modules\ContentPartial\ContentPartialModule;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialService;
use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;

class SiteHeaderModule extends Module
{
    const DEPENDENCIES = [ContentPartialModule::class];

    public const FEATURES = ['registerBlockPatterns'];

    protected ContentPartialService $contentService;

    public function __construct(ContentPartialService $contentService)
    {
        $this->contentService = $contentService;
    }

    public function init(): void
    {
        $this->contentService->addTemplateArea('header');

        $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
            $handle = 'site-header';
            $assets->enqueueStyle($handle, 'main.css');
            $assets->enqueueScript($handle, 'main.mjs', ['sitchco/ui-framework']);
        });
    }

    public function registerBlockPatterns(): void
    {
        $this->contentService->addBlockPatterns('header', $this->path());
    }
}