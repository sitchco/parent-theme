<?php

namespace Sitchco\Parent\Modules\ContentPartial;

use Sitchco\Utils\BlockPattern;

/**
 * class ContentPartialBlockPatterns
 * @package Sitchco\Parent\ContentPartial
 *
 * TODO: this should get folded into Module (this is a temporary class)
 */
class ContentPartialBlockPatterns
{
    public static function init(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action('current_screen', function ($screen) {
            if ($screen && $screen->id === ContentPartialPost::POST_TYPE) {
                $files_to_register = glob(self::getBlockPatternDir() . '/*');
                foreach($files_to_register as $file) {
                    self::register(pathinfo($file, PATHINFO_FILENAME));
                }
            }
        });
    }

    public static function register(string $name): void
    {
        BlockPattern::register($name, self::getBlockPatternDir() . "/$name.json");
    }

    private static function getBlockPatternDir(): string
    {
        $reflector = new \ReflectionClass(static::class);
        return dirname($reflector->getFileName()) . '/block-patterns';
    }
}