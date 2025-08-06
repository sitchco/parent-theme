function registerHasStickyHeaderActions() {
    window.sitchco.hooks.addAction(window.sitchco.constants.SCROLL, () => {
        const isSticky = window.scrollY > 10;
        document.body.classList.toggle('has-sticky-header', isSticky);
    });
}

window.sitchco.register(registerHasStickyHeaderActions);