# ContentSlider Module

A generic slider container block using Kadence Section blocks as slides, powered by the Splide library.

## Dependencies

- Kadence Blocks plugin (provides slide blocks and Splide library)
- ACF Pro (settings fields)

## Architecture

```
ContentSlider/
├── ContentSlider.php          # Module class (registers Kadence Splide dependency, variation scanner)
├── acf-json/                  # ACF field definitions
│   └── group_68f7cf60b9668.json
├── blocks/content-slider/
│   ├── block.json             # Block registration and asset declarations
│   ├── block.php              # Context preparation (ACF → Splide config → variation merge)
│   ├── block.twig             # Template (editor preview vs frontend carousel)
│   ├── script.js              # Frontend Splide initialization
│   ├── style.css              # Frontend styles
│   └── editor-style.css       # Editor-only styles (stacked slide cards)
└── variations/                # (child themes) JSON variation files
```

## Per-Instance Settings (ACF Fields)

These fields are available on every Content Slider block instance in the editor:

| Field | Splide Option | Default |
|-------|---------------|---------|
| Autoplay | `autoplay` | Off |
| Autoplay Speed | `interval` | 5000ms |
| Arrows | `arrows` | On |
| Dots | `pagination` | On |
| Slide Sizing | see below | Slides per View |
| Slides Per View (Wide Desktop) | `perPage` | 4 |
| Slides Per View (Desktop) | `breakpoints.1440.perPage` | 3 |
| Slides Per View (Tablet) | `breakpoints.1024.perPage` | 2 |
| Slides Per View (Mobile) | `breakpoints.600.perPage` | 1 |
| Minimum Slide Width | `fixedWidth` (floor) | 280px |
| Preferred Slide Width | `fixedWidth` (fluid term) | 25% |
| Maximum Slide Width | `fixedWidth` (ceiling) | 420px |
| Vertical Alignment | CSS class | stretch |

### Slide Sizing

The two modes are mutually exclusive, and the config says which one is in force by
which keys it carries.

**Slides per View** divides the visible track by a count. Slide width is therefore a
function of the viewport, and it changes discontinuously: crossing a breakpoint changes
the divisor, so every slide re-shapes across one pixel of viewport.

Splide reads breakpoints as max-widths and the narrower match wins, so the base value is
the desktop tier and each key is the *top* of the tier below it:

| Field | Applies at | Config |
|-------|-----------|--------|
| Wide Desktop | 1441px and up | `perPage` |
| Desktop | 1025–1440px | `breakpoints.1440.perPage` |
| Tablet | 601–1024px | `breakpoints.1024.perPage` |
| Mobile | 600px and under | `breakpoints.600.perPage` |

The edges come from measuring the track, not from device names. A count divides the track,
so a slide's width is whatever the viewport leaves over, and a band's width ratio *is* the
slide's size range. Holding the 360:511 posters these sliders carry to a usable 280–420px
puts one-across at 345–515, two at 685–1015, three at 1030–1530 and four from 1370 up —
which is where 600/1024/1440 sit. Any key equal to the base is omitted; it could never
change the outcome.

`per_view_wide` postdates the other three fields, and ACF never backfills a definition into
stored meta, so sliders saved under the old three-tier config report it as absent. Absent
falls back to the desktop count, which keeps 1441px and up rendering exactly as before.
Every other tier kept its slug and took a wider band, so a viewport can only land on the
same count or the one below it — never a higher one, given counts that don't rise as the
screen narrows.

**Fixed Slide Width** emits a single fluid `fixedWidth` anchor and no counts at all:

```css
clamp(min(280px, 85%), 25%, 420px)
```

- The middle term is a percentage of the **track**, not `vw`, so it respects the
  container's max-width, the page gutters, and any peek padding. Splide's own CSS
  pins `.splide__slide { flex-shrink: 0 }`, so the percentage holds.
- The `min()` floor caps the minimum at 85% of the track. A minimum wide enough to
  overflow a phone is the one authoring mistake this mode can make, and this is what
  makes it unauthorable.
- Nothing about the ratio reaches Splide. Every internal measurement comes from
  `getBoundingClientRect()`, so `fixedWidth` is only an anchor length — slide *shape*
  stays a CSS concern, owned by the cards.

Fixed-width mode omits `perPage` rather than merely ignoring it. Splide keeps reading
that key for the pagination dot count (`ceil(slides / perPage)`, taken from the literal
config and never measured), the `isEnough()` autoplay gate, Controller index
arithmetic, and the lazy-load radius. Omitting it lets `script.js`'s `perPage: 1`
default stand, which is the value those calculations want when every slide moves
individually.

Blocks saved before this field existed have no `sizing_mode` value at all — ACF never
backfills field definitions into stored meta — so they resolve to Slides per View and
render exactly as before. The fallback is intentionally **not** filterable: a child
theme flipping it would re-size legacy blocks using width values they never saved.

## Variations System

Child themes define named slider configurations (e.g., "Production Cards", "Testimonial Carousel") as JSON files. These appear as selectable styles in the block editor's Styles panel and merge Splide config overrides at render time.

### Creating a Variation

