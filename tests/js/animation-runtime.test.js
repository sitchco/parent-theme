import { describe, expect, it, vi } from 'vitest';
import { createRuntime, kebab } from '../../modules/Animation/assets/scripts/includes/runtime';

/**
 * The runtime touches the DOM only through what it is handed, so elements are plain objects: an
 * animated element is its attributes and its computed custom properties; a root is whatever
 * querySelectorAll returns.
 */
function element(animation, { motion, style = {} } = {}) {
    const attributes = {
        'data-animation': animation,
        'data-animation-motion': motion,
    };
    return {
        getAttribute: (name) => attributes[name] ?? null,
        matches: () => true,
        style,
        querySelectorAll: () => [],
    };
}

function root(...els) {
    return {
        matches: () => false,
        querySelectorAll: () => els,
    };
}

function runtime(behaviors, { reducedMotion = false, console } = {}) {
    return createRuntime({
        behaviors,
        reducedMotion: () => reducedMotion,
        getComputedStyle: (el) => ({ getPropertyValue: (name) => el.style[name] ?? '' }),
        console,
    });
}

describe('animation runtime', () => {
    it('starts each element’s behaviour once, however often it is scanned', () => {
        const init = vi.fn();
        const el = element('parallax');
        const { scan } = runtime({ parallax: { init } });

        scan(root(el));
        scan(root(el));

        expect(init).toHaveBeenCalledTimes(1);
        expect(init).toHaveBeenCalledWith(
            el,
            expect.objectContaining({
                key: 'parallax',
                reducedMotion: false,
            })
        );
    });

    it('skips a key with no behaviour, as every CSS-only animation is', () => {
        const init = vi.fn();

        expect(() => runtime({ parallax: { init } }).scan(root(element('letter'), element('')))).not.toThrow();
        expect(init).not.toHaveBeenCalled();
    });

    it('includes the root itself when it is animated', () => {
        const init = vi.fn();
        const el = element('parallax');

        runtime({ parallax: { init } }).scan(el);

        expect(init).toHaveBeenCalledWith(el, expect.anything());
    });

    it('reads options from the control’s custom property, as the stylesheet does', () => {
        let ctx;
        const el = element('fade-up', { style: { '--fade-up-animation-start-at': ' 20% ' } });

        runtime({
            'fade-up': {
                init: (_, context) => {
                    ctx = context;
                },
            },
        }).scan(root(el));

        expect(ctx.option('startAt')).toBe('20%');
        // A control that emits nothing reads as '', so the behaviour supplies its own default.
        expect(ctx.option('speed')).toBe('');
    });

    describe('reduced motion', () => {
        it('does not start a behaviour the framework stops', () => {
            const init = vi.fn();

            runtime({ parallax: { init } }, { reducedMotion: true }).scan(root(element('parallax')));

            expect(init).not.toHaveBeenCalled();
        });

        it('starts one that handles reduced motion itself, and tells it so', () => {
            const init = vi.fn();
            const el = element('spinner', { motion: 'own' });

            runtime({ spinner: { init } }, { reducedMotion: true }).scan(root(el));

            expect(init).toHaveBeenCalledWith(el, expect.objectContaining({ reducedMotion: true }));
        });
    });

    describe('teardown', () => {
        it('runs each cleanup, and lets a later scan start the element again', () => {
            const cleanup = vi.fn();
            const init = vi.fn(() => cleanup);
            const region = root(element('parallax'), element('parallax'));
            const { scan, teardown } = runtime({ parallax: { init } });

            scan(region);
            teardown(region);
            teardown(region);
            scan(region);

            expect(cleanup).toHaveBeenCalledTimes(2);
            expect(init).toHaveBeenCalledTimes(4);
        });

        it('forgets an element whose behaviour returned no cleanup', () => {
            const init = vi.fn();
            const region = root(element('parallax'));
            const { scan, teardown } = runtime({ parallax: { init } });

            scan(region);
            expect(() => teardown(region)).not.toThrow();
            scan(region);

            expect(init).toHaveBeenCalledTimes(2);
        });

        it('leaves elements outside the root running', () => {
            const cleanup = vi.fn();
            const inside = element('parallax');
            const outside = element('parallax');
            const { scan, teardown } = runtime({ parallax: { init: () => cleanup } });

            scan(root(inside, outside));
            teardown(root(inside));

            expect(cleanup).toHaveBeenCalledTimes(1);
        });
    });

    it('reports a behaviour that throws, starts the rest, and does not retry it', () => {
        const report = { error: vi.fn() };
        const broken = vi.fn(() => {
            throw new Error('boom');
        });
        const working = vi.fn();
        const region = root(element('broken'), element('working'));
        const { scan } = runtime(
            {
                broken: { init: broken },
                working: { init: working },
            },
            { console: report }
        );

        scan(region);
        scan(region);

        expect(working).toHaveBeenCalledTimes(1);
        expect(broken).toHaveBeenCalledTimes(1);
        expect(report.error).toHaveBeenCalledWith(expect.stringContaining("'broken'"), expect.any(Error));
    });
});

describe('kebab', () => {
    it('spells a control name as AnimationFrameworkModule::cssProperty() does', () => {
        expect(kebab('color')).toBe('color');
        expect(kebab('startAt')).toBe('start-at');
        expect(kebab('step2')).toBe('step2');
    });
});
