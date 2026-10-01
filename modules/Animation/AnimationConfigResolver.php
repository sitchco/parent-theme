<?php

namespace Sitchco\Parent\Modules\Animation;

/**
 * Resolves the `animations` config section into a block => animations map.
 *
 * This is the config language half of the framework, kept apart from AnimationFrameworkModule's
 * lifecycle half (discovery, memoization, the init() timing rule, logging). It takes the section as
 * loaded plus a way to look an animation up by key, and returns the map together with every
 * authoring problem it found. It never logs: whether and how to report is the module's call.
 *
 * The shape it reads:
 *
 *     'animations' => [
 *         // Bare list: every listed animation, all options at their own defaults.
 *         'core/group' => ['parallax', 'fade-up'],
 *
 *         'kadence/rowlayout' => [
 *             'letter' => [
 *                 'allowed'  => ['color' => ['purple', 'green']],  // restrict the palette here
 *                 'defaults' => ['opacity' => '30'],               // and start at 30%
 *             ],
 *             'parallax' => true,
 *         ],
 *
 *         'kadence/column' => [
 *             'parallax' => false,   // a child theme removing a parent default
 *         ],
 *     ],
 *
 * `allowed` and `defaults` are the only reserved sub-keys.
 *
 * REMOVAL. Merging is additive, so a child theme cannot delete a key an ancestor set — removal is
 * `=> false`, and `null` works wherever `false` does. That applies at exactly four points:
 *
 *     'animations' => false,                          // the whole section: every block at once
 *     'kadence/column' => false,                      // one block
 *     'parallax' => false,                            // one animation on a block
 *     'allowed' => ['color' => ['green' => false]],   // one permitted value
 *
 * It stops there. `'allowed' => false`, `'allowed' => ['color' => false]` and `'defaults' => false`
 * are not a fourth and fifth removal idiom: they are authoring mistakes, each logged, each leaving
 * that option unrestricted or undefaulted. The one thing that does clear a whole inherited override
 * array is trap 2 below, and it does so by accident rather than on request.
 *
 * REPLACING an inherited restriction or default is not a matter of stating the one you want. What
 * the merge actually does:
 *
 *   - An `allowed` list you write MERGES with the inherited one. Over a parent's
 *     `'color' => ['purple', 'green']`, a child's `['red']` resolves to purple, green AND red, and
 *     a child's `['purple']` changes nothing at all. Neither logs a word, and the inherited default
 *     is still permitted, so nothing downstream notices either.
 *   - To narrow a list, remove each value you do not want: `'color' => ['green' => false]`.
 *   - A single SCALAR replaces the whole list: `'color' => 'red'` resolves to red alone.
 *   - `defaults` are replaced key by key, which is the intuitive behaviour: a child's
 *     `'opacity' => '50'` wins, and an ancestor's sibling defaults survive.
 *
 * THREE SILENT TRAPS, none of which this class can detect at runtime:
 *
 * 1. A numeric-keyed ARRAY is discarded by ConfigRegistry::normalizeData() before it ever reaches
 *    here. Never mix the two forms in one list:
 *
 *        'core/group' => [
 *            'parallax',                                   // fine: becomes 'parallax' => true
 *            ['letter' => ['defaults' => ['opacity' => '30']]],   // SILENTLY GONE
 *        ],
 *
 *    Write every entry with an explicit key as soon as one of them needs overrides.
 *
 * 2. Mentioning an inherited animation again as `true` REPLACES its overrides, in either form.
 *    ConfigRegistry::normalizeData() rewrites a bare `'parallax'` into `'parallax' => true` before
 *    anything merges, so the bare-list and keyed forms arrive as the same scalar — and
 *    ArrayUtil::mergeRecursiveDistinct lets a scalar replace an array. A child writing either
 *    `['parallax']` or `['parallax' => true]` over a parent's `'parallax' => ['defaults' => [...]]`
 *    therefore discards those defaults, silently.
 *
 *        'kadence/rowlayout' => ['parallax' => []],   // keeps the parent's overrides
 *
 *    Two arrays merge, so `[]` leaves the ancestor's entry untouched; leaving the animation out
 *    does the same. Naming a DIFFERENT animation is additive in either form and never touches its
 *    siblings — it is only re-stating an inherited one as `true` that costs anything.
 *
 * 3. A non-integer numeric permitted value has to be QUOTED. Normalization turns each permitted
 *    value into an array key, and PHP truncates a float key to an int, so `[0.5, 0.7]` collapses
 *    into the single key 0 and comes back as `['0']`. Write `['0.5', '0.7']`. Integers are safe
 *    either way: `[10, 30]` and `['10', '30']` both resolve to '10' and '30'.
 */
