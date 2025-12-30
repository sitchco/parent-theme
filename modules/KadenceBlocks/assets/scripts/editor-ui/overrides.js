import { addFilter } from '@wordpress/hooks';

const makePresetVar = (type, slug) => `var(--wp--preset--${type}--${slug})`;

const themeSpacing = () => window.sitchco?.themeSettings.spacing.spacingSizes.theme || [];
const themeFontSizes = () => window.sitchco?.themeSettings.typography.fontSizes.theme || [];

function createSizeOverride({ settings, type }) {
    return function (originalOptions) {
        const themeSizes = settings() || [];
        return [
            ...originalOptions.filter((option) => ['ss-auto', '0', 'none'].includes(option.value)),
            ...themeSizes.map((size) => {
                const labelMatch = size.name.match(/^(.*?)\d/);
                const label = labelMatch ? size.name.slice(0, labelMatch[0].length - 1).trim() : size.name;
                const numbers = size.name.match(/\d+/g);
                const parsedSize = numbers ? parseInt(numbers.at(-1)) : null;
                const option = {
                    label,
                    value: `${type}-${size.slug}`,
                    ...(parsedSize && { size: parsedSize }),
                };
                if (originalOptions[0].name) {
                    option.name = label;
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
});

const fontSizeOverride = createSizeOverride({
    settings: themeFontSizes,
    type: 'font-size',
});

addFilter(
    'kadence.constants.packages.helpers.constants.spacingSizesMap',
    'sitchco/kadence-override/spacing',
    spacingOverride,
    20
);

addFilter('kadence.constants.blocks.rowlayout.spacingSizesMap', 'sitchco/kadence-override/spacing', spacingOverride, 20);

addFilter(
    'kadence.constants.packages.components.measurement-range-control.optionsMap',
    'sitchco/kadence-override/spacing',
    spacingOverride,
    20
);

addFilter('kadence.blocks.rowlayout.rowGutterOptions', 'sitchco/kadence-override/gutterOptions', spacingOverride, 20);

addFilter('kadence.blocks.rowlayout.columnGutterOptions', 'sitchco/kadence-override/gutterOptions', spacingOverride, 20);

addFilter('kadence.blocks.column.verticalGapOptions', 'sitchco/kadence-override/gapOptions', spacingOverride, 20);

addFilter('kadence.blocks.column.horizontalGapOptions', 'sitchco/kadence-override/gapOptions', spacingOverride, 20);

function gutterSizeOverride(size, _, gutter) {
    const themeSizes = themeSpacing() || [];
    const match = themeSizes.find((s) => `spacing-${s.slug}` === gutter);
    if (match) {
        return makePresetVar('spacing', match.slug);
    }
    return size;
}

addFilter('kadence.block.column.previewGutterSize', 'sitchco/kadence-override/previewGutterSize', gutterSizeOverride, 20);

addFilter(
    'kadence.block.rowlayout.previewGutterSize',
    'sitchco/kadence-override/previewGutterSize',
    gutterSizeOverride,
    20
);

addFilter(
    'kadence.constants.packages.helpers.constants.fontSizesMap',
    'sitchco/kadence-override/fontSizesMap',
    fontSizeOverride,
    20
);
