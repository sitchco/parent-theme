/**
 * Handles the sticky state for the site header by listening to scroll events.
 */
export function registerStickyHeader() {
    const { util, hooks } = window.sitchco;
    const header = document.querySelector('.site-header--sticky');

    // Exit if the header element isn't found
    if (!header) {
        return;
    }

    const handleScroll = () => {
        const headerHeight = hooks.applyFilters('header-height', 50);
        const isSticky = window.scrollY > headerHeight;

        // Toggle the 'has-sticky-header' class on the <body> element
        document.body.classList.toggle('has-sticky-header', isSticky);

        // Toggle the 'site-header--is-sticky' modifier class on the header element
        header.classList.toggle('site-header--is-sticky', isSticky);
    };

    const throttledScrollHandler = util.throttle(handleScroll, 100);
    window.addEventListener('scroll', throttledScrollHandler);

    // Run once on load to set the initial state
    handleScroll();
}