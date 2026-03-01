import { addFilter } from '@wordpress/hooks';
import { ToolbarGroup } from '@wordpress/components';
import { alignNone } from '@wordpress/icons';
import { ALIGNMENTS } from './alignments.js';

function TabsAlignmentToolbar({ blockAlignment, setAttributes }) {
    const activeIcon = ALIGNMENTS.find((a) => a.value === blockAlignment)?.icon ?? alignNone;
    return (
        <ToolbarGroup
            isCollapsed
            icon={activeIcon}
            label="Change alignment"
            controls={ALIGNMENTS.map((opt) => ({
                icon: opt.icon,
                title: opt.label,
                isActive: blockAlignment === opt.value,
                onClick: () => {
                    const next = blockAlignment === opt.value ? undefined : opt.value;
                    setAttributes({ blockAlignment: next });
                },
                role: 'menuitemradio',
            }))}
        />
    );
}

addFilter(
    'kadence.blocks.tabs.alignmentToolbar',
    'sitchco/kadence-tabs-alignment',
    (defaultToolbar, { blockAlignment, setAttributes }) => {
        if (!window.sitchco?.themeSettings?.custom?.extraWideSize) {
            return defaultToolbar;
        }
        return <TabsAlignmentToolbar blockAlignment={blockAlignment} setAttributes={setAttributes} />;
    }
);

export default function ({ extendBlockClasses }) {
    extendBlockClasses({
        blocks: 'kadence/tabs',
        namespace: 'sitchco/kadence-tabs-alignment',
        classGenerator: (attributes) => (attributes.blockAlignment === 'xwide' ? ['alignxwide'] : []),
    });
}
