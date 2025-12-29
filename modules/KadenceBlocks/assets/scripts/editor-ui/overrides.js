import { addFilter } from '@wordpress/hooks';

const makePresetVar = (type, slug) => `var(--wp--preset--${type}--${slug})`;

const themeSpacing = () => window.sitchco?.themeSettings.spacing.spacingSizes.theme || [];
const themeFontSizes = () => window.sitchco?.themeSettings.typography.fontSizes.theme || [];

function createSizeOverride({ settings, type, labelTransform }) {
    return function (originalOptions) {
        const themeSizes = settings() || [];
        return [
            ...originalOptions.filter((option) => ['ss-auto', '0', 'none'].includes(option.value)),
            ...themeSizes.map((size) => {
                const label = labelTransform(size.name);
                const option = {
                    label,
                    value: `${type}-${size.slug}`,
                    size: parseInt(label.split('/').at(-1)),
                };
                if (originalOptions[0].name) {
                    option.name = size.name;
                    option.output = makePresetVar(type, size.slug);
                }
                return option;
            }),
        ];
    };
}

const spacingOverride = createSizeOverride({
    settings: themeSpacing,
    type: 'spacing',
    labelTransform: (name) => name.replace('px / ', '/'),
});

const fontSizeOverride = createSizeOverride({
    settings: themeFontSizes,
    type: 'font-size',
    labelTransform: (name) => name.split('/').at(-1).trim(),
});

addFilter(
    'kadence.constants.packages.helpers.constants.spacingSizesMap',
    'sitchco/kadence-override/spacing',
    spacingOverride
);

addFilter('kadence.constants.blocks.rowlayout.spacingSizesMap', 'sitchco/kadence-override/spacing', spacingOverride);

addFilter(
    'kadence.constants.packages.components.measurement-range-control.optionsMap',
    'sitchco/kadence-override/spacing',
    spacingOverride
);

addFilter('kadence.blocks.rowlayout.rowGutterOptions', 'sitchco/kadence-override/gutterOptions', spacingOverride);

addFilter('kadence.blocks.rowlayout.columnGutterOptions', 'sitchco/kadence-override/gutterOptions', spacingOverride);

addFilter('kadence.blocks.column.verticalGapOptions', 'sitchco/kadence-override/gapOptions', spacingOverride);

addFilter('kadence.blocks.column.horizontalGapOptions', 'sitchco/kadence-override/gapOptions', spacingOverride);

function gutterSizeOverride(size, _, gutter) {
    const themeSizes = themeSpacing() || [];
    const match = themeSizes.find((s) => `spacing-${s.slug}` === gutter);
    if (match) {
        return makePresetVar('spacing', match.slug);
    }
    return size;
}

addFilter('kadence.block.column.previewGutterSize', 'sitchco/kadence-override/previewGutterSize', gutterSizeOverride);

addFilter(
    'kadence.block.rowlayout.previewGutterSize',
    'sitchco/kadence-override/previewGutterSize',
    gutterSizeOverride
);

addFilter(
    'kadence.constants.packages.helpers.constants.fontSizesMap',
    'sitchco/kadence-override/fontSizesMap',
    fontSizeOverride
);
