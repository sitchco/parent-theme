import { describe, expect, it } from 'vitest';
import { createSavePropsFilter } from '../../modules/ExtendBlock/assets/scripts/includes/utils/save-props';

/**
 * The save filter decides block validation for every existing extendBlock() caller: anything it
 * adds is compared against the markup already saved in post content. Core compares the props with
 * isShallowEqual, so a fresh object with equal values is harmless — an added key is what breaks.
 */
const GROUP = { name: 'core/group' };

const animationField = {
    name: 'animation',
    className: (value) => (value ? `has-${value}` : null),
    attributes: (value) => ({ 'data-animation': value || undefined }),
};

describe('createSavePropsFilter', () => {
    it('hands a non-target block back untouched', () => {
        const filter = createSavePropsFilter(['core/group'], [animationField]);
        const props = { className: 'x' };

        expect(filter(props, { name: 'core/paragraph' }, { animation: 'fade-up' })).toBe(props);
    });

    it('hands the same props back when there is nothing to add', () => {
        const filter = createSavePropsFilter(['core/group'], [animationField]);
        const props = { className: 'x' };

        const result = filter(props, GROUP, { animation: '' });

        expect(result).toBe(props);
        expect(Object.keys(result)).toEqual(['className']);
    });

    it('merges classes and attributes onto the wrapper together', () => {
        const filter = createSavePropsFilter(['core/group'], [animationField]);

        expect(filter({ className: 'x' }, GROUP, { animation: 'fade-up' })).toEqual({
            className: 'x has-fade-up',
            'data-animation': 'fade-up',
        });
    });

    it('never lets the attribute channel overwrite className', () => {
        const filter = createSavePropsFilter(['core/group'], [], {
            classGenerator: () => ['generated'],
            attributeGenerator: () => ({
                className: 'clobbered',
                'data-x': '1',
            }),
        });

        expect(filter({ className: 'x' }, GROUP, {})).toEqual({
            className: 'x generated',
            'data-x': '1',
        });
    });

    it('gives the generators only the block name', () => {
        const seen = [];
        const filter = createSavePropsFilter(['core/group'], [], {
            classGenerator: (attributes, context) => {
                seen.push(context);
                return [];
            },
            attributeGenerator: (attributes, context) => {
                seen.push(context);
                return {};
            },
        });

        filter({}, GROUP, {});

        expect(seen).toEqual([{ blockName: 'core/group' }, { blockName: 'core/group' }]);
    });

    /* The no-op above only holds if "nothing" always arrives as an empty list. These are the
       shapes a natural callback returns on an untouched block. */
    it.each([[['']], [[null]], [[false]], [''], [null]])(
        'hands the same props back when a field className returns %j',
        (output) => {
            const filter = createSavePropsFilter(
                ['core/group'],
                [
                    {
                        name: 'animation',
                        className: () => output,
                    },
                ]
            );
            const props = {};

            expect(filter(props, GROUP, { animation: '' })).toBe(props);
        }
    );

    it('hands the same props back when a classGenerator returns undefined', () => {
        const filter = createSavePropsFilter(['core/group'], [], { classGenerator: () => undefined });
        const props = {};

        expect(filter(props, GROUP, {})).toBe(props);
    });

    it('emits nothing for an untouched block whose function default emits nothing', () => {
        const field = {
            name: 'speed',
            default: () => '',
            attributes: (value) => ({ 'data-speed': value || undefined }),
        };
        const filter = createSavePropsFilter(['core/group'], [field]);
        const props = {};

        expect(filter(props, GROUP, {})).toBe(props);
    });

    it('splits a classGenerator string into classes, not characters', () => {
        const filter = createSavePropsFilter(['core/group'], [], { classGenerator: () => 'a b' });

        expect(filter({}, GROUP, {})).toEqual({ className: 'a b' });
    });

    /* The style channel is canvas-only: a style in saved markup would be compared on every load,
       and the front end gets it from the server instead (ExtendBlockModule's wrapper-props). */
    it('never saves a field style', () => {
        const styled = {
            name: 'color',
            style: (value) => ({ '--x-color': value }),
        };
        const props = { className: 'x' };

        expect(createSavePropsFilter(['core/group'], [styled])(props, GROUP, { color: 'red' })).toBe(props);
    });

    /* Each registration adds its own filter; neither may clobber the other's classes, and the
       last one wins a shared attribute, as it does in the canvas. */
    it('stacks two registrations on one block, last registration winning', () => {
        const first = createSavePropsFilter(['core/group'], [], {
            classGenerator: () => ['first'],
            attributeGenerator: () => ({
                'data-x': 'first',
                'data-first': '1',
            }),
        });
        const second = createSavePropsFilter(['core/group'], [], {
            classGenerator: () => ['second'],
            attributeGenerator: () => ({ 'data-x': 'second' }),
        });

        expect(second(first({ className: 'x' }, GROUP, {}), GROUP, {})).toEqual({
            className: 'x first second',
            'data-x': 'second',
            'data-first': '1',
        });
    });

    it('applies a function default to an untouched block, and a stored value over it', () => {
        const field = {
            name: 'speed',
            default: ({ blockName }) => (blockName === 'core/group' ? '25' : '40'),
            attributes: (value) => ({ 'data-speed': value || undefined }),
        };
        const filter = createSavePropsFilter(['core/group'], [field]);

        expect(filter({}, GROUP, {})).toEqual({ 'data-speed': '25' });
        expect(filter({}, GROUP, { speed: '10' })).toEqual({ 'data-speed': '10' });
    });
});
