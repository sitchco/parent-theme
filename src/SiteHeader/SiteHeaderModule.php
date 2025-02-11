<?php

namespace Sitchco\Parent\SiteHeader;

use Sitchco\Framework\Core\Module;
use Sitchco\Parent\ContentPartial\ContentPartialModule;
use Sitchco\Parent\ContentPartial\ContentPartialService;
use Sitchco\Parent\ContentPartial\ContentPartialPost;

/**
 * class SiteHeaderModule
 * @package Sitchco\Parent\SiteHeader
 */
class SiteHeaderModule extends Module
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
        $this->contentService->registerContentFilters('header');
        $this->contentService->ensureTaxonomyTermExists('header');
    }

    public function registerBlockPatterns(): void
    {
        add_action('init', [HeaderBlockPatterns::class, 'init'], 11);
    }
}
