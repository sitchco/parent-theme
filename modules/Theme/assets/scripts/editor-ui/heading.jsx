import domReady from '@wordpress/dom-ready';
import { registerFormatType } from '@wordpress/rich-text';
import { RichTextToolbarButton } from '@wordpress/block-editor';

domReady(() => {
    const headingFormats = [
        {
            name: 'sitchco/heading-style',
            title: 'Heading',
            className: 'wp-block-heading',
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
