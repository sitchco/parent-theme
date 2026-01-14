import { useSelect } from '@wordpress/data';

/**
 * Hook to get the active tab for a Kadence block's inspector panel.
 * Reads directly from Kadence's Redux store.
 *
 * @param {Object} props - Block props (must include `name` and `clientId`)
 * @param {Object} [options] - Optional configuration
 * @param {string} [options.panelName] - Override the panel name (defaults to block name without 'kadence/' prefix)
 * @returns {{ activeTab: string, isGeneralTab: boolean }}
 */
export function useKadenceActiveTab(props, options = {}) {
    const { clientId, name } = props;

    // Derive panel name from block name (e.g., 'kadence/column' -> 'column')
    // Allow override via options for edge cases
    const panelName = options.panelName || name.replace('kadence/', '');

    const activeTab = useSelect(
        (select) => {
            const store = select('kadenceblocks/data');
            // Guard against kadenceblocks/data not being available
            if (!store?.getOpenSidebarTabKey) {
                return 'general';
            }
            return store.getOpenSidebarTabKey(panelName + clientId, 'general');
        },
        [panelName, clientId]
    );
    return {
        activeTab,
        isGeneralTab: !activeTab || activeTab === 'general',
    };
}

/**
 * Checks if a block name is a Kadence block.
 *
 * @param {string} blockName - The block name to check
 * @returns {boolean}
 */
export function isKadenceBlock(blockName) {
    return blockName.startsWith('kadence/');
}
