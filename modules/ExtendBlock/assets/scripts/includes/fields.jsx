import {
    SelectControl,
    ToggleControl,
    TextControl,
    __experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { resolveOptions, withStaleValue } from './utils/options';
import { toNumberValue } from './utils/number-value';
import { hasContextualDefault } from './utils/field-value';

// Kept importable from here, where it always lived; it moved out so it can be unit tested.
export { fieldsToAttributes } from './utils/fields-to-attributes';

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
 * - className:  (value, context) => string | string[] | null — merged into the wrapper's class
 * - attributes: (value, context) => Object | null          — merged onto the wrapper as props: the
 *   saved markup of a static block, and the editor canvas of any block. A dynamic block's
 *   front end does not get them until S7; only classes are synced there.
 *
 * Use `className` for anything a stylesheet matches and `attributes` for anything JS reads.
 *
 * Rules for the attribute channel (the full list is at the top of utils/attributes.js):
 * - Only `data-*` and `aria-*` names are emitted; anything else is dropped with a warning.
 * - Return `undefined` for unset. `''`, `0` and `false` are kept and serialize on a `data-*` or
 *   `aria-*` attribute, and `''` and `false` are exactly the select, text and toggle defaults, so
 *   a generator that passes the value straight through adds markup to every untouched block.
 * - Don't emit a key the target block or core sets itself: the extension wins it on save and
 *   the block wins it in the canvas. See the precedence note in utils/editor-props.js.
 *
 * `condition`, `className` and `attributes` receive the output context, `{ blockName }`, in
 * every phase. `render` and `options` receive the richer render context.
 *
 * `default` may be a function of the output context, `({ blockName }) => value`, when one
 * registration serves blocks whose defaults differ. The attribute is then registered without a
 * default and the value is resolved on every read. A dynamic block follows the default until an
 * author picks a value; a static block stores the default once the field applies, so its saved
 * markup cannot go stale. Not supported inside responsive(). See utils/field-value.js.
 */
export const fields = {
    /**
     * Select dropdown field.
     *
     * @param {Object} config
     * @param {string} config.name - Attribute name
     * @param {string} config.label - Control label
     * @param {Array<{label: string, value: string}>|Function} config.options - Dropdown options,
     *   or a function of the render context — `({ blockName, clientId }) => options` — when one
     *   registration serves blocks whose choices differ. Inside responsive() it is called
     *   `(deviceType, context)` instead; see resolveResponsiveOptions in utils/options.js.
     *   A saved value no option offers is shown as a disabled "(unavailable)" entry, so it stays
     *   visible and choosing another option clears it
     * @param {string|Function} [config.default=''] - Default value, or a function of the output context
     * @param {Function} [config.className] - Class generator (value, context) => string|string[]|null
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
                options={withStaleValue(resolveOptions(field, context), value)}
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
     * @param {boolean|Function} [config.default=false] - Default value, or a function of the output context
     * @param {Function} [config.className] - Class generator (value, context) => string|string[]|null
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
     * @param {string|Function} [config.default=''] - Default value, or a function of the output context
     * @param {Function} [config.className] - Class generator (value, context) => string|string[]|null
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
     * @param {number|Function} [config.default=0] - Default value, or a function of the output
     *   context. Clearing the input falls back to it (see utils/number-value.js).
     * @param {number} [config.min] - Minimum value
     * @param {number} [config.max] - Maximum value
     * @param {number} [config.step] - Increment, and the grid a value is rounded to whenever the
     *   input commits (on blur or Enter), touched or not; without one, whole numbers. A default
     *   off that grid is rewritten the first time an author tabs through the field.
     * @param {Function} [config.className] - Class generator (value, context) => string|string[]|null
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
                onChange={(newValue) =>
                    onChange(toNumberValue(newValue, hasContextualDefault(field) ? undefined : field.default))
                }
                min={field.min}
                max={field.max}
                step={field.step}
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
     * @param {Function} config.render - Render function
     *   ({ field, value, onChange, attributes, setAttributes, context }) => JSX. Inside
     *   responsive(), `field.name` is the active breakpoint's attribute name (`fooTablet`, …) and
     *   `field.responsive.baseName` the unsuffixed one, so `setAttributes({ [field.name]: v })`
     *   writes the breakpoint being edited.
     * @param {Function} [config.className] - Class generator (value, context) => string|string[]|null
     * @param {Function} [config.attributes] - Attribute generator (value, context) => Object|null
     */
    custom: (config) => ({
        type: 'custom',
        ...config,
    }),
};
