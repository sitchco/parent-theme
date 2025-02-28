<?php

use Sitchco\Integration\Wordpress\Cleanup;
use Sitchco\Parent\ContentPartial\ContentPartialModule;
use Sitchco\Parent\ContentPartialBlock\ContentPartialBlockModule;
use Sitchco\Parent\SiteFooter\SiteFooterModule;
use Sitchco\Parent\SiteHeader\SiteHeaderModule;

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