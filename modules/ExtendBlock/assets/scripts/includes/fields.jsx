import {
    SelectControl,
    ToggleControl,
    TextControl,
    __experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { resolveOptions } from './utils/options';

/**
 * Creates a field definition with the given type and defaults.
 *
 * @param {string} type - Field type identifier
 * @param {Object} defaults - Default configuration for this field type
 * @returns {Function} Field factory function
 */
function createField(type, defaults) {
    return (config) => ({
        type,
        ...defaults,
        ...config,
    });
}

/**
 * Field type definitions.
 *
 * Each field type provides:
 * - attributeType: The Gutenberg attribute type
 * - default: Default value for the attribute
 * - render: React component for the inspector control
 *
 * A field emits through two independent, optional channels, both gated by `condition`:
 * - className:  (value) => string | string[] | null    — merged into the wrapper's class
 * - attributes: (value, context) => Object | null      — merged onto the wrapper as props
 *
 * Use `className` for anything a stylesheet matches and `attributes` for anything JS reads.
 * The attribute channel drops `class`, `className` and `style`; those belong to the other one.
 */
export const fields = {
    /**
     * Select dropdown field.
     *
     * @param {Object} config
     * @param {string} config.name - Attribute name
     * @param {string} config.label - Control label
     * @param {Array<{label: string, value: string}>|Function} config.options - Dropdown options,
     *   or a function of the render context — `({ blockName, clientId, deviceType }) => options` —
     *   when one registration serves blocks or breakpoints whose choices differ
     * @param {string} [config.default=''] - Default value
     * @param {Function} [config.className] - Class generator (value) => string|string[]|null
     * @param {Function} [config.attributes] - Attribute generator (value, context) => Object|null
     * @param {string} [config.help] - Help text
     */
    select: createField('select', {
        attributeType: 'string',
        default: '',
        render: ({ field, value, onChange, context }) => (
            <SelectControl
                label={field.label}
                value={value}
                options={resolveOptions(field, context)}
                onChange={onChange}
                help={field.help}
            />
        ),
    }),

    /**
     * Toggle/switch field.
     *
     * @param {Object} config
     * @param {string} config.name - Attribute name
     * @param {string} config.label - Control label
     * @param {boolean} [config.default=false] - Default value
     * @param {Function} [config.className] - Class generator (value) => string|string[]|null
     * @param {Function} [config.attributes] - Attribute generator (value, context) => Object|null
     * @param {string} [config.help] - Help text
     */
    toggle: createField('toggle', {
        attributeType: 'boolean',
        default: false,
        render: ({ field, value, onChange }) => (
            <ToggleControl label={field.label} checked={value} onChange={onChange} help={field.help} />
        ),
    }),

    /**
     * Text input field.
     *
     * @param {Object} config
     * @param {string} config.name - Attribute name
     * @param {string} config.label - Control label
     * @param {string} [config.default=''] - Default value
     * @param {Function} [config.className] - Class generator (value) => string|string[]|null
     * @param {Function} [config.attributes] - Attribute generator (value, context) => Object|null
     * @param {string} [config.help] - Help text
     */
    text: createField('text', {
        attributeType: 'string',
        default: '',
        render: ({ field, value, onChange }) => (
            <TextControl label={field.label} value={value} onChange={onChange} help={field.help} />
        ),
    }),

    /**
     * Number input field.
     *
     * @param {Object} config
     * @param {string} config.name - Attribute name
     * @param {string} config.label - Control label
     * @param {number} [config.default=0] - Default value
     * @param {number} [config.min] - Minimum value
     * @param {number} [config.max] - Maximum value
     * @param {Function} [config.className] - Class generator (value) => string|string[]|null
     * @param {Function} [config.attributes] - Attribute generator (value, context) => Object|null
     * @param {string} [config.help] - Help text
     */
    number: createField('number', {
        attributeType: 'number',
        default: 0,
        render: ({ field, value, onChange }) => (
            <NumberControl
                label={field.label}
                value={value}
                onChange={(newValue) => onChange(Number(newValue))}
                min={field.min}
                max={field.max}
                help={field.help}
            />
        ),
    }),

    /**
     * Custom field with user-provided render function.
     *
     * @param {Object} config
     * @param {string} config.name - Attribute name
     * @param {string} config.attributeType - Gutenberg attribute type
     * @param {*} config.default - Default value
     * @param {Function} config.render - Render function ({ field, value, onChange, context }) => JSX
     * @param {Function} [config.className] - Class generator (value) => string|string[]|null
     * @param {Function} [config.attributes] - Attribute generator (value, context) => Object|null
     */
    custom: (config) => ({
        type: 'custom',
        ...config,
    }),
};

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

        attributes[field.name] = {
            type: field.attributeType,
            default: field.default,
        };
    }
    return attributes;
}
