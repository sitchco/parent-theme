import { addFilter } from '@wordpress/hooks';
import { ToolbarGroup } from '@wordpress/components';
import { stretchWide, stretchFullWidth, alignNone } from '@wordpress/icons';

const ALIGNMENTS = [
    {
        value: undefined,
        label: 'None',
        icon: alignNone,
    },
    {
        value: 'wide',
        label: 'Wide',
        icon: stretchWide,
    },
    {
        value: 'xwide',
        label: 'Extra Wide',
        icon: stretchWide,
    },
    {
        value: 'full',
        label: 'Full',
        icon: stretchFullWidth,
    },
];

function ColumnAlignmentToolbar({ align, setAttributes }) {
    const activeIcon = ALIGNMENTS.find((a) => a.value === align)?.icon ?? alignNone;
    return (
        <ToolbarGroup
            isCollapsed
            icon={activeIcon}
            label="Change alignment"
            controls={ALIGNMENTS.map((opt) => ({
                icon: opt.icon,
                title: opt.label,
                isActive: align === opt.value,
                onClick: () => {
                    const next = align === opt.value ? undefined : opt.value;
                    setAttributes({ align: next });
                },
                role: 'menuitemradio',
            }))}
        />
    );
}

addFilter(
    'kadence.blocks.column.alignmentToolbar',
    'sitchco/kadence-column-alignment',
    (defaultToolbar, { align, setAttributes }) => {
        if (!window.sitchco?.themeSettings?.custom?.extraWideSize) {
            return defaultToolbar;
        }
        return <ColumnAlignmentToolbar align={align} setAttributes={setAttributes} />;
    }
);

export default function ({ extendBlockClasses }) {
    extendBlockClasses({
        blocks: 'kadence/column',
        namespace: 'sitchco/kadence-column-alignment',
        classGenerator: (attributes) => (attributes.align === 'xwide' ? ['alignxwide'] : []),
    });
}
