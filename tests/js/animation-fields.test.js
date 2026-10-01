import { describe, expect, it } from 'vitest';
import {
    buildAnimationFields,
    restrictOptions,
} from '../../modules/Animation/assets/scripts/editor-ui/animation-fields';
import { resolveOptions } from '../../modules/ExtendBlock/assets/scripts/includes/utils/options';
import { readFieldValue } from '../../modules/ExtendBlock/assets/scripts/includes/utils/field-value';
import { generateFieldClasses } from '../../modules/ExtendBlock/assets/scripts/includes/utils/class-names';
import { generateFieldAttributes } from '../../modules/ExtendBlock/assets/scripts/includes/utils/attributes';

/** Mirrors the real factories: `(config) => ({ type, attributeType, default, render, ...config })`. */
const factory = (type, attributeType, fallback) => (config) => ({
    type,
    attributeType,
    default: fallback,
    ...config,
});
const fields = {
    select: factory('select', 'string', ''),
    toggle: factory('toggle', 'boolean', false),
    number: factory('number', 'number', 0),
    text: factory('text', 'string', ''),
};

const options = (...values) =>
    values.map((value) => ({
        label: value || 'Default',
        value,
    }));

/** The shape AnimationFrameworkModule::getAnimationControls() serializes to. */
const CONTROLS = {
    letter: [
        {
            type: 'select',
            name: 'color',
            attribute: 'letterColor',
            label: 'Color',
            default: '',
            optionsFilter: 'theme.color-options',
        },
        {
            type: 'select',
            name: 'opacity',
            attribute: 'letterOpacity',
            label: 'Opacity',
            default: '',
            options: options('', '10', '30', '50'),
        },
    ],
    'fade-up': [
        {
            type: 'number',
            name: 'speed',
            attribute: 'fadeUpSpeed',
            label: 'Speed',
            default: 50,
            min: 0,
            max: 100,
        },
        {
            type: 'toggle',
            name: 'reverse',
            attribute: 'fadeUpReverse',
            label: 'Reverse',
            default: false,
        },
    ],
};

const entry = (key, overrides = {}) => ({
    key,
    label: key,
    allowed: {},
    defaults: {},
    ...overrides,
});

const BLOCKS = {
    'kadence/rowlayout': {
        letter: entry('letter', {
            allowed: {
                color: ['purple', 'green'],
            },
            defaults: { opacity: '30' },
        }),
        'fade-up': entry('fade-up', { defaults: { speed: 25 } }),
    },
    'core/group': { 'fade-up': entry('fade-up') },
};

const PALETTE = options('', 'purple', 'green', 'red');
const applyFilters = (name, initial) => (name === 'theme.color-options' ? PALETTE : initial);

function build(blocks = BLOCKS, controls = CONTROLS) {
    return Object.fromEntries(
        buildAnimationFields(
            fields,
            {
                blocks,
                controls,
            },
            applyFilters
        ).map((f) => [f.name, f])
    );
}

describe('restrictOptions', () => {
    it('keeps the empty option and the permitted values, in the options’ own order', () => {
        expect(restrictOptions(PALETTE, ['red', 'purple']).map((o) => o.value)).toEqual(['', 'purple', 'red']);
    });

    it('leaves an unrestricted list alone', () => {
        expect(restrictOptions(PALETTE, undefined)).toBe(PALETTE);
    });
});

describe('buildAnimationFields', () => {
    it('builds one field per control, stored under the attribute PHP named', () => {
        const built = build();

        expect(Object.keys(built)).toEqual(['letterColor', 'letterOpacity', 'fadeUpSpeed', 'fadeUpReverse']);

        expect(built.fadeUpSpeed).toMatchObject({
            type: 'number',
            attributeType: 'number',
            label: 'Speed',
            min: 0,
            max: 100,
        });

        expect(built.fadeUpReverse.type).toBe('toggle');
    });

    it('builds nothing when no animation has controls', () => {
        expect(
            buildAnimationFields(
                fields,
                {
                    blocks: BLOCKS,
                    controls: {},
                },
                applyFilters
            )
        ).toEqual([]);

        // PHP serializes an empty map as a JSON list.
        expect(
            buildAnimationFields(
                fields,
                {
                    blocks: BLOCKS,
                    controls: [],
                },
                applyFilters
            )
        ).toEqual([]);
    });

    describe('condition', () => {
        const { fadeUpSpeed } = build();
        const on = (blockName, animation) => fadeUpSpeed.condition({ animation }, { blockName });

        it('shows a control only while its animation is selected', () => {
            expect(on('core/group', 'fade-up')).toBe(true);
            expect(on('core/group', '')).toBe(false);
            expect(on('kadence/rowlayout', 'letter')).toBe(false);
        });

        it('hides a stale animation’s controls on a block that no longer allows it', () => {
            expect(on('core/paragraph', 'fade-up')).toBe(false);
        });
    });

    describe('options', () => {
        it('narrows a hook’s palette to the block’s allowed list, keeping Default', () => {
            const { letterColor } = build();

            expect(
                resolveOptions(letterColor, {
                    blockName: 'kadence/rowlayout',
                    clientId: 'x',
                }).map((o) => o.value)
            ).toEqual(['', 'purple', 'green']);
        });

        it('offers a static select in full where nothing restricts it', () => {
            const { letterOpacity } = build();

            expect(resolveOptions(letterOpacity, { blockName: 'kadence/rowlayout' })).toEqual(
                CONTROLS.letter[1].options
            );
        });

        it('gives non-select controls no options', () => {
            expect(build().fadeUpSpeed.options).toBeUndefined();
        });
    });

    describe('default', () => {
        it('starts an untouched block on its config default, else the control’s own', () => {
            const { fadeUpSpeed, letterOpacity } = build();

            expect(readFieldValue(fadeUpSpeed, {}, { blockName: 'kadence/rowlayout' })).toBe(25);
            expect(readFieldValue(fadeUpSpeed, {}, { blockName: 'core/group' })).toBe(50);
            expect(readFieldValue(letterOpacity, {}, { blockName: 'kadence/rowlayout' })).toBe('30');
        });

        it('honours a config default that is falsy', () => {
            const { fadeUpReverse } = build(
                { 'core/group': { 'fade-up': entry('fade-up', { defaults: { reverse: false } }) } },
                {
                    'fade-up': [
                        {
                            ...CONTROLS['fade-up'][1],
                            default: true,
                        },
                    ],
                }
            );

            expect(readFieldValue(fadeUpReverse, {}, { blockName: 'core/group' })).toBe(false);
        });

        it('copes with the empty defaults PHP serializes as a list', () => {
            const { fadeUpSpeed } = build({
                'core/group': {
                    'fade-up': entry('fade-up', {
                        defaults: [],
                        allowed: [],
                    }),
                },
            });

            expect(readFieldValue(fadeUpSpeed, {}, { blockName: 'core/group' })).toBe(50);
        });
    });

    /* S5 writes attributes and emits nothing, so saved markup is unchanged. S6 is meant to break
       this on purpose. */
    it('emits no class and no attribute', () => {
        const built = Object.values(build());
        const attributes = {
            animation: 'fade-up',
            fadeUpSpeed: 80,
            fadeUpReverse: true,
        };

        for (const field of built) {
            expect(field.className).toBeUndefined();
            expect(field.attributes).toBeUndefined();
        }

        expect(generateFieldClasses(built, attributes, { blockName: 'core/group' })).toEqual([]);
        expect(generateFieldAttributes(built, attributes, { blockName: 'core/group' })).toEqual({});
    });
});
