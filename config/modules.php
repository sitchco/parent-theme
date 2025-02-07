<?php

use Sitchco\Parent\SiteHeader\SiteHeaderModule;
use Sitchco\Integration\Wordpress\Cleanup;

return [
    Cleanup::class => [
        'disableGutenbergBlockCss' => false,
        'removeGutenbergStyles' => false
    ],
    SiteHeaderModule::class => true
];