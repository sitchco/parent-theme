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
 * Reads --kb-row-default-padding from the row element, which varies by context
 * (e.g., background rows get generous padding, others get minimal).
 * Caches values to handle device preview transitions gracefully.
 */
const rowPaddingCache = new Map();

addFilter('kadence.rowlayout.defaultPadding', 'sitchco/kadence-row-default-padding', (_defaultVal, { attributes }) => {
    const { uniqueID } = attributes;
    const editorDocument = document.querySelector('iframe[name="editor-canvas"]')?.contentWindow?.document || document;
    // During transitions (no body or no row element), return cached or default
    if (!editorDocument.body) {
        return rowPaddingCache.get(uniqueID) ?? _defaultVal;
    }

    // Find the row element by its unique ID class
    const rowElement = uniqueID ? editorDocument.querySelector(`.kb-row-id-${uniqueID}`) : null;
    if (rowElement) {
        // Measure by creating a temporary element inside the row
        // This resolves fluid/clamp values and inherits the row's context
        const measure = editorDocument.createElement('div');
        measure.style.cssText = 'position:absolute;visibility:hidden;height:var(--kb-row-default-padding)';
        rowElement.appendChild(measure);
        const resolved = measure.offsetHeight;
        measure.remove();

        // Cache for use during transitions
        rowPaddingCache.set(uniqueID, resolved);
        return resolved;
    }
    // Row not found (likely mid-transition), use cached value or default
    return rowPaddingCache.get(uniqueID) ?? _defaultVal;
});
