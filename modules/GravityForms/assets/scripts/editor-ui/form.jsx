export default function ({ extendBlock, fields }) {
    extendBlock({
        blocks: 'gravityforms/form',
        namespace: 'sitchco/form',
        panel: {
            title: 'Submit Button Attributes',
            group: 'styles',
            initialOpen: true,
        },
        fields: [
            fields.select({
                name: 'theme',
                label: 'Theme',
                options: sitchco.hooks.applyFilters('theme.color-options', []),
            }),
            fields.select({
                name: 'icon',
                label: 'Icon',
                options: sitchco.hooks.applyFilters('theme.icon-options', []),
            }),
        ],
    });
}
