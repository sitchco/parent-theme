<?php

namespace Sitchco\Parent\Modules\Animation;

/**
 * One editor control an animation offers: a select, toggle, number or text field.
 *
 * Declared in PHP and serialized to the editor, where it becomes the ExtendBlock field of the same
 * type. PHP owns the definition so that config resolution can check `allowed` and `defaults`
 * against real controls, and so that later server-side output can read the same definitions the
 * editor does.
 *
 *     public function controls(): array
 *     {
 *         return [
 *             AnimationControl::select('color', 'Glyph Color', ['optionsFilter' => 'theme.color-options']),
 *             AnimationControl::select('opacity', 'Glyph Opacity', [
 *                 'options' => [
 *                     ['label' => 'Default', 'value' => ''],
 *                     ['label' => '30%', 'value' => '30'],
 *                 ],
 *             ]),
 *         ];
 *     }
 *
 * `name` is local to the animation: `color`, never `letterAnimationColor`. The framework builds the block
 * attribute from the animation key and this name (AnimationFrameworkModule::attributeName()), so two
 * animations can each have a `color` without colliding.
 *
 * A select's choices come from one of two places:
 * - `options`, a static list of `['label' => …, 'value' => …]` pairs. Config `allowed` values are
 *   checked against it.
 * - `optionsFilter`, the name of a JS hook the editor resolves with `applyFilters(name, [])` —
 *   for a palette the theme already shares with its other controls. PHP cannot see those values,
 *   so `allowed` is applied but not checked.
 *
 * A select's empty value, `''`, means "no override": the animation's own stylesheet default.
 * Config `allowed` never removes it. It is not the block's config default: on a block whose config
 * sets one, choosing `''` stores `''`, so the editor labels it "Animation default" whatever the
 * options call it (EMPTY_OPTION_LABEL in editor-ui/animation-fields.js).
 *
 * Validation is the coordinator's, not this class's: a malformed control is logged and dropped
 * there, alongside every other definition problem, rather than thrown from a module's controls().
 * An option key a factory does not know (a misspelled `defualt`) is recorded in `unknownOptions`
 * for the coordinator to report. The one exception is a value of the wrong PHP type for a typed
 * property — `'options' => 'red'`, `'min' => 'low'` — which throws a TypeError here, as any
 * mistyped constructor argument would.
 */
