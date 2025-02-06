<?php

namespace Sitchco\Parent\ContentPartial;

use Sitchco\Framework\Core\Module;

/**
 * class ContentPartialModule
 * @package Sitchco\Parent\ContentPartial
 */
class ContentPartialModule extends Module
{
    public const POST_CLASSES = [
        ContentPartialPost::class,
    ];

    public const FEATURES = [
        'registerBlockPatterns'
    ];

    public function registerBlockPatterns(): void
    {
        add_action('init', [ContentPartialBlockPatterns::class, 'register'], 11);
    }
}