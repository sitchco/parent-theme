<?php

namespace Sitchco\Parent\SiteHeader;

use Sitchco\Framework\Core\Module;
use Sitchco\Parent\ContentPartial\ContentPartialModule;
use Sitchco\Parent\ContentPartial\ContentPartialRepository;

/**
 * Class SiteHeaderModule
 * Handles setting the header content within the Timber context.
 */
class SiteHeaderModule extends Module
{
    const DEPENDENCIES = [
        ContentPartialModule::class
    ];

    public const FEATURES = [
        'registerBlockPatterns'
    ];

    protected ContentPartialRepository $repository;

    public function __construct(ContentPartialRepository $repository)
    {
        $this->repository = $repository;
    }

    public function init(): void
    {
        add_filter('timber/context', [$this, 'setContext']);
    }

    public function setContext(array $context): array
    {
        $header = $this->repository->findHeaderFromPage() ?? $this->repository->findDefaultHeader();
        $context['site_header'] = $header->post_name ? ['name' => $header->post_name, 'content' => $header?->content()] : null;
        return $context;
    }

    public function registerBlockPatterns(): void
    {
        /*
         * Priority needs to be 11 or higher since we are removing all
         * default WordPress block patterns in Sitchco\Cleanup.php
         * at priority 10.
         *
         * Feature: removeDefaultBlockPatterns
         */
        add_action('init', [HeaderBlockPatterns::class, 'init'], 11);
    }
}
