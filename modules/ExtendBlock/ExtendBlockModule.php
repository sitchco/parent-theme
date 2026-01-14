<?php

namespace Sitchco\Parent\Modules\ExtendBlock;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;

class ExtendBlockModule extends Module
{
    public function init(): void
    {
        $this->enqueueEditorUIAssets(function (ModuleAssets $assets) {
            $assets->enqueueScript('extend-block', 'extend-block.js', [
                'wp-hooks',
                'wp-compose',
                'wp-block-editor',
                'wp-components',
                'wp-data',
                'wp-element',
                'sitchco/ui-framework',
            ]);
        }, 1);
    }
}
