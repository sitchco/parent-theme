/* global Splide */
/**
 * Content Slider - Frontend Initialization
 *
 * Initializes Splide carousels on elements with data-sc-slider attribute.
 * Depends on kad-splide (Kadence's Splide library) which provides global Splide.
 *
 * Filter: 'content-slider.config' - Modify Splide config before initialization
 * @param {Object} config - The Splide configuration object
 * @param {HTMLElement} element - The slider element
 * @returns {Object} Modified config
 */

const { applyFilters } = window.sitchco.hooks;

function initSlider(element) {
    if (element.classList.contains('is-initialized')) {
        return;
    }

    try {
        const dataConfig = JSON.parse(element.dataset.scSlider || '{}');

        // Base defaults, then spread dataConfig to allow any Splide option
        let config = {
            type: 'slide',
            autoplay: false,
            interval: 5000,
            speed: 400,
            arrows: true,
            pagination: true,
            gap: '1rem',
            perPage: 1,
            perMove: 1,
            keyboard: true,
            breakpoints: {},
            pauseOnHover: true,
            pauseOnFocus: true,
            resetProgress: false,
            ...dataConfig,
        };

        // Allow child themes to filter the config
        config = applyFilters('content-slider.config', config, element);

        // Prevent loop cloning when all items fit on screen
        const slideCount = element.querySelectorAll('.splide__slide').length;
        const maxPerPage = Math.max(
            config.perPage,
            ...Object.values(config.breakpoints || {}).map((bp) => bp.perPage || 0)
        );
        if (slideCount <= maxPerPage) {
            config.type = 'slide';
        }

        const splide = new Splide(element, config);

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
