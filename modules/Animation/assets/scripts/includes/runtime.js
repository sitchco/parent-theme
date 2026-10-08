/**
 * The front-end animation runtime: starts each animation's JS behaviour on the blocks that carry it.
 *
 * Most animations are CSS only and register nothing here; their stylesheet matches
 * `[data-animation='<key>']` and that is the whole of it. An animation that needs JS — continuous
 * parallax, say — registers a behaviour under its key through the `animation.behaviors` filter,
 * during `sitchco.init`:
 *
 *     sitchco.init(() => {
 *         sitchco.hooks.addFilter('animation.behaviors', 'mytheme/parallax', (behaviors) => ({
 *             ...behaviors,
 *             parallax: {
 *                 init(el, ctx) {
 *                     const speed = parseFloat(ctx.option('speed')) || 0;
 *                     const onScroll = (event, position) => { … };
 *                     sitchco.hooks.addAction('scroll', onScroll);
 *                     return () => sitchco.hooks.removeAction('scroll', onScroll);
 *                 },
 *             },
 *         }));
 *     });
 *
 * - `init(el, ctx)` runs once per element. It may return a cleanup function, which teardown()
 *   calls.
 * - `ctx.option(name)` reads the control's custom property, `--{key}-animation-{name}`, from the
 *   element's computed style: the same value the stylesheet sees, so CSS and JS share one channel.
 *   It is `''` when the control emits nothing, so the behaviour supplies its own default.
 * - `ctx.reducedMotion` is the visitor's preference. A behaviour only sees it true when its
 *   animation handles reduced motion itself (AnimationModule::MOTION_OWN): any other animation's
 *   behaviour is not started at all while the preference is set.
 * - Reveal-on-enter belongs in `sitchco.scrollWatch(els, cb)`; continuous motion in the `scroll`
 *   action, offset by the `header-height` filter, as SiteHeader's sticky.js does.
 *
 * Regions that re-render after load (fetched results, say) call `sitchco.animations.teardown(root)`
 * before replacing their content and `sitchco.animations.scan(root)` after, so nothing is started
 * twice and nothing is left running against a detached element.
 *
 * Plain JS that touches the DOM only through what it is handed, so it is unit testable under node
 * with plain objects for elements. See tests/js/animation-runtime.test.js.
 */

const SELECTOR = '[data-animation]';

/**
 * A control name as its custom property spells it: camelCase to kebab-case. Mirrors
 * AnimationFrameworkModule::cssProperty().
 *
 * @param {string} name
 * @returns {string}
 */
export function kebab(name) {
    return name.replace(/[A-Z]/g, (letter) => `-${letter.toLowerCase()}`);
}

/**
 * @param {Object}   options
 * @param {Object}   options.behaviors        - Animation key => { init(el, ctx) }
 * @param {Function} options.reducedMotion    - () => whether the visitor prefers reduced motion
 * @param {Function} options.getComputedStyle - (el) => CSSStyleDeclaration
 * @param {Object}   [options.console]        - Where a behaviour's error is reported
 * @returns {{ scan: Function, teardown: Function }}
 */
export function createRuntime({ behaviors, reducedMotion, getComputedStyle, console = globalThis.console }) {
    /** Element => its behaviour's cleanup, or null when it returned none. */
    const started = new WeakMap();

    /** The animated elements under root, root itself included. */
    function animatedIn(root) {
        const own = typeof root.matches === 'function' && root.matches(SELECTOR) ? [root] : [];
        return [...own, ...root.querySelectorAll(SELECTOR)];
    }

    function contextFor(el, key) {
        return {
            key,
            reducedMotion: reducedMotion(),
            option: (name) =>
                getComputedStyle(el)
                    .getPropertyValue(`--${key}-animation-${kebab(name)}`)
                    .trim(),
        };
    }

    /**
     * Starts the behaviour of every animated element under root that has one and hasn't started.
     *
     * An element whose key has no behaviour is skipped without a word: that is every CSS-only
     * animation. A behaviour that throws is reported and counted as started, so one broken
     * behaviour neither stops the others nor is retried on every scan.
     *
     * @param {ParentNode} root
     */
    function scan(root) {
        for (const el of animatedIn(root)) {
            if (started.has(el)) {
                continue;
            }

            const key = el.getAttribute('data-animation');
            const behavior = behaviors[key];
            if (typeof behavior?.init !== 'function') {
                continue;
            }
            if (reducedMotion() && el.getAttribute('data-animation-motion') !== 'own') {
                continue;
            }

            let cleanup = null;

            try {
                const returned = behavior.init(el, contextFor(el, key));
                cleanup = typeof returned === 'function' ? returned : null;
            } catch (error) {
                console.error(`[animation] The '${key}' behaviour failed to start.`, error);
            }

            started.set(el, cleanup);
        }
    }

    /**
     * Stops every started behaviour under root, calling its cleanup, so a later scan() can start
     * it again on fresh content.
     *
     * @param {ParentNode} root
     */
    function teardown(root) {
        for (const el of animatedIn(root)) {
            if (!started.has(el)) {
                continue;
            }

            const cleanup = started.get(el);
            started.delete(el);
            cleanup?.();
        }
    }
    return {
        scan,
        teardown,
    };
}
