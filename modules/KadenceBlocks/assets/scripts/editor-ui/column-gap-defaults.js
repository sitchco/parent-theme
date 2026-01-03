import { addFilter } from '@wordpress/hooks';

/**
 * Set default rowGapVariable for Kadence columns in the editor.
 *
 * Uses the kadence.block.column.defaultRowGapVariable filter to provide
 * 'content-flow' as the default when no gap is specified, matching the
 * PHP behavior on the frontend.
 */
addFilter('kadence.block.column.defaultRowGapVariable', 'sitchco/kadence-column-gap-default', () => 'content-flow');
