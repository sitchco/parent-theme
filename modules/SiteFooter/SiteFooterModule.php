<?php

namespace Sitchco\Parent\Modules\SiteFooter;

use Sitchco\Parent\Modules\ContentPartial\ContentPartialModule;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialRepository;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialService;
use Sitchco\Framework\Module;

/**
 * class SiteFooterModulenamespace SitchcoParentModulesSiteHeader;
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
    protected ContentPartialRepository $repository;

    public function __construct(ContentPartialService $contentService, ContentPartialRepository $repository)
    {
        $this->contentService = $contentService;
        $this->repository = $repository;
    }

    public function init(): void
    {
        $this->contentService->addModule('footer', $this);
    }

    // TODO: create trait to abstract this between SiteHeader/SiteFooter modules
    public function getContext(string $templateArea): ?array
    {
        $partial = $this->repository->findPartialOverrideFromPage($templateArea) ?? $this->repository->findDefaultPartial($templateArea);
        return $partial?->post_name ? ['name' => $partial->post_name, 'content' => $partial?->content()] : null;
    }

    public function registerBlockPatterns(): void
    {
        add_action('init', [FooterBlockPatterns::class, 'init'], 11);
    }
}
