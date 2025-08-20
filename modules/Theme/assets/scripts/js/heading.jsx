window.sitchco.hooks.addAction(window.sitchco.constants.INIT, () => {
    const { registerFormatType } = window.wp.richText;
    const { RichTextToolbarButton } = window.wp.blockEditor;

    // An array to define all our custom heading formats.
    const headingFormats = [
        {
            name: 'sitchco/heading-style-default',
            title: 'Heading Default',
            className: 'is-styled-as-heading',
        },
        {
            name: 'sitchco/heading-style-small',
            title: 'Heading Small',
            className: 'is-styled-as-heading-small',
        },
        {
            name: 'sitchco/heading-style-large',
            title: 'Heading Large',
            className: 'is-styled-as-heading-large',
        },
        {
            name: 'sitchco/heading-style-xlarge',
            title: 'Heading X-Large',
            className: 'is-styled-as-heading-xlarge',
        },
    ];

    // Loop through the formats and register each one.
    headingFormats.forEach(({ name, title, className }) => {
        registerFormatType(name, {
            title,
            tagName: 'span',
            className,
            edit({ isActive, value, onChange }) {
                return (
                    <RichTextToolbarButton
                        icon="heading"
                        title={title}
                        onClick={() => {
                            onChange(
                                window.wp.richText.toggleFormat(value, {
                                    type: name,
                                })
                            );
                        }}
                        isActive={isActive}
                    />
                );
            },
        });
    });
});