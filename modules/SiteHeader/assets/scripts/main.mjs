const { addAction } = window.wp.hooks.createHooks();

export function registerHasStickyHeaderActions() {
    addAction('scroll', () => {
        const isSticky = window.scrollY > 10;
        document.body.classList.toggle('has-sticky-header', isSticky);
    });
}

const register = (cb) => addAction('initRegister', cb, 100);
register(registerHasStickyHeaderActions);