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
        value: 'center',
        label: 'Extra Wide',
        icon: stretchWide,
    },
    {
        value: 'full',
        label: 'Full',
        icon: stretchFullWidth,
    },
];

function RowAlignmentToolbar({ align, setAttributes }) {
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
    'kadence.blocks.rowlayout.alignmentToolbar',
    'sitchco/kadence-row-alignment',
    (defaultToolbar, { align, setAttributes }) => {
        if (!window.sitchco?.themeSettings?.custom?.extraWideSize) {
            return defaultToolbar;
        }
        return <RowAlignmentToolbar align={align} setAttributes={setAttributes} />;
    }
);
