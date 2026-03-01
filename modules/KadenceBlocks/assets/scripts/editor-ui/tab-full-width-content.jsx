export default function ({ extendBlock, fields }) {
    extendBlock({
        blocks: 'kadence/tab',
        namespace: 'sitchco/kadence-tab-full-width',
        kadenceTabAware: false,
        panel: {
            title: 'Content Layout',
            group: 'settings',
            initialOpen: true,
        },
        fields: [
            fields.toggle({
                name: 'fullWidthContent',
                label: 'Independent content widths',
                help: 'Content width is no longer inherited from the Tabs block. Each child block uses its own alignment setting instead.',
                className: (value) => (value ? 'kt-tab-full-width' : null),
            }),
        ],
    });
}
