import {
    SelectControl,
    ToggleControl,
    TextControl,
    __experimentalNumberControl as NumberControl,
} from '@wordpress/components';

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
 */
export const fields = {
    /**
     * Select dropdown field.
     *
     * @param {Object} config
     * @param {string} config.name - Attribute name
     * @param {string} config.label - Control label
     * @param {Array<{label: string, value: string}>} config.options - Dropdown options
     * @param {string} [config.default=''] - Default value
     * @param {Function} [config.className] - Class generator (value) => string|string[]|null
     * @param {string} [config.help] - Help text
     */
    select: createField('select', {
        attributeType: 'string',
        default: '',
        render: ({ field, value, onChange }) => (
            <SelectControl
                label={field.label}
                value={value}
                options={field.options}
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
     * @param {Function} config.render - Render function ({ field, value, onChange }) => JSX
     * @param {Function} [config.className] - Class generator (value) => string|string[]|null
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
        attributes[field.name] = {
            type: field.attributeType,
            default: field.default,
        };
    }
    return attributes;
}
