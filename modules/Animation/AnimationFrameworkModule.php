<?php

namespace Sitchco\Parent\Modules\Animation;

use Sitchco\Framework\ConfigRegistry;
use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;
use Sitchco\Framework\ModuleRegistry;
use Sitchco\Modules\UIFramework\UIFramework;
use Sitchco\Parent\Modules\ExtendBlock\ExtendBlockModule;
use Sitchco\Utils\Logger;

/**
 * Coordinator for the animation framework.
 *
 * Animations are discovered, not registered: this asks ModuleRegistry for the active modules and
 * keeps the ones that are AnimationModule instances. An animation therefore lives in whichever theme
 * wants it and is activated like any other module, and adding one never means editing a list here.
 *
 * Two config sections have to agree. `modules` says which animations exist; `animations` says which
 * blocks may use them. The config language itself — the section's shape, the removal idiom, how
 * an inherited override merges, and the traps normalization sets — is documented and implemented
 * in AnimationConfigResolver; this class supplies the active animations and reports what it finds.
 *
 * The merged config is object-cached for a day under `sitchco_config`, so on non-local environments a
 * config edit — including one that fixes a warning logged from here — needs ConfigRegistry::clearCache()
 * or a cache flush before it takes effect.
 *
 * The coordinator discovers animations and their controls, resolves config against both, and hands
 * the result to the editor, where one ExtendBlock registration turns it into an Animation select
 * per configured block plus each animation's own controls.
 *
 * It also renders what a block emits on the front end: `data-animation="<key>"` and one custom
 * property per control, `--{key}-animation-{name}` (see wrapperProps()). That output is written
 * server-side for every block, static or dynamic, through ExtendBlockModule's `wrapper-props`
 * filter, and never into saved markup: the editor registration uses `saveOutput: false` and
 * shows the same output in the canvas only. So no block can fail validation over an animation,
 * and a changed config default reaches every block that hasn't stored a value of its own.
 *
 * An animation that needs markup of its own inside the block has it inserted the same way, on
 * render_block (injectMarkup()).
 */
class AnimationFrameworkModule extends Module
{
    public const HOOK_SUFFIX = 'animation-framework';

    /** The editor control is an ExtendBlock registration, so the library has to be present. */
    public const DEPENDENCIES = [ExtendBlockModule::class];

    /** Top-level config section mapping block names to the animations allowed on them. */
    public const CONFIG_KEY = 'animations';

    /** The form every animation key must take: lowercase kebab, each segment led by a letter. */
    public const KEY_PATTERN = '/^[a-z][a-z0-9]*(-[a-z][a-z0-9]*)*$/D';

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

    /**
     * Memoized result of validateControls(); null until first asked.
     * @var array<string, array<string, AnimationControl>>|null
     */
    private ?array $controls = null;

    /** Whether init() has run; until it has, nothing below memoizes. See getAnimations(). */
    private bool $initialized = false;

    /** Animation and block pairs already warned about having no markup host this request. */
    private array $warnedHostless = [];

    public function __construct(
        protected ModuleRegistry $moduleRegistry,
        protected ConfigRegistry $configRegistry,
        protected AnimationControlValidator $controlValidator,
    ) {}

