function registerOverlayHeader() {
    const header = document.querySelector('.has-site-header-overlay');

    if (!header) {
        return;
    }

    document.body.classList.add('has-overlay-header');
}

const { register } = window.sitchco;
register(registerOverlayHeader)