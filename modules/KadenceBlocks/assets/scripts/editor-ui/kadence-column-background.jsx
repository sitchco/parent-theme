/**
 * Check if column attributes indicate a background is set.
 *
 * @param {Object} attributes - Block attributes
 * @returns {boolean} - Whether a background is set
 */
function hasColumnBackground(attributes) {
    // 1. Inline style background (WordPress core)
    if (attributes?.style?.color?.background) {
        return true;
    }
    // 2. Solid background color (Kadence)
    if (attributes.background && typeof attributes.background === 'string' && attributes.background.length > 0) {
        return true;
    }
    // 3. Gradient background (excluding explicit 'none')
    if (attributes.gradient && attributes.gradient !== 'none') {
        return true;
    }
    // 4. Background image (check nested bgImg/url in array)
    if (Array.isArray(attributes.backgroundImg) && attributes.backgroundImg.length > 0) {
        if (attributes.backgroundImg.some((item) => item && (item.bgImg || item.url))) {
            return true;
        }
    }
    return false;
}

export default function ({ extendBlockClasses }) {
    extendBlockClasses({
        blocks: 'kadence/column',
        namespace: 'sitchco/kadence-column-background',
        classGenerator: (attributes) => (hasColumnBackground(attributes) ? ['kt-column-has-bg'] : []),
    });
}
