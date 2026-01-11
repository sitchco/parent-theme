import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';

/**
 * Check if column attributes indicate a background is set.
 *
 * Note: This logic is intentionally aligned with the server-side detection
 * in KadenceBlocks.php::hasColumnBackground() to ensure consistent behavior
 * between the block editor and frontend rendering.
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

const withColumnBackgroundClass = createHigherOrderComponent((BlockListBlock) => {
    return (props) => {
        const { name, attributes } = props;
        if (name !== 'kadence/column') {
            return <BlockListBlock {...props} />;
        }
        if (hasColumnBackground(attributes)) {
            const newProps = {
                ...props,
                className: [props.className, 'kt-column-has-bg'].filter(Boolean).join(' '),
            };
            return <BlockListBlock {...newProps} />;
        }
        return <BlockListBlock {...props} />;
    };
}, 'withColumnBackgroundClass');

addFilter('editor.BlockListBlock', 'sitchco/with-column-background-class', withColumnBackgroundClass);
