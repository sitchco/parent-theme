/* global Splide */
/**
 * Content Slider - Frontend Initialization
 *
 * Initializes Splide carousels on elements with data-sc-slider attribute.
 * Depends on kad-splide (Kadence's Splide library) which provides global Splide.
 */

function initSlider(element) {
    if (element.classList.contains('is-initialized')) {
        return;
    }

    try {
        const config = JSON.parse(element.dataset.scSlider || '{}');

        const splide = new Splide(element, {
            type: config.type || 'slide',
            autoplay: config.autoplay || false,
            interval: config.interval || 5000,
            speed: config.speed || 400,
            arrows: config.arrows ?? true,
            pagination: config.pagination ?? true,
            gap: config.gap || '1rem',
            perPage: config.perPage || 1,
            perMove: 1,
            keyboard: config.keyboard ?? true,
            breakpoints: config.breakpoints || {},
            pauseOnHover: true,
            pauseOnFocus: true,
            resetProgress: false,
        });

        splide.mount();
        element.classList.add('is-initialized');
    } catch (error) {
        console.error('Failed to initialize content slider:', error);
    }
}

function initAllSliders() {
    document.querySelectorAll('[data-sc-slider]').forEach(initSlider);
}

const { register } = window.sitchco;
register(initAllSliders);
