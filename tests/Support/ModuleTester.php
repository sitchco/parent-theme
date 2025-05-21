<?php

namespace Sitchco\Parent\Tests\Support;

use Sitchco\Framework\Module;
use Sitchco\Parent\ContentPartial\ContentPartialRepository;
use Sitchco\Parent\ContentPartial\ContentPartialService;

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
        $this->contentService->addModule('sidebar', $this);
    }
}