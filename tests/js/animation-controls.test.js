import { beforeEach, describe, expect, it } from 'vitest';
import extendAnimation from '../../modules/Animation/assets/scripts/editor-ui/animation-controls.jsx';
import { resolveOptions } from '../../modules/ExtendBlock/assets/scripts/includes/utils/options';

/**
 * Stands in for window.sitchco.extendBlock, recording what the control registers rather than
 * touching Gutenberg. The `fields` stub mirrors the real factory closely enough for this:
 * fields.select() is `(config) => ({ type, attributeType, default, render, ...config })`.
 */
function api() {
    const calls = [];
    return {
        calls,
        extendBlock: (config) => calls.push(config),
        fields: {
            select: (config) => ({
                type: 'select',
                attributeType: 'string',
                default: '',
                ...config,
            }),
            toggle: (config) => ({
                type: 'toggle',
                attributeType: 'boolean',
                default: false,
                ...config,
            }),
        },
    };
}

function entry(key, label) {
    return {
        key,
        label,
        allowed: {},
        defaults: {},
    };
}

/** The `blocks` half of the blob: what AnimationFrameworkModule::getBlockAnimations() serializes to. */
const MAP = {
    'core/paragraph': { 'fade-up': entry('fade-up', 'Fade Up') },
    'core/heading': {
        'fade-up': entry('fade-up', 'Fade Up'),
        parallax: entry('parallax', 'Parallax'),
    },
    'kadence/accordion': { parallax: entry('parallax', 'Parallax') },
};

describe('animation controls', () => {
    let sitchco;

    beforeEach(() => {
        sitchco = api();
    });

    it('registers nothing when no block is configured', () => {
        extendAnimation(sitchco, { blocks: {} });

        expect(sitchco.calls).toEqual([]);
    });

    /* One registration, not one per group of blocks sharing an animation set: each extendBlock()
       call adds an editor.BlockEdit HOC that wraps every block in the editor, and the namespace
       becomes a key persisted into dynamic-block content later, so it has to stay stable. */
    it('registers exactly once, over every configured block, under a fixed namespace', () => {
        extendAnimation(sitchco, { blocks: MAP });

        expect(sitchco.calls).toHaveLength(1);
        expect(sitchco.calls[0].blocks).toEqual(['core/paragraph', 'core/heading', 'kadence/accordion']);

        expect(sitchco.calls[0].namespace).toBe('sitchco/animation');
    });

    it('puts the panel on Kadence’s Style tab without claiming the native Styles slot', () => {
        extendAnimation(sitchco, { blocks: MAP });

        // group: 'styles' would fill WP's own slot and push a second native tab bar above Kadence's.
        expect(sitchco.calls[0].panel).toMatchObject({
            group: 'settings',
            kadenceTab: 'style',
        });
    });

    it('declares a single select writing the animation attribute', () => {
        extendAnimation(sitchco, { blocks: MAP });
        const [field] = sitchco.calls[0].fields;

        expect(sitchco.calls[0].fields).toHaveLength(1);
        expect(field).toMatchObject({
            name: 'animation',
            attributeType: 'string',
            default: '',
        });

        // No class: the select emits data-animation alone.
        expect(field.className).toBeUndefined();
        expect(sitchco.calls[0].classGenerator).toBeUndefined();
        expect(sitchco.calls[0].attributeGenerator).toBeUndefined();
    });

    /* The "no block-validation risk" claim: nothing this registration emits is saved. The front end
       is rendered by AnimationFrameworkModule::wrapperProps(). */
    it('emits to the canvas only, never into saved markup', () => {
        extendAnimation(sitchco, { blocks: MAP });

        expect(sitchco.calls[0].saveOutput).toBe(false);
    });

    it('emits data-animation only for an animation the block may use now', () => {
        extendAnimation(sitchco, { blocks: MAP });
        const [field] = sitchco.calls[0].fields;
        const emitted = (value, blockName) => field.attributes(value, { blockName })?.['data-animation'];

        expect(emitted('parallax', 'core/heading')).toBe('parallax');
        // Stored, but not offered on this block: shown as "(unavailable)", and emits nothing.
        expect(emitted('parallax', 'core/paragraph')).toBeUndefined();
        expect(emitted('', 'core/heading')).toBeUndefined();
        expect(emitted(undefined, 'core/heading')).toBeUndefined();
    });

    /* The heart of the design: one registration still offers a different list per block, because
       options resolve from the render context rather than once at registration time. */
    it('marks an animation that handles reduced motion itself, and only that one', () => {
        extendAnimation(sitchco, {
            blocks: MAP,
            ownMotion: ['parallax'],
        });

        const [field] = sitchco.calls[0].fields;

        expect(field.attributes('parallax', { blockName: 'core/heading' })).toEqual({
            'data-animation': 'parallax',
            'data-animation-motion': 'own',
        });

        expect(field.attributes('fade-up', { blockName: 'core/heading' })).toEqual({
            'data-animation': 'fade-up',
            'data-animation-motion': undefined,
        });
    });

    it('offers each block only the animations its own config entry allows', () => {
        extendAnimation(sitchco, { blocks: MAP });
        const [field] = sitchco.calls[0].fields;
        const optionsFor = (blockName) => resolveOptions(field, { blockName });

        expect(optionsFor('core/paragraph')).toEqual([
            {
                label: 'None',
                value: '',
            },
            {
                label: 'Fade Up',
                value: 'fade-up',
            },
        ]);

        expect(optionsFor('core/heading')).toEqual([
            {
                label: 'None',
                value: '',
            },
            {
                label: 'Fade Up',
                value: 'fade-up',
            },
            {
                label: 'Parallax',
                value: 'parallax',
            },
        ]);

        expect(optionsFor('kadence/accordion')).toEqual([
            {
                label: 'None',
                value: '',
            },
            {
                label: 'Parallax',
                value: 'parallax',
            },
        ]);
    });

    it('offers None alone for a block the map does not describe', () => {
        extendAnimation(sitchco, { blocks: MAP });
        const [field] = sitchco.calls[0].fields;

        expect(resolveOptions(field, { blockName: 'core/list' })).toEqual([
            {
                label: 'None',
                value: '',
            },
        ]);
    });

    it('keeps the config order of a block’s animations', () => {
        extendAnimation(sitchco, {
            blocks: {
                'core/group': {
                    parallax: entry('parallax', 'Parallax'),
                    'fade-up': entry('fade-up', 'Fade Up'),
                },
            },
        });

        const [field] = sitchco.calls[0].fields;

        expect(resolveOptions(field, { blockName: 'core/group' }).map((o) => o.value)).toEqual([
            '',
            'parallax',
            'fade-up',
        ]);
    });

    it('follows the select with each animation’s own controls, in the same panel', () => {
        extendAnimation(sitchco, {
            blocks: MAP,
            controls: {
                parallax: [
                    {
                        type: 'toggle',
                        name: 'reverse',
                        attribute: 'parallaxAnimationReverse',
                        label: 'Reverse',
                        default: false,
                    },
                ],
            },
        });

        expect(sitchco.calls).toHaveLength(1);
        expect(sitchco.calls[0].fields.map((f) => f.name)).toEqual(['animation', 'parallaxAnimationReverse']);
    });
});
