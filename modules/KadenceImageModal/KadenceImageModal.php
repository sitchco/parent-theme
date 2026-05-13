<?php

namespace Sitchco\Parent\Modules\KadenceImageModal;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;
use Sitchco\Modules\UIModal\UIModal;
use Sitchco\Parent\Modules\ExtendBlock\ExtendBlockModule;

class KadenceImageModal extends Module
{
    public const HOOK_SUFFIX = 'kadence-image-modal';

    public const DEPENDENCIES = [UIModal::class, ExtendBlockModule::class];

    public function __construct(private readonly UIModal $uiModal) {}

    public function init(): void
    {
        $this->uiModal->registerType('image');

        $this->enqueueGlobalAssets(function (ModuleAssets $assets) {
            $assets->enqueueStyle(static::hookName(), 'main.css');
        });
    }
}
