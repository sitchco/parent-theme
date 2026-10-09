/**
 * The style channel: CSS custom properties a field or generator emits onto the block wrapper.
 *
 * A sibling of attributes.js, with one difference that defines it: this channel is CANVAS ONLY.
 * The save filter never reads it, so a style can never change a block's saved markup or its
 * validation. The front end gets the same properties from the server, through
 * ExtendBlockModule's `wrapper-props` filter, which applies the same rules as this file. An
 * extension using it is expected to register with `saveOutput: false` and mirror its output in
 * PHP; see extendBlock() in extend-block.jsx.
 *
 * Plain JS on purpose, with no JSX and no @wordpress imports, so the logic below is unit
 * testable without a transform. See tests/js/styles.test.js.
 *
 * What a generator author has to know:
 *
 * - Only custom properties (`--*`) are emitted. Anything else is dropped, with a one-time console
 *   warning per name. A block's own `style` belongs to the block and to core's block supports.
 * - A value is a string or a finite number. One containing `;`, `{`, `}`, `\`, `<` or `>` is
 *   dropped, because it could end its own declaration and start another.
 * - `undefined`, `null` and `''` are "unset" and dropped, so a generator can return its keys
 *   unconditionally.
 * - In the canvas, `useBlockProps` merges `style` key by key with the block's own style
 *   (`{ ...wrapperProps.style, ...props.style }`), so a custom property the block itself never
 *   sets survives unchanged.
 *
 * Callbacks get the output context, `{ blockName }`, exactly as the class and attribute channels
 * do; see the note at the top of utils/attributes.js.
 */

import { readFieldValue } from './field-value';

/** Custom property names only. Mirrors ExtendBlockModule::STYLE_NAME_PATTERN. */
const ALLOWED_NAME = /^--[A-Za-z0-9_-]+$/;

/** Mirrors ExtendBlockModule::UNSAFE_STYLE_VALUE_PATTERN. */
const UNSAFE_VALUE = /[;{}\\<>]|\/\*/;

/** Mirrors ExtendBlockModule::CLOSED_QUOTES_PATTERN: every quote in the value closes. */
const CLOSED_QUOTES = /^(?:[^'"]|"[^"]*"|'[^']*')*$/;

const warned = new Set();

function warnDropped(key, message) {
    if (warned.has(key)) {
        return;
    }

    warned.add(key);
    console.warn(`[extendBlock] ${message}`);
}

function isPlainObject(value) {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const proto = Object.getPrototypeOf(value);
    return proto === Object.prototype || proto === null;
}

/**
 * A value as the string it renders as, or undefined for "unset".
 *
 * @param {*} value
 * @returns {string|undefined}
 */
function toStyleValue(value) {
    if (typeof value === 'number') {
        return Number.isFinite(value) ? String(value) : undefined;
    }
    if (typeof value !== 'string' || value === '') {
        return undefined;
    }
    return value;
}

/**
 * Merges style objects left to right, dropping what must not be emitted.
 *
 * A source that isn't a plain object is skipped whole, as in mergeAttributes().
 *
 * @param {...(Object|null|undefined)} sources
 * @returns {Object} Custom property name => string value
 */
export function mergeStyles(...sources) {
    const merged = {};

    for (const source of sources) {
        if (!isPlainObject(source)) {
            continue;
        }

        for (const [name, raw] of Object.entries(source)) {
            const value = toStyleValue(raw);
            if (value === undefined) {
                continue;
            }
            if (!ALLOWED_NAME.test(name)) {
                warnDropped(
                    `name:${name}`,
                    `Dropped the '${name}' style: extensions can only emit CSS custom properties (--*).`
                );

                continue;
            }
            if (UNSAFE_VALUE.test(value) || !CLOSED_QUOTES.test(value)) {
                warnDropped(
                    `value:${name}`,
                    `Dropped the '${name}' style: its value contains ; { } \\ < > or /*, or leaves a quote open.`
                );

                continue;
            }

            merged[name] = value;
        }
    }
    return merged;
}

/**
 * Generates wrapper custom properties from fields based on their attribute values.
 *
 * Gated by `condition` exactly as generateFieldAttributes() is, so switching between two
 * animations cannot leave the previous one's properties behind.
 *
 * @param {Array}  fields     - Array of field definitions
 * @param {Object} attributes - Block attributes
 * @param {Object} [context]  - Output context, `{ blockName }`
 * @returns {Object} Custom property name => value, empty when nothing applies
 */
export function generateFieldStyles(fields, attributes, context = {}) {
    const generated = [];

    for (const field of fields) {
        if (!field.style) {
            continue;
        }
        if (field.condition && !field.condition(attributes, context)) {
            continue;
        }

        generated.push(field.style(readFieldValue(field, attributes, context), context));
    }
    return mergeStyles(...generated);
}
