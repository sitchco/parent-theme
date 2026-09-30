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

/** The shape AnimationFrameworkModule::getBlockAnimations() serializes to. */
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
        extendAnimation(sitchco, {});

        expect(sitchco.calls).toEqual([]);
    });

    /* One registration, not one per group of blocks sharing an animation set: each extendBlock()
       call adds an editor.BlockEdit HOC that wraps every block in the editor, and the namespace
       becomes a key persisted into dynamic-block content later, so it has to stay stable. */
    it('registers exactly once, over every configured block, under a fixed namespace', () => {
        extendAnimation(sitchco, MAP);

        expect(sitchco.calls).toHaveLength(1);
        expect(sitchco.calls[0].blocks).toEqual(['core/paragraph', 'core/heading', 'kadence/accordion']);

        expect(sitchco.calls[0].namespace).toBe('sitchco/animation');
    });

    it('puts the panel on Kadence’s Style tab without claiming the native Styles slot', () => {
        extendAnimation(sitchco, MAP);

        // group: 'styles' would fill WP's own slot and push a second native tab bar above Kadence's.
        expect(sitchco.calls[0].panel).toMatchObject({
            group: 'settings',
            kadenceTab: 'style',
        });
    });

    it('declares a single select writing the animation attribute', () => {
        extendAnimation(sitchco, MAP);
        const [field] = sitchco.calls[0].fields;

        expect(sitchco.calls[0].fields).toHaveLength(1);
        expect(field).toMatchObject({
            name: 'animation',
            attributeType: 'string',
            default: '',
        });

        /* The select writes the attribute and emits nothing, which is the whole of this PR's "no
           block-validation risk" claim — toMatchObject alone would ignore an output channel added
           later. S6 is meant to break this on purpose. */
        expect(field.className).toBeUndefined();
        expect(field.attributes).toBeUndefined();
    });

    /* The heart of the design: one registration still offers a different list per block, because
       options resolve from the render context rather than once at registration time. */
    it('offers each block only the animations its own config entry allows', () => {
        extendAnimation(sitchco, MAP);
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
        extendAnimation(sitchco, MAP);
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
            'core/group': {
                parallax: entry('parallax', 'Parallax'),
                'fade-up': entry('fade-up', 'Fade Up'),
            },
        });

        const [field] = sitchco.calls[0].fields;

        expect(resolveOptions(field, { blockName: 'core/group' }).map((o) => o.value)).toEqual([
            '',
            'parallax',
            'fade-up',
        ]);
    });
});
