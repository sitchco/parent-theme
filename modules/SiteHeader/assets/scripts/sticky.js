function registerStickyHeader() {
    const { hooks } = window.sitchco;
    const header = document.querySelector('.has-site-header-sticky');
    if (!header) {
        return;
    }

    // Consumes core header-height filter (backed by --dynamic__header-height).
    hooks.addAction('scroll', (event, position) => {
        const headerHeight = hooks.applyFilters('header-height', 0);
        header.classList.toggle('sticking', position.top > headerHeight);
    });
}

const { register } = window.sitchco;
register(registerStickyHeader);