readonly class AnimationControl implements \JsonSerializable
{
    /**
     * @param list<array{label: string, value: string|int|float}>|null $options
     */
    private function __construct(
        public string $type,
        public string $name,
        public string $label,
        public mixed $default,
        public ?string $help = null,
        public ?array $options = null,
        public ?string $optionsFilter = null,
        public int|float|null $min = null,
        public int|float|null $max = null,
        public int|float|null $step = null,
        /** @var list<string> Option keys the factory did not recognize, reported by the coordinator. */
        public array $unknownOptions = [],
    ) {}

    /**
     * @param array{options?: list<array{label: string, value: string|int|float}>, optionsFilter?: string, default?: string, help?: string} $options
     */
    public static function select(string $name, string $label, array $options = []): self
    {
        [$options, $unknown] = self::withDefaults(
            ['options' => null, 'optionsFilter' => null, 'default' => '', 'help' => null],
            $options,
        );

        return new self(
            type: 'select',
            name: $name,
            label: $label,
            default: (string) $options['default'],
            help: $options['help'],
            options: $options['options'],
            optionsFilter: $options['optionsFilter'],
            unknownOptions: $unknown,
        );
    }

    /**
     * @param array{default?: bool, help?: string} $options
     */
    public static function toggle(string $name, string $label, array $options = []): self
    {
        [$options, $unknown] = self::withDefaults(['default' => false, 'help' => null], $options);

        return new self(
            type: 'toggle',
            name: $name,
            label: $label,
            default: (bool) $options['default'],
            help: $options['help'],
            unknownOptions: $unknown,
        );
    }

    /**
     * `step` is the increment the control moves in, and also the grid the editor rounds a value to
     * whenever the input commits (on blur or Enter), whether or not anyone typed in it. Without one
     * the grid is whole numbers, so a fractional value such as 0.75 needs a `step` that reaches it.
     *
     * The default must sit on that grid (see onStep()), or tabbing through the inspector would
     * silently rewrite a value nobody touched. The coordinator drops a control whose default does
     * not.
     *
     * @param array{default?: int|float, min?: int|float, max?: int|float, step?: int|float, help?: string} $options
     */
    public static function number(string $name, string $label, array $options = []): self
    {
        [$options, $unknown] = self::withDefaults(
            ['default' => 0, 'min' => null, 'max' => null, 'step' => null, 'help' => null],
            $options,
        );

        return new self(
            type: 'number',
            name: $name,
            label: $label,
            default: $options['default'],
            help: $options['help'],
            min: $options['min'],
            max: $options['max'],
            step: $options['step'],
            unknownOptions: $unknown,
        );
    }

    /**
     * @param array{default?: string, help?: string} $options
     */
    public static function text(string $name, string $label, array $options = []): self
    {
        [$options, $unknown] = self::withDefaults(['default' => '', 'help' => null], $options);

        return new self(
            type: 'text',
            name: $name,
            label: $label,
            default: (string) $options['default'],
            help: $options['help'],
            unknownOptions: $unknown,
        );
    }

    /**
     * A factory's options merged over its defaults, and the keys among them it does not know.
     *
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private static function withDefaults(array $defaults, array $options): array
    {
        return [array_merge($defaults, $options), array_map('strval', array_keys(array_diff_key($options, $defaults)))];
    }

    /**
     * The values a static select offers, as strings; null when its options come from a JS hook, or
     * for any other type of control.
     *
     * @return list<string>|null
     */
    public function optionValues(): ?array
    {
        $options = $this->stringOptions();

        return $options === null ? null : array_column($options, 'value');
    }

    /**
     * Whether a number lies within this control's `min` and `max`, either of which may be unset.
     */
    public function inRange(int|float $value): bool
    {
        return ($this->min === null || $value >= $this->min) && ($this->max === null || $value <= $this->max);
    }

    /**
     * The range inRange() checks, for a problem message: "0 to 100", "at least 0", "at most 100".
     */
    public function describeRange(): string
    {
        return match (true) {
            $this->min !== null && $this->max !== null => "{$this->min} to {$this->max}",
            $this->min !== null => "at least {$this->min}",
            default => "at most {$this->max}",
        };
    }

    /**
     * Whether a number lies on the grid the editor rounds this control's values to.
     *
     * The grid is the one NumberControl's ensureValidStep() rounds to on commit: multiples of
     * `step` (1 when unset), offset by `min` when `min` is not itself a multiple of `step`. The
     * comparison allows for float error, so 0.3 sits on a grid of 0.1.
     */
    public function onStep(int|float $value): bool
    {
        $steps = ($value - $this->stepBase()) / ($this->step ?? 1);

        return abs($steps - round($steps)) < 1e-9;
    }

    /**
     * The grid onStep() checks, for a problem message: "whole numbers", "steps of 5 from 0".
     */
    public function describeStep(): string
    {
        $base = $this->stepBase();
        if ($this->step === null && $base == 0) {
            return 'whole numbers';
        }

        return sprintf('steps of %s from %s', $this->step ?? 1, $base);
    }

    /**
     * Where the grid starts: `min` when it is off the multiples of `step`, 0 otherwise.
     */
    private function stepBase(): int|float
    {
        $step = $this->step ?? 1;

        return $this->min !== null && fmod($this->min, $step) != 0 ? $this->min : 0;
    }

    /**
     * The static options with each value cast to a string, so `30` and `'30'` are one value
     * everywhere. Any other keys are kept as declared.
     *
     * @return list<array{label: string, value: string}>|null
     */
    private function stringOptions(): ?array
    {
        if ($this->options === null) {
            return null;
        }

        return array_map(fn(array $option) => [...$option, 'value' => (string) $option['value']], $this->options);
    }

    /**
     * Unset settings are left out rather than sent as null.
     *
     * Option values are sent as strings, the same form optionValues() gives config checks: the
     * editor matches them with `===`, so a `30` here would never match the `'30'` that `allowed`
     * was checked as, nor a stored value.
     */
    public function jsonSerialize(): array
    {
        return array_filter(
            [
                'type' => $this->type,
                'name' => $this->name,
                'label' => $this->label,
                'default' => $this->default,
                'help' => $this->help,
                'options' => $this->stringOptions(),
                'optionsFilter' => $this->optionsFilter,
                'min' => $this->min,
                'max' => $this->max,
                'step' => $this->step,
            ],
            fn($value) => $value !== null,
        );
    }
}
