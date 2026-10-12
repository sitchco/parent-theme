import { afterEach, describe, expect, it, vi } from 'vitest';

/**
 * animation.js, the entry point, wires the runtime to the page. It reads `window.sitchco` when it
 * is imported, so each case stubs the globals first and imports a fresh copy.
 */
async function start(behaviors) {
    let onChange;
    const query = {
        matches: false,
        addEventListener: (type, listener) => {
            if (type === 'change') {
                onChange = listener;
            }
        },
    };
    const el = {
        getAttribute: (name) => (name === 'data-animation' ? 'parallax' : null),
        matches: () => true,
        querySelectorAll: () => [],
    };

    vi.stubGlobal('document', {
        matches: () => false,
        querySelectorAll: () => [el],
    });

    vi.stubGlobal('window', {
        matchMedia: () => query,
        getComputedStyle: () => ({ getPropertyValue: () => '' }),
        sitchco: {
            ready: (callback) => callback(),
            hooks: { applyFilters: (name, value) => (name === 'animation.behaviors' ? behaviors : value) },
        },
    });

    vi.resetModules();
    await import('../../modules/Animation/assets/scripts/animation.js');
    return {
        changePreference(matches) {
            query.matches = matches;
            onChange();
        },
    };
}

describe('animation entry point', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('starts behaviours on the document when ready', async () => {
        const init = vi.fn();
        await start({ parallax: { init } });

        expect(init).toHaveBeenCalledTimes(1);
    });

    it('stops and restarts behaviours when the reduced-motion preference changes', async () => {
        const cleanup = vi.fn();
        const init = vi.fn(() => cleanup);
        const page = await start({ parallax: { init } });

        page.changePreference(true);

        expect(cleanup).toHaveBeenCalledTimes(1);
        expect(init).toHaveBeenCalledTimes(1);

        page.changePreference(false);

        expect(init).toHaveBeenCalledTimes(2);
    });
});
