# KadenceImageModal

Adds an "Open in modal" toggle to the `kadence/image` block. When enabled, the image becomes a trigger that opens the full-size attachment in a lightbox-style `<dialog>` on the frontend.

## How it works

The feature is **server-side only**. There is no new JavaScript runtime module; the existing `UIModal` infrastructure (`sitchco-core/modules/UIModal`) handles all click/keyboard activation, focus, and dismissal.

### Pipeline

1. **Editor toggle** (`assets/scripts/editor-ui.js`) — Adds an `openInModal` boolean attribute to `kadence/image` via the `ExtendBlock` module. Saved post HTML is **never** modified; the toggle only persists as a block attribute in the post content.
2. **Server-side render filter** (`KadenceImageRenderer::render`) — Runs on `render_block_kadence/image` at priority 15.
3. **Modal registration** — On match, `UIModal::loadModal()` queues a footer `<dialog>` for emission via `wp_footer`. Modal `id` is `img-{attachment_id}` so blocks referencing the same attachment share a single dialog.
4. **Frontend activation** — `UIModal`'s `DOMContentLoaded` initializer auto-wires every `[data-target="#id"]` element as a trigger (adds `js-modal-trigger`, `aria-haspopup`, `aria-expanded`, and — for non-`<a>`/`<button>` elements — `role="button"` and `tabindex="0"`). A delegated click handler opens the dialog.

### Renderer behavior

When all of the following hold, the renderer mutates the block HTML; otherwise it returns the original content untouched:

- `openInModal` attribute is truthy.
- `id` attribute is a non-zero integer.
- The attachment's MIME type is not `image/svg+xml`.
- `wp_get_attachment_image_src($id, 'full')` resolves (returns a non-false value).

When the renderer runs, using `WP_HTML_Tag_Processor` it:

1. Adds `has-image-modal` to the root element (figure, or div when `align` is `left`/`right`/`center`).
2. Adds `data-target="#img-{attachment_id}"` to the **trigger element** (selection rules below).
3. When the resolved `alt` is empty and the trigger has no existing `aria-label`, sets `aria-label` to the dialog's heading.
4. Calls `UIModal::loadModal()` to queue the dialog content.

### Trigger element selection

The trigger is the **first** element matching this priority order (priorities encoded by `triggerQueries()` in `KadenceImageRenderer.php`):

1. `<a class="kb-advanced-image-link">` — Present when the block has Kadence's `link` set. Decorating the existing anchor avoids creating nested anchors and preserves the `href`/`target`/`rel` as a no-JS fallback. The modal wins over navigation because `UIModal`'s delegated click handler calls `e.preventDefault()`.
2. `.kb-is-ratio-image` wrapper — Present when Kadence's ratio-image option is enabled.
3. `.kb-image-has-overlay` wrapper — Present when Kadence's overlay option is enabled. Kadence renders `.kb-image-has-overlay::after` with `z-index: 9` over the `<img>`, so the overlay would swallow clicks if the bare `<img>` were the trigger. Decorating the wrapper neutralizes this structurally.
4. `<img class="kb-img">` — The bare image, used when none of the above wrappers are present.

The caption (`<figcaption>`) is always a sibling of the trigger inside `<figure>`, never inside it, so caption clicks (including caption hyperlinks) are unaffected. No `UIModal` click-handler patch is required.

### Accessible-name cascade

The dialog heading and (when needed) the trigger's synthesized `aria-label` are taken from the first non-empty value in this cascade:

1. `<img>` `alt` attribute
2. Attachment `post_title` (via `get_the_title($id)`)
3. Attachment filename — basename of the full-size URL, extension stripped, `-`/`_` replaced with spaces, sanitized
4. Literal: `"View full-size image"`

The cascade guarantees an accessible name in every case, so `aria-labelledby` and the trigger label are never empty.

When two blocks reference the same attachment but resolve different `alt` values, the **first-write-wins** dedup in `UIModal::loadModal()` means the registered dialog uses the first block's name. Subsequent blocks with empty `alt` adopt the registered heading as their trigger's `aria-label`. Blocks with their own non-empty `alt` keep it; the renderer does not override an explicit `alt`. If two blocks reuse the same attachment with conflicting explicit alts, their trigger labels will differ from each other and from the shared dialog heading — an authoring inconsistency, not a renderer bug.

### Modal type and sizing

The module registers a programmatic-only modal type via `UIModal::registerType('image')` (no label → not exposed in any editor UI). The dialog renders as `<dialog class="sitchco-modal sitchco-modal--image">` and its visual treatment lives in `assets/styles/main.css`:

