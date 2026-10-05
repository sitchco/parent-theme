import { describe, expect, it } from 'vitest';
import {
    buildAnimationFields,
    EMPTY_OPTION_LABEL,
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
            attribute: 'letterAnimationColor',
            label: 'Color',
            default: '',
            optionsFilter: 'theme.color-options',
        },
        {
            type: 'select',
            name: 'opacity',
            attribute: 'letterAnimationOpacity',
            label: 'Opacity',
            default: '',
            options: options('', '10', '30', '50'),
        },
    ],
    'fade-up': [
        {
            type: 'number',
            name: 'speed',
            attribute: 'fadeUpAnimationSpeed',
            label: 'Speed',
            default: 50,
            min: 0,
            max: 100,
        },
        {
            type: 'toggle',
            name: 'reverse',
            attribute: 'fadeUpAnimationReverse',
            label: 'Reverse',
            default: false,
            help: 'Play the animation backwards.',
        },
        {
            type: 'text',
            name: 'caption',
            attribute: 'fadeUpAnimationCaption',
            label: 'Caption',
            default: '',
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

        expect(Object.keys(built)).toEqual([
            'letterAnimationColor',
            'letterAnimationOpacity',
            'fadeUpAnimationSpeed',
            'fadeUpAnimationReverse',
            'fadeUpAnimationCaption',
        ]);

        expect(built.fadeUpAnimationSpeed).toMatchObject({
            type: 'number',
            attributeType: 'number',
            label: 'Speed',
            min: 0,
            max: 100,
        });

        expect(built.fadeUpAnimationReverse).toMatchObject({
            type: 'toggle',
            help: 'Play the animation backwards.',
        });

        expect(built.fadeUpAnimationCaption).toMatchObject({
            type: 'text',
            attributeType: 'string',
            label: 'Caption',
        });

        // Settings a control does not have are left off the field, not set to undefined.
        expect(Object.hasOwn(built.fadeUpAnimationCaption, 'help')).toBe(false);
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
        const { fadeUpAnimationSpeed } = build();
        const on = (blockName, animation) => fadeUpAnimationSpeed.condition({ animation }, { blockName });

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
            const { letterAnimationColor } = build();

            expect(
                resolveOptions(letterAnimationColor, {
                    blockName: 'kadence/rowlayout',
                    clientId: 'x',
                }).map((o) => o.value)
            ).toEqual(['', 'purple', 'green']);
        });

        it('offers a static select in full where nothing restricts it', () => {
            const { letterAnimationOpacity } = build();

            expect(
                resolveOptions(letterAnimationOpacity, { blockName: 'kadence/rowlayout' }).map((o) => o.value)
            ).toEqual(CONTROLS.letter[1].options.map((o) => o.value));
        });

        it('names the empty option for what it means, on hook and static selects alike', () => {
            const { letterAnimationColor, letterAnimationOpacity } = build();
            const emptyLabel = (field) =>
                resolveOptions(field, { blockName: 'core/group' }).find((o) => o.value === '').label;

            expect(emptyLabel(letterAnimationColor)).toBe(EMPTY_OPTION_LABEL);
            expect(emptyLabel(letterAnimationOpacity)).toBe(EMPTY_OPTION_LABEL);
            // The shared palette itself is untouched.
            expect(PALETTE[0].label).toBe('Default');
        });

        it('gives non-select controls no options', () => {
            expect(build().fadeUpAnimationSpeed.options).toBeUndefined();
        });
    });

    describe('default', () => {
        it('starts an untouched block on its config default, else the control’s own', () => {
            const { fadeUpAnimationSpeed, letterAnimationOpacity } = build();

            expect(readFieldValue(fadeUpAnimationSpeed, {}, { blockName: 'kadence/rowlayout' })).toBe(25);
            expect(readFieldValue(fadeUpAnimationSpeed, {}, { blockName: 'core/group' })).toBe(50);
            expect(readFieldValue(letterAnimationOpacity, {}, { blockName: 'kadence/rowlayout' })).toBe('30');
        });

        it('honours a config default that is falsy', () => {
            const { fadeUpAnimationReverse } = build(
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

            expect(readFieldValue(fadeUpAnimationReverse, {}, { blockName: 'core/group' })).toBe(false);
        });

        it('copes with the empty defaults PHP serializes as a list', () => {
            const { fadeUpAnimationSpeed } = build({
                'core/group': {
                    'fade-up': entry('fade-up', {
                        defaults: [],
                        allowed: [],
                    }),
                },
            });

            expect(readFieldValue(fadeUpAnimationSpeed, {}, { blockName: 'core/group' })).toBe(50);
        });
    });

    /* S5 writes attributes and emits nothing, so saved markup is unchanged. S6 is meant to break
       this on purpose. */
    it('emits no class and no attribute', () => {
        const built = Object.values(build());
        const attributes = {
            animation: 'fade-up',
            fadeUpAnimationSpeed: 80,
            fadeUpAnimationReverse: true,
        };

        for (const field of built) {
            expect(field.className).toBeUndefined();
            expect(field.attributes).toBeUndefined();
        }

        expect(generateFieldClasses(built, attributes, { blockName: 'core/group' })).toEqual([]);
        expect(generateFieldAttributes(built, attributes, { blockName: 'core/group' })).toEqual({});
    });
});
