<?php

namespace Sitchco\Parent\Modules\KadenceImageModal;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;
use Sitchco\Modules\SvgSprite\SvgSprite;
use Sitchco\Modules\UIModal\UIModal;
use Sitchco\Parent\Modules\ExtendBlock\ExtendBlockModule;
use Sitchco\Parent\Modules\KadenceBlocks\KadenceBlocks;

class KadenceImageModal extends Module
{
    public const HOOK_SUFFIX = 'kadence-image-modal';

    public const DEPENDENCIES = [UIModal::class, KadenceBlocks::class, SvgSprite::class];

    public function __construct(private readonly UIModal $uiModal, private readonly KadenceImageRenderer $renderer) {}

    public function init(): void
    {
        $this->uiModal->registerType('image');

        add_filter('render_block_kadence/image', [$this->renderer, 'render'], 15, 2);

        $this->enqueueGlobalAssets(function (ModuleAssets $assets) {
            $assets->enqueueStyle(static::hookName(), 'main.css');
        });

        $this->enqueueEditorUIAssets(function (ModuleAssets $assets) {
            $assets->enqueueScript(static::hookName('editor-ui'), 'editor-ui.js', [
                'wp-blocks',
                'wp-element',
                'wp-hooks',
                ExtendBlockModule::hookName(),
            ]);
        });
    }
}
