<?php

namespace Sitchco\Parent\Modules\SiteHeader;

use Sitchco\Parent\Modules\ContentPartial\ContentPartialModule;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialService;
use Sitchco\Framework\Module;

class SiteHeaderModule extends Module
{
    const DEPENDENCIES = [ContentPartialModule::class];

    public const FEATURES = ['registerBlockPatterns', 'loadAssets'];

    protected ContentPartialService $contentService;

    public function __construct(ContentPartialService $contentService)
    {
        $this->contentService = $contentService;
    }

    public function init(): void
    {
        $this->contentService->addTemplateArea('header');
        add_action('init', [$this, 'setupAssets'], 5);
    }

    public function registerBlockPatterns(): void
    {
        $this->contentService->addBlockPatterns('header', $this->path());
    }

    public function loadAssets(): void
    {
        add_action('wp_enqueue_scripts', function () {
            $this->enqueueStyle(static::hookName());
            $this->enqueueScript(static::hookName());
        });
    }

    public function setupAssets(): void
    {
        $handle = static::hookName();
        $this->registerScript($handle, $this->scriptUrl('main.mjs'), ['wp-hooks']);
        $this->registerStyle($handle, $this->styleUrl('main.css'));
    }
}