Create a JSON file in the child theme at `modules/ContentSlider/variations/`:

```
modules/ContentSlider/variations/production-cards.json
```

```json
{
    "title": "Production Cards",
    "splide": {
        "gap": "1.5rem",
        "padding": { "left": 0, "right": "80px" },
        "breakpoints": {
            "768": {
                "padding": { "left": 0, "right": "40px" }
            }
        }
    }
}
```

| Key | Required | Description |
|-----|----------|-------------|
| `title` | Yes | Display name in the editor Styles panel |
| `splide` | Yes | Splide config overrides, deep-merged into the base config |

The slug is derived from the filename: `production-cards.json` becomes `is-style-production-cards`.

### How Variations Interact with Per-Instance Fields

Variations are applied **after** the base config is built from ACF fields, using `array_replace_recursive`. This means:

- A variation setting `gap` doesn't touch `autoplay` — the editor still controls it
- Fields not covered by the variation retain their per-instance values

**Variations cannot set sizing options.** `perPage`, `fixedWidth`, `fixedHeight`,
`heightRatio`, and `autoWidth` are stripped before the merge — at the top level and
inside `breakpoints` — and the drop is logged. Block fields own sizing; variations own
everything else (gap, padding, focus/trim, navigation presentation).

This is enforced rather than merely documented because the merge is
`array_replace_recursive`, which **adds without removing**: a variation setting
`fixedWidth` would leave the editor's `perPage` and every breakpoint count in place,
producing a config that claims both modes at once — Splide would take slide width from
one and its pagination dot count from the other.

### CSS Custom Properties

These are **editor-only**: `block.twig` emits the wrapper attributes on the preview
branch alone, where the block renders as a wrapping grid rather than a carousel.

| Mode | Properties |
|------|------------|
| Slides per View | `--slides-per-view-desktop`, `--slides-per-view-tablet`, `--slides-per-view-mobile` |
| Fixed Slide Width | `--slide-width-anchor` (the whole `clamp()` expression) |

Each mode publishes only its own, so a stale count can never be rendered as a column
count nothing on the frontend controls. Values come from the **final merged config**,
after any variation overrides.

`editor-style.css` switches at `max-width: 1440px`, `1024px` and `600px`, the same tiers
Splide is given, so the count preview and the frontend agree. Keep them in step if either
moves. Fixed-width mode has no counts and previews from the anchor itself.

## Extension Points

### PHP Filter: `sitchco/content-slider/variations`

The variations filter is the underlying mechanism for the JSON discovery system. Hook it directly for programmatic variations:

```php
add_filter('sitchco/content-slider/variations', function ($variations) {
    $variations['custom-layout'] = [
        'splide' => ['type' => 'fade', 'perPage' => 1],
    ];
    return $variations;
});
```

When using the filter directly, you also need to register the block style for the editor picker:

```php
register_block_style('sitchco/content-slider', [
    'name'  => 'custom-layout',
    'label' => 'Custom Layout',
]);
```

### JS Filter: `content-slider.config`

Child theme JavaScript can modify the Splide config after all PHP-side merging is complete:

```js
window.sitchco.hooks.addFilter('content-slider.config', (config, element) => {
    // Final override point — runs on the frontend before the instance is constructed
    return config;
});
```

The wrapper signature is `addFilter(hookName, callback, priority = 10, subNamespace = '')`
— it supplies the namespace itself. Passing one explicitly (the WordPress core argument
order) puts a string where the callback belongs, and `@wordpress/hooks` logs
`The hook callback must be a function.` and returns **without registering**.

### JS Action: `content-slider.init`

Fires after the Splide instance is constructed and before `mount()`, which is the only
window in which a listener can catch Splide's initial `overflow` emit:

```js
window.sitchco.hooks.addAction('content-slider.init', (splide, element) => {
    splide.on('overflow', (isOverflow) => {
        splide.options = { padding: isOverflow ? { left: 0, right: '15%' } : 0 };
    });
});
```

Use `overflow` rather than comparing the slide count to `perPage`. It is post-layout
geometry — measured slider size against measured track size — so it means the same
thing under both sizing modes, and Splide recomputes it on mount, resize, load, and
breakpoint change. The count comparison reports an assumption, and under fixed-width
sizing `perPage` no longer describes how many slides are visible at all.

Three properties worth knowing when driving options from it:

- Runtime option writes land on the **base** options (`Media.set` assigns to the
  prototype), so they survive the next breakpoint change instead of being reset by it.
- Splide measures overflow against the **padded** track. Apply peek padding from the
  event rather than seeding it into the config, or the peek becomes part of the
  evidence for whether there is anything to peek at.
- Splide's Layout applies track padding during its own mount and starts listening for
  option changes only afterwards, so an option Layout owns (padding) set from the
  initial emit would otherwise never reach the DOM. `script.js` re-emits `overflow`
  once after `mount()` to flush it — which is why handlers here must be idempotent.

The instance is also parked on the element as `element.splide` for debugging.

`script.js` uses this same event internally to keep a non-overflowing slider from
cloning: `type` falls back to `slide` and `clones` to `0` until the track actually
overflows.
