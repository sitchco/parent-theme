function registerSiteHeaderClass() {
    const headerElement = document.querySelector('body > header');
    if (headerElement) {
        const firstChildOfHeader = headerElement.firstElementChild;
        if (firstChildOfHeader && !firstChildOfHeader.classList.contains('site-header')) {
            firstChildOfHeader.classList.add('site-header');
        }
    }
}

const { register } = window.sitchco;
register(registerSiteHeaderClass);