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
 * The coordinator discovers, resolves, and hands the resolved map to the editor, where one
 * ExtendBlock registration turns it into an Animation select per configured block. Emitting the
 * data attributes and injecting per-animation markup arrive with the stories that need them.
 */
class AnimationFrameworkModule extends Module
{
    public const HOOK_SUFFIX = 'animation-framework';

    /** The editor control is an ExtendBlock registration, so the library has to be present. */
    public const DEPENDENCIES = [ExtendBlockModule::class];

    /** Top-level config section mapping block names to the animations allowed on them. */
    public const CONFIG_KEY = 'animations';

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

    /** Whether init() has run; until it has, nothing below memoizes. See getAnimations(). */
    private bool $initialized = false;

    public function __construct(protected ModuleRegistry $moduleRegistry, protected ConfigRegistry $configRegistry) {}

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
            $assets->inlineScriptData(static::hookName('editor-ui'), 'animations', $blockAnimations);
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
        $resolver = new AnimationConfigResolver(
            $this->configRegistry->load(static::CONFIG_KEY),
            fn(string $key) => $this->getAnimation($key),
        );
        ['blocks' => $blocks, 'problems' => $problems] = $resolver->resolve();

        $this->reportConfigProblems($problems);

        return $blocks;
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
