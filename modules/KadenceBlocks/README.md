# KadenceBlocks Integration Module

Integration layer between Kadence Blocks plugin and theme.json-based themes. Kadence Blocks provides responsive controls (different values per breakpoint) that the core block editor lacks, but it completely ignores WordPress design systems. This module makes Kadence Blocks respect theme.json by providing CSS variable aliases, replacing presets with theme.json values, and ensuring editor/frontend parity.

**Before making changes to this module**, read [ADR.md](./ADR.md) to understand the architectural decisions and constraints.

## Requirements

- **Forked Kadence Blocks plugin**: This module depends on a fork of Kadence Blocks (`sitchco/kadence-blocks`) that adds editor-side filter hooks not present in upstream.
- **theme.json design tokens**: Spacing/font size presets and the `--wp--custom--*` variables referenced by this module must be defined (directly or via inheritance) in `theme.json`.
- **Module enabled**: Ensure the module is registered in `sitchco.config.php`.

## What This Module Does

- Replaces Kadence's spacing/font presets with theme.json values
- Aliases Kadence's `--global-*` CSS variables to WordPress `--wp--*` properties
- Injects a theme-controlled default gap for columns (via hidden `content-flow` token)
- Enables CSS variable output for padding (allows CSS overrides)
- Adds background detection class (`kt-column-has-bg`) for styling hooks
- Provides parallel JavaScript implementations for editor preview parity

## File Structure

The module is organized into three layers:

**PHP (KadenceBlocks.php)** - Filters that replace Kadence presets with theme.json values, inject defaults, and add styling hooks. This is where the Kadence-to-theme.json mapping happens.

**JavaScript (assets/scripts/editor-ui/)** - Editor-side implementations that mirror the PHP behavior. Each PHP filter has a corresponding JavaScript hook to maintain editor/frontend parity.

**CSS (assets/styles/)** - Split between frontend (`main/`) and editor (`admin-editor/`) because the HTML structures differ significantly. Variable aliases live in `_variables.css` files; block-specific rules in their own files.

The editor CSS files include inline documentation of the HTML structure they target, since this differs from frontend output and isn't obvious from inspection.

## Integration Points

### PHP Filters (KadenceBlocks.php)

| Filter | Purpose |
|--------|---------|
| `kadence_blocks_css_spacing_sizes` | Replace spacing presets with theme.json scale values |
| `kadence_blocks_css_gap_sizes` | Replace gap presets with theme.json scale values + add hidden `content-flow` token (maps to `--wp--custom--content-spacing`) |
| `kadence_blocks_css_font_sizes` | Replace font presets with theme.json values |
| `kadence_blocks_column_render_block_attributes` | Inject `content-flow` as default gap when none selected (works with `gap_sizes` filter above) |
| `kadence_blocks_measure_output_css_variables` | Enable CSS variable output for padding |
| `render_block_kadence/column` | Add `kt-column-has-bg` class |

### JavaScript Hooks (editor-ui/*.js)

| Hook | Purpose |
|------|---------|
| `kadence.block.column.defaultRowGapVariable` | Set default gap in editor (requires fork) |
| `kadence.blocks.measureOutputCssVariables` | Enable CSS variable output in editor |
| `kadence.rowlayout.defaultPadding` | Provide default padding for resize handles |

### CSS Variables

```css
/* Column/row padding (when CSS variable output enabled) */
--kb-padding-top
--kb-padding-right
--kb-padding-bottom
--kb-padding-left

/* Semantic spacing (defined in theme.json, used by this module) */
--wp--custom--content-spacing         /* Vertical rhythm between content */
--wp--custom--container-inset-x-sm    /* Screen edge padding (narrow contexts) */
--wp--custom--container-inset-x-lg    /* Content inset for backgrounds (wide contexts) */
```

The `--kb-padding-*` variables enable child elements to reference parent padding. Any child element with `.alignfull` class automatically applies negative margins based on `--kb-padding-left` and `--kb-padding-right`, achieving a full-bleed effect. This works with images, groups, or any block that supports alignment - giving editors versatile layout control without nesting additional containers.

## Child Theme Integration

The parent theme handles the Kadence Blocks integration. Child themes customize appearance through theme.json. No PHP filters or JavaScript hooks are required in child themes.

### How It Works

1. The parent theme's spacing scale is mapped to Kadence's preset controls
2. Semantic variables reference values from the spacing scale
3. The integration module ensures Kadence blocks use these values

### What Child Themes Define

**Spacing scale** (optional - inherit or override):

```json
{
  "settings": {
    "spacing": {
      "spacingSizes": [
        { "slug": "40", "size": "1rem", "name": "Small" },
        { "slug": "50", "size": "1.5rem", "name": "Medium" },
        { "slug": "60", "size": "2rem", "name": "Large" }
      ]
    }
  }
}
```

