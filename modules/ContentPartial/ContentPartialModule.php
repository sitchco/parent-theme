<?php

namespace Sitchco\Parent\Modules\ContentPartial;

use Sitchco\Framework\Module;

/**
 * class ContentPartialModulenamespace SitchcoParentModulesSiteHeader;
 */
class ContentPartialModule extends Module
{
    public const POST_CLASSES = [
        ContentPartialPost::class,
    ];

    public function init(): void
    {
    }
}