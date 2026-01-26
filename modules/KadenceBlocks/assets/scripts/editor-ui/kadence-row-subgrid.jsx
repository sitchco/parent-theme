export default function ({ extendBlock, fields }) {
    extendBlock({
        blocks: 'kadence/rowlayout',
        namespace: 'sitchco/kadence-row-subgrid',
        panel: {
            title: 'Column Items',
            group: 'settings',
            initialOpen: false,
        },
        fields: [
            fields.toggle({
                name: 'subgridItems',
                label: 'Align items across columns',
                help: 'Items at the same position in each column will align horizontally',
                className: (value) => (value ? 'kb-subgrid-layout' : null),
            }),
        ],
    });
}
