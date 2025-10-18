import domReady from '@wordpress/dom-ready';
import { registerFormatType } from '@wordpress/rich-text';
import { RichTextToolbarButton } from '@wordpress/block-editor';

domReady(() => {
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
