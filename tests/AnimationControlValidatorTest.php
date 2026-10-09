<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Parent\Modules\Animation\AnimationControl;
use Sitchco\Parent\Modules\Animation\AnimationControlValidator;
use Sitchco\Parent\Modules\Animation\AnimationModule;
use Sitchco\Parent\Tests\Support\CollidingAnimationTester;
use Sitchco\Parent\Tests\Support\MalformedControlsAnimationTester;
use Sitchco\Parent\Tests\Support\SecondAnimationTester;
use Sitchco\Tests\TestCase;

/**
 * The definition rules for AnimationControl, checked against the validator alone.
 *
 * validate() is stateless and takes the active animations as an argument, so no framework is booted
 * and no logger captured: what it returns is the whole of what it decides. That the coordinator
 * logs these problems, once, is AnimationFrameworkModuleTest's to show.
 */
class AnimationControlValidatorTest extends TestCase
{
    /**
     * @param class-string<AnimationModule> ...$classnames
     * @return array{controls: array<string, array<string, \Sitchco\Parent\Modules\Animation\AnimationControl>>, problems: list<string>}
     */
    private function validate(string ...$classnames): array
    {
        $animations = [];
        foreach ($classnames as $classname) {
            $animation = $this->container->get($classname);
            $animations[$animation->key()] = $animation;
        }

        return $this->container->get(AnimationControlValidator::class)->validate($animations);
    }

    public function testEveryBrokenControlIsReportedAndDropped(): void
    {
        ['problems' => $problems] = $this->validate(
            MalformedControlsAnimationTester::class,
            CollidingAnimationTester::class,
        );

        $optionsRule =
            "`options` must be a non-empty list of ['label' => …, 'value' => …] pairs, each label a non-empty string, each value a distinct string or finite number, and any other key a string, bool or finite number. Dropping it.";

        $this->assertSame(
            [
                'malformed-controls-tester / #0: is not an AnimationControl. Dropping it.',
                'malformed-controls-tester / Bad-Name: the name must be camelCase letters and digits, starting with a lowercase letter. Dropping it.',
                "malformed-controls-tester / trailingNewline\n: the name must be camelCase letters and digits, starting with a lowercase letter. Dropping it.",
                'malformed-controls-tester / noOptions: a select needs exactly one of `options` or `optionsFilter`. Dropping it.',
                'malformed-controls-tester / bothOptions: a select needs exactly one of `options` or `optionsFilter`. Dropping it.',
                "malformed-controls-tester / looseOptions: {$optionsRule}",
                'malformed-controls-tester / emptyFilter: a select needs exactly one of `options` or `optionsFilter`. Dropping it.',
                "malformed-controls-tester / emptyOptions: {$optionsRule}",
                "malformed-controls-tester / nullValue: {$optionsRule}",
                "malformed-controls-tester / arrayValue: {$optionsRule}",
                "malformed-controls-tester / numberLabel: {$optionsRule}",
                "malformed-controls-tester / emptyLabel: {$optionsRule}",
                "malformed-controls-tester / extraInfinite: {$optionsRule}",
                "malformed-controls-tester / infiniteValue: {$optionsRule}",
                "malformed-controls-tester / castDuplicate: {$optionsRule}",
                'malformed-controls-tester / unofferedDefault: its default "" is not one of its options. Dropping it.',
                'malformed-controls-tester / outOfRange: its default 5 is outside its range (at most 3). Dropping it.',
                'malformed-controls-tester / infinite: its default must be a finite number. Dropping it.',
                'malformed-controls-tester / notANumber: its default must be a finite number. Dropping it.',
                'malformed-controls-tester / nanMax: its min, max and step must be finite numbers. Dropping it.',
                'malformed-controls-tester / minAboveMax: its min 5 is greater than its max 1. Dropping it.',
                'malformed-controls-tester / zeroStep: its step must be greater than zero. Dropping it.',
                'malformed-controls-tester / negativeStep: its step must be greater than zero. Dropping it.',
                'malformed-controls-tester / infiniteStep: its min, max and step must be finite numbers. Dropping it.',
                'malformed-controls-tester / offGrid: its default 3 is off its step grid (steps of 5 from 0). Dropping it.',
                'malformed-controls-tester / fractionNoStep: its default 0.5 is off its step grid (whole numbers). Dropping it.',
                // Its min above its max goes unreported: the first defect found is the one named.
                'malformed-controls-tester / twoDefects: its label must not be empty. Dropping it.',
                'malformed-controls-tester / blankLabel: its label must not be empty. Dropping it.',
                'malformed-controls-tester / stringToggle: its default must be true or false. Dropping it.',
                'malformed-controls-tester / arrayDefault: its default must be a string or a number. Dropping it.',
                'malformed-controls-tester / arrayText: its default must be a string or a number. Dropping it.',
                'malformed-controls-tester / stringOptions: `options` must be a list. Dropping it.',
                'malformed-controls-tester / wordMin: `min` must be a number. Dropping it.',
                'malformed-controls-tester / twoTypes: `help` must be a string; `min` must be a number. Dropping it.',
                'malformed-controls-tester / cssWrongType: `css` must be a string or an array. Dropping it.',
                'malformed-controls-tester / cssNoPlaceholder: its `css` must be a template containing {value}. Dropping it.',
                'malformed-controls-tester / cssUnsafe: its `css` contains ; { } \\ < or >, which a style value cannot. Dropping it.',
                "malformed-controls-tester / toggleCssString: a toggle's `css` must be ['on' => …, 'off' => …], each a string, and nothing else. Dropping it.",
                "malformed-controls-tester / toggleCssHalf: a toggle's `css` must be ['on' => …, 'off' => …], each a string, and nothing else. Dropping it.",
                'malformed-controls-tester / toggleCssUnsafe: its `css` contains ; { } \\ < or >, which a style value cannot. Dropping it.',
                "malformed-controls-tester / toggleCssExtra: a toggle's `css` must be ['on' => …, 'off' => …], each a string, and nothing else. Dropping it.",
                "malformed-controls-tester / optionCssNumber: an option's `css` must be a string. Dropping it.",
                'malformed-controls-tester / optionCssUnsafe: its `css` contains ; { } \\ < or >, which a style value cannot. Dropping it.',
                'malformed-controls-tester / filterTypo: does not know the option `optionFilter`. Ignoring it.',
                'malformed-controls-tester / filterTypo: a select needs exactly one of `options` or `optionsFilter`. Dropping it.',
                'malformed-controls-tester / defaultTypo: does not know the options `defualt`, `hlep`. Ignoring them.',
                'malformed-controls-tester / foreignTypedOption: does not know the option `options`. Ignoring it.',
                // Only the malformed first `twice` is reported: the valid second is not a duplicate of it.
                'malformed-controls-tester / twice: a select needs exactly one of `options` or `optionsFilter`. Dropping it.',
                'malformed-controls-tester / ok: is declared twice. Keeping the first valid one.',
                'malformed-controls-tester-animation-edge / speed: its attribute "malformedControlsTesterAnimationEdgeAnimationSpeed" is already used by malformed-controls-tester / edgeAnimationSpeed. Dropping it.',
            ],
            $problems,
        );
    }

