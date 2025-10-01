function registerStickyHeader() {
    const { util, hooks } = window.sitchco;
    const header = document.querySelector('.has-site-header-sticky');
    if (!header) {
        return;
    }

    document.body.classList.add('has-sticky-header');

    const handleScroll = () => {
        const headerHeight = hooks.applyFilters('header-height', 50);
        const isSticky = window.scrollY > headerHeight;

        header.classList.toggle('sticking', isSticky);
    };

    const throttledScrollHandler = util.throttle(handleScroll, 100);
    window.addEventListener('scroll', throttledScrollHandler);
}

const { register } = window.sitchco;
register(registerStickyHeader);
