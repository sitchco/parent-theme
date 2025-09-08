function registerStickyHeader() {
    const { util, hooks } = window.sitchco;
    const header = document.querySelector('.site-header--sticky');

    if (!header) {
        return;
    }

    const handleScroll = () => {
        const headerHeight = hooks.applyFilters('header-height', 50);
        const isSticky = window.scrollY > headerHeight;

        document.body.classList.toggle('has-sticky-header', isSticky);
        header.classList.toggle('site-header--is-sticky', isSticky);
    };

    const throttledScrollHandler = util.throttle(handleScroll, 100);
    window.addEventListener('scroll', throttledScrollHandler);
}

const { register } = window.sitchco;
register(registerStickyHeader)