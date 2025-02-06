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

    public function init(): void
    {
        add_filter('timber/context', [$this, 'setTimberContext']);
    }

    public function setTimberContext(array $context): array
    {
        $repository = new ContentPartialRepository();
        $header = $repository->findHeaderFromPage() ?? $repository->findDefaultHeader();
        $context['site_header'] = $header?->content();
        return $context;
    }
}
