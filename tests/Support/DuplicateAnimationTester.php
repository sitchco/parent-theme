<?php

namespace Sitchco\Parent\Tests\Support;

use Sitchco\Parent\Modules\Animation\AnimationModule;

/**
 * Overrides key() onto the key AnimationTester takes by default, which is the whole point: the
 * collision has to be a logged error rather than one animation silently replacing the other.
 */
class DuplicateAnimationTester extends AnimationModule
{
    public const HOOK_SUFFIX = 'duplicate-animation-tester';

    public function key(): string
    {
        return 'animation-tester';
    }

    public function label(): string
    {
        return 'Duplicate Animation Tester';
    }
}
