<?php

namespace Sitchco\Parent\Modules\GravityForms;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;

class GravityForms extends Module
{
    public const HOOK_SUFFIX = 'gravity-forms';

    public function init()
    {
        $this->enqueueGlobalAssets(function (ModuleAssets $assets) {
            $assets->enqueueStyle(static::HOOK_SUFFIX, 'main.css');
        });

        $this->enqueueEditorUIAssets(function (ModuleAssets $assets) {
            $assets->enqueueScript('editor-ui', 'editor-ui.js', [
                'wp-blocks',
                'wp-components',
                'wp-element',
                'wp-hooks',
                'sitchco/extend-block',
            ]);
        }, 1);

        add_filter('gform_config_data_gform_admin_config', [$this, 'hideBlockStyleControls']);
        add_filter('gform_form_after_open', [$this, 'bridgeInlineStyles'], 1000, 2);
        add_filter('gform_get_form_confirmation_filter', [$this, 'bridgeInlineStyles'], 1000, 2);
        add_filter('gform_submit_button', [$this, 'replaceSubmitButton'], 10, 2);
    }

    /**
     * Hides Orbital style panels in the block editor by setting orbitalDefault to false.
     * The JS gate (Re = "orbital" === theme || orbitalDefault && "" === theme) evaluates
     * to false when the block's theme attribute is "" (inherit), hiding all style panels.
     */
    public function hideBlockStyleControls(array $config): array
    {
        if (isset($config['block_editor']['gravityforms/form']['data'])) {
            $config['block_editor']['gravityforms/form']['data']['orbitalDefault'] = false;
        }

        return $config;
    }

    /**
     * Wraps each --gf-* value in GF's inline <style> block with a var() reference
     * to the equivalent --wp--custom-- property, preserving the original as fallback.
     *
     * --gf-color-primary: #204ce5;
     * → --gf-color-primary: var(--wp--custom--gf--color-primary, #204ce5);
     *
     * Child themes override any GF variable via nested theme.json custom settings:
     *   { "custom": { "gf": { "colorPrimary": "var:preset|color|purple" } } }
     *   → generates --wp--custom--gf--color-primary, which the var() picks up.
     *
     * Icon properties (SVG data URIs) are skipped — they're large and not
     * practical to override via theme.json.
     */
    public function bridgeInlineStyles(string $markup): string
    {
        return preg_replace_callback(
            '/--(gf-(?!icon)[a-z0-9-]+):\s*([^;]+);/',
            fn($m) => "--{$m[1]}: var(--wp--custom--gf--" . substr($m[1], 3) . ", {$m[2]});",
            $markup,
        );
    }

    /**
     * Replaces GF's submit button markup with WP Button block markup so
     * the theme's .wp-block-button styles (font, shape, arrow, colors) apply.
     *
     * Preserves GF's functional attributes (id, onclick, data-submission-type,
     * tabindex) so form submission continues to work.
     *
     * <input type="submit" id="gform_submit_button_1" value="Submit" ... />
     * → <div class="wp-block-button">
     *     <button type="submit" class="wp-block-button__link wp-element-button" id="..." ...>Submit</button>
     *   </div>
     */
    public function replaceSubmitButton(string $button_html, array $form): string
    {
        // Extract button text from value="..." (input) or inner content (button)
        if (preg_match('/<input\b/i', $button_html)) {
            preg_match('/value=["\']([^"\']*)["\']/', $button_html, $m);
            $text = $m[1] ?? 'Submit';
        } else {
            // Strip SVG tags and get inner text content
            $text = strip_tags(preg_replace('/<svg\b[^>]*>.*?<\/svg>/si', '', $button_html));
            $text = trim($text) ?: 'Submit';
        }

        // Extract functional attributes
        $attrs = [];
        $attr_names = ['id', 'tabindex', 'data-submission-type', 'onclick'];
        foreach ($attr_names as $name) {
            if (preg_match('/' . preg_quote($name, '/') . '=["\']([^"\']*)["\']/', $button_html, $m)) {
                $attrs[] = sprintf('%s="%s"', $name, esc_attr($m[1]));
            }
        }
        $attrs_string = $attrs ? ' ' . implode(' ', $attrs) : '';

        // Build wrapper classes with filter for child theme customization
        $wrapper_classes = ['wp-block-button'];
        $wrapper_classes = apply_filters(static::hookName('submit-button-classes'), $wrapper_classes, $form);

        $text = esc_html($text);

        return sprintf(
            '<div class="%s"><button type="submit" class="wp-block-button__link wp-element-button gform-theme-no-framework"%s>%s</button></div>',
            esc_attr(implode(' ', $wrapper_classes)),
            $attrs_string,
            $text,
        );
    }
}
