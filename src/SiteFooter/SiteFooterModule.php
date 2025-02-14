<?php

namespace Sitchco\Parent\SiteFooter;

use Sitchco\Framework\Core\Module;
use Sitchco\Parent\ContentPartial\ContentPartialModule;
use Sitchco\Parent\ContentPartial\ContentPartialService;

/**
 * class SiteFooterModule
 * @package Sitchco\Parent\SiteFooter
 */
class SiteFooterModule extends Module
{
    const DEPENDENCIES = [
        ContentPartialModule::class
    ];

    public const FEATURES = [
        'registerBlockPatterns'
    ];

    protected ContentPartialService $contentService;

    public function __construct(ContentPartialService $contentService)
    {
        $this->contentService = $contentService;
    }

    public function init(): void
    {
        $this->contentService->registerContentFilters('footer');
        $this->contentService->ensureTaxonomyTermExists('footer');
    }

    public function registerBlockPatterns(): void
    {
        add_action('init', [FooterBlockPatterns::class, 'init'], 11);
    }
}
