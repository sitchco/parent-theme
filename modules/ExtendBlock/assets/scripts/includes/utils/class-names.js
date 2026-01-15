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
 * Merges generated classes with existing className prop.
 *
 * @param {string|undefined} existingClassName
 * @param {string[]} newClasses
 * @returns {string}
 */
export function mergeClassNames(existingClassName, newClasses) {
    return classNames(existingClassName, ...newClasses);
}
