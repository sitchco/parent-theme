<?php

namespace Sitchco\Parent\Tests\Support;

use Sitchco\Parent\Modules\Animation\AnimationModule;

/**
 * A second animation, distinctly keyed — and keyed by an override that differs from its
 * HOOK_SUFFIX, which is the escape hatch an animation needs after a rename.
 */
class SecondAnimationTester extends AnimationModule
{
    public const HOOK_SUFFIX = 'second-animation-tester';

    public function key(): string
    {
        return 'second-tester';
    }

    public function label(): string
    {
        return 'Second Animation Tester';
    }
}
