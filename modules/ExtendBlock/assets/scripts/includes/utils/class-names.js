import { readFieldValue } from './field-value';

/**
 * Turns whatever a class callback returned into a list of non-empty class names.
 *
 * Every consumer of a `className` callback or a `classGenerator` goes through this, so "nothing
 * to add" is always an empty array. That is what keeps the save filter a strict no-op on an
 * untouched block: a callback like `(v) => [v && 'x-' + v]` returns `['']` there, which is not
 * empty until it's normalized. A bare string is split into its classes (not its characters),
 * and `undefined` reads as nothing rather than throwing.
 *
 * @param {*} output - string, (nested) array of strings, or anything falsy
 * @returns {string[]}
 */
export function toClassList(output) {
    return [output]
        .flat(Infinity)
        .filter((c) => typeof c === 'string')
        .flatMap((c) => c.split(/\s+/))
        .filter(Boolean);
}

/**
 * Merges class names, filtering out falsy values.
 *
 * @param {...(string|string[]|null|undefined|false)} classes
 * @returns {string}
 */
export function classNames(...classes) {
    return [...new Set(toClassList(classes))].join(' ');
}

/**
 * Generates class names from fields based on their attribute values.
 *
 * Fields hidden by their `condition` contribute nothing, so a stale value left behind by a
 * control the author can no longer see does not keep emitting its class.
 *
 * @param {Array} fields - Array of field definitions
 * @param {Object} attributes - Block attributes
 * @param {Object} [context] - Output context, `{ blockName }`, passed on to `condition` and `className` —
 *   see the note at the top of utils/attributes.js
 * @returns {string[]} Array of class names
 */
export function generateFieldClasses(fields, attributes, context = {}) {
    const classes = [];

    for (const field of fields) {
        if (!field.className) {
            continue;
        }
        if (field.condition && !field.condition(attributes, context)) {
            continue;
        }

        const value = readFieldValue(field, attributes, context);
        classes.push(...toClassList(field.className(value, context)));
    }
    return classes;
}

/**
 * Generates editor-preview classes, using the active device to select
 * the correct breakpoint value for responsive fields.
 *
 * For responsive fields, applies the inheritance cascade:
 * Mobile -> Tablet -> Desktop (falls back to next larger breakpoint).
 * Returns unprefixed classes (no tablet:/mobile: prefix in editor).
 *
 * @param {Array} fields - Array of field definitions (may include responsive fields)
 * @param {Object} attributes - Block attributes
 * @param {Object} [context] - Output context, `{ blockName }`, passed on to `condition` and `className` —
 *   see the note at the top of utils/attributes.js
 * @param {string} [device='Desktop'] - The editor's preview device. Its own argument rather than
 *   part of the context, because only this cascade may read it: nothing that decides output does.
 *   Defaulted because desktop is the unprefixed baseline.
 * @returns {string[]} Array of class names
 */
export function generateEditorFieldClasses(fields, attributes, context = {}, device = 'Desktop') {
    const classes = [];

    for (const field of fields) {
        if (!field.className) {
            continue;
        }
        if (field.condition && !field.condition(attributes, context)) {
            continue;
        }
        if (field.responsive) {
            // Only process once per responsive group (the desktop field)
            if (!field.responsive.isDesktop) {
                continue;
            }

            const { baseName, originalClassName } = field.responsive;
            const desktopValue = attributes[baseName] || '';
            const tabletValue = attributes[`${baseName}Tablet`] || '';
            const mobileValue = attributes[`${baseName}Mobile`] || '';

            // Inheritance cascade: mobile -> tablet -> desktop
            let value;
            if (device === 'Mobile') {
                value = mobileValue || tabletValue || desktopValue;
            } else if (device === 'Tablet') {
                value = tabletValue || desktopValue;
            } else {
                value = desktopValue;
            }

            classes.push(...toClassList(originalClassName(value, context)));
        } else {
            // Non-responsive field — unchanged behavior
            const value = readFieldValue(field, attributes, context);
            classes.push(...toClassList(field.className(value, context)));
        }
    }
    return classes;
}

/**
 * Wraps a className callback to prefix its output with a breakpoint prefix.
 *
 * Lives here rather than in responsive.jsx so it can be tested in node. The wrapper hands the
 * output context through: a tablet or mobile field is still a field, and its `className` gets the
 * same `{ blockName }` as any other.
 *
 * @param {Function|undefined} classNameFn - The field's own className callback
 * @param {string} prefix - Breakpoint prefix, e.g. 'tablet:'; empty for desktop
 * @returns {Function|undefined}
 */
export function prefixClassName(classNameFn, prefix) {
    if (!classNameFn || !prefix) {
        return classNameFn;
    }
    return (value, context) => {
        const result = classNameFn(value, context);
        if (!result) {
            return result;
        }
        if (Array.isArray(result)) {
            return result.map((c) => `${prefix}${c}`);
        }
        return `${prefix}${result}`;
    };
}

/**
 * Merges generated classes with existing className prop.
 *
 * @param {string|undefined} existingClassName
 * @param {string[]} newClasses
 * @returns {string}
 */
export function mergeClassNames(existingClassName, newClasses) {
    return classNames(existingClassName, ...newClasses);
}
