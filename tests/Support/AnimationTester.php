<?php

namespace Sitchco\Parent\Tests\Support;

use Sitchco\Parent\Modules\Animation\AnimationModule;

/**
 * Minimal animation, standing in for a real one wherever the AnimationModule type itself is what a
 * test exercises. Declares no key(), so it also covers the default of keying off HOOK_SUFFIX.
 */
class AnimationTester extends AnimationModule
{
    public const HOOK_SUFFIX = 'animation-tester';

    public function label(): string
    {
        return 'Animation Tester';
    }
}
