<?php

namespace Sitchco\Parent\Tests\Support;

use Sitchco\Parent\Modules\Animation\AnimationControl;
use Sitchco\Parent\Modules\Animation\AnimationModule;

/**
 * An animation whose controls break every definition rule the coordinator enforces, alongside one
 * that is fine. A fake of its own because the AnimationModule contract is what is under test here.
 *
 * Its valid `edgeAnimationSpeed` is one half of an attribute collision: CollidingAnimationTester,
 * keyed `malformed-controls-tester-animation-edge`, declares `speed`, and both build
 * malformedControlsTesterAnimationEdgeAnimationSpeed. Kebab keys cannot collide on their own, so
 * a control name carrying `Animation` is the only way two attributes still meet.
 */
class MalformedControlsAnimationTester extends AnimationModule
{
    public const HOOK_SUFFIX = 'malformed-controls-tester';

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
            AnimationControl::select('extraInfinite', 'Extra infinite', [
                'options' => [['label' => 'A', 'value' => 'a', 'meta' => INF]],
            ]),
            AnimationControl::select('infiniteValue', 'Infinite value', [
                'options' => [['label' => 'A', 'value' => INF]],
            ]),
            AnimationControl::select('castDuplicate', 'Cast duplicate', [
                'options' => [['label' => '30', 'value' => 30], ['label' => 'Thirty', 'value' => '30']],
            ]),
            AnimationControl::select('unofferedDefault', 'Unoffered default', [
                'options' => [['label' => 'Red', 'value' => 'red']],
            ]),
            AnimationControl::number('outOfRange', 'Out of range', ['default' => 5, 'max' => 3]),
            AnimationControl::number('infinite', 'Infinite', ['default' => INF]),
            AnimationControl::number('notANumber', 'Not a number', ['default' => 'fast']),
            AnimationControl::number('nanMax', 'NAN max', ['max' => NAN]),
            AnimationControl::number('minAboveMax', 'Min above max', ['min' => 5, 'max' => 1]),
            AnimationControl::number('zeroStep', 'Zero step', ['step' => 0]),
            AnimationControl::text('blankLabel', ' '),
            AnimationControl::select('filterTypo', 'Filter typo', ['optionFilter' => 'test.tint-options']),
            AnimationControl::toggle('defaultTypo', 'Default typo', ['defualt' => true, 'hlep' => 'x']),
            AnimationControl::number('edgeAnimationSpeed', 'Collision source'),
            // Dropped for its missing source; the toggle of the same name after it is kept.
            AnimationControl::select('twice', 'Malformed first'),
            AnimationControl::toggle('twice', 'Valid second'),
            AnimationControl::toggle('ok', 'Kept'),
            AnimationControl::toggle('ok', 'Declared twice'),
        ];
    }
}
