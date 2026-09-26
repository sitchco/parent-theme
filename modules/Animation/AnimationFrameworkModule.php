<?php

namespace Sitchco\Parent\Modules\Animation;

use Sitchco\Framework\ConfigRegistry;
use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleRegistry;
use Sitchco\Utils\Logger;

/**
 * Coordinator for the animation framework.
 *
 * Animations are discovered, not registered: this asks ModuleRegistry for the active modules and
 * keeps the ones that are AnimationModule instances. An animation therefore lives in whichever theme
 * wants it and is activated like any other module, and adding one never means editing a list here.
 *
 * Two config sections have to agree. `modules` says which animations exist; `animations` says which
 * blocks may use them:
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
 * `allowed` and `defaults` are the only reserved sub-keys. Merging is additive, so a child theme
 * cannot delete a key an ancestor set — removal is `=> false`. That works at exactly three points:
 * a block (`'kadence/column' => false`), an animation on a block (`'parallax' => false`), and one
 * permitted value inside `allowed` (`'allowed' => ['color' => ['green' => false]]`).
 *
 * It stops there. There is no way to unset a whole inherited restriction or default: `'allowed' =>
 * false`, `'allowed' => ['color' => false]` and `'defaults' => false` are authoring mistakes, each
 * logged and each leaving that option unrestricted or undefaulted. A theme wanting a different
 * palette or default states the one it wants rather than removing the one it inherited.
 *
 * TWO SILENT TRAPS, neither of which this class can detect at runtime:
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
 * The merged config is object-cached for a day under `sitchco_config`, so on non-local environments a
 * config edit — including one that fixes a warning logged from here — needs ConfigRegistry::clearCache()
 * or a cache flush before it takes effect.
 *
 * At this stage the coordinator discovers and resolves. Building the editor controls and emitting the
 * data attributes arrive with the stories that need them.
 */
class AnimationFrameworkModule extends Module
{
    public const HOOK_SUFFIX = 'animation-framework';

    /** Top-level config section mapping block names to the animations allowed on them. */
    public const CONFIG_KEY = 'animations';

    /** The only sub-keys a per-block override array may use. */
    private const OVERRIDE_ALLOWED = 'allowed';
    private const OVERRIDE_DEFAULTS = 'defaults';

    /**
     * Memoized result of discoverAnimations(); null until first asked.
     * @var array<string, AnimationModule>|null
     */
    private ?array $animations = null;

    /**
     * Memoized result of resolveBlockAnimations(); null until first asked.
     * @var array<string, array<string, array{key: string, label: string, allowed: array<string, list<string>>, defaults: array<string, mixed>}>>|null
     */
    private ?array $blockAnimations = null;

    /** Problems found during one resolution pass, emitted together as a single warning. */
    private array $configProblems = [];

    /** Whether init() has run; until it has, nothing below memoizes. See getAnimations(). */
    private bool $initialized = false;

    public function __construct(protected ModuleRegistry $moduleRegistry, protected ConfigRegistry $configRegistry) {}

    /**
     * Opens memoization, and nothing else.
     *
     * ModuleRegistry runs every init() only after the registration pass has finished, so this is the
     * earliest moment at which the active-module list is whole and an answer is safe to keep.
     */
    public function init(): void
    {
        $this->initialized = true;
    }

    /**
     * Every active animation, keyed by its key().
     *
     * Safe from init() onward, never from a constructor. ModuleRegistry::registerActiveModule()
     * builds a module through the container before adding it to the active list, so the list is
     * still filling while constructors run; ModuleRegistry::activateModules() completes that whole
     * registration pass before initializing anything, so by the time any init() runs it is whole.
     *
     * Memoizing a partial list would drop an animation for the rest of the request and say nothing,
     * which is why the rule is enforced rather than just stated: until init() has run, this answers
     * from whatever is registered so far but keeps nothing. A too-early caller gets a possibly short
     * list; it cannot freeze one in for everyone else.
     *
     * @return array<string, AnimationModule>
     */
    public function getAnimations(): array
    {
        if (!$this->initialized) {
            return $this->discoverAnimations();
        }

        return $this->animations ??= $this->discoverAnimations();
    }

    /**
     * A single animation by key, or null when no active animation claims it.
     */
    public function getAnimation(string $key): ?AnimationModule
    {
        return $this->getAnimations()[$key] ?? null;
    }

