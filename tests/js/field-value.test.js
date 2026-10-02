import { describe, expect, it } from 'vitest';
import {
    contextualDefaultsToStore,
    hasContextualDefault,
    readFieldValue,
} from '../../modules/ExtendBlock/assets/scripts/includes/utils/field-value';

const speedFor = ({ blockName }) => (blockName === 'core/group' ? 25 : 40);

describe('hasContextualDefault', () => {
    it('is true only for a function default', () => {
        expect(hasContextualDefault({ default: speedFor })).toBe(true);
        expect(hasContextualDefault({ default: '' })).toBe(false);
        expect(hasContextualDefault({})).toBe(false);
    });
});

describe('readFieldValue', () => {
    const field = {
        name: 'speed',
        default: speedFor,
    };

    it('resolves a function default from the block name while the attribute is absent', () => {
        expect(readFieldValue(field, {}, { blockName: 'core/group' })).toBe(25);
        expect(readFieldValue(field, {}, { blockName: 'kadence/rowlayout' })).toBe(40);
    });

    it('prefers a stored value, even one equal to another block’s default', () => {
        expect(readFieldValue(field, { speed: 40 }, { blockName: 'core/group' })).toBe(40);
    });

    it('treats falsy stored values as choices, not as absence', () => {
        const toggle = {
            name: 'reverse',
            default: () => true,
        };

        expect(readFieldValue(toggle, { reverse: false }, { blockName: 'core/group' })).toBe(false);
        expect(
            readFieldValue(
                {
                    name: 'color',
                    default: () => 'purple',
                },
                { color: '' },
                {}
            )
        ).toBe('');

        expect(
            readFieldValue(
                {
                    name: 'n',
                    default: () => 5,
                },
                { n: 0 },
                {}
            )
        ).toBe(0);
    });

    it('returns the stored value for a literal default, absent or not', () => {
        // Gutenberg fills a literal default in itself, so there is nothing to supply here.
        const literal = {
            name: 'color',
            default: '',
        };

        expect(readFieldValue(literal, { color: 'green' })).toBe('green');
        expect(readFieldValue(literal, {})).toBeUndefined();
    });

    it('gives the default only the context it is handed', () => {
        const seen = [];
        readFieldValue(
            {
                name: 'x',
                default: (context) => seen.push(context),
            },
            {},
            { blockName: 'core/group' }
        );

        expect(seen).toEqual([{ blockName: 'core/group' }]);
    });
});

describe('contextualDefaultsToStore', () => {
    const context = { blockName: 'core/group' };
    const animation = {
        name: 'animation',
        default: '',
    };
    const speed = {
        name: 'speed',
        default: speedFor,
        condition: (attributes) => attributes.animation === 'fade-up',
    };

    it('resolves each applicable, unstored function default for the block', () => {
        expect(contextualDefaultsToStore([animation, speed], { animation: 'fade-up' }, context)).toEqual({
            speed: 25,
        });
    });

    it('leaves out a field whose condition fails, so nothing is stored for fields that emit nothing', () => {
        expect(contextualDefaultsToStore([animation, speed], { animation: '' }, context)).toBeNull();
    });

    it('leaves out a stored value, falsy ones included', () => {
        const reverse = {
            name: 'reverse',
            default: () => true,
        };

        expect(
            contextualDefaultsToStore(
                [speed, reverse],
                {
                    animation: 'fade-up',
                    speed: 40,
                    reverse: false,
                },
                context
            )
        ).toBeNull();
    });

    it('stores a field with no condition', () => {
        expect(
            contextualDefaultsToStore(
                [
                    {
                        name: 'speed',
                        default: speedFor,
                    },
                ],
                {},
                context
            )
        ).toEqual({ speed: 25 });
    });

    it('checks conditions against the defaults stored alongside them', () => {
        const mode = {
            name: 'mode',
            default: () => 'fade-up',
        };
        const gated = {
            ...speed,
            condition: (attributes) => attributes.mode === 'fade-up',
        };

        expect(contextualDefaultsToStore([mode, gated], {}, context)).toEqual({
            mode: 'fade-up',
            speed: 25,
        });
    });
});
