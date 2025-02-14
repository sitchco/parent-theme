<?php

use Sitchco\Parent\SiteHeader\SiteHeaderModule;
use Sitchco\Parent\SiteFooter\SiteFooterModule;
use Sitchco\Parent\ContentPartial\ContentPartialBlockModule;
use Sitchco\Integration\Wordpress\Cleanup;

return [
    Cleanup::class => [
        'disableGutenbergBlockCss' => false,
        'removeGutenbergStyles' => false
    ],
    SiteHeaderModule::class => true,
    SiteFooterModule::class =>true,
    ContentPartialBlockModule::class => true
];