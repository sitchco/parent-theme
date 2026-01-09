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
        if (property === 'padding' && blockName === 'kadence/column') {
            return true;
        }
        return useVariables;
    }
);