class AnimationConfigResolver
{
    /** The only sub-keys a per-block override array may use. */
    private const OVERRIDE_ALLOWED = 'allowed';
    private const OVERRIDE_DEFAULTS = 'defaults';

    /** @var callable(string): ?AnimationModule */
    private $findAnimation;

    /** Problems found during one resolution pass. */
    private array $problems = [];

    /**
     * @param array                               $section       The `animations` config section, as loaded
     * @param callable(string): ?AnimationModule  $findAnimation Looks an active animation up by key
     */
    public function __construct(private readonly array $section, callable $findAnimation)
    {
        $this->findAnimation = $findAnimation;
    }

    /**
     * Every block this resolves something for, in config order, mapped to the animations it may use,
     * plus every authoring problem met on the way. A configured block is left out when it is removed,
     * written bare, written as a scalar, or ends up with no animations at all.
     *
     * @return array{
     *     blocks:   array<string, array<string, array{key: string, label: string, allowed: array<string, list<string>>, defaults: array<string, mixed>}>>,
     *     problems: list<string>,
     * }
     */
    public function resolve(): array
    {
        $this->problems = [];
        $resolved = [];

        foreach ($this->section as $blockName => $blockConfig) {
            /* Block-level removal, and the two ways of saying "nothing here". All intentional, so
             none of them is worth a word. */
            if ($blockConfig === false || $blockConfig === null || $blockConfig === []) {
                continue;
            }

            if (!is_array($blockConfig)) {
                $this->flagProblem(
                    $blockName,
                    $blockConfig === true
                        ? 'is listed with no animations. A bare block name normalizes to `true`, which cannot be read as a list of animation keys — map the block to the animations it may use.'
                        : 'maps to a scalar where a list of animation keys was expected.',
                );
                continue;
            }

            $entries = $this->resolveBlock($blockName, $blockConfig);
            if ($entries) {
                $resolved[$blockName] = $entries;
            }
        }

        return ['blocks' => $resolved, 'problems' => $this->problems];
    }

    /**
     * @return array<string, array{key: string, label: string, allowed: array<string, list<string>>, defaults: array<string, mixed>}>
     */
    private function resolveBlock(string $blockName, array $blockConfig): array
    {
        $entries = [];

        foreach ($blockConfig as $key => $value) {
            /* The removal idiom, tested explicitly rather than by truthiness. A `=== true` test, as
               BlockConfig::filterDisabledBlocks() uses, would drop every override array; and `[]`
               means "enabled, no overrides", which array_filter() would drop. */
            if ($value === false || $value === null) {
                continue;
            }

            $animation = ($this->findAnimation)((string) $key);
            if (!$animation) {
                $this->flagProblem(
                    "{$blockName} / {$key}",
                    'names an animation no active module provides. Dropping it.',
                );
                continue;
            }

            if ($value !== true && !is_array($value)) {
                $this->flagProblem(
                    "{$blockName} / {$key}",
                    'has a scalar value where `true` or an override array was expected. Dropping it.',
                );
                continue;
            }

            $overrides = is_array($value)
                ? $this->resolveOverrides($blockName, (string) $key, $value)
                : [self::OVERRIDE_ALLOWED => [], self::OVERRIDE_DEFAULTS => []];

            $entries[$key] = [
                'key' => (string) $key,
                'label' => $animation->label(),
                'allowed' => $overrides[self::OVERRIDE_ALLOWED],
                'defaults' => $overrides[self::OVERRIDE_DEFAULTS],
            ];
        }

        return $entries;
    }

