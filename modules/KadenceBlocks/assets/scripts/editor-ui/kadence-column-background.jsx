import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';

const withColumnBackgroundClass = createHigherOrderComponent((BlockListBlock) => {
    return (props) => {
        const { name, attributes } = props;
        if (name !== 'kadence/column') {
            return <BlockListBlock {...props} />;
        }

        let hasBackground = false;
        // 1. Check for background color in style attribute
        if (attributes?.style?.color?.background) {
            hasBackground = true;
        }
        // 2. Check for backgroundColor attribute
        if (!hasBackground && attributes.backgroundColor) {
            hasBackground = true;
        }
        // 3. Check for gradient attribute
        if (!hasBackground && attributes.gradient && attributes.gradient !== 'none') {
            hasBackground = true;
        }
        // 4. Check for background image in `background`, `backgroundImg`, or `backgroundImage` attributes
        if (!hasBackground) {
            const bgAttributes = [attributes.background, attributes.backgroundImg, attributes.backgroundImage];

            for (const attr of bgAttributes) {
                if (!attr) {
                    continue;
                }
                if (typeof attr === 'string' && attr.length > 0) {
                    hasBackground = true;
                    break;
                }
                if (Array.isArray(attr) && attr.length > 0) {
                    // Check for bgImg or url inside the array's objects
                    if (attr.some((item) => item && (item.bgImg || item.url))) {
                        hasBackground = true;
                        break;
                    }
                }
            }
        }
        if (hasBackground) {
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
