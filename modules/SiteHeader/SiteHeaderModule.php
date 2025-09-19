<?php

namespace Sitchco\Parent\Modules\SiteHeader;

use Sitchco\Parent\Modules\ContentPartial\ContentPartialModule;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialService;
use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;

class SiteHeaderModule extends Module
{
    const DEPENDENCIES = [ContentPartialModule::class];

    public const FEATURES = [
        'registerBlockPatterns',
        'overlayHeader',
        'stickyHeader',
    ];

    protected ContentPartialService $contentService;

    public function __construct(ContentPartialService $contentService)
    {
        $this->contentService = $contentService;
        $this->enqueueFrontendAssets(function(ModuleAssets $assets) {
            $assets->enqueueScript('site-header/frontend', 'index.js', ['sitchco/ui-framework']);
        });
        $this->enqueueAdminAssets(function(ModuleAssets $assets) {
            $assets->enqueueScript('site-header/admin', 'admin-index.js', ['wp-dom-ready', 'wp-data']);
        });
    }

    public function init(): void
    {
        $this->contentService->addTemplateArea('header');
    }

    public function registerBlockPatterns(): void
    {
        $this->contentService->addBlockPatterns('header', $this->path());
    }

    public function overlayHeader(): void
    {
        $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
            $assets->enqueueStyle('site-header/overlay', 'overlay.css');
        });
    }

    public function stickyHeader(): void
    {
        $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
            $handle = 'site-header/sticky';
            $assets->registerStyle('site-header/overlay', 'overlay.css');
            $assets->enqueueStyle($handle, 'sticky.css', ['sitchco/site-header/overlay']);
            $assets->enqueueScript($handle, 'sticky.js', ['sitchco/ui-framework']);
        });
    }
}