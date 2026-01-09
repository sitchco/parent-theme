# KadenceBlocks Integration Module

This module is an integration layer between Kadence Blocks plugin and our theme's design system. It exists because Kadence Blocks is tightly coupled to Kadence Theme and makes assumptions that conflict with WordPress theme.json conventions.

## Philosophy

**theme.json is the source of truth.** Kadence Blocks has its own spacing presets, font sizes, and layout assumptions. This module redirects those to use theme.json values instead, giving us a single place to define our design tokens.

**Explicit over automatic.** Early iterations tried to inject smart defaults and automatic spacing. This led to specificity conflicts and unpredictable behavior. The current approach: strip Kadence's assumptions to zero, let content editors set values explicitly in the CMS, and provide good defaults only where Kadence would otherwise output broken CSS.

## Problems Solved

### 1. Kadence ignores theme.json presets

**Problem:** Kadence has hardcoded spacing presets (sm/md/lg) and font sizes that don't match theme.json values.

**Solution:** PHP filters (`overrideSpacingSizes`, `overrideGapSizes`, `overrideFontSizes`) replace Kadence's presets with theme.json presets. Editor JS (`overrides.js`) does the same for the block editor UI.

### 2. Columns have no default content spacing

**Problem:** Kadence Theme uses bottom margin for vertical content spacing. Our design system uses top margin. Kadence Blocks without Kadence Theme has neither assumption—it just outputs whatever gap value is set (or nothing). Trying to reconcile this with CSS overrides across responsive breakpoints was brittle and created specificity conflicts.

**Solution:** Set default gap at the source rather than fight with CSS:
- `KadenceBlocks.php` — registers `content-flow` token, injects as default during render via `kadence_blocks_column_render_block_attributes` filter
- `assets/scripts/editor-ui/column-gap-defaults.js` — mirrors default in editor preview via `kadence.block.column.defaultRowGapVariable` filter (requires fork)

The CSS variable mappings live in `variables.css`, and zero-margin rules (to let gap control spacing) are in `column.css`.

**Dependency:** This requires a fork of Kadence Blocks. The fork adds a `kadence.block.column.defaultRowGapVariable` filter to `src/blocks/column/edit.js`. Without this, the editor preview doesn't match the frontend.

### 3. Kadence expects Kadence Theme variables

**Problem:** Kadence Blocks references `--global-*` CSS variables that only exist in Kadence Theme.

**Solution:** `variables.css` aliases these to our theme.json custom properties:
```css
--global-content-width: var(--wp--style--global--content-size);
--global-row-gutter-md: var(--wp--custom--grid-gutter-spacing);
```

### 4. Row Layout ignores WordPress alignment

**Problem:** Kadence Row Layout outputs alignment classes but doesn't constrain width, causing layout inconsistencies with native WordPress blocks.

**Solution:** `row-layout.css` applies WordPress alignment constraints:
- Default: `max-width: var(--wp--style--global--content-size)`
- `.alignwide`: `max-width: var(--wp--style--global--wide-size)`
- `.alignfull`: full width with root padding

### 5. Column padding can't be overridden without knowing preset values

**Problem:** Kadence outputs padding directly as `padding-top: var(--wp--preset--spacing--70)`. To override this in CSS, you need to know which preset was chosen—there's no intermediate variable to target.

**Solution:** Enable CSS variable output for padding via `kadence_blocks_measure_output_css_variables` filter. This changes output to:
```css
--kb-padding-top: var(--wp--preset--spacing--70);
padding-top: var(--kb-padding-top);
```

Now CSS can override `--kb-padding-top` without knowing the original preset. The editor-side equivalent is in `measure-css-variables.js`.

## File Structure

```
KadenceBlocks/
├── KadenceBlocks.php          # Main module: PHP filters for presets, gap injection
├── README.md                  # This file
└── assets/
    ├── scripts/
    │   ├── editor-ui.js       # Entry point for editor JS
    │   └── editor-ui/
    │       ├── column-gap-defaults.js     # Default gap for editor preview
    │       ├── kadence-column-background.jsx  # Background detection component
    │       ├── measure-css-variables.js   # Enable CSS variable output for padding
    │       └── overrides.js               # Replace Kadence presets with theme.json
    └── styles/
        ├── main.css           # Frontend entry point
        ├── main/
        │   ├── variables.css  # Kadence Theme variable aliases + gap token mappings
        │   ├── row-layout.css # Row alignment and width constraints
        │   └── column.css     # Column-specific fixes + zero-margin rules for gap
        ├── admin-editor.css   # Editor preview entry point
        └── admin-editor/
            ├── ui-fixes.css   # Fixes for Kadence editor UI controls
            ├── column.css     # Editor-specific column styles
            └── row-layout.css # Editor-specific row layout styles
```

## Dependencies

### Kadence Blocks Fork

This module requires modifications to the Kadence Blocks plugin. The fork adds filter hooks that don't exist in upstream Kadence.

**Modified file:** `wp-content/plugins/kadence-blocks/src/blocks/column/edit.js`

**Change:** Added `kadence.block.column.defaultRowGapVariable` filter at line ~992.

When updating Kadence Blocks, this change must be reapplied or the editor preview will show `row-gap: 0` for new columns.

## Known Issues / Open Questions

1. **Column overflow: hidden** — Applied globally to fix border-radius clipping. May cause issues with sticky elements or dropdowns that need to overflow. See `column.css`.

2. **Row background padding** — Rows with backgrounds (`kt-row-has-bg`) may need different padding treatment. Currently zeroing `--global-kb-row-default-top/bottom` for rows without backgrounds.

3. **Responsive gap inheritance** — Gap injection sets desktop value only `['content-flow', '', '']`. Tablet/mobile inherit from desktop, which is usually correct but may need refinement.

## History

This module was created to encapsulate scattered Kadence modifications from both parent and child themes into a single, maintainable location. Early approaches tried to be too clever with automatic spacing injection, leading to specificity conflicts. The current approach favors explicit editor control with sensible defaults only where Kadence would otherwise produce broken output.
