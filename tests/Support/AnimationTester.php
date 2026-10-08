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
 * select rather than a number because the fixtures restrict it with `allowed`. Opacity's option
 * values are integers, as an author may well write them, so every check against them covers the
 * cast to the strings config and the editor compare.
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
                'css' => 'var(--wp--preset--color--{value})',
            ]),
            AnimationControl::select('opacity', 'Opacity', [
                'options' => self::options('', 10, 30, 50),
                'css' => 'calc({value} / 100)',
            ]),
            AnimationControl::select('speed', 'Speed', [
                'options' => self::options('25', '50'),
                'default' => '25',
                'css' => '{value}ms',
            ]),
            AnimationControl::toggle('reverse', 'Reverse', ['css' => ['on' => 'reverse', 'off' => 'normal']]),
        ];
    }

    /**
     * Option pairs labelled by their own value, or "Default" for the empty one, since a label may not
     * be empty; the label is never what a test reads.
     */
    private static function options(string|int ...$values): array
    {
        return array_map(
            fn(string|int $value) => ['label' => $value === '' ? 'Default' : (string) $value, 'value' => $value],
            $values,
        );
    }
}
