import { registerStickyHeader } from './has-sticky-header.js';

document.addEventListener('sitchco/core/init', () => {
    const { register } = window.sitchco;
    register(registerStickyHeader);
});