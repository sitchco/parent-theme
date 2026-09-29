/**
 * Merges class names, filtering out falsy values.
 *
 * @param {...(string|string[]|null|undefined|false)} classes
 * @returns {string}
 */
export function classNames(...classes) {
    return [
        ...new Set(
            classes
                .flat()
                .filter(Boolean)
                .flatMap((c) => c.split(/\s+/))
        ),
    ].join(' ');
}

/**
 * Generates class names from fields based on their attribute values.
 *
 * Fields hidden by their `condition` contribute nothing, so a stale value left behind by a
 * control the author can no longer see does not keep emitting its class.
 *
 * @param {Array} fields - Array of field definitions
 * @param {Object} attributes - Block attributes
 * @param {Object} [context] - Render context, passed on to `condition`; its keys differ per
 *   phase — see the note at the top of utils/attributes.js
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

        const value = attributes[field.name];
        const result = field.className(value);
        if (result) {
            if (Array.isArray(result)) {
                classes.push(...result);
            } else {
                classes.push(result);
            }
        }
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
 * @param {Object} [context] - Render context, passed on to `condition`; its keys differ per
 *   phase — see the note at the top of utils/attributes.js
 * @returns {string[]} Array of class names
 */
export function generateEditorFieldClasses(fields, attributes, context = {}) {
    /* Defaulted rather than required: a caller with no responsive fields has no reason to
       resolve the preview device, and desktop is the unprefixed baseline. */
    const device = context.deviceType || 'Desktop';
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

            const result = originalClassName(value);
            if (result) {
                if (Array.isArray(result)) {
                    classes.push(...result);
                } else {
                    classes.push(result);
                }
            }
        } else {
            // Non-responsive field — unchanged behavior
            const value = attributes[field.name];
            const result = field.className(value);
            if (result) {
                if (Array.isArray(result)) {
                    classes.push(...result);
                } else {
                    classes.push(result);
                }
            }
        }
    }
    return classes;
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
