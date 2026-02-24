export default function ({ extendBlock, fields }) {
    const themeColorOptions = sitchco.hooks.applyFilters('theme.color-options', []);
    const iconOptions = sitchco.hooks.applyFilters('theme.icon-options', []);
    const fieldList = [
        fields.select({
            name: 'theme',
            label: 'Theme',
            options: themeColorOptions,
            className: (value) => (value ? `has-theme-${value}` : null),
        }),
    ];
    if (iconOptions.length) {
        fieldList.push(
            fields.select({
                name: 'icon',
                label: 'Icon',
                options: [
                    {
                        label: 'Select Icon',
                        value: '',
                    },
                    ...iconOptions,
                ],
                className: (value) => (value ? ['has-icon', `has-icon-${value}`] : null),
            })
        );
    }

    extendBlock(
        sitchco.hooks.applyFilters('extendBlock.button', {
            blocks: ['core/button'],
            namespace: 'sitchco/button',
            panel: {
                title: 'Button Attributes',
                group: 'styles',
                initialOpen: true,
            },
            fields: fieldList,
        })
    );
}
