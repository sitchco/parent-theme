<?php

namespace Sitchco\Parent\Tests\Support;

use Sitchco\Parent\Modules\ContentPartial\ContentPartialService;
use Sitchco\Framework\Module;

class ModuleTester extends Module
{
    const DEPENDENCIES = [];

    public const POST_CLASSES = [];

    const FEATURES = [];

    protected ContentPartialService $contentService;

    public bool $initialized = false;

    public function __construct(ContentPartialService $contentService)
    {
        $this->contentService = $contentService;
    }

    public function init(): void
    {
        $this->initialized = true;
        $this->contentService->addTemplateArea('sidebar');
    }
}
