<?php

namespace Sitchco\Parent\Tests\Support;

use Sitchco\Parent\Modules\Animation\AnimationControl;
use Sitchco\Parent\Modules\Animation\AnimationModule;

/**
 * An animation whose controls break every definition rule the coordinator enforces, alongside one
 * that is fine. A fake of its own because the AnimationModule contract is what is under test here.
 *
 * Keyed `second` so that its `testerSpeed` meets SecondAnimationTester's `speed` in one attribute
 * name, secondTesterSpeed: distinct keys and distinct control names, one collision.
 */
class MalformedControlsAnimationTester extends AnimationModule
{
    public const HOOK_SUFFIX = 'malformed-controls-tester';

    public function key(): string
    {
        return 'second';
    }

    public function label(): string
    {
        return 'Malformed Controls Tester';
    }

    public function controls(): array
    {
        return [
            'not a control',
            AnimationControl::text('Bad-Name', 'Bad name'),
            AnimationControl::select('noOptions', 'No options'),
            AnimationControl::select('bothOptions', 'Both', [
                'options' => [['label' => 'A', 'value' => 'a']],
                'optionsFilter' => 'test.tint-options',
            ]),
            AnimationControl::select('looseOptions', 'Loose options', ['options' => ['a', 'b']]),
            AnimationControl::number('testerSpeed', 'Collides'),
            AnimationControl::toggle('ok', 'Kept'),
            AnimationControl::toggle('ok', 'Declared twice'),
        ];
    }
}
