<?php

namespace Sitchco\Parent\SiteHeader;

use Sitchco\Parent\ContentPartial\ContentPartialSiteModule;
use Timber\Post;

/**
 * class SiteHeaderModule
 * @package Sitchco\Parent\SiteHeader
 */
class SiteHeaderModule extends ContentPartialSiteModule
{
    protected function getTemplateArea(): string
    {
        return 'header';
    }

    protected function getContextKey(): string
    {
        return 'site_header';
    }

    protected function findOverrideFromPage(): ?Post
    {
        return $this->repository->findHeaderOverrideFromPage();
    }

    protected function findDefault(): ?Post
    {
        return $this->repository->findDefaultHeader();
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
