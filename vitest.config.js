import { defineConfig } from 'vitest/config';

/**
 * Specs live in tests/js/, deliberately not beside the sources they cover.
 *
 * @sitchco/project-scanner globs `modules/<Module>/assets/{scripts,styles}/*.{js,mjs,jsx,scss,css}`
 * for build entry points, so a `*.test.js` next to a module script would be compiled as its own
 * entry and shipped to dist/.
 *
 * The node environment is enough: everything covered here is a pure function in a plain .js
 * module, with no DOM, no React and no @wordpress imports. Anything needing a rendered control
 * belongs in a spec that asks for jsdom, not in a default that quietly slows these down.
 */
export default defineConfig({
    test: {
        environment: 'node',
        include: ['tests/js/**/*.test.js'],
    },
});
