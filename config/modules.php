<?php

use Sitchco\Parent\ContentPartial\ContentPartialModule;
use Sitchco\Parent\SiteHeader\SiteHeaderModule;
use Sitchco\Parent\SiteFooter\SiteFooterModule;
use Sitchco\Parent\ContentPartial\ContentPartialBlockModule;
use Sitchco\Integration\Wordpress\Cleanup;

return [
    Cleanup::class => [
        'disableGutenbergBlockCss' => false,
        'removeGutenbergStyles' => false
    ],
    ContentPartialModule::class => true,
    ContentPartialBlockModule::class => true,
    SiteHeaderModule::class => true,
    SiteFooterModule::class =>true,
];