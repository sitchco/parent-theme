import extendAnimation from './editor-ui/animation-controls.jsx';

/**
 * Phase 2 only: there is nothing to configure in phase 1.
 *
 * The block-to-animations map and every animation's controls are resolved in PHP and arrive as
 * inline script data, so this registers no filters of its own.
 *
 * Registering in editorReady is the library's contract, not a workaround for missing data:
 * extendBlock() reads its fields once, at call time, and a select whose options come from a JS
 * hook (`optionsFilter`, e.g. theme.color-options) reads a registry that themes fill during
 * editorInit. Those options are resolved per render, so they see whatever editorInit registered.
 *
 * The blob itself is available either way. inlineScriptData() prints at position 'before', so it
 * lands ahead of this script's own tag; and ModuleAssets forces isDevServer off under is_admin()
 * (ModuleAssets.php:29-32), so the block editor never takes the deferred admin_head path that
 * makes inline data arrive late on the front end.
 */
sitchco.editorReady(() => {
    extendAnimation(sitchco.extendBlock, sitchco.animations ?? {}, sitchco.hooks.applyFilters);
});
