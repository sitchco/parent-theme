<?php

namespace Sitchco\Parent\SiteFooter;

use Sitchco\Parent\ContentPartial\ContentPartialSiteModule;
use Timber\Post;

/**
 * class SiteFooterModule
 * @package Sitchco\Parent\SiteFooter
 */
class SiteFooterModule extends ContentPartialSiteModule
{
    protected function getTemplateArea(): string
    {
        return 'footer';
    }

    protected function getContextKey(): string
    {
        return 'site_footer';
    }

    protected function findOverrideFromPage(): ?Post
    {
        return $this->repository->findFooterOverrideFromPage();
    }

    protected function findDefault(): ?Post
    {
        return $this->repository->findDefaultFooter();
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
        add_action('init', [FooterBlockPatterns::class, 'init'], 11);
    }
}
