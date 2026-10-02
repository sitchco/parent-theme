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
 * Config `allowed` never removes it.
 *
 * Validation is the coordinator's, not this class's: a malformed control is logged and dropped
 * there, alongside every other definition problem, rather than thrown from a module's controls().
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
    ) {}

    /**
     * @param array{options?: list<array{label: string, value: string|int|float}>, optionsFilter?: string, default?: string, help?: string} $options
     */
    public static function select(string $name, string $label, array $options = []): self
    {
        $options = array_merge(['options' => null, 'optionsFilter' => null, 'default' => '', 'help' => null], $options);

        return new self(
            type: 'select',
            name: $name,
            label: $label,
            default: (string) $options['default'],
            help: $options['help'],
            options: $options['options'],
            optionsFilter: $options['optionsFilter'],
        );
    }

    /**
     * @param array{default?: bool, help?: string} $options
     */
    public static function toggle(string $name, string $label, array $options = []): self
    {
        $options = array_merge(['default' => false, 'help' => null], $options);

        return new self('toggle', $name, $label, (bool) $options['default'], $options['help']);
    }

    /**
     * @param array{default?: int|float, min?: int|float, max?: int|float, help?: string} $options
     */
    public static function number(string $name, string $label, array $options = []): self
    {
        $options = array_merge(['default' => 0, 'min' => null, 'max' => null, 'help' => null], $options);

        return new self(
            type: 'number',
            name: $name,
            label: $label,
            default: $options['default'],
            help: $options['help'],
            min: $options['min'],
            max: $options['max'],
        );
    }

    /**
     * @param array{default?: string, help?: string} $options
     */
    public static function text(string $name, string $label, array $options = []): self
    {
        $options = array_merge(['default' => '', 'help' => null], $options);

        return new self('text', $name, $label, (string) $options['default'], $options['help']);
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
            ],
            fn($value) => $value !== null,
        );
    }
}
