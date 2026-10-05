<?php

namespace Sitchco\Parent\Tests\Support;

use Sitchco\Parent\Modules\Animation\AnimationControl;
use Sitchco\Parent\Modules\Animation\AnimationModule;

/**
 * An animation whose controls break every definition rule the coordinator enforces, alongside one
 * that is fine. A fake of its own because the AnimationModule contract is what is under test here.
 *
 * Keyed `secondTester` so that its `speed` meets SecondAnimationTester's (keyed `second-tester`) in
 * one attribute name, secondTesterAnimationSpeed: distinct keys, one collision.
 */
class MalformedControlsAnimationTester extends AnimationModule
{
    public const HOOK_SUFFIX = 'malformed-controls-tester';

    public function key(): string
    {
        return 'secondTester';
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
            AnimationControl::text("trailingNewline\n", 'Trailing newline'),
            AnimationControl::select('noOptions', 'No options'),
            AnimationControl::select('bothOptions', 'Both', [
                'options' => [['label' => 'A', 'value' => 'a']],
                'optionsFilter' => 'test.tint-options',
            ]),
            AnimationControl::select('looseOptions', 'Loose options', ['options' => ['a', 'b']]),
            AnimationControl::select('emptyFilter', 'Empty filter', ['optionsFilter' => '']),
            AnimationControl::select('emptyOptions', 'Empty options', ['options' => []]),
            AnimationControl::select('nullValue', 'Null value', ['options' => [['label' => 'A', 'value' => null]]]),
            AnimationControl::select('arrayValue', 'Array value', ['options' => [['label' => 'A', 'value' => ['a']]]]),
            AnimationControl::select('numberLabel', 'Number label', ['options' => [['label' => 1, 'value' => 'a']]]),
            AnimationControl::select('emptyLabel', 'Empty label', ['options' => [['label' => '', 'value' => 'a']]]),
            AnimationControl::select('unofferedDefault', 'Unoffered default', [
                'options' => [['label' => 'Red', 'value' => 'red']],
            ]),
            AnimationControl::number('outOfRange', 'Out of range', ['default' => 5, 'max' => 3]),
            AnimationControl::number('infinite', 'Infinite', ['default' => INF]),
            AnimationControl::number('notANumber', 'Not a number', ['default' => 'fast']),
            AnimationControl::number('nanMax', 'NAN max', ['max' => NAN]),
            AnimationControl::select('filterTypo', 'Filter typo', ['optionFilter' => 'test.tint-options']),
            AnimationControl::toggle('defaultTypo', 'Default typo', ['defualt' => true, 'hlep' => 'x']),
            AnimationControl::number('speed', 'Collides'),
            AnimationControl::toggle('ok', 'Kept'),
            AnimationControl::toggle('ok', 'Declared twice'),
        ];
    }
}
