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
 * What a control emits is its `css`: the CSS value behind each of its values, which the framework
 * writes to the block as the custom property `--{key}-animation-{name}` (cssValue()). A control
 * without one stores its value and emits nothing.
 * - select: a template, `'var(--wp--preset--color--{value})'`, and/or a `css` key on each static
 *   option, which wins over the template. A select fed by `optionsFilter` can only use a template,
 *   since PHP never sees its values.
 * - toggle: `['on' => …, 'off' => …]`.
 * - number and text: a template.
 * `{value}` is replaced with the value as a string. The empty value, `''`, always emits nothing,
 * so the animation's own stylesheet default applies.
 *
 * Validation is the coordinator's, not this class's: a malformed control is logged and dropped
 * there, alongside every other definition problem, rather than thrown from a module's controls().
 * An option key a factory does not know (a misspelled `defualt`) is recorded in `unknownOptions`
 * for the coordinator to report. So is an option of the wrong PHP type — `'options' => 'red'`,
 * `'min' => 'low'` — which is recorded in `typeProblems` and left unset rather than thrown as a
 * TypeError. Nor is a `default` cast to its control's type: a toggle's `'false'` would become
 * `true`. It is kept as written, and the coordinator drops a control whose default is the wrong
 * type.
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
        /** @var string|array{on: string, off: string}|null The CSS value behind each value; see the class docblock. */
        public string|array|null $css = null,
        /** @var list<string> Option keys the factory did not recognize, reported by the coordinator. */
        public array $unknownOptions = [],
        /** @var list<string> Options of the wrong PHP type, as `` `key` must be … ``, reported by the coordinator. */
        public array $typeProblems = [],
    ) {}

    /**
     * @param array{options?: list<array{label: string, value: string|int|float, css?: string}>, optionsFilter?: string, default?: string, help?: string, css?: string} $options
     */
    public static function select(string $name, string $label, array $options = []): self
    {
        [$options, $unknown, $typeProblems] = self::withDefaults(
            ['options' => null, 'optionsFilter' => null, 'default' => '', 'help' => null, 'css' => null],
            $options,
        );

        return new self(
            type: 'select',
            name: $name,
            label: $label,
            default: self::stringScalar($options['default']),
            help: $options['help'],
            options: $options['options'],
            optionsFilter: $options['optionsFilter'],
            css: $options['css'],
            unknownOptions: $unknown,
            typeProblems: $typeProblems,
        );
    }

    /**
     * @param array{default?: bool, help?: string, css?: array{on: string, off: string}} $options
     */
    public static function toggle(string $name, string $label, array $options = []): self
    {
        [$options, $unknown, $typeProblems] = self::withDefaults(
            ['default' => false, 'help' => null, 'css' => null],
            $options,
        );

        return new self(
            type: 'toggle',
            name: $name,
            label: $label,
            default: $options['default'],
            help: $options['help'],
            css: $options['css'],
            unknownOptions: $unknown,
            typeProblems: $typeProblems,
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
     * @param array{default?: int|float, min?: int|float, max?: int|float, step?: int|float, help?: string, css?: string} $options
     */
    public static function number(string $name, string $label, array $options = []): self
    {
        [$options, $unknown, $typeProblems] = self::withDefaults(
            ['default' => 0, 'min' => null, 'max' => null, 'step' => null, 'help' => null, 'css' => null],
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
            css: $options['css'],
            unknownOptions: $unknown,
            typeProblems: $typeProblems,
        );
    }

    /**
     * @param array{default?: string, help?: string, css?: string} $options
     */
    public static function text(string $name, string $label, array $options = []): self
    {
        [$options, $unknown, $typeProblems] = self::withDefaults(
            ['default' => '', 'help' => null, 'css' => null],
            $options,
        );

        return new self(
            type: 'text',
            name: $name,
            label: $label,
            default: self::stringScalar($options['default']),
            help: $options['help'],
            css: $options['css'],
            unknownOptions: $unknown,
            typeProblems: $typeProblems,
        );
    }

    /**
     * The PHP type each typed option must have, and how a problem message names it. `default` is
     * not here: its type depends on the control, and the coordinator checks it.
     */
    private const OPTION_TYPES = [
        'options' => ['is_array', 'a list'],
        'optionsFilter' => ['is_string', 'a string'],
        'help' => ['is_string', 'a string'],
        'min' => [[self::class, 'isNumber'], 'a number'],
        'max' => [[self::class, 'isNumber'], 'a number'],
        'step' => [[self::class, 'isNumber'], 'a number'],
        'css' => [[self::class, 'isStringOrArray'], 'a string or an array'],
    ];

    /**
     * A factory's options merged over its defaults, the keys among them it does not know, and the
     * typed options whose values have the wrong PHP type. Those are reset to their default, null,
     * so the constructor never sees them, and reported instead.
     *
     * @return array{0: array<string, mixed>, 1: list<string>, 2: list<string>}
     */
    private static function withDefaults(array $defaults, array $options): array
    {
        $merged = array_merge($defaults, $options);
        $typeProblems = [];

        foreach (self::OPTION_TYPES as $key => [$check, $expected]) {
            /* A key this factory has no default for is not one it reads: it is reported once, as
             unknown, rather than type-checked and reset to a default that does not exist. */
            if (
                !array_key_exists($key, $defaults) ||
                !array_key_exists($key, $options) ||
                $options[$key] === null ||
                $check($options[$key])
            ) {
                continue;
            }

            $typeProblems[] = "`{$key}` must be {$expected}";
            $merged[$key] = $defaults[$key];
        }

        return [$merged, array_map('strval', array_keys(array_diff_key($options, $defaults))), $typeProblems];
    }

    private static function isNumber(mixed $value): bool
    {
        return is_int($value) || is_float($value);
    }

    private static function isStringOrArray(mixed $value): bool
    {
        return is_string($value) || is_array($value);
    }

    /**
     * A default as a string when it is a string or a number, so `30` and `'30'` are one value;
     * anything else kept as written, for the coordinator to reject.
     */
    private static function stringScalar(mixed $value): mixed
    {
        return is_string($value) || self::isNumber($value) ? (string) $value : $value;
    }

    /** What a template's placeholder is replaced with. */
    public const CSS_PLACEHOLDER = '{value}';

    /**
     * The CSS value one of this control's values emits, or null for none.
     *
     * Null for the empty value (`''` or null), for a value of the wrong type, for a static select's
     * value that it does not offer, and for a control without `css`. A toggle's false is a value,
     * not "unset": it emits its `off` CSS.
     *
     * This answers for the control alone. Whether a block permits the value is the coordinator's
     * question. Mirrored by cssValue() in editor-ui/animation-fields.js; the parity fixture,
     * tests/fixtures/animation-css-cases.json, holds the two to the same answers.
     */
    public function cssValue(mixed $value): ?string
    {
        if ($this->type === 'toggle') {
            return is_bool($value) && is_array($this->css) ? $this->css[$value ? 'on' : 'off'] ?? null : null;
        }

        $string = match ($this->type) {
            'number' => is_int($value) || (is_float($value) && is_finite($value)) ? (string) $value : null,
            default => is_string($value) || is_int($value) || is_float($value) ? (string) $value : null,
        };
        if ($string === null || $string === '') {
            return null;
        }

        if ($this->type === 'select' && $this->options !== null) {
            $option =
                array_values(
                    array_filter($this->stringOptions(), fn(array $option) => $option['value'] === $string),
                )[0] ?? null;
            if ($option === null) {
                return null;
            }
            if (is_string($option['css'] ?? null)) {
                return $option['css'];
            }
        }

        return is_string($this->css) ? str_replace(self::CSS_PLACEHOLDER, $string, $this->css) : null;
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
                'css' => $this->css,
            ],
            fn($value) => $value !== null,
        );
    }
}
