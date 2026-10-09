<?php

namespace Sitchco\Parent\Tests\Support;

use Sitchco\Parent\Modules\Animation\AnimationControl;
use Sitchco\Parent\Modules\Animation\AnimationModule;

/**
 * An animation whose controls break every definition rule the coordinator enforces, alongside some
 * that are fine. A fake of its own because the AnimationModule contract is what is under test here.
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
            AnimationControl::number('negativeStep', 'Negative step', ['step' => -1]),
            AnimationControl::number('infiniteStep', 'Infinite step', ['step' => INF]),
            AnimationControl::number('offGrid', 'Off grid', ['default' => 3, 'min' => 0, 'step' => 5]),
            // No step means whole numbers, the grid the editor rounds to without one.
            AnimationControl::number('fractionNoStep', 'Fraction, no step', ['default' => 0.5]),
            // Kept: a range of one value, and a fractional step its default sits on.
            AnimationControl::number('pinned', 'Pinned', ['default' => 2, 'min' => 2, 'max' => 2]),
            AnimationControl::number('quarterStep', 'Quarter step', ['default' => 0.75, 'step' => 0.25]),
            // Kept: off the multiples of 0.5, so the grid starts at min, as the editor's does.
            AnimationControl::number('offsetGrid', 'Offset grid', ['default' => 1.25, 'min' => 0.25, 'step' => 0.5]),
            // Two defects: the blank label is reported, and the control is not looked at further.
            AnimationControl::number('twoDefects', ' ', ['min' => 5, 'max' => 1]),
            AnimationControl::text('blankLabel', ' '),
            // Wrong PHP types: reported and dropped, never cast or thrown.
            AnimationControl::toggle('stringToggle', 'String toggle', ['default' => 'false']),
            AnimationControl::select('arrayDefault', 'Array default', [
                'options' => [['label' => 'A', 'value' => 'a']],
                'default' => [],
            ]),
            AnimationControl::text('arrayText', 'Array text', ['default' => ['a']]),
            AnimationControl::select('stringOptions', 'String options', ['options' => 'red']),
            AnimationControl::number('wordMin', 'Word min', ['min' => 'low']),
            AnimationControl::number('twoTypes', 'Two types', ['min' => 'low', 'help' => 5]),
            // CSS that could never be written.
            AnimationControl::text('cssWrongType', 'CSS wrong type', ['css' => 5]),
            AnimationControl::text('cssNoPlaceholder', 'CSS no placeholder', ['css' => 'red']),
            AnimationControl::number('cssUnsafe', 'CSS unsafe', ['css' => '{value}ms; color: red']),
            AnimationControl::toggle('toggleCssString', 'Toggle CSS string', ['css' => 'reverse']),
            AnimationControl::toggle('toggleCssHalf', 'Toggle CSS half', ['css' => ['on' => 'reverse']]),
            AnimationControl::toggle('toggleCssUnsafe', 'Toggle CSS unsafe', ['css' => ['on' => 'a}', 'off' => 'b']]),
            AnimationControl::toggle('toggleCssExtra', 'Toggle CSS extra', [
                'css' => ['on' => 'reverse', 'off' => 'normal', 'm' => INF],
            ]),
            AnimationControl::select('optionCssNumber', 'Option CSS number', [
                'options' => [['label' => 'A', 'value' => 'a', 'css' => 1]],
                'default' => 'a',
            ]),
            AnimationControl::select('optionCssUnsafe', 'Option CSS unsafe', [
                'options' => [['label' => 'A', 'value' => 'a', 'css' => '</style>']],
                'default' => 'a',
            ]),
            AnimationControl::select('filterTypo', 'Filter typo', ['optionFilter' => 'test.tint-options']),
            AnimationControl::toggle('defaultTypo', 'Default typo', ['defualt' => true, 'hlep' => 'x']),
            // A typed option this factory doesn't define: unknown, not a type problem.
            AnimationControl::toggle('foreignTypedOption', 'Foreign typed option', ['options' => 'red']),
            AnimationControl::number('edgeAnimationSpeed', 'Collision source'),
            // Dropped for its missing source; the toggle of the same name after it is kept.
            AnimationControl::select('twice', 'Malformed first'),
            AnimationControl::toggle('twice', 'Valid second'),
            AnimationControl::toggle('ok', 'Kept'),
            AnimationControl::toggle('ok', 'Declared twice'),
        ];
    }
}
