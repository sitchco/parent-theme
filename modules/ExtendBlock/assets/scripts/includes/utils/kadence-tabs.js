/**
 * Which of Kadence's inspector tabs a panel can actually render on.
 *
 * Kadence draws its General / Style / Advanced bar with an `allowedTabs` prop, and many blocks
 * leave Style out. The active tab key only changes when a rendered tab button is clicked, so on
 * those blocks it can never be 'style' — and a panel pinned there would be filtered out on every
 * tab, with nothing to say why.
 *
 * The prop lives in each block's edit component and is not exposed through any store or block
 * metadata, so the list below is copied from the plugin's source: every `allowedTabs` in
 * wp-content/plugins/kadence-blocks/src/blocks/*\/edit.js that is not all three tabs, plus the
 * child blocks that render no tab bar. Checked against Kadence Blocks 1003.7.2.0; after an update,
 * re-run `rg -n 'allowedTabs=' wp-content/plugins/kadence-blocks/src/blocks` and compare every
 * result that isn't all three tabs with the list below. Like DYNAMIC_BLOCKS in extend-block.jsx
 * it is maintained by hand. A Kadence block missing from it is
 * treated as having all three tabs, which is today's behavior — so a missed entry can only
 * reproduce the old failure, never introduce a new one.
 *
 * Plain JS, no JSX, no imports, so it is unit testable on its own.
 */
const RESTRICTED_TABS = {
    'kadence/advancedbtn': ['general', 'advanced'],
    'kadence/icon': ['general', 'advanced'],
    'kadence/lottie': ['general', 'advanced'],
    'kadence/pane': ['general', 'advanced'],
    'kadence/show-more': ['general', 'advanced'],
    'kadence/single-icon': ['general', 'advanced'],
    'kadence/spacer': ['general', 'advanced'],
    'kadence/tab': ['general', 'advanced'],
    'kadence/testimonial': ['general', 'advanced'],
    'kadence/table-data': ['general'],
    'kadence/table-row': ['general'],
    // Kadence passes 'transform' too, but InspectorControlTabs draws no tab for it without a `tabs` prop.
    'kadence/vector': ['general', 'advanced'],
    'kadence/advanced-form-accept': ['general', 'advanced'],
    'kadence/advanced-form-checkbox': ['general', 'advanced'],
    'kadence/advanced-form-date': ['general', 'advanced'],
    'kadence/advanced-form-email': ['general', 'advanced'],
    'kadence/advanced-form-file': ['general', 'advanced'],
    'kadence/advanced-form-hidden': ['general', 'advanced'],
    'kadence/advanced-form-number': ['general', 'advanced'],
    'kadence/advanced-form-radio': ['general', 'advanced'],
    'kadence/advanced-form-select': ['general', 'advanced'],
    'kadence/advanced-form-telephone': ['general', 'advanced'],
    'kadence/advanced-form-text': ['general', 'advanced'],
    'kadence/advanced-form-textarea': ['general', 'advanced'],
    'kadence/advanced-form-time': ['general', 'advanced'],
    // No tab bar at all, so the active key never leaves its 'general' default.
    'kadence/countdown-inner': ['general'],
    'kadence/countdown-timer': ['general'],
    'kadence/header-column': ['general'],
    'kadence/header-container-desktop': ['general'],
    'kadence/header-container-tablet': ['general'],
    'kadence/header-section': ['general'],
};

/** Every block has General, which is also where Kadence opens. */
const FALLBACK_TAB = 'general';

const warned = new Set();

/**
 * Resolves the tab a panel renders on for one block.
 *
 * The requested tab when the block has it, 'general' otherwise. Falling back is announced once per
 * block and tab, so whoever configures a panel for a block without that tab learns why it moved.
 *
 * @param {string} blockName    - e.g. 'kadence/advancedbtn'
 * @param {string} requestedTab - The panel's `kadenceTab`
 * @param {string} [panelTitle] - Named in the warning
 * @returns {string} The tab to render on
 */
export function resolveKadenceTab(blockName, requestedTab, panelTitle = 'A panel') {
    const tabs = RESTRICTED_TABS[blockName];
    if (!tabs || tabs.includes(requestedTab)) {
        return requestedTab;
    }

    const key = `${blockName}:${requestedTab}`;
    if (!warned.has(key)) {
        warned.add(key);
        console.warn(
            `[extendBlock] ${panelTitle} asks for Kadence's '${requestedTab}' tab, which ${blockName} doesn't have; rendering it on '${FALLBACK_TAB}' instead.`
        );
    }
    return FALLBACK_TAB;
}
