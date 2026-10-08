import { buildAnimationFields } from './animation-fields';

/**
 * The Animation select, on every block the `animations` config section names, followed by the
 * selected animation's own controls.
 *
 * One extendBlock() registration covers every configured block rather than one per group of
 * blocks sharing an animation set. Two reasons: each registration adds an editor.BlockEdit
 * higher-order component that wraps *every* block in the editor, not only its targets; and the
 * namespace becomes a key persisted into dynamic-block content later, so it has to be stable
 * against config edits. `sitchco/animation` is that stable key.
 *
 * What differs per block is the option list, which `options` resolves from the render context
 * on each render. Animations themselves stay block-agnostic — an animation declares its key and
 * label and never learns which blocks offer it. The narrowing is entirely the config's doing,
 * resolved in AnimationFrameworkModule and handed over as the map below.
 *
 * Shape of the blob, as AnimationFrameworkModule serializes it:
 *
 *     {
 *         blocks: {
 *             'core/group': {
 *                 parallax: { key: 'parallax', label: 'Parallax', allowed: [], defaults: { speed: 25 } },
 *                 letter: { key: 'letter', label: 'Letter', allowed: { opacity: ['30', '50'] }, defaults: { opacity: '30' } },
 *             },
 *         },
 *         controls: {
 *             parallax: [{
 *                 type: 'number', name: 'speed', attribute: 'parallaxAnimationSpeed',
 *                 cssProperty: '--parallax-animation-speed', label: 'Speed', default: 50, css: '{value}%',
 *             }],
 *             letter: [{
 *                 type: 'select', name: 'opacity', attribute: 'letterAnimationOpacity',
 *                 cssProperty: '--letter-animation-opacity', label: 'Opacity', default: '',
 *                 options: [{ label: 'Default', value: '' }, { label: '30%', value: '30', css: '0.3' }, { label: '50%', value: '50', css: '0.5' }],
 *             }],
 *         },
 *         ownMotion: ['letter'],
 *     }
 *
 * `blocks` decides which animations each block offers, and narrows their controls per block
 * through `allowed` and `defaults`. `controls` holds each animation's own control definitions,
 * which buildAnimationFields() turns into the fields below the select. Each carries the block
 * `attribute` its value is stored under and the `cssProperty` its `css` value is emitted to.
 * `ownMotion` lists the animations that handle reduced motion themselves
 * (AnimationModule::MOTION_OWN), which the select marks with `data-animation-motion`.
 *
 * What PHP's encoding means for the reader:
 * - An empty `allowed` or `defaults` arrives as a list, `[]`, not `{}`. asMap() reads it as an
 *   empty map.
 * - `allowed[option]` is always a list of strings, and a select's option values and defaults are
 *   strings too, even when declared as numbers. That is the form the editor compares with `===`.
 * - Unset settings (`help`, `min`, `max`, …) are left out rather than sent as null.
 *
 * @param {Object}   api                 - window.sitchco.extendBlock
 * @param {Object}   blob                - The resolved map from PHP
 * @param {Object}   blob.blocks         - Block name => animation key => entry
 * @param {Object}   [blob.controls]     - Animation key => serialized controls
 * @param {string[]} [blob.ownMotion]    - Keys of the animations that handle reduced motion themselves
 * @param {Function} [applyFilters]      - sitchco.hooks.applyFilters, for options from a JS hook
 */
export default function (
    { extendBlock, fields },
    { blocks: blockAnimations = {}, controls = {}, ownMotion = [] },
    applyFilters
) {
    const blocks = Object.keys(blockAnimations);
    /* PHP already skips the enqueue when the map is empty, so this only catches a blob that
       failed to land. Registering over an empty block list would add filters that can never
       match, and a select with nothing but None in it. */
    if (!blocks.length) {
        return;
    }

    extendBlock({
        blocks,
        namespace: 'sitchco/animation',
        /* The front end is rendered server-side for every block, static or dynamic, by
           AnimationFrameworkModule::wrapperProps(). What this registration emits reaches the
           canvas only, so saved markup — and block validation — never depend on an animation. */
        saveOutput: false,
        panel: {
            title: 'Animation',
            /* 'settings', not 'styles'. Kadence's Style tab renders into the default inspector
               group; filling WP's own styles slot would make core push a second native
               "Settings | Styles" tab bar above Kadence's.

               Core blocks get the same group: an Animation panel of its own on their Settings tab,
               rather than one folded into a core panel. One placement for every block keeps the
               control where an editor learns to look for it, and `kadenceTab` below is ignored
               off Kadence blocks. */
            group: 'settings',
            kadenceTab: 'style',
            initialOpen: false,
        },
        fields: [
            fields.select({
                name: 'animation',
                label: 'Animation',
                /* Resolved per render rather than once at registration, which is what lets one
                   registration offer a different list on each block. A block missing from the
                   map cannot reach here — `blocks` is built from its keys — but the fallback
                   keeps a stale registration from throwing rather than degrading. */
                /* `data-animation` only for a key the block may use now. A stale value stays visible
                   in the select as "(unavailable)", and emits nothing, as on the front end. */
                attributes: (value, { blockName } = {}) => {
                    if (!value || !blockAnimations[blockName]?.[value]) {
                        return undefined;
                    }
                    return {
                        'data-animation': value,
                        // Exempts the block from the framework's reduced-motion rule, as on the front end.
                        'data-animation-motion': ownMotion.includes(value) ? 'own' : undefined,
                    };
                },
                options: ({ blockName }) => [
                    {
                        label: 'None',
                        value: '',
                    },
                    ...Object.values(blockAnimations[blockName] ?? {}).map(({ key, label }) => ({
                        label,
                        value: key,
                    })),
                ],
            }),
            ...buildAnimationFields(
                fields,
                {
                    blocks: blockAnimations,
                    controls,
                },
                applyFilters
            ),
        ],
    });
}
