export function registerStickyHeader() {
    const { util } = window.sitchco;

    const handleScroll = () => {
        const isSticky = window.scrollY > 10;
        document.body.classList.toggle('site-header--is-sticky', isSticky);
    };

    const throttledScrollHandler = util.throttle(handleScroll, 100);
    window.addEventListener('scroll', throttledScrollHandler);
    handleScroll();
}