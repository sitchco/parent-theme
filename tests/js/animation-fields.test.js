import { readFileSync } from 'node:fs';
import { describe, expect, it, vi } from 'vitest';
import {
    buildAnimationFields,
    cssValue,
    EMPTY_OPTION_LABEL,
    normalizeHookOptions,
    restrictOptions,
} from '../../modules/Animation/assets/scripts/editor-ui/animation-fields';
import { resolveOptions, withStaleValue } from '../../modules/ExtendBlock/assets/scripts/includes/utils/options';
import {
    contextualDefaultsToStore,
    readFieldValue,
} from '../../modules/ExtendBlock/assets/scripts/includes/utils/field-value';
import { generateFieldClasses } from '../../modules/ExtendBlock/assets/scripts/includes/utils/class-names';
import { generateFieldAttributes } from '../../modules/ExtendBlock/assets/scripts/includes/utils/attributes';
import { generateFieldStyles } from '../../modules/ExtendBlock/assets/scripts/includes/utils/styles';

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
            step: 5,
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

describe('normalizeHookOptions', () => {
    it('casts every value to a string, the form allowed and stored values compare against', () => {
        const normalized = normalizeHookOptions(
            [
                {
                    label: 'Default',
                    value: '',
                },
                {
                    label: '30%',
                    value: 30,
                    extra: true,
                },
            ],
            'test.cast'
        );

        expect(normalized).toEqual([
            {
                label: 'Default',
                value: '',
            },
            {
                label: '30%',
                value: '30',
                extra: true,
            },
        ]);
    });

    it('adds an empty option first when the hook has none', () => {
        expect(normalizeHookOptions(options('purple'), 'test.add').map((o) => o.value)).toEqual(['', 'purple']);
    });

    it('keeps only the first of several empty options', () => {
        const normalized = normalizeHookOptions(
            [
                {
                    label: 'Default',
                    value: '',
                },
                {
                    label: 'Purple',
                    value: 'purple',
                },
                {
                    label: 'None',
                    value: '',
                },
            ],
            'test.dedupe'
        );

        expect(normalized.map((o) => o.label)).toEqual(['Default', 'Purple']);
    });

    it('leaves the empty option when a hook returns nothing, and warns once per hook', () => {
        const warn = vi.spyOn(globalThis.console, 'warn').mockImplementation(() => {});

        try {
            expect(normalizeHookOptions([], 'test.empty')).toEqual([
                {
                    label: EMPTY_OPTION_LABEL,
                    value: '',
                },
            ]);

            expect(normalizeHookOptions(undefined, 'test.empty')).toEqual([
                {
                    label: EMPTY_OPTION_LABEL,
                    value: '',
                },
            ]);

            expect(warn).toHaveBeenCalledTimes(1);
            expect(warn.mock.calls[0][0]).toContain("'test.empty' hook returned no options");
        } finally {
            warn.mockRestore();
        }
    });

    it('drops entries that are not options', () => {
        expect(normalizeHookOptions([null, 'red', { label: 'X' }, ...options('red')], 'test.junk')).toEqual([
            {
                label: EMPTY_OPTION_LABEL,
                value: '',
            },
            {
                label: 'red',
                value: 'red',
            },
        ]);
    });

    it('holds hook entries to the rules PHP holds a static list to', () => {
        expect(
            normalizeHookOptions(
                [
                    {
                        label: 'A',
                        value: 30,
                    },
                    {
                        label: 'B',
                        value: '30',
                    },
                    { value: 'red' },
                    {
                        label: 'C',
                        value: true,
                    },
                    {
                        label: '',
                        value: 'blank',
                    },
                    {
                        label: 'D',
                        value: NaN,
                    },
                    {
                        label: 'E',
                        value: { hex: '#f00' },
                    },
                ],
                'test.strict'
            )
        ).toEqual([
            {
                label: EMPTY_OPTION_LABEL,
                value: '',
            },
            {
                label: 'A',
                value: '30',
            },
        ]);
    });

    it('keeps the first of two values alike once cast, as a layered hook can produce', () => {
        expect(
            normalizeHookOptions(
                [
                    ...options('', 'purple'),
                    {
                        label: 'Child purple',
                        value: 'purple',
                    },
                ],
                'test.layered'
            )
        ).toEqual(options('', 'purple'));
    });

    it('warns once, and says so, when a hook returns only entries it cannot use', () => {
        const warn = vi.spyOn(globalThis.console, 'warn').mockImplementation(() => {});

        try {
            expect(
                normalizeHookOptions(
                    [
                        { value: 'red' },
                        {
                            label: 'C',
                            value: true,
                        },
                    ],
                    'test.invalid'
                )
            ).toEqual([
                {
                    label: EMPTY_OPTION_LABEL,
                    value: '',
                },
            ]);

            normalizeHookOptions([{ value: 'red' }], 'test.invalid');

            expect(warn).toHaveBeenCalledTimes(1);
            expect(warn.mock.calls[0][0]).toContain("'test.invalid' hook returned 2 options, none with");
        } finally {
            warn.mockRestore();
        }
    });

    it('does not warn while at least one entry is usable', () => {
        const warn = vi.spyOn(globalThis.console, 'warn').mockImplementation(() => {});

        try {
            normalizeHookOptions([{ value: 'red' }, ...options('purple')], 'test.partial');

            expect(warn).not.toHaveBeenCalled();
        } finally {
            warn.mockRestore();
        }
    });

    it('lets a numeric hook value meet a narrowed allowed list', () => {
        const blockName = 'core/group';
        const numericHook = (name, initial) =>
            name === 'theme.color-options'
                ? [
                      {
                          label: '30',
                          value: 30,
                      },
                  ]
                : initial;
        const { letterAnimationColor } = Object.fromEntries(
            buildAnimationFields(
                fields,
                {
                    blocks: { [blockName]: { letter: entry('letter', { allowed: { color: ['30'] } }) } },
                    controls: CONTROLS,
                },
                numericHook
            ).map((f) => [f.name, f])
        );

        expect(resolveOptions(letterAnimationColor, { blockName }).map((o) => o.value)).toEqual(['', '30']);
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
            step: 5,
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

        it('shows a config default its allowed list excludes as unavailable, beside the animation default', () => {
            const blockName = 'core/group';
            const { letterAnimationColor } = build({
                [blockName]: {
                    letter: entry('letter', {
                        allowed: { color: ['green'] },
                        defaults: { color: 'purple' },
                    }),
                },
            });
            const value = readFieldValue(letterAnimationColor, {}, { blockName });

            expect(withStaleValue(resolveOptions(letterAnimationColor, { blockName }), value)).toEqual([
                {
                    label: EMPTY_OPTION_LABEL,
                    value: '',
                },
                {
                    label: 'green',
                    value: 'green',
                },
                {
                    label: 'purple (unavailable)',
                    value: 'purple',
                    disabled: true,
                },
            ]);
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

        it('gives each control type its own block’s default', () => {
            const built = build({
                'core/cover': {
                    'fade-up': entry('fade-up', {
                        defaults: {
                            speed: 0.75,
                            reverse: true,
                            caption: 'Hi',
                        },
                    }),
                },
                'core/group': { 'fade-up': entry('fade-up') },
            });
            const defaultsOn = (blockName) =>
                ['fadeUpAnimationSpeed', 'fadeUpAnimationReverse', 'fadeUpAnimationCaption'].map((name) =>
                    readFieldValue(built[name], {}, { blockName })
                );

            expect(defaultsOn('core/cover')).toEqual([0.75, true, 'Hi']);

            expect(defaultsOn('core/group')).toEqual([50, false, '']);
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

    describe('stored defaults', () => {
        it('stores the selected animation’s defaults on a static block, and nothing for the others', () => {
            const built = Object.values(build());

            expect(
                contextualDefaultsToStore(built, { animation: 'fade-up' }, { blockName: 'kadence/rowlayout' })
            ).toEqual({
                fadeUpAnimationSpeed: 25,
                fadeUpAnimationReverse: false,
                fadeUpAnimationCaption: '',
            });
        });

        it('stores nothing with no animation, or one the block does not allow', () => {
            const built = Object.values(build());

            expect(contextualDefaultsToStore(built, { animation: '' }, { blockName: 'kadence/rowlayout' })).toBeNull();
            expect(contextualDefaultsToStore(built, { animation: 'letter' }, { blockName: 'core/group' })).toBeNull();
        });
    });

    /* Output is the style channel alone: no class, and no attribute from a control. The select's
       data-animation is animation-controls.jsx's. */
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

    describe('style', () => {
        /** CONTROLS with `css` declared and the cssProperty PHP ships beside each. */
        const STYLED = {
            letter: [
                {
                    ...CONTROLS.letter[0],
                    cssProperty: '--letter-animation-color',
                    css: 'var(--wp--preset--color--{value})',
                },
                {
                    ...CONTROLS.letter[1],
                    cssProperty: '--letter-animation-opacity',
                    options: [
                        ...options('', '10'),
                        {
                            label: '30',
                            value: '30',
                            css: '0.3',
                        },
                        {
                            label: '50',
                            value: '50',
                            css: '0.5',
                        },
                    ],
                },
            ],
            'fade-up': [
                {
                    ...CONTROLS['fade-up'][0],
                    cssProperty: '--fade-up-animation-speed',
                    css: '{value}ms',
                },
                {
                    ...CONTROLS['fade-up'][1],
                    cssProperty: '--fade-up-animation-reverse',
                    css: {
                        on: 'reverse',
                        off: 'normal',
                    },
                },
                CONTROLS['fade-up'][2],
            ],
        };
        const styles = (attributes, blockName) =>
            generateFieldStyles(Object.values(build(BLOCKS, STYLED)), attributes, { blockName });

        it('emits each control’s CSS value under its custom property', () => {
            expect(
                styles(
                    {
                        animation: 'letter',
                        letterAnimationColor: 'green',
                        letterAnimationOpacity: '50',
                    },
                    'kadence/rowlayout'
                )
            ).toEqual({
                '--letter-animation-color': 'var(--wp--preset--color--green)',
                '--letter-animation-opacity': '0.5',
            });
        });

        it('emits a block’s config default for an untouched control, and a toggle’s false', () => {
            expect(styles({ animation: 'fade-up' }, 'kadence/rowlayout')).toEqual({
                '--fade-up-animation-speed': '25ms',
                '--fade-up-animation-reverse': 'normal',
            });

            expect(styles({ animation: 'letter' }, 'kadence/rowlayout')).toEqual({
                '--letter-animation-opacity': '0.3',
            });
        });

        it('emits nothing for the empty value, so the stylesheet default applies', () => {
            expect(
                styles(
                    {
                        animation: 'letter',
                        letterAnimationOpacity: '',
                    },
                    'kadence/rowlayout'
                )
            ).toEqual({});
        });

        it('emits nothing for a value the block’s allowed list no longer permits', () => {
            expect(
                styles(
                    {
                        animation: 'letter',
                        letterAnimationColor: 'red',
                    },
                    'kadence/rowlayout'
                )
            ).toEqual({
                '--letter-animation-opacity': '0.3',
            });
        });

        it('leaves nothing behind once the animation is switched or set to None', () => {
            const stale = {
                letterAnimationColor: 'green',
                fadeUpAnimationSpeed: 80,
            };

            expect(
                styles(
                    {
                        ...stale,
                        animation: '',
                    },
                    'kadence/rowlayout'
                )
            ).toEqual({});

            expect(
                styles(
                    {
                        ...stale,
                        animation: 'fade-up',
                    },
                    'core/group'
                )
            ).toEqual({
                '--fade-up-animation-speed': '80ms',
                '--fade-up-animation-reverse': 'normal',
            });
        });

        it('gives a control without css no style callback', () => {
            expect(build(BLOCKS, STYLED).fadeUpAnimationCaption.style).toBeUndefined();
        });
    });
});

/**
 * The cases PHP's AnimationControl::cssValue() is held to as well, so the canvas and the front end
 * cannot drift. Each control is built as getAnimationControls() serializes it: the factory's
 * options, with every static option value a string.
 */
describe('cssValue parity', () => {
    const cases = JSON.parse(
        readFileSync(new globalThis.URL('../fixtures/animation-css-cases.json', import.meta.url), 'utf8')
    );

    it.each(cases)('$case', ({ type, options: declared, value, expected }) => {
        const control = {
            type,
            ...declared,
            ...(declared.options && {
                options: declared.options.map((option) => ({
                    ...option,
                    value: String(option.value),
                })),
            }),
        };

        expect(cssValue(control, value)).toBe(expected);
    });
});
