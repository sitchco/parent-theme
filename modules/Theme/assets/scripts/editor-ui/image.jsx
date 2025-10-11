import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { InspectorAdvancedControls } from '@wordpress/block-editor';
import { ToggleControl } from '@wordpress/components';
import { Fragment } from '@wordpress/element';

addFilter('blocks.registerBlockType', 'my-plugin/extend-core-image', (settings, name) => {
    if (name !== 'core/image') {
        return settings;
    }
    return {
        ...settings,
        attributes: {
            ...settings.attributes,
            inlineSvg: {
                type: 'boolean',
                default: false,
            },
        },
    };
});

const addInlineSvgToggle = createHigherOrderComponent((BlockEdit) => {
    return (props) => {
        if (props.name !== 'core/image') {
            return <BlockEdit {...props} />;
        }

        const { attributes, setAttributes } = props;
        const { inlineSvg } = attributes;
        return (
            <Fragment>
                <BlockEdit {...props} />
                <InspectorControls>
                    <InspectorAdvancedControls>
                        <ToggleControl
                            label="Inline SVG"
                            checked={!!inlineSvg}
                            onChange={(value) => setAttributes({ inlineSvg: value })}
                            help="For SVG images only. Outputs as inline SVG markup instead of an <img> tag."
                        />
                    </InspectorAdvancedControls>
                </InspectorControls>
            </Fragment>
        );
    };
}, 'addInlineSvgToggle');

addFilter('editor.BlockEdit', 'my-plugin/add-inline-svg-toggle', addInlineSvgToggle);