    /**
     * Every configured block, in config order, mapped to the animations it may use.
     *
     * Resolved in one pass rather than per block on demand, for two reasons: the editor needs the
     * whole map anyway to build its controls, and lazy per-block resolution would make the config
     * warnings below fire zero, one or many times depending on which blocks a request happened to
     * render. Memoized, and subject to the same timing rule as getAnimations(), which it calls.
     *
     * The result is deliberately plain, JSON-serializable data rather than AnimationModule instances:
     * it is passed to the editor as inline script data, and anything needing the module itself has
     * the key to look it up with getAnimation().
     *
     * Not object-cached. It depends on the runtime active-module set and not only on config, so
     * caching it would need a second invalidation surface for no measurable gain.
     *
     * @return array<string, array<string, array{
     *     key:      string,
     *     label:    string,
     *     allowed:  array<string, list<string>>,
     *     defaults: array<string, mixed>,
     * }>>
     */
    public function getBlockAnimations(): array
    {
        if (!$this->initialized) {
            return $this->resolveBlockAnimations();
        }

        return $this->blockAnimations ??= $this->resolveBlockAnimations();
    }

    /**
     * The animations one block may use, or an empty array when the block is not configured for any.
     *
     * @return array<string, array{key: string, label: string, allowed: array<string, list<string>>, defaults: array<string, mixed>}>
     */
    public function getAnimationsForBlock(string $blockName): array
    {
        return $this->getBlockAnimations()[$blockName] ?? [];
    }

    /**
     * @return array<string, AnimationModule>
     */
    private function discoverAnimations(): array
    {
        $animations = [];

        foreach ($this->moduleRegistry->getActiveModules() as $classname => $module) {
            if (!($module instanceof AnimationModule)) {
                continue;
            }

            /* Reachable only through an override: the default key() returns HOOK_SUFFIX, which
             ModuleRegistry::addModules() has already refused to leave empty. */
            $key = $module->key();
            if ($key === '') {
                Logger::error("Animation {$classname} returned an empty key(). Skipping.");
                continue;
            }

            /* First registered wins. Modules are instantiated in dependency order, so the winner is
               stable across requests rather than whichever happened to land last — and the loser is
               logged, because two animations claiming one key is the kind of mistake that otherwise
               shows up only as an animation that mysteriously never applies. */
            if (isset($animations[$key])) {
                $kept = get_class($animations[$key]);
                Logger::error(
                    "Duplicate animation key \"{$key}\": {$classname} collides with {$kept}. Keeping {$kept}.",
                );
                continue;
            }

            $animations[$key] = $module;
        }

        return $animations;
    }

    /**
     * @return array<string, array<string, array{key: string, label: string, allowed: array<string, list<string>>, defaults: array<string, mixed>}>>
     */
    private function resolveBlockAnimations(): array
    {
        $this->configProblems = [];
        $resolved = [];

        foreach ($this->configRegistry->load(static::CONFIG_KEY) as $blockName => $blockConfig) {
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

        $this->reportConfigProblems();

        return $resolved;
    }

    /**
     * @return array<string, array{key: string, label: string, allowed: array<string, list<string>>, defaults: array<string, mixed>}>
     */
    private function resolveBlock(string $blockName, array $blockConfig): array
    {
        $entries = [];

        foreach ($blockConfig as $key => $value) {
            /* The removal idiom. Tested explicitly rather than by truthiness, because an override
               array and an empty array are both falsy-adjacent values that must survive: `[]` means
               "enabled, no overrides", and array_filter() or a `=== true` test would drop both. */
            if ($value === false || $value === null) {
                continue;
            }

            $animation = $this->getAnimation((string) $key);
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
                    'has an override array with neither `allowed` nor `defaults` (found: %s). Ignoring the overrides.',
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
            $resolved[$option] = array_map(
                'strval',
                array_keys(array_filter($values, fn($permitted) => $permitted !== false && $permitted !== null)),
            );

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
                /* Unrecoverable by the time it arrives: ConfigRegistry::normalizeData() has already
                   rewritten ['a', 'b'] into ['a' => true, 'b' => true], so neither the authored order
                   nor the fact that it was a list survives to be interpreted. */
                $this->flagProblem(
                    "{$context} / defaults / {$option}",
                    'has an array default, which config normalization rewrites into a keyed map. Give it a scalar default instead. Dropping it.',
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
        $this->configProblems[] = "{$context}: {$problem}";
    }

    /**
     * One warning per resolution, not one per problem.
     *
     * Every one of these is a config authoring mistake with a defined, safe fallback — the offending
     * entry is dropped — so none of them is an error. Collecting them keeps the log to a single line,
     * makes their order deterministic, and keeps them all assertable: Logger retains only its last
     * entry, so separate calls would hide each other.
     */
    private function reportConfigProblems(): void
    {
        if (!$this->configProblems) {
            return;
        }

        Logger::warning([
            'message' => sprintf(
                'Animation config problems in the "%s" section of sitchco.config.php.',
                static::CONFIG_KEY,
            ),
            'problems' => $this->configProblems,
        ]);
    }
}
