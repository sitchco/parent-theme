<?php

namespace Sitchco\Parent\SiteFooter;

use Sitchco\Framework\Core\Module;
use Sitchco\Parent\ContentPartial\ContentPartialModule;;

/**
 * Class SiteFooterModule
 * Handles setting the footer content within the Timber context.
 */
class SiteFooterModule extends Module
{
    const DEPENDENCIES = [
        ContentPartialModule::class
    ];

//    public function init(): void
//    {
//        add_filter('timber/context', [$this, 'setTimberContext']);
//    }
//
//    public function setTimberContext(array $context): array
//    {
//        $repository = new ContentPartialRepository();
//        $footer = $repository->findFooterFromPage() ?? $repository->findDefaultFooter();
//        $context['site_footer'] = $footer?->content();
//        return $context;
//    }
}
