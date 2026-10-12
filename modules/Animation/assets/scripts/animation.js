import { createRuntime } from './includes/runtime.js';

/**
 * Starts animation behaviours on the front end. See includes/runtime.js for how an animation
 * registers one.
 *
 * The registry is read once, in `ready`: themes and animations add their behaviours during `init`,
 * and components may still filter during `register`. `sitchco.animations` exists from then on,
 * for regions that re-render to scan and tear down their own content.
 */
const { ready, hooks } = window.sitchco;

ready(() => {
    const query = window.matchMedia('(prefers-reduced-motion: reduce)');
    const runtime = createRuntime({
        behaviors: hooks.applyFilters('animation.behaviors', {}),
        reducedMotion: () => query.matches,
        getComputedStyle: (el) => window.getComputedStyle(el),
    });

    window.sitchco.animations = {
        scan: (root = document) => runtime.scan(root),
        teardown: (root = document) => runtime.teardown(root),
    };

    runtime.scan(document);

    // The stylesheet follows a change of preference by itself; the behaviours need restarting.
    query.addEventListener('change', () => {
        runtime.teardown(document);
        runtime.scan(document);
    });
});
