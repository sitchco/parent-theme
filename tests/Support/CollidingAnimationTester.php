<?php

namespace Sitchco\Parent\Tests\Support;

use Sitchco\Parent\Modules\Animation\AnimationControl;
use Sitchco\Parent\Modules\Animation\AnimationModule;

/**
 * The other half of MalformedControlsAnimationTester's attribute collision. Its key extends that
 * animation's with `-animation-edge`, so its `speed` builds the same attribute as the malformed
 * tester's `edgeAnimationSpeed`: malformedControlsTesterAnimationEdgeAnimationSpeed.
 */
class CollidingAnimationTester extends AnimationModule
{
    public const HOOK_SUFFIX = 'colliding-animation-tester';

    public function key(): string
    {
        return 'malformed-controls-tester-animation-edge';
    }

    public function label(): string
    {
        return 'Colliding Animation Tester';
    }

    public function controls(): array
    {
        return [AnimationControl::number('speed', 'Collides')];
    }
}
