# ADR: Kadence Blocks Integration Module

**Status**: Accepted  
**Date**: 2026-01-11  
**Deciders**: Jeremy Strom  

## Summary

This module provides an integration layer between Kadence Blocks plugin and WordPress theme.json-based themes. It exists because Kadence Blocks is architecturally coupled to Kadence Theme and makes assumptions that conflict with standard WordPress theme development patterns.

## Context

Kadence Blocks is a powerful page builder, but it was designed to work with Kadence Theme. When used with other themes, several problems emerge:

1. **Hardcoded presets**: Kadence has its own spacing scale (xs/sm/md/lg/xl/xxl) and font sizes that ignore theme.json values. Editors see Kadence's presets instead of the theme's design tokens.

2. **Missing CSS variables**: Kadence Blocks references `--global-*` CSS variables that only exist in Kadence Theme. Without these, layouts break or use fallback values that don't match the design system.

3. **No default content spacing**: Without Kadence Theme, columns have no vertical content spacing. The plugin outputs `row-gap: 0` unless explicitly set, requiring editors to manually configure every column.

4. **Inline values prevent overrides**: Kadence outputs spacing as direct values (e.g., `padding-top: 24px`). To override this in CSS, you need to know the exact value - there's no intermediate variable to target.

5. **Editor/frontend mismatch**: PHP renders the frontend, JavaScript renders the editor preview. Without parallel implementations, editors see different behavior than visitors.

These issues were previously addressed with scattered fixes across the Theme module. As fixes accumulated, maintenance became difficult and the reasoning behind each fix was lost.

## Decision

Create a dedicated `KadenceBlocks` module in the parent theme that:

1. **Makes theme.json the single source of truth** - PHP filters replace Kadence's preset maps with theme.json values. JavaScript hooks mirror this in the editor UI.

2. **Provides CSS variable aliases** - Maps Kadence's expected `--global-*` variables to WordPress `--wp--*` custom properties, allowing Kadence Blocks to function without Kadence Theme.

3. **Replaces arbitrary defaults with theme-controlled defaults** - Kadence's hardcoded defaults (like `sm` padding or `25px` fallbacks) are removed and replaced with semantic tokens from theme.json. Content should look good out of the box - but "good" is defined by the theme, not by Kadence's assumptions.

4. **Enables CSS variable indirection for padding** - Uses plugin filters to output intermediate CSS variables (`--kb-padding-top`) that can be targeted in CSS without knowing the original preset value.

5. **Maintains editor/frontend parity** - Every PHP behavior has a corresponding JavaScript implementation so editors see accurate previews.

