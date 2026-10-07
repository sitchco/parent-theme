<?php

namespace Sitchco\Parent\Tests\Support;

use Sitchco\Parent\Modules\Animation\AnimationControl;
use Sitchco\Parent\Modules\Animation\AnimationModule;

/**
 * A second animation, distinctly keyed — and keyed by an override that differs from its
 * HOOK_SUFFIX, which is the escape hatch an animation needs after a rename.
 *
 * Its controls cover what AnimationTester's do not: a number, a select with a non-empty default of
 * its own, and a select whose options come from a JS hook. The hyphenated key also exercises
 * attribute naming: `speed` is stored as secondTesterAnimationSpeed.
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

    public function controls(): array
    {
        return [
            AnimationControl::number('speed', 'Speed', ['default' => 50, 'min' => 0, 'max' => 100, 'step' => 5]),
            AnimationControl::select('direction', 'Direction', [
                'options' => [['label' => 'Up', 'value' => 'up'], ['label' => 'Down', 'value' => 'down']],
                'default' => 'up',
            ]),
            AnimationControl::select('tint', 'Tint', ['optionsFilter' => 'test.tint-options']),
            AnimationControl::text('caption', 'Caption', ['default' => 'hello']),
        ];
    }
}