    /**
     * @return array{allowed: array<string, list<string>>, defaults: array<string, mixed>}
     */
    private function resolveOverrides(string $blockName, string $key, array $value): array
    {
        $empty = [self::OVERRIDE_ALLOWED => [], self::OVERRIDE_DEFAULTS => []];

        /* An empty array is a legitimate way to say "enabled, nothing overridden", and the way a
         child theme re-states an inherited animation without discarding its overrides: two arrays
         merge, so `[]` over an ancestor's overrides keeps them and never reaches here. This runs
         only when someone wrote `[]` directly. Not a mistake either way. */
        if ($value === []) {
            return $empty;
        }

        $context = "{$blockName} / {$key}";
        $unknown = array_diff(array_keys($value), [self::OVERRIDE_ALLOWED, self::OVERRIDE_DEFAULTS]);

        if (count($unknown) === count($value)) {
            /* Option names where the reserved keys belong. Deliberately not guessed at: `['color' =>
               ['purple']]` is equally consistent with a forgotten `allowed` wrapper and with a
               `defaults` list that normalization has already rewritten. */
            $this->flagProblem(
                $context,
                sprintf(
                    'has an override array with neither `allowed` nor `defaults` (found: %s). Ignoring the overrides. The animation stays enabled.',
                    implode(', ', $unknown),
                ),
            );

            return $empty;
        }

        if ($unknown) {
            $this->flagProblem(
                $context,
                sprintf('has unrecognized override key(s): %s. Ignoring them.', implode(', ', $unknown)),
            );
        }

        $allowed = array_key_exists(self::OVERRIDE_ALLOWED, $value)
            ? $this->resolveAllowed($context, $value[self::OVERRIDE_ALLOWED])
            : [];
        $defaults = array_key_exists(self::OVERRIDE_DEFAULTS, $value)
            ? $this->resolveDefaults($context, $value[self::OVERRIDE_DEFAULTS])
            : [];

        $this->flagUnpermittedDefaults($context, $allowed, $defaults);

        return [self::OVERRIDE_ALLOWED => $allowed, self::OVERRIDE_DEFAULTS => $defaults];
    }

