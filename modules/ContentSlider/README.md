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
| Slides Per View (Desktop) | `perPage` | 3 |
| Slides Per View (Tablet) | `breakpoints.768.perPage` | 2 |
| Slides Per View (Mobile) | `breakpoints.480.perPage` | 1 |
| Vertical Alignment | CSS class | stretch |

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
        "fixedWidth": "360px",
        "gap": "1.5rem",
        "padding": { "left": 0, "right": "80px" },
        "breakpoints": {
            "768": {
                "fixedWidth": "280px",
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

- A variation setting `fixedWidth` doesn't touch `autoplay` — the editor still controls it
- A variation setting `perPage` **does** override the editor's Slides Per View setting
- Fields not covered by the variation retain their per-instance values

### CSS Custom Properties

The block sets `--slides-per-view-desktop`, `--slides-per-view-tablet`, and `--slides-per-view-mobile` as inline styles. These reflect the **final merged config**, so they stay in sync with what Splide actually uses even when a variation overrides `perPage`.

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

### JS Filter: `contentSlider.config`

Child theme JavaScript can modify the Splide config after all PHP-side merging is complete:

```js
window.sitchco.hooks.addFilter('contentSlider.config', 'my-theme', (config, element) => {
    // Final override point — runs on the frontend before Splide.mount()
    return config;
});
```
