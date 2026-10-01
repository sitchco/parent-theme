<?php

namespace Sitchco\Parent\Tests\Support;

use Sitchco\Parent\Modules\Animation\AnimationControl;
use Sitchco\Parent\Modules\Animation\AnimationModule;

/**
 * Minimal animation, standing in for a real one wherever the AnimationModule type itself is what a
 * test exercises. Declares no key(), so it also covers the default of keying off HOOK_SUFFIX.
 *
 * Its controls are the option names the animation fixtures override — color, opacity, speed and
 * reverse — so those overrides are checked against real controls, as live config is. `speed` is a
 * select rather than a number because the fixtures restrict it with `allowed`.
 */
class AnimationTester extends AnimationModule
{
    public const HOOK_SUFFIX = 'animation-tester';

    public function label(): string
    {
        return 'Animation Tester';
    }

    public function controls(): array
    {
        return [
            AnimationControl::select('color', 'Color', [
                'options' => self::options('', 'purple', 'green', 'red'),
            ]),
            AnimationControl::select('opacity', 'Opacity', [
                'options' => self::options('', '10', '30', '50'),
            ]),
            AnimationControl::select('speed', 'Speed', [
                'options' => self::options('25', '50'),
                'default' => '25',
            ]),
            AnimationControl::toggle('reverse', 'Reverse'),
        ];
    }

    /** Option pairs labelled by their own value; the label is never what a test reads. */
    private static function options(string ...$values): array
    {
        return array_map(fn(string $value) => ['label' => $value, 'value' => $value], $values);
    }
}
