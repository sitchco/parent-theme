/**
 * Merges class names, filtering out falsy values.
 *
 * @param {...(string|string[]|null|undefined|false)} classes
 * @returns {string}
 */
export function classNames(...classes) {
    return classes.flat().filter(Boolean).join(' ');
}

/**
 * Generates class names from fields based on their attribute values.
 *
 * @param {Array} fields - Array of field definitions
 * @param {Object} attributes - Block attributes
 * @returns {string[]} Array of class names
 */
export function generateFieldClasses(fields, attributes) {
    const classes = [];

    for (const field of fields) {
        if (!field.className) {
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
 * @param {string} device - 'Desktop', 'Tablet', or 'Mobile'
 * @returns {string[]} Array of class names
 */
export function generateEditorFieldClasses(fields, attributes, device) {
    const classes = [];

    for (const field of fields) {
        if (!field.className) {
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
