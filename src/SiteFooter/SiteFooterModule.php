<?php

namespace Sitchco\Parent\SiteFooter;

use Sitchco\Framework\Module;
use Sitchco\Parent\ContentPartial\ContentPartialModule;
use Sitchco\Parent\ContentPartial\ContentPartialRepository;
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
