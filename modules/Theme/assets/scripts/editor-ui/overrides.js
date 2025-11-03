import { addFilter } from '@wordpress/hooks';

const makeSpacingVar = (slug) => `var(--wp--preset--spacing--${slug})`;

function spacingOverride(originalOptions) {
    const themeSizes = window.sitchco?.themeSettings?.spacing?.spacingSizes?.theme || [];
    const spacing = [
        ...originalOptions.filter((option) => ['ss-auto', '0', 'none'].includes(option.value)),
        ...themeSizes.map((size) => {
            const label = size.name.replace('px / ', '/');
            const option = {
                label,
                value: `spacing-${size.slug}`,
                size: parseInt(label.split('/').at(-1)),
            };
            if (originalOptions[0].name) {
                option.name = size.name;
                option.output = makeSpacingVar(size.slug);
            }
            return option;
        }),
    ];
    window.sitchco.spacing = spacing;
    return spacing;
}

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
    const themeSizes = window.sitchco?.themeSettings?.spacing?.spacingSizes?.theme || [];
    const match = themeSizes.find((s) => s.slug === gutter);
    if (match) {
        return makeSpacingVar(match.slug);
    }
    return size;
}

addFilter('kadence.block.column.previewGutterSize', 'sitchco/kadence-override/previewGutterSize', gutterSizeOverride);

addFilter(
    'kadence.block.rowlayout.previewGutterSize',
    'sitchco/kadence-override/previewGutterSize',
    gutterSizeOverride
);
