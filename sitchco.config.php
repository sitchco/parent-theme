<?php

use Sitchco\Integration\Wordpress\Cleanup;
use Sitchco\Parent\ContentPartial\ContentPartialModule;
use Sitchco\Parent\ContentPartialBlock\ContentPartialBlockModule;
use Sitchco\Parent\PageOrder\PageOrderModule;
use Sitchco\Parent\SiteFooter\SiteFooterModule;
use Sitchco\Parent\SiteHeader\SiteHeaderModule;
use Sitchco\Parent\Theme;

return [
    'modules' => [
        Cleanup::class => [
            'disableGutenbergStyles' => false,
        ],
        ContentPartialModule::class,
        ContentPartialBlockModule::class,
        SiteHeaderModule::class,
        SiteFooterModule::class,
        PageOrderModule::class,
        Theme::class
    ]
];
