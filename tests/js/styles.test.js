import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { generateFieldStyles, mergeStyles } from '../../modules/ExtendBlock/assets/scripts/includes/utils/styles';

/**
 * The style channel's rules. ExtendBlockModule::injectWrapperProps() applies the same ones on the
 * server (ExtendBlockModuleTest), so the canvas and the front end drop exactly the same things.
 */
describe('mergeStyles', () => {
    beforeEach(() => {
        vi.spyOn(globalThis.console, 'warn').mockImplementation(() => {});
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('merges left to right, with later sources winning', () => {
        expect(
            mergeStyles(
                {
                    '--a': '1',
                    '--b': '2',
                },
                { '--b': '3' }
            )
        ).toEqual({
            '--a': '1',
            '--b': '3',
        });
    });

    it('drops unset values, so an untouched control adds nothing', () => {
        expect(
            mergeStyles({
                '--a': undefined,
                '--b': null,
                '--c': '',
            })
        ).toEqual({});
    });

    it('renders finite numbers as strings and drops anything else that is not a string', () => {
        expect(
            mergeStyles({
                '--n': 0.5,
                '--inf': Infinity,
                '--nan': NaN,
                '--bool': true,
                '--obj': {},
            })
        ).toEqual({ '--n': '0.5' });
    });

    it('skips a source that is not a plain object', () => {
        expect(mergeStyles(null, 'abc', ['x'], { '--a': '1' })).toEqual({ '--a': '1' });
    });

    it.each(['color', 'Color', '-a', '--', '--a b'])('drops the non-custom property %j', (name) => {
        expect(mergeStyles({ [name]: 'red' })).toEqual({});
        expect(globalThis.console.warn).toHaveBeenCalledWith(expect.stringContaining(`'${name}' style`));
    });

    it.each(['red; background: url(x)', 'a}b', 'a{b', '</style>', '\\66'])(
        'drops the value %j, which could break out of its declaration',
        (value) => {
            expect(
                mergeStyles({
                    '--unsafe': value,
                    '--safe': 'var(--wp--preset--color--purple)',
                })
            ).toEqual({
                '--safe': 'var(--wp--preset--color--purple)',
            });
        }
    );

    it('warns once per name, not once per block', () => {
        mergeStyles({ 'once-only': 'x' });
        mergeStyles({ 'once-only': 'x' });

        const calls = globalThis.console.warn.mock.calls.filter(([message]) => message.includes("'once-only'"));
        expect(calls).toHaveLength(1);
    });
});

describe('generateFieldStyles', () => {
    const colorField = {
        name: 'color',
        style: (value) => ({ '--x-color': value ? `var(--wp--preset--color--${value})` : undefined }),
    };

    it('emits each field’s properties from its value', () => {
        expect(generateFieldStyles([colorField], { color: 'purple' })).toEqual({
            '--x-color': 'var(--wp--preset--color--purple)',
        });
    });

    it('lets a false condition gate the output, as it gates classes and attributes', () => {
        const gated = {
            ...colorField,
            condition: (attributes) => attributes.animation === 'x',
        };

        expect(
            generateFieldStyles([gated], {
                animation: 'y',
                color: 'purple',
            })
        ).toEqual({});

        expect(
            generateFieldStyles([gated], {
                animation: 'x',
                color: 'purple',
            })
        ).toEqual({
            '--x-color': 'var(--wp--preset--color--purple)',
        });
    });

    it('resolves a function default for an untouched field', () => {
        const withDefault = {
            ...colorField,
            default: ({ blockName }) => (blockName === 'core/group' ? 'green' : ''),
        };

        expect(generateFieldStyles([withDefault], {}, { blockName: 'core/group' })).toEqual({
            '--x-color': 'var(--wp--preset--color--green)',
        });

        expect(generateFieldStyles([withDefault], {}, { blockName: 'core/cover' })).toEqual({});
    });

    it('gives the callback the output context', () => {
        const style = vi.fn(() => ({}));

        generateFieldStyles(
            [
                {
                    name: 'a',
                    style,
                },
            ],
            { a: 'v' },
            { blockName: 'core/group' }
        );

        expect(style).toHaveBeenCalledWith('v', { blockName: 'core/group' });
    });
});
