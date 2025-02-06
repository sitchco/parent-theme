<?php

namespace Sitchco\Parent\ContentPartial;

use Sitchco\Utils\BlockPattern;

/**
 * class ContentPartialBlockPatterns
 * @package Sitchco\Parent\ContentPartial
 */
class ContentPartialBlockPatterns
{
    const BLOCK_PATTERN_DIR = __DIR__ . '/block-patterns';

    public static function init(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action('current_screen', function ($screen) {
            if ($screen && $screen->id === ContentPartialPost::POST_TYPE) {
                self::register('standard-header');
                self::register('standard-header-no-cta');
                self::register('standard-header-swapped');
                self::register('standard-header-swapped-no-cta');
                self::register('standard-header-no-site-logo');
            }
        });
    }

    public static function register(string $name): void
    {
        BlockPattern::register($name, self::BLOCK_PATTERN_DIR . "/$name.json");
    }
}