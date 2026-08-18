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
 *
 * Action: 'content-slider.init' - Reach the live Splide instance before it mounts
 * @param {Splide} splide - The constructed, not-yet-mounted instance
 * @param {HTMLElement} element - The slider element
 */

const { applyFilters, doAction } = window.sitchco.hooks;

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

        const splide = new Splide(element, config);

        // Prevent loop cloning when all items fit on screen.
        //
        // `overflow` is post-layout geometry — the slider's measured size against the
        // track's — so it holds under any sizing mode, including fixed-width sliders
        // where `perPage` no longer describes how many slides are visible. Splide
        // emits the initial state from Layout's mount, which runs before Clones, so a
        // listener registered here still decides whether clones are built at all.
        // Runtime option writes land on the base options (Media.set), so they survive
        // breakpoint changes rather than being reset by the next one.
        let overflowing;
        splide.on('overflow', (isOverflow) => {
            overflowing = isOverflow;
            splide.options = {
                type: isOverflow ? config.type : 'slide',
                clones: isOverflow ? undefined : 0,
            };
        });

        // The instance is otherwise unreachable once this function returns: child
        // themes need it to react to overflow themselves, and it has to be handed over
        // before mount() for a listener to catch the initial emit.
        element.splide = splide;
        doAction('content-slider.init', splide, element);

        splide.mount();

        // Splide's Layout applies the track's padding during its own mount and only
        // *then* starts listening for option changes, so anything a listener sets from
        // that initial `overflow` emit reaches the options but never the DOM. Options
        // consumed by a later-mounting component (type, clones) are unaffected; ones
        // Layout itself applies are stranded, and a slider with no breakpoints has
        // nothing that would ever emit `updated` to flush them. Re-emit once, now that
        // Layout is listening — the handlers are idempotent, so this settles rather
        // than compounds.
        if (overflowing !== undefined) {
            splide.emit('overflow', overflowing);
        }

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
