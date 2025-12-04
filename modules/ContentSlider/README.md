# ContentSlider Module

A generic slider container block using Kadence Section blocks as slides, powered by the Splide library.

## Current Status

### Working
- **Editor view**: Slides display as stacked cards with visual labels for easy editing
- **Frontend**: Functional Splide carousel with responsive breakpoints
- **Basic settings**: Autoplay, arrows, dots, slides per view (desktop/tablet/mobile)
- **Kadence integration**: Uses `kadence/column` as slide containers and leverages Kadence's bundled Splide library

### Dependencies
- Kadence Blocks plugin (provides slide blocks and Splide library)
- ACF Pro (settings fields)

## Architecture

```
ContentSlider/
├── ContentSlider.php          # Module class (registers Kadence Splide dependency)
├── acf-json/                  # ACF field definitions
│   └── group_68f7cf60b9668.json
└── blocks/content-slider/
    ├── block.json             # Block registration and asset declarations
    ├── block.php              # Context preparation (ACF → Splide config)
    ├── block.twig             # Template (editor preview vs frontend carousel)
    ├── script.js              # Frontend Splide initialization
    ├── style.css              # Frontend styles
    └── editor-style.css       # Editor-only styles (stacked slide cards)
```

## TODO

### Child Theme Override System
Need a mechanism for child themes to set site-wide slider defaults without exposing them at the block level. Considerations:
- [ ] Define which settings should be "global" vs "per-block"
- [ ] Determine override mechanism (filter? theme.json? config file?)
- [ ] Allow child theme to lock certain options (hide from block UI)

### Additional Splide Options
Current ACF fields only expose a subset of Splide options. Missing:
- [ ] Slider type (`slide`, `loop`, `fade`)
- [ ] Transition speed
- [ ] Pause on hover/focus (currently hardcoded to `true`)
- [ ] Rewind behavior
- [ ] Drag/swipe toggle
- [ ] Custom gap value (currently uses CSS variable `--wp--custom--carousel-gap`)

### Configuration Mapping
- [ ] Review `block.php` config mapping—ensure ACF field names align with intended Splide options
- [ ] The `dots` ACF field maps to Splide's `pagination` option (naming mismatch)

### Accessibility
- [ ] Make `ariaLabel` configurable or smarter (currently hardcoded to "Content slider")
- [ ] Review keyboard navigation settings

### Styling
- [ ] Arrow/dot customization (size, color, position)
- [ ] Consider whether base styles should live in module CSS or be fully delegable to child theme