- Dark backdrop (`rgb(0 0 0 / 0.85)`)
- Transparent `fit-content` container with no padding or border radius
- Image clamped to `max-width: calc(100dvw - 2 * var(--modal-box-gap))`, `max-height: calc(100dvh - 2 * var(--modal-box-gap))`, with `object-fit: contain`
- White close button at the top-right with a semi-transparent dark circle background
- `--modal-box-gap` is `2rem` by default, `1rem` below 576px

The selector `.sitchco-modal.sitchco-modal--image` is doubled to lift these overrides to specificity (0,2,0) so they win over child-theme rules that set the same `--modal-*` custom properties on a single-class selector.

### Modal `<img>`

The full-size source comes from `wp_get_attachment_image_src($id, 'full')` — **never** from the rendered figure's `src` or `srcset`. This is important because `CloudinaryKadenceModule` may swap the rendered `src`/`srcset` for CDN URLs at filter priority 10. The modal source must remain the original WP attachment, not a transformed candidate.

The `<img>` carries `loading="lazy"` and `decoding="async"` as best-effort hints so unopened modals avoid fetching the full-size source. `loading="lazy"` is not a hard guarantee; a strict zero-pre-open policy would require a `data-src` swap on open and conflict with the no-new-JS rule.

## Integration points

### `Theme` module exemption

`Theme::modalContentAttributes()` (`../Theme/Theme.php`) adds `is-layout-constrained has-global-padding` to `.sitchco-modal__content` for every modal type **except** types in its exemption list. The child theme's `contentSize` (typically `850px`) becomes a `max-width` cap on direct children of constrained content, which would clamp the modal image.

The `image` type must be present in the exemption list alongside `'video'`. Without it, the modal `<img>` is capped to the child-theme content width. The paired test in `../../tests/ThemeModuleTest.php` (`nonVideoTypeProvider`) asserts that `image` does **not** receive `is-layout-constrained` or `has-global-padding` — so a missed exemption fails CI immediately.

### Filter priority

The renderer hooks `render_block_kadence/image` at priority **15**, between:

- `CloudinaryKadenceModule` at priority 10 — swaps `src`/`srcset` for CDN URLs.
- `InlineSVGModule` at priority 20 — inlines `<img>` tags whose `src` ends in `.svg`.

Photo attachments flow through `InlineSVGModule` untouched (it only mutates SVG sources), so the `data-target` decoration survives.

Kadence prepends an inline `<style>` block before the saved HTML at render time. `WP_HTML_Tag_Processor::next_tag()` skips it automatically because `<style>` is a raw-text element.

### Module dependencies

Declared in `KadenceImageModal::DEPENDENCIES`:

- `UIModal` — modal infrastructure
- `KadenceBlocks` — required for the `kadence/image` block to exist

The editor toggle additionally depends on the `ExtendBlock` module's JS handle for the `wp_register_script` ordering.

## Scenarios

### Core

#### S1 — Toggle on, no link, no caption

A `kadence/image` block with a valid attachment, `openInModal: true`, no `link`, no caption.

- Saved post HTML is unchanged.
- On render, `has-image-modal` is added to the figure (or aligned div) and `data-target="#img-{id}"` is added to the trigger — wrapper (`.kb-is-ratio-image` / `.kb-image-has-overlay`) when present, else the bare `<img>`.
- A `<dialog id="img-{id}" class="sitchco-modal sitchco-modal--image">` is emitted in the footer containing `<img loading="lazy" src="…full-size…">`.
- `UIModal` initializer applies `js-modal-trigger`, `role="button"`, `tabindex="0"`, `aria-haspopup="dialog"`, `aria-expanded="false"` to the trigger.
- Click / tap / Enter / Space opens the dialog; focus moves to the container (autofocus).
- Image renders at native size up to viewport clamps with `object-fit: contain`. Close button at top-right; backdrop covers the viewport.
- Escape / backdrop click / close button dismisses; focus returns to the trigger.

#### S2 — Toggle on, `link` set

The block has Kadence's `link` set, so Kadence renders `<a class="kb-advanced-image-link" href="…">` wrapping only the image (not the caption).

