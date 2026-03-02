import { addFilter } from '@wordpress/hooks';
import { ToolbarGroup } from '@wordpress/components';
import { alignNone } from '@wordpress/icons';
import { ALIGNMENTS } from './alignments.js';

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