**Semantic variables** (assign scale values to purposes):

```json
{
  "settings": {
    "custom": {
      "contentSpacing": "var(--wp--preset--spacing--50)",
      "containerInsetXSm": "var(--wp--preset--spacing--50)",
      "containerInsetXLg": "var(--wp--preset--spacing--60)"
    }
  }
}
```

### What You Get

- Kadence spacing/font controls show your theme.json presets
- New columns have vertical content spacing by default
- Columns with backgrounds receive appropriate inset
- Row layouts respect WordPress alignment constraints

All defaults are overridable via editor controls. No magic spacing that editors cannot adjust.

## Fork Dependency

This module requires a forked version of Kadence Blocks (`sitchco/kadence-blocks`). The fork adds JavaScript filter hooks that don't exist in upstream. The most critical for editor/frontend parity is:

**Hook**: `kadence.block.column.defaultRowGapVariable`
**Purpose**: Allows setting default row gap in the editor preview
**Impact without fork**: Editor preview shows `row-gap: 0` for new columns while frontend shows the correct default

Other fork hooks used by this module include `kadence.blocks.measureOutputCssVariables` and `kadence.rowlayout.defaultPadding`.

Note: The PHP filter `kadence_blocks_column_render_block_attributes` exists in upstream Kadence (via the abstract block class's dynamic filter pattern). Only the JavaScript hooks require the fork.

### Fork Maintenance

See [Fork Maintenance](./ADR.md#fork-maintenance) in ADR.md for scope, commit references, and update process.

## Common Issues

- [Kadence controls show wrong preset names](#kadence-controls-show-wrong-preset-names)
- [Editor preview doesn't match frontend](#editor-preview-doesnt-match-frontend)
- [Columns have no vertical spacing](#columns-have-no-vertical-spacing)
- [Background padding not working](#background-padding-not-working)

### Kadence controls show wrong preset names

**Symptom**: Spacing dropdown shows "xs/sm/md/lg" instead of theme.json preset names.

#### Cause 1: Vite HMR race condition (local development)

During local development with `pnpm dev`, `Sitchco\Framework\ModuleAssets::inlineScriptData()` defers script data to the `admin_head` hook, but the editor UI JavaScript loads and executes during the earlier `enqueue_block_editor_assets` hook. The filters in `overrides.js` try to read `window.sitchco.themeSettings` before it exists, fall back to empty arrays, and Kadence uses its default presets.

In production builds, Vite bundles the inline data directly into the JavaScript file, so it's available when filters register.

**Impact**: If content is saved in this state, blocks are saved with Kadence's values (like `sm`) instead of theme.json references. This is difficult to detect because the visual editor looks normal—corrupted values only appear in code view.

**Workaround**: When editing content in the CMS, avoid editing while `pnpm dev` is running. Use a compiled build (`pnpm build`) so the filters are applied correctly.

**Detecting corrupted blocks**: Switch to Code editor mode (Options menu → Code editor, or `Shift+Option+Command+M` on macOS) and search for "padding", "margin", or "gap". If you see values like `"sm"` or `"md"`, the block was saved without filters applied.

**Fixing corrupted blocks**: Delete the incorrect values from the raw HTML, then reapply spacing using the editor controls. The correct theme.json references will be saved.

*This is a known bug with a fix scheduled for a future sprint. Until resolved, avoid editing content while `pnpm dev` is running.*

#### Cause 2: Module not running

If you're not running `pnpm dev` and still see Kadence defaults, the `kadence_blocks_css_spacing_sizes` filter likely isn't running. Verify the module is registered in `sitchco.config.php`.

### Editor preview doesn't match frontend

**Symptom**: Spacing looks different in the editor than on the published page.

**Likely causes**:
- Fork not applied after Kadence update (check for `kadence.block.column.defaultRowGapVariable` filter)
- JavaScript hook not firing (check browser console for errors)
- CSS specificity conflict in editor styles

### Columns have no vertical spacing

**Symptom**: Content in columns stacks with no gap between elements.

**Likely cause**: The default gap isn't being injected. Check that both filters are working:
1. `kadence_blocks_css_gap_sizes` must add `content-flow` to the gap sizes map
2. `kadence_blocks_column_render_block_attributes` must inject `content-flow` when `rowGapVariable` is empty

### Background padding not working

**Symptom**: Columns with backgrounds have no content inset.

**Likely cause**: The `kt-column-has-bg` class isn't being added. Check the `render_block_kadence/column` filter, or verify background detection logic matches how the background was applied.

## Related Documentation

- [ADR.md](./ADR.md) - Architectural decisions and rationale behind this module
- Parent theme `theme.json` - Spacing scale and semantic variable definitions
- Kadence Blocks documentation - Understanding block attributes and controls
