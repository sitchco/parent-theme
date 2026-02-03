/**
 * Kadence Accordion Icon Selection
 *
 * Adds icon picker fields to the kadence/accordion block for selecting
 * custom collapsed and expanded state icons from the sitchco icon library.
 */

export default function ({ extendBlock, fields }) {
    // Get icon options from inline script data
    const iconOptions = window.sitchco?.sitchcoIcons || [{ label: 'Default', value: '' }];

    extendBlock({
        blocks: 'kadence/accordion',
        namespace: 'sitchco/accordion-icons',
        panel: {
            title: 'Accordion Icons',
            group: 'settings',
            initialOpen: false,
        },
        fields: [
            fields.select({
                name: 'accordionIconCollapsed',
                label: 'Collapsed Icon',
                options: iconOptions,
                default: 'plus',
            }),
            fields.select({
                name: 'accordionIconExpanded',
                label: 'Expanded Icon',
                options: iconOptions,
                default: 'minus',
                help: 'Custom icons appear on the frontend. Use Preview to see them.',
            }),
        ],
    });
}
