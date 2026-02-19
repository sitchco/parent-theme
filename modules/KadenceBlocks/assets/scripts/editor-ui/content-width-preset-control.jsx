import { addFilter } from '@wordpress/hooks';
import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

addFilter(
    'kadence.blocks.rowlayout.contentWidthPresetControl',
    'sitchco/content-width-preset-control',
    (defaultControl, { attributes, setAttributes }) => {
        const presets = window.sitchco?.contentWidthPresets;
        if (!presets) {
            return defaultControl;
        }

        const options = Object.entries(presets).map(([key, p]) => ({
            value: key,
            label: p.label,
        }));
        return (
            <SelectControl
                label={__('Width Preset', 'kadence-blocks')}
                value={attributes.innerContentWidth || 'theme'}
                options={options}
                onChange={(value) => setAttributes({ innerContentWidth: value })}
            />
        );
    }
);
