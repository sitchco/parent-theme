/**
 * Kadence Blocks interaction tracking.
 *
 * Tab tracking: delegated click listener (capture phase) detects tab opens
 * by inspecting DOM state before Kadence's handlers update it. Capture phase
 * is required because Kadence's click handler runs at target/bubble phase and
 * synchronously updates the parent <li>'s kt-tab-title-active class.
 *
 * Accordion tracking: delegated click listener (bubble phase) detects
 * accordion opens by inspecting aria-expanded after Kadence's handlers set it
 * synchronously in togglePanel (line 740). Bubble phase is correct here
 * because we need the post-update state.
 *
 * Deep-link tracking: hashStateChange hook maps hash to tab/accordion element.
 *
 * All fire GTM_INTERACTION hooks for analytics via the bridge.
 */

const { hooks, constants } = window.sitchco;

const textContent = (parent, selector) => {
    const el = parent.querySelector(selector);
    return el ? el.textContent.trim() : '';
};

const paneIndex = (pane) => (pane ? Array.from(pane.parentElement.children).indexOf(pane) + 1 : 1);

const tabPayload = (tabTitle) => ({
    event: 'tab_open',
    tab: {
        label: textContent(tabTitle, '.kt-title-text'),
        index: parseInt(tabTitle.getAttribute('data-tab'), 10),
    },
});

const accordionPayload = (header) => ({
    event: 'accordion_open',
    accordion: {
        label: textContent(header, '.kt-blocks-accordion-title'),
        index: paneIndex(header.closest('.wp-block-kadence-pane')),
    },
});

const fireInteraction = (payload, element) => hooks.doAction(constants.GTM_INTERACTION, payload, element);

// Tab tracking — capture phase (reads pre-update active class)
const handleTabClick = (e) => {
    const tabTitle = e.target.closest('.kt-tab-title');
    if (!tabTitle) {
        return;
    }

    // Active-tab guard: kt-tab-title-active is on the parent <li>,
    // not the <button>. In capture phase, the class reflects pre-click state.
    const tabItem = tabTitle.closest('.kt-title-item');
    if (!tabItem || tabItem.classList.contains('kt-tab-title-active')) {
        return;
    }

    fireInteraction(tabPayload(tabTitle), tabTitle);
};

// Accordion tracking — bubble phase (reads post-update aria-expanded)
const handleAccordionClick = (e) => {
    const header = e.target.closest('.kt-blocks-accordion-header');
    if (!header || header.getAttribute('aria-expanded') !== 'true') {
        return;
    }

    fireInteraction(accordionPayload(header), header);
};

// Deep-link tracking — hashStateChange hook (S5, S6)
const handleDeepLink = (hashState) => {
    if (!hashState.current) {
        return;
    }

    const hash = hashState.current;

    // Tab deep-link: use static data-anchor attribute (present in server HTML)
    // rather than dynamic #id which is set by Kadence's JS at runtime
    const tabButton = document.querySelector(`.kt-title-item > [data-anchor="${CSS.escape(hash)}"]`);
    if (tabButton) {
        fireInteraction(tabPayload(tabButton), tabButton);
        return;
    }

    // Accordion deep-link: hash matches a .wp-block-kadence-pane by ID
    const element = document.getElementById(hash);
    if (!element?.classList.contains('wp-block-kadence-pane')) {
        return;
    }

    const header = element.querySelector('.kt-blocks-accordion-header');
    if (!header) {
        return;
    }

    fireInteraction(accordionPayload(header), header);
};

export function registerKadenceTracking() {
    document.addEventListener('click', handleTabClick, true);
    document.addEventListener('click', handleAccordionClick);
    hooks.addAction(constants.HASH_STATE_CHANGE, handleDeepLink);
}
