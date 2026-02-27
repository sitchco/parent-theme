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

    public const FEATURES = ['registerBlockPatterns', 'overlayHeader', 'stickyHeader'];

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
        add_filter('body_class', function (array $classes): array {
            if (!empty(get_field('header_overlay'))) {
                $classes[] = 'has-overlay-header';
            }
            return $classes;
        });
    }

    public function stickyHeader(): void
    {
        add_filter('body_class', function (array $classes): array {
            if ($this->contentService->getPartial('header')?->is_sticky) {
                $classes[] = 'has-sticky-header';
            }
            return $classes;
        });

        $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
            if (!$this->contentService->getPartial('header')?->is_sticky) {
                return;
            }
            $assets->enqueueStyle('site-header', 'main.css');
            $assets->enqueueScript('site-header/sticky/js', 'sticky.js', ['sitchco/ui-framework']);
        });
    }

    public function addPageContextToSiteHeader(array $context): array
    {
        $context['site_header']->is_overlaid = !empty(get_field('header_overlay'));
        return $context;
    }
}
