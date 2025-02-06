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

    public function init(): void
    {
        add_filter('timber/context', [$this, 'setContext']);
    }

    public function setContext(array $context): array
    {
        $repository = new ContentPartialRepository();
        $header = $repository->findHeaderFromPage() ?? $repository->findDefaultHeader();
        $context['site_header'] = $header?->content();
        return $context;
    }

    public function registerBlockPatterns(): void
    {
        /*
         * Priority needs to be 11 here since we are removing all
         * default wordpress block patterns in Sitchco\Cleanup.php
         *
         * Feature: removeDefaultBlockPatterns
         */
        add_action('init', [HeaderBlockPatterns::class, 'init'], 11);
    }
}
