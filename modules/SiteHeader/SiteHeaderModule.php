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

        // This is the correct way to enqueue assets for the front-end.
        $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
            $handle = 'site-header'; // The framework will namespace this automatically.
            $assets->enqueueStyle($handle, 'main.css');
            $assets->enqueueScript($handle, 'main.mjs', ['wp-hooks']);
        });
    }

    public function registerBlockPatterns(): void
    {
        $this->contentService->addBlockPatterns('header', $this->path());
    }
}