This module depends on a forked version of Kadence Blocks ([sitchco/kadence-blocks](https://github.com/sitchco/kadence-blocks)) that adds filter hooks not present in upstream. Key fork changes used by this module:

- `kadence.block.column.defaultRowGapVariable` - JS filter for column row gap default
- `kadence.blocks.measureOutputCssVariables` - JS filter mirroring the PHP measure filter
- `kadence.rowlayout.defaultPadding` - JS filter for row layout resize handle defaults

The fork contains additional modifications for other parts of the platform not covered here. See "Fork Maintenance" section below for details.

### Philosophy: Theme-Controlled Defaults, Editor-Overridable

Early iterations attempted to strip all defaults and require explicit editor input for every spacing decision. This proved unwieldy - editors were forced to manually solve complex responsive layout problems using block controls, but those controls didn't work consistently due to existing defaults and "magic spacing" baked into the core Kadence Blocks plugin. The combination of zeroed theme defaults and unpredictable plugin defaults created an unworkable editing experience.

The current approach: solve the underlying problems with stacked spacing and responsive behavior first, then provide sensible defaults that flow from theme.json. Content editors get good results without manual configuration, but when they do make adjustments, they see the theme's design tokens rather than Kadence's arbitrary scale.

**Critical constraint**: Any default set in code must be overridable by the corresponding control in the CMS. There is no "magic spacing" that editors cannot remove or adjust. This preserves the flexibility to create any layout the design requires.

**How overridability is verified**:

| Default Applied by Module | Editor Override Path |
|-----------------|-----------------|
| Column row gap default (`content-flow`) | Column > Row Gap dropdown |
| Column/row padding (with background) | Padding controls per block |
| Full-bleed negative margins | Turn off "Align Full" or set explicit margin |
| Content margin resets | Set margin on the individual block |

When editors reset a control (clearing their custom value), the theme default is restored - not removed. This applies consistently across all defaults: row gap reverts to `content-flow`, padding reverts to theme inset values.

**Known violation addressed**: Kadence's top-level Row content padding (`--global-content-edge-padding`) was too opinionated. Rather than providing an override path, we removed it at the source (zeroed in CSS, commented in fork) - see "Deliberately removed" in section 3 below.

**Future considerations**: Additional edge cases may emerge as the platform matures. The principle guides evaluation: if an editor can't override something via CMS controls, the implementation should be reconsidered.

## Key Architectural Decisions

### 1. Module extraction over inline fixes

**Choice**: Create a dedicated module rather than keeping fixes in the Theme module.

**Rationale**: Kadence-specific code was scattered across Theme.php, editor scripts, and stylesheets. Consolidating into one module provides a single location for all Kadence integration, clear documentation of integration decisions, and the ability to enable/disable Kadence support atomically.

### 2. Filter-based preset replacement

**Choice**: Use `kadence_blocks_css_spacing_sizes`, `kadence_blocks_css_gap_sizes`, and `kadence_blocks_css_font_sizes` filters to completely replace Kadence's preset maps.

**Rationale**: Kadence Blocks completely ignores WordPress/Gutenberg design systems. Its spacing scale (xs/sm/md/lg) and font sizes are hardcoded and have no awareness of theme.json. The platform was built on Gutenberg's theme.json-based design system - we adopted Kadence primarily for its responsive controls (setting different values per breakpoint), not its design tokens. Full replacement forces Kadence to respect the existing design system rather than maintaining two parallel systems.

**Behavior**: When theme.json spacing changes, Kadence blocks update automatically. This is expected behavior - design system changes should propagate to all blocks that reference those tokens.

### 3. CSS variable aliases

**Choice**: Define CSS aliases that map Kadence's `--global-*` variables to WordPress `--wp--*` custom properties.

**Problem**: Kadence Blocks references CSS variables like `--global-kb-spacing-sm` and `--global-content-width` that are normally provided by Kadence Theme. Without these variables, layouts break or fall back to Kadence's hardcoded pixel values (e.g., `1rem`, `2rem`), which don't match the design system.

**Solution**: The module defines aliases in `assets/styles/main/_variables.css` and `assets/styles/admin-editor/_variables.css`.

**Why collapse the scale to semantic tokens**: Investigation revealed that Kadence wasn't using their spacing scale (xxs through 5xl) as a true scale - they arbitrarily assigned those values to different use cases throughout their blocks. Rather than mapping Kadence's scale 1:1 to our scale, the aliases map to semantic tokens (`--wp--custom--content-spacing`, `--wp--custom--grid-gutter-spacing`, etc.) based on how Kadence actually uses them.

**How this was discovered**: During early development, we tried to accommodate Kadence's default padding behavior (SM value for top/bottom, null for left/right). We eventually removed those defaults from the forked source, but the aliases remain as a safety net. If those defaults resurface in a plugin update, they'll resolve to sensible theme values rather than arbitrary pixel values.

**Current aliases**:
- Layout: `--global-content-width` → `--wp--style--global--content-size`
- Row gutters: `--global-row-gutter-*` → `--wp--custom--grid-gutter-spacing`
- Content gaps: `--global-kb-gap-*` → `--wp--custom--content-spacing`
- Spacing scale: `--global-kb-spacing-*` → mapped to semantic tokens based on usage

**Investigated and not aliased**: Three variables were flagged during documentation validation but determined not to need aliases after investigation:
- `--global-content-wide-width` - Editor-only (`rowlayout/editor.scss`); frontend uses WordPress native `--wp--style--global--wide-size`
- `--global-row-edge-sm` - Editor-only grid visualization fallback (`gridvisualizer.js`)
- `--global-row-edge-theme` - References `--global-content-edge-padding` which is zeroed out; row padding uses semantic tokens instead

**Deliberately removed - `--global-content-edge-padding`**: This variable is zeroed out in the module's CSS and commented out in several places in the forked plugin. Kadence uses it for default row padding, but it created forced override scenarios - the value was too opinionated and required convoluted CSS to undo when not desired. Rather than aliasing it to a theme value (which would perpetuate the problem), we removed it at the source. If this causes issues after a plugin update, the solution is to re-apply the fork changes, not to add an alias.

### 4. CSS variable indirection for padding

**Choice**: Enable `kadence_blocks_measure_output_css_variables` filter for column and row padding.

**Rationale**: Kadence outputs `padding-top: var(--wp--preset--spacing--70)`. With variable indirection, output becomes `--kb-padding-top: var(--wp--preset--spacing--70); padding-top: var(--kb-padding-top)`.

**Benefit 1 - Inheritance**: Child elements can reference parent padding values. Any child element with `.alignfull` class automatically applies negative margins based on `--kb-padding-left` and `--kb-padding-right`, achieving a full-bleed effect. This works with images, groups, or any block that supports alignment - giving editors versatile layout control without nesting additional containers.

**Benefit 2 - Simpler child theme CSS** (theoretical): Without variable indirection, child theme CSS faces a specificity dilemma: rules must be specific enough to override Kadence's inline output, but weak enough to still be overridable by CMS editor selections. This leads to convoluted selectors with `:where()` pseudo-selectors and fragile specificity management.

With variable indirection, child themes can target `--kb-padding-top` directly. There's no specificity war because you're setting a variable, not competing with inline declarations. CMS editor changes still work because they set the variable's value - the child theme's override of the variable itself doesn't interfere.

*Note: This benefit is aspirational. Real-world validation will occur during integration with project sites.*

**Tradeoff**: Slightly more verbose CSS output. Worth it for inheritance; override flexibility benefits will be validated during project integration.

### 5. Hidden default gap token (`content-flow`)

**Choice**: Add a `content-flow` token to the gap sizes map and inject it as the default when no gap is selected, but don't expose it as a selectable option in the editor.

**Problem**: Kadence columns need a default row gap value. Without one, columns render with `row-gap: 0`, requiring editors to manually set spacing for every column. But which scale value should be the default? That's a theme decision - the module shouldn't hardcode `spacing-20` or any specific preset.

**Why hide it instead of showing "Default" in the dropdown?**: Exposing `content-flow` would duplicate the spacing scale - since `--wp--custom--content-spacing` itself references a scale value, showing both would be redundant. The semantic token should only apply automatically to new blocks or when an editor clicks the reset button.

**Solution**:
1. Add `content-flow` to `gap_sizes` so Kadence can resolve it to `var(--wp--custom--content-spacing)`
2. Inject `content-flow` as the default via `kadence_blocks_column_render_block_attributes` when `rowGapVariable` is empty (this filter exists in upstream Kadence via the abstract block class)
3. Use `kadence.block.column.defaultRowGapVariable` JS hook for editor parity (requires fork)
4. Don't add it to the editor's dropdown options - the `spacingOverride` function replaces the entire dropdown with theme.json spacing, so `content-flow` is never shown as a selectable option

This "hidden default" pattern lets the theme control what the default content spacing is (via theme.json's `contentSpacing` custom property) without exposing the indirection to editors. When an editor explicitly selects a scale value, it replaces the `content-flow` default. When they click reset, the attribute clears and `content-flow` applies again.

### 6. Background detection class

**Choice**: Add `kt-column-has-bg` class to columns with backgrounds via `render_block` filter.

**Rationale**: Columns with backgrounds need different styling (content inset, visual edge treatment). Kadence provides `kt-row-has-bg` for row layouts but has no equivalent for columns - likely because the complexity of background detection across all column attributes was deemed not worth tackling. This module fills that gap by detecting backgrounds in PHP and adding a class as a stable CSS hook.

**Detection logic**: Scans block attributes for keys containing `'background'` or `'bgColor'`, and checks `style.color.background` for inline styles. This covers color, gradient, and image backgrounds. Edge cases may emerge as more background configurations are tested.

**Usage**: The class enables CSS to apply content inset padding and full-bleed alignment handling only when backgrounds are present. See `assets/styles/main/column.css` for the styling rules.

### 7. Editor/frontend parity implementation

**Choice**: Implement every rendering-related PHP filter as a corresponding JavaScript hook in the editor.

**Rationale**: Kadence Blocks has completely separate rendering paths - PHP for the frontend, JavaScript for the editor preview. Without parallel implementations, editors see different behavior than visitors. This undermines trust in the editing experience and makes content decisions difficult.

**Mapping**:

| PHP Filter | JS Counterpart(s) |
|------------|-------------------|
| `render_block_kadence/column` (bg class) | `editor.BlockListBlock` HOC |
| `kadence_blocks_column_render_block_attributes` (default gap) | `kadence.block.column.defaultRowGapVariable` |
| `kadence_blocks_css_spacing_sizes` | 6 hooks in overrides.js (spacingSizesMap, gutter/gap options) |
| `kadence_blocks_css_gap_sizes` | Same hooks + `previewGutterSize` hooks |
| `kadence_blocks_css_font_sizes` | `fontSizesMap` hook |
| `kadence_blocks_measure_output_css_variables` | `kadence.blocks.measureOutputCssVariables` |
| `option_kadence_blocks_config_blocks` | *No counterpart* (config filter, not rendering) |

The JS side has more hooks than PHP (13 vs 7) because editor UI also needs to populate dropdown options, not just render output. Editor-only hooks like `kadence.rowlayout.defaultPadding` have no PHP counterpart because they serve UI purposes (resize handles) that don't exist on the frontend.

**Row layout resize handles**: The `kadence.rowlayout.defaultPadding` hook provides the default value shown on padding resize handles. The module implementation returns 0 for rows without backgrounds (no default padding) and measures `--wp--custom--row-inset-y` for rows with backgrounds. Additionally, drag-to-resize is disabled via CSS (`pointer-events: none` on `.kt-padding-resize-box`) to prevent editors from setting arbitrary pixel values - forcing use of the design system's spacing scale.

**Testing approach**: Currently relies on visual inspection during development. A systematic testing approach would strengthen confidence but hasn't been implemented yet. Parity issues are expected to be discovered as the system is used on real projects.

**Background detection alignment**: The background detection for `kt-column-has-bg` uses identical explicit attribute checks in both PHP (`hasColumnBackground()`) and JS (`hasColumnBackground()`). Both implementations check:
1. Inline style background (`style.color.background`)
2. Solid background color (`background` attribute)
3. Gradient background (`gradient` attribute, excluding 'none')
4. Background image array (`backgroundImg` with nested `bgImg` or `url`)

## Consequences

### What this enables

- **Consistent design tokens**: Editors see the same spacing/font options in Kadence blocks as in native WordPress blocks
- **Theme-level control**: Changing a spacing value in theme.json updates both native and Kadence blocks
- **CSS overridability**: Intermediate CSS variables allow targeted overrides without specificity wars
- **Good defaults**: New columns and rows have sensible spacing without manual configuration
- **Maintainable integration**: All Kadence-specific code lives in one documented location

### What this requires

- **Parent theme with theme.json values**: This module assumes usage with the sitchco-parent-theme (or a child of it) which defines the spacing and font size scales. If theme.json doesn't define these values, the preset dropdowns will be empty - there is no fallback to Kadence defaults.
- **Forked Kadence Blocks**: The editor preview parity depends on custom JavaScript filter hooks. Plugin updates require verifying the fork still applies.
- **Parallel implementations**: PHP and JavaScript must stay in sync. Changes to one often require changes to the other.
- **CSS variable definitions**: The theme must define the `--wp--custom--*` variables that the module references (content-spacing, page-gutter, etc.)

### What to watch for

- **Plugin updates**: Kadence Blocks updates may change filter signatures or rendering behavior. Test thoroughly after updates.
- **New block types**: This module specifically handles row-layout and column blocks. New Kadence blocks may need similar integration.
- **Responsive edge cases**: Gap injection currently sets desktop value only; tablet/mobile inherit. Some layouts may need explicit responsive handling.
- **Vite HMR and block editing**: During local development with `pnpm dev`, a race condition causes editor UI filters to load after Kadence initializes. `Sitchco\Framework\ModuleAssets::inlineScriptData()` defers to `admin_head`, but JavaScript executes during `enqueue_block_editor_assets`. Filters read undefined theme settings and fall back to empty arrays, causing Kadence defaults to appear. This is local dev only—staging and production use compiled builds. See README "Common Issues" section for detection, impact, and workaround.

## Fork Maintenance

The module depends on a forked Kadence Blocks plugin at [sitchco/kadence-blocks](https://github.com/sitchco/kadence-blocks). This section documents the fork's purpose and maintenance approach.

### Why a fork?

Upstream Kadence Blocks lacks JavaScript filter hooks needed for editor/frontend parity. Early attempts to report bugs received initial responses but communication stalled. The customizations are sitchco-specific anyway—they solve our integration problems but aren't relevant to Kadence's general user base.

### Fork scope

The fork adds multiple filter hooks following a consistent pattern: replacing hardcoded defaults with filterable, themeable values through `applyFilters`. Not all fork changes relate to this module—some modifications address other platform needs.

**Changes used by this module**:
- `kadence.block.column.defaultRowGapVariable` (commit `891764fb3`)
- `kadence.blocks.measureOutputCssVariables` (commit `a728e9d4d`)
- `kadence.rowlayout.defaultPadding` (commit `4bf487f7f`)
- `getMeasureStyles()` helper for JS parity (commit `a728e9d4d`)

**Other fork changes** (not documented here):
- Column visibility settings
- Spacing key fallback
- Default padding removal

### Maintenance approach

The fork is maintained indefinitely via back-merging:

1. Merge upstream `release` branch into our fork
2. Resolve conflicts (typically in files we've modified)
3. Run the build
4. Commit to `sitchco/kadence-blocks`
5. Update `composer.lock` in the project

The maintenance burden has been manageable. We don't intend to maintain full parity with upstream—we use what works and back-merge as needed.

### Version tracking

- **Current fork version**: 3.5.30
- **Composer reference**: `kadencewp/kadence-blocks: dev-release` via VCS repository
