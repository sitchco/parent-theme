<?php

//use Sitchco\Integration\Wordpress\Cleanup;
use Sitchco\Parent\ContentPartial\ContentPartialModule;
use Sitchco\Parent\ContentPartialBlock\ContentPartialBlockModule;
use Sitchco\Parent\PageOrder\PageOrderModule;
use Sitchco\Parent\SiteFooter\SiteFooterModule;
use Sitchco\Parent\SiteHeader\SiteHeaderModule;
use Sitchco\Parent\Theme;

return [
    // TODO: it seems we are turning off all features when we override this way.
//    Cleanup::class => [
//        'disableGutenbergBlock' => false,
//    ],
    ContentPartialModule::class => true,
    ContentPartialBlockModule::class => true,
    SiteHeaderModule::class => true,
    SiteFooterModule::class =>true,
    PageOrderModule::class => true,
    Theme::class => true
];