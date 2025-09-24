<?php

namespace Sitchco\Parent\Modules\SiteHeader;

use Sitchco\Parent\Modules\ContentPartial\ContentPartialModule;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialService;
use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;
use Sitchco\Utils\Hooks;

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
    }

    public function init(): void
    {
        $this->contentService->addTemplateArea('header');
        add_filter(Hooks::name('template-context/partials/site-header'), [$this, 'addPageContextToSiteHeader'], 99, 1);
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

    public function addPageContextToSiteHeader(array $context): array
    {
        $header_overlay = get_field('header_overlay');
        if ($header_overlay === 'overlay') {
            $context['site_header']->is_overlaid = true;
        } elseif ($header_overlay === 'separate') {
            $context['site_header']->is_overlaid = false;
        }
        return $context;
    }
}