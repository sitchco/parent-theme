# GravityForms Module

Bridges Gravity Forms' Orbital theme system with the WordPress/theme design system so forms inherit the site's typography, colors, and button styles without per-form configuration.

## What It Does

### 1. CSS Variable Bridge (`bridgeInlineStyles`)

GF's Orbital theme injects an inline `<style>` block per form with hardcoded `--gf-*` values. This method wraps each value with a `var()` reference to a `--wp--custom--` property, preserving the original as fallback:

```
--gf-color-primary: #204ce5;
  becomes
--gf-color-primary: var(--wp--custom--gf--color-primary, #204ce5);
```

Child themes override any GF variable via `theme.json` custom properties:

```json
{
  "settings": {
    "custom": {
      "gf": {
        "colorPrimary": "var:preset|color|purple",
        "radius": "25px"
      }
    }
  }
}
```

WordPress generates `--wp--custom--gf--color-primary` from `gf.colorPrimary`, which the `var()` bridge picks up automatically.

Runs on both `gform_form_after_open` (front-end render) and `gform_get_form_confirmation_filter` (confirmation message).

### 2. Submit Button Replacement (`replaceSubmitButton`)

GF renders submit buttons as `<input type="submit">` (which can't have `::after` pseudo-elements) or `<button>` with GF-specific classes. This method replaces the markup with standard WP Button block structure:

```html
<!-- Before -->
<input type="submit" id="gform_submit_button_1" class="gform_button" value="Submit" />

<!-- After -->
<div class="wp-block-button">
  <button type="submit"
          class="wp-block-button__link wp-element-button gform-theme-no-framework"
          id="gform_submit_button_1">Submit</button>
</div>
```

- Extracts the button text from `value` (input) or inner content (button)
- Preserves functional attributes: `id`, `tabindex`, `data-submission-type`, `onclick`
- Adds `gform-theme-no-framework` to opt the button out of GF's framework styles
- The `.wp-block-button` / `.wp-block-button__link` classes let the theme's button design system (font, pill shape, animated arrow, color modifiers) apply automatically

### 3. Block Editor Panel Hiding (`hideBlockStyleControls`)

Sets `orbitalDefault` to `false` in GF's block editor config, which hides Orbital's style control panels (input styles, label styles, button colors, etc.) from the editor sidebar. This prevents editors from creating per-form style overrides that conflict with the theme.

The "Form Styles" panel isn't gated by `orbitalDefault`, so it's hidden via CSS in `main.css`.

### 4. Button Font Normalization (`main.css`)

GF's `.gform_button` and `.gform-theme-button` elements (used for next/previous in multi-page forms) get `font-family: inherit` so they use the theme's font instead of browser form defaults.

## Child Theme Customization

### Submit Button Classes

Add wrapper classes to the submit button's `<div>` via the `sitchco/gravity-forms/submit-button-classes` filter:

```php
add_filter('sitchco/gravity-forms/submit-button-classes', function (array $classes, array $form) {
    $classes[] = 'is-style-default';   // activates theme's default button style (e.g., purple arrow)
    $classes[] = 'has-theme-purple';   // adds a color modifier
    return $classes;
}, 10, 2);
```

The `$form` array is passed so you can conditionally style buttons per form.

### GF Variable Overrides via theme.json

Any `--gf-*` variable can be overridden through nested `custom` properties in `theme.json`. The naming convention converts `--gf-color-primary` to `gf.colorPrimary` (strip the `gf-` prefix, camelCase the rest, nest under `gf`):

| GF Variable | theme.json Path |
|---|---|
| `--gf-color-primary` | `custom.gf.colorPrimary` |
| `--gf-radius` | `custom.gf.radius` |
| `--gf-ctrl-border-color` | `custom.gf.ctrlBorderColor` |
| `--gf-font-size-secondary` | `custom.gf.fontSizeSecondary` |

### Global Style Defaults

Set default Orbital styles for all forms via the `gform_default_styles` filter:

```php
add_filter('gform_default_styles', function () {
    return [
        'theme'                        => 'orbital',
        'inputBorderRadius'            => 10,
        'inputBorderColor'             => '#767676',
        'buttonPrimaryBackgroundColor' => '#8C5FCD',
        'buttonPrimaryColor'           => '#fff',
    ];
});
```

## Files

| File | Purpose |
|---|---|
| `GravityForms.php` | Module class with all filters |
| `assets/styles/main.css` | Button font normalization + editor panel hiding |
| `assets/scripts/editor-ui.js` | Reserved for future block editor JS (currently empty) |
| `ADR.md` | Deep reference on GF's Orbital internals, CSS architecture, and theme layers API |