<?php

namespace Sitchco\Parent\Tests\Support;

use Sitchco\Parent\Modules\Animation\AnimationModule;

/**
 * Returns a reducedMotion() value that is neither constant, which is logged and treated as the
 * framework's.
 */
class InvalidMotionAnimationTester extends AnimationModule
{
    public const HOOK_SUFFIX = 'invalid-motion-tester';

    public function label(): string
    {
        return 'Invalid Motion Tester';
    }

    public function reducedMotion(): string
    {
        return 'sometimes';
    }
}
