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
 * Settings of its own are optional, declared through controls() — see AnimationControl. So is markup
 * the block needs, declared through markup() and placed by markupHosts().
 *
 * HOOK_SUFFIX is deliberately left empty here so that every subclass declares its own — a module
 * with an empty suffix is skipped by ModuleRegistry::addModules() with a logged warning. The
 * animation key defaults to it, so one declaration covers both; see key() for when to override.
 *
 * Two things that are easy to get wrong:
 *
 * 1. Assets load on every page, not per block. An animation enqueues its CSS and JS from init(),
 *    rather than conditionally wherever it happens to be used. Animations are expected across many
 *    blocks and pages, and it is the per-block data-animation attribute that actually triggers one
 *    on a given element; a stylesheet with no matching attribute on the page costs only its
 *    transfer size and the render-blocking parse it pays for in the head, while conditional loading
 *    produces an inconsistent feel. If one animation's payload grows heavy, revisit it alone rather
 *    than changing this default.
 *
 *    The stylesheet goes through enqueueGlobalAssets(), which hooks enqueue_block_assets and so
 *    reaches the front end and the editor canvas both. The canvas carries the same data-animation
 *    and custom properties the front end does, so the animation previews as it will look. The
 *    script, if there is one, stays on enqueueFrontendAssets(), so animation JS does not run
 *    inside the editor by accident. That is the split KadenceBlocks.php already uses. Not
 *    enqueueEditorPreviewAssets(): it hooks enqueue_block_assets behind an is_admin() guard
 *    (Module.php), so a stylesheet there would be missing from the front end.
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

    /** Reduced motion is the framework's: its stylesheet stops this animation's motion. */
    public const MOTION_FRAMEWORK = 'framework';

    /** Reduced motion is the animation's own: its stylesheet or script handles the preference. */
    public const MOTION_OWN = 'own';

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
     *
     * A key must be lowercase kebab-case with each segment led by a letter — `letter`, `fade-up` —
     * or the animation is skipped with a logged error (AnimationFrameworkModule::KEY_PATTERN).
     */
    public function key(): string
    {
        return static::HOOK_SUFFIX;
    }

    /**
     * Human-readable name, shown in the editor's Animation select.
     */
    abstract public function label(): string;

    /**
     * The settings this animation offers in the editor, shown only while it is the selected one.
     *
     * None by default: an animation driven purely by its own stylesheet needs no controls.
     *
     * Names are local — `color`, not `letterAnimationColor`. The framework builds each block attribute from
     * key() and the name (AnimationFrameworkModule::attributeName()), which is what keeps two
     * animations' controls from colliding. That makes a control's name content in the same way the
     * key is: saved blocks store their values under it, so renaming a control on an animation that
     * has shipped orphans every value already chosen with it, silently.
     *
     * @return list<AnimationControl>
     */
    public function controls(): array
    {
        return [];
    }

    /**
     * Who handles `prefers-reduced-motion` for this animation.
     *
     * MOTION_FRAMEWORK, the default: the framework's stylesheet stops the motion, settling each
     * animation on its last frame. An animation that declares nothing therefore degrades safely.
     *
     * MOTION_OWN: the block carries `data-animation-motion="own"`, the framework's rule skips it,
     * and this animation handles the preference itself — pausing on a chosen frame, say, or
     * keeping a busy indicator spinning because the motion is the information. A JS behaviour of
     * an animation that doesn't return MOTION_OWN is not started under reduced motion at all.
     *
     * Any other value is logged and treated as MOTION_FRAMEWORK.
     */
    public function reducedMotion(): string
    {
        return static::MOTION_FRAMEWORK;
    }

    /**
     * Markup this animation needs inside the block — a glyph, an overlay — or null for none.
     *
     * Most animations need none. When given, the framework inserts it as the first child of the
     * block's host element (see markupHosts()) wherever the animation is selected. It is inserted
     * unescaped, so it must be a constant: never anything an author entered.
     */
    public function markup(): ?string
    {
        return null;
    }

    /**
     * Where markup() goes: the first element, in document order, carrying any of these classes.
     * Empty, the default, means the block's own outermost element.
     *
     * Name the element an animation's stylesheet expects to host it. The letter animation crops
     * its glyph against Kadence's inner container, where a column's background is painted, so it
     * lists `kt-inside-inner-col` and `kt-row-column-wrap`.
     *
     * Every block configured for this animation must render one of these hosts itself. The lookup
     * runs over the block's rendered HTML, which already holds its inner blocks, so on a block
     * without one the markup lands in the first inner block that has one. Only when nothing in
     * the block carries any of them is the markup skipped, with a logged warning.
     *
     * @return list<string>
     */
    public function markupHosts(): array
    {
        return [];
    }
}