    /**
     * Opens memoization and registers the editor control.
     *
     * ModuleRegistry runs every init() only after the registration pass has finished, so this is the
     * earliest moment at which the active-module list is whole and an answer is safe to keep.
     *
     * Nothing is resolved here. The enqueue callback runs on enqueue_block_editor_assets, long after
     * every module has initialized, which is both what the timing rule above requires and what keeps
     * a front-end request from resolving config it will never use.
     */
    public function init(): void
    {
        $this->initialized = true;

        /* Front-end assets only where blocks may carry animations at all. Checked against the raw
           section, which is a cached config read, rather than by resolving it: resolution would log
           any config problem on every page view, not just where an animated block renders. */
        // The reduced-motion rule; global, so the canvas follows the preference as the front end does.
        $this->enqueueGlobalAssets(function (ModuleAssets $assets) {
            if ($this->hasAnimationConfig()) {
                $assets->enqueueStyle(static::hookName(), 'main.css');
            }
        });
        // The behaviour runtime; front end only, so no animation JS runs inside the editor.
        $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
            if ($this->hasAnimationConfig()) {
                $assets->enqueueScript(static::hookName('runtime'), 'animation.js', [UIFramework::hookName()]);
            }
        });

        add_filter(ExtendBlockModule::hookName('wrapper-props'), [$this, 'wrapperProps'], 10, 2);
        // After core's block supports at 10, so a block they hide arrives empty and is skipped.
        add_filter('render_block', [$this, 'injectMarkup'], 11, 2);

        $this->enqueueEditorUIAssets(function (ModuleAssets $assets) {
            $blockAnimations = $this->getBlockAnimations();

            /* No configured block means no control, and no reason to ship the script that would
               build one. Same shape as BlockConfig::postTypeBlockVisibility(), which is the
               established way to hand a resolved config section to the editor. */
            if (!$blockAnimations) {
                return;
            }

            $assets->enqueueScript(static::hookName('editor-ui'), 'editor-ui.js', [
                'wp-block-editor',
                'wp-components',
                'wp-compose',
                'wp-data',
                'wp-element',
                'wp-hooks',
                UIFramework::hookName('editor'),
                ExtendBlockModule::hookName(),
            ]);
            $assets->inlineScriptData(static::hookName('editor-ui'), 'animations', [
                'blocks' => $blockAnimations,
                'controls' => $this->getAnimationControls(),
                'ownMotion' => $this->getOwnMotionKeys(),
            ]);
        });
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
     * The block attribute a control's value is stored under: the animation key in camelCase, then
     * `Animation`, then the control name with its first letter raised. `letter` + `color` is
     * `letterAnimationColor`; `fade-up` + `speed` is `fadeUpAnimationSpeed`.
     *
     * `Animation` is in the name because a key alone often says nothing about what it is —
     * `letterColor` or `stickyOffset` could belong to anything on the block. By convention an
     * animation's class is named `{Key}Animation` (LetterAnimation for `letter`), so the attribute
     * reads as the module's own name. It is derived from the key and not from the class because the
     * key is the value pinned against renames; a class can be renamed in a refactor, and every saved
     * value would follow it into orphanhood.
     *
     * Built here, once, and handed to the editor with each control, so nothing on the JS side ever
     * derives it a second time. Both inputs are content — see key() and controls() — so the result
     * is too: it is the name saved blocks store their values under.
     */
    public static function attributeName(string $key, string $name): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $key)))) . 'Animation' . ucfirst($name);
    }

    /**
     * The custom property a control's CSS value is written to: `--{key}-animation-{name}`, with a
     * camelCase name in kebab-case. `letter` + `color` is `--letter-animation-color`; `fade-up` +
     * `startAt` is `--fade-up-animation-start-at`.
     *
     * The animation's stylesheet consumes it, and a JS behaviour reads it with getComputedStyle(),
     * so like attributeName() it is built here once and shipped to the editor with each control.
     * Kebab keys and camelCase names keep it unambiguous: no two controls can share one.
     */
    public static function cssProperty(string $key, string $name): string
    {
        return "--{$key}-animation-" . strtolower(preg_replace('/[A-Z]/', '-$0', $name));
    }

    /**
     * What a block emits for its animation, added to ExtendBlockModule's `wrapper-props`.
     *
     * Nothing unless the block's stored `animation` is one the block may use now: a key the config
     * withdrew, or an animation no longer active, emits nothing, as the editor's select shows it as
     * "(unavailable)". Otherwise `data-animation`, `data-animation-motion="own"` for an animation
     * that handles reduced motion itself, and for each control with a CSS value, its custom
     * property.
     *
     * A control's value is resolved in the order the editor's function default uses: the value
     * stored on the block, else the block's config default, else the control's own. A value the
     * block's `allowed` list no longer permits emits nothing for that control.
     *
     * Hooked for every block on every request, so the cheapest test comes first: most blocks have
     * no `animation` attribute at all, and leave without config being resolved.
     *
     * @param array{attributes: array<string, mixed>, style: array<string, mixed>} $props
     * @param array{blockName?: ?string, attrs?: array<string, mixed>} $block
     */
    public function wrapperProps(array $props, array $block): array
    {
        $attrs = $block['attrs'] ?? [];
        $key = $attrs['animation'] ?? null;
        if (!is_string($key) || $key === '') {
            return $props;
        }

        $entry = $this->getAnimationsForBlock((string) ($block['blockName'] ?? ''))[$key] ?? null;
        if ($entry === null) {
            return $props;
        }

        $props['attributes']['data-animation'] = $key;
        if ($this->getAnimation($key)?->reducedMotion() === AnimationModule::MOTION_OWN) {
            $props['attributes']['data-animation-motion'] = AnimationModule::MOTION_OWN;
        }

        foreach ($this->getControls()[$key] ?? [] as $name => $control) {
            $attribute = static::attributeName($key, $name);
            $value = match (true) {
                isset($attrs[$attribute]) => $attrs[$attribute],
                array_key_exists($name, $entry['defaults']) => $entry['defaults'][$name],
                default => $control->default,
            };

            $permitted = $entry['allowed'][$name] ?? null;
            if ($permitted !== null && (!is_scalar($value) || !in_array((string) $value, $permitted, true))) {
                continue;
            }

            $css = $control->cssValue($value);
            if ($css !== null) {
                $props['style'][static::cssProperty($key, $name)] = $css;
            }
        }

        return $props;
    }

    /**
     * Inserts the selected animation's markup() into a block's rendered HTML, at its host.
     *
     * Only for an animation the block may use now, as with wrapperProps(), and only when the
     * animation declares markup. A block with no host for it (see AnimationModule::markupHosts())
     * renders without the markup, and the miss is logged as a warning once per animation and block
     * type per request: the fix is a config or markupHosts() change, and render_block runs on every
     * request, so a warning per render would bury it.
     *
     * Driven by the stored `animation` attribute, not by a class: nothing an ExtendBlock control
     * writes reaches `attrs.className`.
     */
    public function injectMarkup(string $blockContent, array $block): string
    {
        $key = $block['attrs']['animation'] ?? null;
        if (!is_string($key) || $key === '') {
            return $blockContent;
        }

        $blockName = (string) ($block['blockName'] ?? '');
        if (!isset($this->getAnimationsForBlock($blockName)[$key])) {
            return $blockContent;
        }

        $animation = $this->getAnimation($key);
        $markup = $animation?->markup();
        if ($markup === null || $markup === '') {
            return $blockContent;
        }
        // A block that rendered nothing — hidden with core's visibility support, say — has nothing
        // to host the markup, and nothing is misconfigured.
        if (trim($blockContent) === '') {
            return $blockContent;
        }

        $hosts = $animation->markupHosts();
        $injected = MarkupInjector::inject($blockContent, $markup, $hosts);
        if ($injected !== null) {
            return $injected;
        }

        if (!isset($this->warnedHostless[$key][$blockName])) {
            $this->warnedHostless[$key][$blockName] = true;
            Logger::warning(
                sprintf(
                    'Animation "%s" has no host for its markup in a %s block (%s), so it renders without it.',
                    $key,
                    $blockName,
                    $hosts
                        ? 'no element with the class ' . implode(' or ', $hosts)
                        : 'no outermost element that can hold children',
                ),
            );
        }

        return $blockContent;
    }

    /**
     * Whether the `animations` config section names any block at all, before resolution.
     */
    private function hasAnimationConfig(): bool
    {
        return !empty($this->configRegistry->load(static::CONFIG_KEY));
    }

    /**
     * The keys of every active animation that handles reduced motion itself, for the editor to
     * emit `data-animation-motion` as wrapperProps() does.
     *
     * @return list<string>
     */
    public function getOwnMotionKeys(): array
    {
        return array_keys(
            array_filter(
                $this->getAnimations(),
                fn(AnimationModule $animation) => $animation->reducedMotion() === AnimationModule::MOTION_OWN,
            ),
        );
    }

    /**
     * Every active animation's valid controls, keyed by animation key and then control name.
     *
     * Validated once and memoized, under the same timing rule as getAnimations(). A control that
     * fails validation is dropped here with an error, so everything downstream — config resolution
     * and the editor alike — sees only controls that work.
     *
     * @return array<string, array<string, AnimationControl>>
     */
    public function getControls(): array
    {
        if (!$this->initialized) {
            return $this->validateControls();
        }

        return $this->controls ??= $this->validateControls();
    }

    /**
     * The controls as the editor receives them: per animation key, a list of serialized controls,
     * each carrying the `attribute` its value is stored under and the `cssProperty` it emits to. Animations without controls are left
     * out.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function getAnimationControls(): array
    {
        $serialized = [];

        foreach ($this->getControls() as $key => $controls) {
            foreach ($controls as $control) {
                $serialized[$key][] = [
                    ...$control->jsonSerialize(),
                    'attribute' => static::attributeName($key, $control->name),
                    'cssProperty' => static::cssProperty($key, $control->name),
                ];
            }
        }

        return $serialized;
    }

    /**
     * Every block this resolves something for, in config order, mapped to the animations it may use.
     * A configured block is left out when it is removed, written bare, written as a scalar, or ends
     * up with no animations at all — see droppedBlockProvider() in the tests for the full set.
     *
     * Resolved in one pass rather than per block on demand, for two reasons: the editor needs the
     * whole map anyway to build its controls, and lazy per-block resolution would make the config
     * warnings below fire zero, one or many times depending on which blocks a request happened to
     * render. Memoized, and subject to the same timing rule as getAnimations(), which it calls.
     *
     * The result is deliberately plain, JSON-serializable data rather than AnimationModule instances:
     * it will be passed to the editor as inline script data, and anything needing the module itself has
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

            /* Normally reachable only through an override: the default key() returns HOOK_SUFFIX,
             which ModuleRegistry::addModules() has already refused to leave empty. An animation
             pulled in solely through another module's DEPENDENCIES never passes through that
             check at all — see ModuleRegistry::registerActiveModule(). */
            $key = $module->key();
            if ($key === '') {
                Logger::error("Animation {$classname} returned an empty key(). Skipping.");
                continue;
            }

            /* The key is content: saved blocks store it, it is emitted as data-animation and
               matched by CSS, and it is the stem of every control's attribute name. So it is held
               to one form from the start, lowercase kebab with each segment led by a letter,
               because a key cannot be changed once content uses it. */
            if (!preg_match(static::KEY_PATTERN, $key)) {
                Logger::error(
                    "Animation {$classname} has the key \"{$key}\", which is not lowercase kebab-case (e.g. \"fade-up\"). Skipping.",
                );
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

            $motion = $module->reducedMotion();
            if (!in_array($motion, [AnimationModule::MOTION_FRAMEWORK, AnimationModule::MOTION_OWN], true)) {
                Logger::error(
                    "Animation {$classname} returned \"{$motion}\" from reducedMotion(), which is neither MOTION_FRAMEWORK nor MOTION_OWN. Treating it as MOTION_FRAMEWORK.",
                );
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
        /* Read once and closed over: before init() getControls() does not memoize, and the resolver
         asks once per override entry, so each ask would re-run validation and log its problems again. */
        $controls = $this->getControls();
        $resolver = new AnimationConfigResolver(
            $this->configRegistry->load(static::CONFIG_KEY),
            fn(string $key) => $this->getAnimation($key),
            fn(string $key) => $controls[$key] ?? [],
        );
        ['blocks' => $blocks, 'problems' => $problems] = $resolver->resolve();

        $this->reportConfigProblems($problems);

        return $blocks;
    }

    /**
     * Drops every control that cannot work, reporting them all in one error. The rules are
     * AnimationControlValidator's; see there for which definition wins.
     *
     * @return array<string, array<string, AnimationControl>>
     */
    private function validateControls(): array
    {
        ['controls' => $controls, 'problems' => $problems] = $this->controlValidator->validate($this->getAnimations());

        if ($problems) {
            Logger::error(['message' => 'Animation control problems.', 'problems' => $problems]);
        }

        return $controls;
    }

    /**
     * One warning per resolution, not one per problem.
     *
     * Every one of these is a config authoring mistake with a defined, safe fallback, so none of them
     * is an error. The fallbacks differ — some drop the offending entry, others keep the animation and
     * apply nothing, or keep a flagged default the control will not offer — so each message states its
     * own rather than relying on a rule stated once here. Collecting them keeps the log to a single
     * line, makes their order deterministic, and keeps them all assertable: Logger retains only its
     * last entry, so separate calls would hide each other.
     *
     * @param list<string> $problems
     */
    private function reportConfigProblems(array $problems): void
    {
        if (!$problems) {
            return;
        }

        Logger::warning([
            'message' => sprintf(
                'Animation config problems in the "%s" section of sitchco.config.php.',
                static::CONFIG_KEY,
            ),
            'problems' => $problems,
        ]);
    }
}
