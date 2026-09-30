<?php

namespace Sitchco\Parent\Modules\Animation;

use Sitchco\Framework\Module;

/**
 * Base class for a single animation.
 *
 * An animation is an ordinary module: it is activated by appearing in the `modules` section of a
 * sitchco.config.php, it resolves through the container with its dependencies autowired, and it owns
 * its own assets. AnimationFrameworkModule finds every active one by type, so there is no second
 * registry to keep in step by hand.
 *
 * A concrete animation declares two things:
 *
 *     class LetterAnimation extends AnimationModule
 *     {
 *         public const HOOK_SUFFIX = 'letter';
 *
 *         public function label(): string { return 'Letter Animation'; }
 *     }
 *
 * HOOK_SUFFIX is deliberately left empty here so that every subclass declares its own — a module
 * with an empty suffix is skipped by ModuleRegistry::addModules() with a logged warning. The
 * animation key defaults to it, so one declaration covers both; see key() for when to override.
 *
 * Two things that are easy to get wrong:
 *
 * 1. Assets load on every page, not per block, and only on the front end. An animation enqueues its
 *    CSS and JS from init() via enqueueFrontendAssets(), rather than conditionally wherever it
 *    happens to be used. Animations are expected across many blocks and pages, and it is the
 *    per-block data-animation attribute that actually triggers one on a given element; a stylesheet
 *    with no matching attribute on the page costs only its transfer size and the render-blocking
 *    parse it pays for in the head, while conditional loading produces an inconsistent feel. If one
 *    animation's payload grows heavy, revisit it alone rather than changing this default.
 *
 *    enqueueFrontendAssets() hooks wp_enqueue_scripts, which does not fire for the block editor
 *    canvas, so an animation built this way does not preview in the editor. That is intended for
 *    now: nothing emits the attributes an animation reacts to yet. If editor preview is wanted
 *    later, the stylesheet moves to enqueueGlobalAssets() — which hooks enqueue_block_assets and so
 *    covers the front end and the editor both — while the script stays on enqueueFrontendAssets().
 *    That is the split KadenceBlocks.php:24-30 already uses, and it keeps whether animation JS runs
 *    inside the editor a decision made then rather than by accident. Not enqueueEditorPreviewAssets():
 *    it hooks enqueue_block_assets behind an is_admin() guard (Module.php:122), so moving the
 *    stylesheet there would take it off the front end.
 *
 * 2. DEPENDENCIES does not merge. PHP replaces a class constant rather than combining it, so a
 *    subclass needing its own dependency has to carry the parent's forward:
 *
 *        public const DEPENDENCIES = [...parent::DEPENDENCIES, ExtendBlockModule::class];
 *
 *    Writing `[ExtendBlockModule::class]` instead drops the coordinator from this animation's
 *    dependencies. The parent theme activates it unconditionally today, so nothing breaks — but a
 *    child theme that switches it off with `AnimationFrameworkModule::class => false` would then
 *    leave this animation with nothing to discover it. ModuleRegistry follows DEPENDENCIES without
 *    consulting config, so keeping the parent's entry is what turns the coordinator back on.
 */
abstract class AnimationModule extends Module
{
    /**
     * Activating any animation pulls in the coordinator. Read note 2 above before overriding this.
     */
    public const DEPENDENCIES = [AnimationFrameworkModule::class];

    /**
     * Unique animation key — the value stored on the block and emitted as data-animation.
     *
     * Defaults to HOOK_SUFFIX, which is already required and already guaranteed non-empty, so a
     * typical animation names itself once. Override only when the two genuinely need to differ.
     *
     * The one case that forces an override is a rename. These two strings have different lifetimes:
     * HOOK_SUFFIX is internal, feeding hook names and asset handles, and renaming it is a refactor.
     * The key is content — it is persisted in saved blocks and matched by CSS. Renaming HOOK_SUFFIX
     * on an animation that has already shipped would therefore change the key too and silently
     * orphan every block using it. Pin the old value here instead:
     *
     *     public function key(): string { return 'letter'; }
     */
    public function key(): string
    {
        return static::HOOK_SUFFIX;
    }

    /**
     * Human-readable name, shown in the editor's Animation select.
     */
    abstract public function label(): string;
}
