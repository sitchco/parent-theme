<?php

namespace Sitchco\Parent\ContentPartial;

use Sitchco\Framework\Module;

/**
 * class ContentPartialModule
 * @package Sitchco\Parent\ContentPartial
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