- The renderer adds `data-target="#img-{id}"` to the existing Kadence anchor — not the `<img>`.
- Original `href`, `target`, `rel`, and any existing `aria-label` (e.g., from Kadence's `linkTitle`) are preserved.
- `UIModal` applies `js-modal-trigger`, `aria-haspopup`, `aria-expanded` (no `role="button"` — the anchor is natively interactive).
- Click on the anchor: `UIModal`'s `e.preventDefault()` suppresses navigation; the modal opens.
- With JavaScript disabled, the anchor falls back to native navigation.

**Must not** create a second wrapping `<a>`. Decoration is always added to the existing Kadence anchor.

#### S3 — Toggle on, caption present (text-only)

A non-link caption is present.

Caption text is passive — clicking does nothing. Only clicks on the trigger element (image or Kadence anchor) open the modal. Caption markup is not modified.

#### S4 — Toggle on, caption with link

A hyperlink inside the caption.

The caption link navigates natively. The modal opens only when the trigger element is activated. No interference. Structurally safe because the caption is outside the trigger element.

#### S5 — Toggle on, attachment unresolvable

`id` is `0`, the attachment has been deleted, or `wp_get_attachment_image_src($id, 'full')` returns false for any reason.

The renderer returns the original block HTML untouched. No class added, no `data-target` decoration, no modal queued, no JS errors.

#### S5b — Toggle on, Kadence overlay enabled

The block also has Kadence's overlay option enabled (`.kb-image-has-overlay::after` with `z-index: 9` over the `<img>`). No `link`.

The renderer applies `data-target` to the **wrapper element** (`.kb-is-ratio-image` / `.kb-image-has-overlay`), not the inner `<img>`. Clicks anywhere within the overlaid image area open the modal because the click target is the wrapper, regardless of whether the click falls on the overlay pseudo-element or the underlying `<img>`.

**Must not** decorate the bare `<img>` when a wrapper is present — the overlay pseudo-element would intercept clicks before `UIModal`'s `[data-target]` listener fires.

#### S6 — Decorative image (`alt=""`)

The `<img>` has `alt=""`.

The renderer still wraps when the toggle is on. The accessible name is built from the cascade (alt → title → filename → literal). The cascade guarantees a non-empty `aria-labelledby` and trigger `aria-label`.

**Test coverage note:** Cascade steps 1–3 are exercised by `KadenceImageModalTest`. Step 4 (the `"View full-size image"` literal) is code-inspection-only — `wp_get_attachment_image_src()` always returns a path with a basename, so engineering a fixture that bypasses step 3 would require reflection or invasive mocking. The four-line branch at `KadenceImageRenderer::resolveAccessibleName()` is verified by reading.

### Modal sizing

#### S7 — Small image (e.g., 600×400)

Container shrinks to `fit-content`; image renders at native size; dark backdrop fills the rest of the viewport. No white card, no internal padding around the image.

#### S8 — Very wide image (e.g., 3840×1200)

`max-width: calc(100dvw - 2 * gap)` clamps the image; height scales proportionally via `object-fit: contain`. No horizontal page or dialog scroll.

#### S9 — Very tall image (e.g., 1200×4000)

`max-height: calc(100dvh - 2 * gap)` clamps the image; width scales proportionally. Image fully visible in one viewport. The dialog has `overflow-y: auto` from `UIModal`'s base styles for safety but should not need to scroll.

**Must not** crop, downscale, or `srcset`-substitute the modal image.

#### S10 — Mobile portrait (e.g., 390×844)

Module CSS applies `--modal-box-gap: 1rem` below 576px. Image clamped to viewport minus 2rem; close button reachable.

#### S11 — Reduced-motion preference

When `prefers-reduced-motion: reduce` is set, the dialog opens/closes instantly. Handled by `UIModal`'s existing `transition-duration: 0s` rule. No new animation in this module.

#### S12 — Multiple image modals on one page

Many `kadence/image` blocks with `openInModal` enabled, including multiple blocks pointing to the same attachment (galleries, query loops).

The modal ID is `img-{attachment_id}`, so blocks pointing to the same attachment share a single footer `<dialog>` via `UIModal::loadModal()`'s ID dedup. First-write-wins is intentional — the modal content is the same full-size source for every instance. Distinct attachments produce distinct dialogs. Each dialog shares the `--image` type class but is instance-scoped by ID; there is no CSS bleed between instances. Full-size sources are not eagerly fetched (best-effort) because every modal `<img>` carries `loading="lazy"`.

Per-block `alt`/heading customization is not preserved across instances of the same attachment — the first instance's resolved name wins for the shared dialog. Accepted trade-off for the simpler ID scheme and reduced footer bloat. See "Accessible-name cascade" above for how the renderer keeps trigger labels aligned with the registered dialog heading in the empty-alt case.

### Round-trip

#### S13 — Toggle round-trip (off → on → off)

The editor enables `openInModal`, saves, then disables it and saves again.

All mutation is server-side. Saved post HTML is unchanged across both save events. Disabling the toggle silently removes all modal-related markup on the next render. No orphaned dialogs in the footer (the renderer simply does not call `loadModal()` when `openInModal` is false).

**Must not** bake any modal-related markup into saved HTML.

### Cross-module integration

#### S14 — `Theme` exemption

The new `image` modal type is added to `Theme::modalContentAttributes()`'s video-exemption check. The paired test in `ThemeModuleTest::nonVideoTypeProvider()` asserts the `image` type does not receive `is-layout-constrained` or `has-global-padding`. Without the exemption, the modal `<img>` is capped to the child theme's `contentSize` and the existing test fails — CI catches a missed exemption.

#### S15 — Module unloaded / type not registered

If `KadenceImageModal` is disabled or removed and legacy posts still carry `openInModal: true`: the render filter does not run, no `data-target` is added, and no modal is queued. The block renders as a vanilla Kadence image — graceful degradation.

If only the type registration is removed but the renderer still runs and queues a modal with `type: 'image'`: `UIModal::resolveType()` falls back to `box`. The image displays inside a constrained `box` modal at the child theme's `contentSize` — visually degraded but not broken.

#### S16 — InlineSVG and Cloudinary co-existence

A `kadence/image` block whose `<img>` is processed by `CloudinaryKadenceModule` (priority 10), then by this renderer (priority 15), then evaluated by `InlineSVGModule` (priority 20).

- Cloudinary swaps `src`/`srcset` for CDN URLs at priority 10. The full-size modal source is fetched independently via `wp_get_attachment_image_src($id, 'full')`, so Cloudinary's transformation does not affect the modal.
- This renderer adds `data-target` at priority 15.
- `InlineSVGModule` at priority 20 only fires for SVG sources. For photo attachments, the `<img>` and its `data-target` flow through unchanged.

**Must not** rely on the rendered `<img>`'s `src` for the modal source — it may be a CDN URL or a `srcset` candidate, neither guaranteed to be the original full-size attachment.

### No-op scenarios

#### N1 — SVG attachment

The renderer skips modal mode when the attachment MIME type is `image/svg+xml`. "Open in modal" is meaningless for vector graphics (resolution-independent; modal would show the same scaled rendering), and skipping also sidesteps any priority-20 attribute concern with `InlineSVGModule`. The block renders normally; `InlineSVGModule` may inline the SVG per its own rules. No modal queued.

#### N2 — Toggle off

`openInModal` is false (or never set). Renderer is a pass-through: no class added, no `data-target` added, no modal queued, no footer markup, no JS execution.

#### N3 — Caption-only click

User clicks caption text (non-link) on a block with `openInModal` enabled. No reaction. Caption is structurally outside the trigger element.

#### N4 — `openInModal: true` but `id: 0`

Placeholder or external image with no attachment ID. No modal queued, no decoration added (per S5).

## Constraints

These rules apply across all scenarios.

1. **Do not** wrap or replace the Kadence root element (figure or aligned div). Kadence's inline CSS targets that root by class and `> figure`; wrapping breaks layout.
2. **Do not** bake modal-related markup into saved HTML. All frontend mutation lives in the PHP renderer; the toggle round-trips cleanly when later turned off.
3. **Do not** create nested anchors. When `link` is set, decorate the existing Kadence `<a>`; never add a second `<a>` inside it.
4. **Do not** hijack a caption's own links. Caption links retain their native click behavior. (Structurally satisfied because the caption is outside the trigger element.)
5. **Do not** crop, downscale, or `srcset`-substitute the modal image. The modal `<img>` source comes from `wp_get_attachment_image_src($id, 'full')`, not the rendered figure's `src`/`srcset`.
6. **Do not** introduce JavaScript errors under any toggle combination (with/without `link`, with/without caption, with placeholder/external images, with SVG attachments).
7. **Do not** render a modal trigger when no full-size attachment is resolvable.
8. **Do not** produce an unnamed/inaccessible dialog. The accessible-name cascade guarantees a non-empty `aria-labelledby` heading and trigger `aria-label`.
9. **Do not** ship a new frontend JavaScript module for this feature. `UIModal`'s existing `[data-target]` auto-wiring covers everything needed.
10. **Do not** ship without the `Theme::modalContentAttributes()` exemption and the paired `ThemeModuleTest::nonVideoTypeProvider()` update. The image modal cannot defeat the `is-layout-constrained` content-width cap without this.
11. **Best-effort lazy loading** is acceptable for the full-size modal source. Modal `<img>` carries `loading="lazy"` so unopened modals avoid the network cost in supporting browsers; this is a hint, not a guarantee. A strict zero-pre-open-fetch policy would require a `data-src` swap on open and conflict with constraint #9.
12. **Do not** enable modal mode for SVG attachments (N1).
