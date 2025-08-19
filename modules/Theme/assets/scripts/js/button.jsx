window.sitchco.hooks.addAction(window.sitchco.constants.INIT, () => {
    const { addFilter } = window.wp.hooks;
    const { createHigherOrderComponent } = window.wp.compose;
    const { InspectorControls } = window.wp.blockEditor;
    const { PanelBody, SelectControl } = window.wp.components;

    const themeOptions = [
        { label: 'Default', value: '' },
        { label: 'Blue', value: 'blue' },
        { label: 'Purple', value: 'purple' },
        { label: 'Red', value: 'red' },
        { label: 'Green', value: 'green' },
        { label: 'Orange', value: 'orange' },
    ];

    const withThemeControl = createHigherOrderComponent((BlockEdit) => {
        return (props) => {
            if (props.name !== 'core/button') {
                return <BlockEdit {...props} />;
            }

            const { theme } = props.attributes;
            const { setAttributes } = props;

            return (
                <>
                    <BlockEdit {...props} />
                    <InspectorControls>
                        <PanelBody title="Theme" initialOpen={true}>
                            <SelectControl
                                label="Theme"
                                value={theme}
                                options={themeOptions}
                                onChange={(newTheme) =>
                                    setAttributes({ theme: newTheme })
                                }
                            />
                        </PanelBody>
                    </InspectorControls>
                </>
            );
        };
    }, 'withThemeControl');

    function addThemeClass(props, blockType, attributes) {
        if (blockType.name !== 'core/button') {
            return props;
        }

        const { theme } = attributes;

        if (theme) {
            const newClassName = [
                props.className,
                `has-theme-${theme}`,
            ]
                .filter(Boolean)
                .join(' ');
            return { ...props, className: newClassName };
        }
        return props;
    }

    addFilter(
        'editor.BlockEdit',
        'sitchco/button/add-theme-control',
        withThemeControl
    );

    addFilter(
        'blocks.getSaveContent.extraProps',
        'sitchco/button/add-theme-class',
        addThemeClass
    );
});