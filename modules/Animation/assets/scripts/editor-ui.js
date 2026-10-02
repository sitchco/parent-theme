import extendAnimation from './editor-ui/animation-controls.jsx';

/**
 * Phase 2 only: there is nothing to configure in phase 1.
 *
 * The block-to-animations map is resolved in PHP and arrives as inline script data, so this
 * registers no filters of its own — unlike the theme registries, whose options have to be
 * collected during editorInit before anything reads them.
 *
 * Registering in editorReady is the library's contract, not a workaround for missing data:
 * extendBlock() reads its fields once, at call time, so it has to run after editorInit has
 * filled the JS-side registries that fields read from. This control reads none of them today —
 * its options come from the inline blob below — but the per-animation option fields will.
 *
 * The blob itself is available either way. inlineScriptData() prints at position 'before', so it
 * lands ahead of this script's own tag; and ModuleAssets forces isDevServer off under is_admin()
 * (ModuleAssets.php:29-32), so the block editor never takes the deferred admin_head path that
 * makes inline data arrive late on the front end.
 */
sitchco.editorReady(() => {
    extendAnimation(sitchco.extendBlock, sitchco.animations ?? {});
});