    /**
     * Flag any default its own option's `allowed` list does not permit.
     *
     * The two halves are authored independently and resolved independently, so nothing else notices
     * when they disagree. The case that actually happens is a child theme narrowing a palette and
     * leaving an ancestor's default behind it: the editor then starts on a value its own control
     * cannot offer. Every other authoring mistake here is flagged, so this one is too.
     *
     * Only defaults set in config are covered. An animation's own built-in defaults are its
     * business, and this class never sees them.
     *
     * @param array<string, list<string>> $allowed
     * @param array<string, mixed>        $defaults
     */
    private function flagUnpermittedDefaults(string $context, array $allowed, array $defaults): void
    {
        foreach ($defaults as $option => $value) {
            /* Only values a control could offer from a list. A bool or null default is a literal
             setting — `reverse => false` — with nothing to match against. */
            if (!is_string($value) && !is_int($value) && !is_float($value)) {
                continue;
            }

            /* No restriction to violate. An `allowed` list that permits nothing at all is already
             flagged by resolveAllowed(), and one authoring mistake earns one problem. */
            if (!isset($allowed[$option]) || $allowed[$option] === []) {
                continue;
            }

            /* Compared as strings: resolveAllowed() casts permitted values to strings, while a
             default keeps the type it was authored with, so `speed => 25` matches '25'. */
            if (in_array((string) $value, $allowed[$option], true)) {
                continue;
            }

            $this->flagProblem(
                "{$context} / defaults / {$option}",
                sprintf(
                    'defaults to "%s", which its own `allowed` list does not permit (%s). The control will not offer it.',
                    $value,
                    implode(', ', $allowed[$option]),
                ),
            );
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function resolveAllowed(string $context, mixed $allowed): array
    {
        if (!is_array($allowed)) {
            /* The merge has already replaced the ancestor's list with this scalar, so the
             restriction is gone whatever happens here; say what the outcome is. */
            $this->flagProblem(
                $context,
                '`allowed` must map option names to their permitted values. Leaving every option unrestricted.',
            );

            return [];
        }

        $resolved = [];

        foreach ($allowed as $option => $values) {
            if (is_string($values) || is_int($values) || is_float($values)) {
                $resolved[$option] = [(string) $values];
                continue;
            }

            if (!is_array($values)) {
                $this->flagProblem(
                    "{$context} / allowed / {$option}",
                    'is not a list of permitted values. Leaving the option unrestricted.',
                );
                continue;
            }

            /* Normalization rewrote the authored list into `value => true`, which is also what lets a
               child theme drop one value with `value => false`. Recovering the list therefore means
               reading back the keys — and PHP casts a numeric-string key to an int on the way in, so
               an authored ['10', '30'] would come back as ints. Cast back: these are option values,
               matched against an editor control's own string values.
               Note the asymmetry with defaults, whose values keep the type they were authored with
               because normalization leaves a string key's scalar value alone. */
            $permitted = [];

            foreach ($values as $value => $marker) {
                if ($marker === false || $marker === null) {
                    continue;
                }

                /* Anything but `true` would otherwise read as permission, and the two ways of
                   getting one are both worth a word: a forgotten nesting level, where
                   ['brand' => ['purple', 'green']] permits the literal "brand" and loses the
                   palette; and a value marked with something that is not a marker, where
                   ['purple' => 0] permits purple all the same. */
                if ($marker !== true) {
                    $this->flagProblem(
                        "{$context} / allowed / {$option} / {$value}",
                        'is marked with neither `true` nor `false`, so it is not a permitted value as written. Dropping the value.',
                    );
                    continue;
                }

                $permitted[] = (string) $value;
            }

            $resolved[$option] = $permitted;

            if ($resolved[$option] === []) {
                $this->flagProblem(
                    "{$context} / allowed / {$option}",
                    'permits no values at all, so its control will offer no choices.',
                );
            }
        }

        return $resolved;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveDefaults(string $context, mixed $defaults): array
    {
        if (!is_array($defaults)) {
            $this->flagProblem(
                $context,
                '`defaults` must map option names to their default values. Applying no defaults.',
            );

            return [];
        }

        $resolved = [];

        foreach ($defaults as $option => $value) {
            if (is_array($value)) {
                /* A default is one value, and there is nothing sensible to do with several. An
                   authored list has lost the fact that it was one by the time it arrives, too:
                   ConfigRegistry::normalizeData() rewrites ['a', 'b'] into ['a' => true, 'b' => true],
                   which is indistinguishable from an authored map. Order survives; only list-versus-map
                   does not, and a string-keyed map like ['mobile' => '10'] arrives untouched. */
                $this->flagProblem(
                    "{$context} / defaults / {$option}",
                    'has an array default where one scalar value was expected. Dropping it.',
                );
                continue;
            }

            /* Everything else is taken literally, `false` and `null` included — `reverse => false`
             is a default, not a removal. There is no "unset the inherited default" sentinel here:
             removal by `=> false` stops one level up, at the animation. */
            $resolved[$option] = $value;
        }

        return $resolved;
    }

    private function flagProblem(string $context, string $problem): void
    {
        $this->problems[] = "{$context}: {$problem}";
    }
}
