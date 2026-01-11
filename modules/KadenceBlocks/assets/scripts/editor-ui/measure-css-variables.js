import { addFilter } from '@wordpress/hooks';

/**
 * Enable CSS variable output for padding in Kadence blocks editor preview.
 *
 * Provides parity with PHP filter `kadence_blocks_measure_output_css_variables`.
 * When enabled, the editor will output intermediate CSS variables that match
 * the frontend output, allowing consistent styling overrides.
 */
addFilter(
    'kadence.blocks.measureOutputCssVariables',
    'sitchco/kadence-measure-css-variables',
    (useVariables, property, blockName, _selector) => {
        if (property === 'padding') {
            if (blockName === 'kadence/column' || blockName === 'kadence/rowlayout') {
                return true;
            }
        }
        return useVariables;
    }
);

/**
 * Provide default padding values for row layout resize handles.
 *
 * When no explicit padding is set, this filter returns the theme's default
 * padding based on whether the row has a background.
 */
addFilter('kadence.rowlayout.defaultPadding', 'sitchco/kadence-row-default-padding', (_defaultVal, { attributes }) => {
    const hasBackground =
        attributes.bgColor ||
        attributes.bgImg ||
        attributes.gradient ||
        attributes.overlay ||
        attributes.overlayGradient ||
        attributes.overlayBgImg;
    if (!hasBackground) {
        return 0;
    }

    const editorDocument = document.querySelector('iframe[name="editor-canvas"]')?.contentWindow?.document || document;
    // Body may be null during device preview transitions (iframe recreation)
    if (!editorDocument.body) {
        return _defaultVal;
    }

    // Resolve fluid/clamp values by measuring an element with the utility class
    const measure = editorDocument.createElement('div');
    measure.className = 'kb-row-default-padding-measure';
    editorDocument.body.appendChild(measure);
    const resolved = measure.offsetHeight;
    measure.remove();
    return resolved;
});
