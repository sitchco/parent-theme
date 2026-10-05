/**
 * Field definitions to Gutenberg attribute definitions.
 *
 * Plain JS with no JSX and no @wordpress imports, so it is unit testable on its own (fields.jsx,
 * which re-exports it, imports @wordpress/components). See tests/js/fields-to-attributes.test.js.
 */

import { hasContextualDefault } from './field-value';

/**
 * Converts field definitions to Gutenberg attribute definitions.
 *
 * @param {Array} fields - Array of field definitions
 * @returns {Object} Gutenberg attributes object
 */
export function fieldsToAttributes(fields) {
    const attributes = {};

    for (const field of fields) {
        if (!field.name) {
            throw new Error('Field is missing required "name" property');
        }

        // A function default is supplied at read time, so the attribute is registered without one
        // and stays undefined until an author picks something. See utils/field-value.js.
        attributes[field.name] = hasContextualDefault(field)
            ? { type: field.attributeType }
            : {
                  type: field.attributeType,
                  default: field.default,
              };
    }
    return attributes;
}
