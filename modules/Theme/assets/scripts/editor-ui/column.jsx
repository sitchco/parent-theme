import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { Fragment } from '@wordpress/element';
import { InspectorControls } from '@wordpress/block-editor';
import { RangeControl } from '@wordpress/components';

const addColumnOpacityControl = (BlockEdit) => (props) => {
    if (props.name !== 'core/column') {
        return <BlockEdit {...props} />;
    }

    const { attributes, setAttributes } = props;
    const { backgroundOpacity } = attributes;

    // Check if a background color is set
    const hasBackgroundColor = attributes.style?.color?.background;

    return (
        <Fragment>
            <BlockEdit {...props} />
            {hasBackgroundColor && (
                <InspectorControls group="color">
                    <div style={{ gridColumn: '1 / -1' }}>
                        <RangeControl
                            label={__('Background Opacity', 'roundabout')}
                            value={backgroundOpacity}
                            onChange={(value) => {
                                setAttributes({ backgroundOpacity: value });
                            }}
                            min={0}
                            max={1}
                            step={0.05}
                        />
                    </div>
                </InspectorControls>
            )}
        </Fragment>
    );
};

addFilter('editor.BlockEdit', 'sitchco/column/opacity-control', addColumnOpacityControl);