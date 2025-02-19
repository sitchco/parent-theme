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

    public function init(): void
    {
        // TODO temporary shim to register content partial block block.json
        add_action('init', function () {
            error_log(get_template_directory() . '/src/ContentPartial/blocks/content-partial-block/block.json');
            register_block_type(get_template_directory() . '/src/ContentPartial/blocks/content-partial-block/block.json');
        });
    }
}