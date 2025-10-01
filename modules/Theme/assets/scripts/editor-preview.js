let viteClientScripts = [];

function cloneViteClientsIntoIframe(iframe) {
    const doc = iframe.contentDocument || iframe.contentWindow?.document;
    if (!doc) {
        return;
    }

    const existing = doc.querySelector('[data-injected-vite-client]');
    if (existing) {
        return;
    } // Already injected

    viteClientScripts.forEach((script, index) => {
        const clone = doc.createElement('script');
        clone.type = 'module';
        clone.src = script.src;
        clone.setAttribute('data-injected-vite-client', `true-${index}`);
        doc.head.appendChild(clone);
    });
}

// Observe when Gutenberg adds/replaces the preview iframe
function observeBlockPreviewIframes() {
    const container = document.querySelector('.editor-visual-editor');
    // Find all vite clients in the main editor page
    viteClientScripts = Array.from(document.querySelectorAll('script[type="module"][src*="@vite/client"]'));

    if (!(container && viteClientScripts.length)) {
        return;
    }

    const observer = new MutationObserver(
        sitchco.util.debounce(() => {
            const iframe = document.querySelector('iframe[name=editor-canvas]');
            if (!iframe || iframe.dataset.viteInjected) {
                return;
            }

            iframe.dataset.viteInjected = 'true';
            iframe.addEventListener('load', () => cloneViteClientsIntoIframe(iframe));
        }, 250)
    );

    observer.observe(container, {
        childList: true,
        subtree: true,
    });
}

window.addEventListener('load', observeBlockPreviewIframes);
