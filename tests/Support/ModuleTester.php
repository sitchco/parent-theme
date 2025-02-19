<?php

namespace Sitchco\Parent\Tests\Support;

use Sitchco\Framework\Core\Module;

class ModuleTester extends Module
{
    const DEPENDENCIES = [];

    public const POST_CLASSES = [];

    const FEATURES = [];

    public bool $initialized = false;

    public function init(): void
    {
        $this->initialized = true;
    }
}