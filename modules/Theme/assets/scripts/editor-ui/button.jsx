export default function ({ extendBlock, fields }) {
    extendBlock({
        blocks: 'core/button',
        namespace: 'sitchco/button',
        panel: {
            title: 'Attributes',
            group: 'styles',
            initialOpen: true,
        },
        fields: [
            fields.select({
                name: 'theme',
                label: 'Theme',
                options: sitchco.hooks.applyFilters('theme.color-options', []),
                className: (value) => (value ? `has-theme-${value}` : null),
            }),
            fields.select({
                name: 'icon',
                label: 'Icon',
                options: sitchco.hooks.applyFilters('theme.icon-options', []),
                className: (value) => (value ? ['has-icon', `has-icon-${value}`] : null),
            }),
        ],
    });
}
