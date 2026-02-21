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
 * Check if row layout attributes indicate a background is set.
 * Matches Kadence's own kt-row-has-bg logic (edit.js:369).
 *
 * @param {Object} attributes - Row layout block attributes
 * @returns {boolean}
 */
function hasRowBackground(attributes) {
    const { bgColor, bgImg, gradient, overlay, overlayGradient, overlayBgImg } = attributes;
    return !!(bgColor || bgImg || gradient || overlay || overlayGradient || overlayBgImg);
}

/**
 * Resolve the editor document and viewport width.
 *
 * On desktop the block editor renders inline (no iframe), so the editor
 * document is just `document`. On tablet/mobile preview the editor uses
 * an iframe named "editor-canvas".
 *
 * @returns {{ editorDocument: Document, viewportWidth: number } | null}
 */
function getEditorContext() {
    const iframe = document.querySelector('iframe[name="editor-canvas"]');
    if (iframe?.contentWindow?.document?.body) {
        return {
            editorDocument: iframe.contentWindow.document,
            viewportWidth: iframe.contentWindow.innerWidth,
        };
    }
    if (document.body) {
        return {
            editorDocument: document,
            viewportWidth: window.innerWidth,
        };
    }
    return null;
}

/**
 * Measure --kb-row-default-padding by creating a standalone measurement context.
 *
 * Creates a temporary element with the correct classes to resolve the CSS
 * variable, rather than searching for an existing .kt-row-has-bg element.
 * This avoids a timing issue where the filter runs during React render
 * before the DOM has been committed with the .kt-row-has-bg class.
 *
 * @param {Document} editorDocument
 * @returns {number | null} Resolved pixel value, or null if CSS not ready
 */
function measureDefaultPadding(editorDocument) {
    const container = editorDocument.createElement('div');
    container.className = 'wp-block-kadence-rowlayout kt-row-has-bg';
    container.style.position = 'absolute';
    container.style.visibility = 'hidden';

    const measure = editorDocument.createElement('div');
    measure.className = 'kb-row-default-padding-measure';
    container.appendChild(measure);
    editorDocument.body.appendChild(container);

    const resolved = measure.offsetHeight;
    container.remove();
    return resolved || null;
}

/**
 * Provide default padding values for row layout resize handles.
 *
 * Returns 0 for rows without backgrounds. For background rows, measures
 * --kb-row-default-padding once per viewport width and caches the result.
 * The CSS variable resolves identically for all background rows at a given
 * viewport width, so one measurement serves all rows.
 */
const paddingCache = new Map();
let lastMeasured = null;

addFilter('kadence.rowlayout.defaultPadding', 'sitchco/kadence-row-default-padding', (_defaultVal, { attributes }) => {
    if (!hasRowBackground(attributes)) {
        return 0;
    }

    const ctx = getEditorContext();
    if (!ctx) {
        return lastMeasured ?? 0;
    }

    const { editorDocument, viewportWidth } = ctx;
    if (paddingCache.has(viewportWidth)) {
        return paddingCache.get(viewportWidth);
    }

    const measured = measureDefaultPadding(editorDocument);
    if (measured == null) {
        return lastMeasured ?? 0;
    }

    lastMeasured = measured;
    paddingCache.set(viewportWidth, measured);
    return measured;
});