    public function testValidControlsAreKeptInDeclarationOrder(): void
    {
        ['controls' => $controls] = $this->validate(
            SecondAnimationTester::class,
            MalformedControlsAnimationTester::class,
            CollidingAnimationTester::class,
        );

        $this->assertSame(['speed', 'direction', 'tint', 'caption'], array_keys($controls['second-tester']));
        $this->assertSame(
            [
                'pinned',
                'quarterStep',
                'offsetGrid',
                'defaultTypo',
                'foreignTypedOption',
                'edgeAnimationSpeed',
                'twice',
                'ok',
            ],
            array_keys($controls['malformed-controls-tester']),
        );
        // The malformed tester comes first, so it keeps the attribute both build.
        $this->assertArrayNotHasKey('malformed-controls-tester-animation-edge', $controls);
    }

    public function testTheFirstValidDefinitionOfANameWins(): void
    {
        $controls = $this->validate(MalformedControlsAnimationTester::class)['controls']['malformed-controls-tester'];

        $this->assertSame('Kept', $controls['ok']->label);
        // A malformed first definition never claims the name.
        $this->assertSame('Valid second', $controls['twice']->label);
    }

    public function testAMisspelledOptionIsReportedButItsControlIsKeptWithoutIt(): void
    {
        $controls = $this->validate(MalformedControlsAnimationTester::class)['controls']['malformed-controls-tester'];

        $this->assertFalse($controls['defaultTypo']->default);
    }

    public function testATypedOptionTheFactoryDoesNotDefineIsOnlyUnknown(): void
    {
        $control = AnimationControl::toggle('a', 'A', ['options' => 'red']);

        $this->assertSame(['options'], $control->unknownOptions);
        $this->assertSame([], $control->typeProblems);
    }

    public function testTheKeptControlsStillEncodeForTheEditor(): void
    {
        $controls = $this->validate(MalformedControlsAnimationTester::class)['controls']['malformed-controls-tester'];

        $this->assertNotFalse(wp_json_encode(array_values($controls)));
    }

    public function testAnimationsWithValidControlsReportNothing(): void
    {
        $this->assertSame([], $this->validate(SecondAnimationTester::class)['problems']);
    }
}
