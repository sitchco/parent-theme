const { extendBlock, fields } = sitchco.extendBlock;

sitchco.editorReady(() => {
    extendBlock({
        blocks: 'kadence/image',
        namespace: 'sitchco/kadence-image-modal',
        panel: {
            title: 'Modal',
            group: 'settings',
            initialOpen: false,
        },
        fields: [
            fields.toggle({
                name: 'openInModal',
                label: 'Open in modal',
                help: 'Opens the full-size image in a dialog when clicked. If a link is also set, the modal takes precedence on the frontend.',
            }),
        ],
    });

    console.log('[KadenceImageModal] editor-ui registered openInModal toggle on kadence/image');
});
