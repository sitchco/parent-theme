/**
 * The Animation select, on every block the `animations` config section names.
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
 * Shape of blockAnimations, keyed by block name then animation key:
 *
 *     {
 *         'core/group': {
 *             parallax: { key: 'parallax', label: 'Parallax', allowed: {}, defaults: {} },
 *         },
 *     }
 *
 * `allowed` and `defaults` are per-animation option overrides. Nothing reads them yet — they
 * belong to the per-animation controls, and arrive with them.
 *
 * @param {Object} api             - window.sitchco.extendBlock
 * @param {Object} blockAnimations - Resolved block => animations map from PHP
 */
export default function ({ extendBlock, fields }, blockAnimations) {
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
        panel: {
            title: 'Animation',
            /* 'settings', not 'styles'. Kadence's Style tab renders into the default inspector
               group; filling WP's own styles slot would make core push a second native
               "Settings | Styles" tab bar above Kadence's. Ignored on non-Kadence blocks, which
               get an ordinary Settings panel. */
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
        ],
    });
}
