import domReady from '@wordpress/dom-ready';

function addSiteHeaderClass() {
    setTimeout(function() {
        const firstElement = document.querySelector('.is-root-container > div');
        if (firstElement && !firstElement.classList.contains('site-header')) {
            firstElement.classList.add('site-header');
        }
    }, 100);
}

domReady(addSiteHeaderClass);