<?php

namespace Sitchco\Parent\Modules\GravityForms;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;
use Sitchco\Parent\Modules\ExtendBlock\ExtendBlockModule;
use WP_HTML_Tag_Processor;

class GravityForms extends Module
{
    public const HOOK_SUFFIX = 'gravity-forms';

    public const DEPENDENCIES = [ExtendBlockModule::class];

    private array $pendingButtonClasses = [];

    public function init(): void
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
                'sitchco/editor-ui-framework',
                'sitchco/extend-block',
            ]);
        });

        add_filter('gform_config_data_gform_admin_config', [$this, 'hideBlockStyleControls']);
        add_filter('gform_form_after_open', [$this, 'bridgeInlineStyles'], 1000, 2);
        add_filter('gform_get_form_confirmation_filter', [$this, 'bridgeInlineStyles'], 1000, 2);
        add_filter('gform_submit_button', [$this, 'replaceSubmitButton'], 10, 2);
        add_filter('gform_next_button', [$this, 'replacePaginationButton'], 10, 2);
        add_filter('gform_previous_button', [$this, 'replacePaginationButton'], 10, 2);
        add_filter('register_block_type_args', [$this, 'registerBlockAttributes'], 10, 2);
        add_filter('render_block_data', [$this, 'prepareButtonThemeClass']);
        add_filter(static::hookName('submit-button-classes'), [$this, 'applyButtonThemeClass']);
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
     * Preserves GF's functional attributes (id, onclick, data-*, tabindex)
     * so form submission continues to work.
     *
     * <input type="submit" id="gform_submit_button_1" value="Submit" ... />
     * → <div class="wp-block-button">
     *     <button type="submit" class="wp-block-button__link wp-element-button" id="..." ...>Submit</button>
     *   </div>
     */
    public function replaceSubmitButton(string $button_html, array $form): string
    {
        $p = new WP_HTML_Tag_Processor($button_html);

        // Extract button text from value="..." (input) or inner content (button)
        if (preg_match('/<input\b/i', $button_html)) {
            $p->next_tag('input');
            $text = $p->get_attribute('value') ?? 'Submit';
        } else {
            $text = html_entity_decode(
                strip_tags(preg_replace('/<svg\b[^>]*>.*?<\/svg>/si', '', $button_html)),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            );
            $text = trim($text) ?: 'Submit';
            $p->next_tag('button');
        }

        // Extract functional attributes
        $attrs = [];
        foreach (['id', 'tabindex', 'onclick'] as $name) {
            $value = $p->get_attribute($name);
            if ($value !== null) {
                $attrs[] = sprintf('%s="%s"', $name, esc_attr($value));
            }
        }
        foreach ($p->get_attribute_names_with_prefix('data-') as $name) {
            $value = $p->get_attribute($name);
            if ($value !== null) {
                $attrs[] = sprintf('%s="%s"', $name, esc_attr($value));
            }
        }
        $attrs_string = $attrs ? ' ' . implode(' ', $attrs) : '';

        // Build wrapper classes with filter for child theme customization
        $wrapper_classes = ['wp-block-button'];
        $wrapper_classes = apply_filters(static::hookName('submit-button-classes'), $wrapper_classes, $form);

        return sprintf(
            '<div class="%s"><button type="submit" class="wp-block-button__link wp-element-button gform-theme-no-framework"%s>%s</button></div>',
            esc_attr(implode(' ', $wrapper_classes)),
            $attrs_string,
            esc_html($text),
        );
    }

    /**
     * Registers custom attributes added by the JS extendBlock system so the
     * REST API block renderer accepts them without "Invalid parameter(s)" errors.
     */
    public function registerBlockAttributes(array $args, string $block_name): array
    {
        if ('gravityforms/form' !== $block_name) {
            return $args;
        }

        $args['attributes']['theme'] = [
            'type' => 'string',
            'default' => '',
        ];
        $args['attributes']['icon'] = [
            'type' => 'string',
            'default' => '',
        ];
        $args['attributes']['extendBlockClasses'] = [
            'type' => 'object',
            'default' => (object) [],
        ];

        return $args;
    }

    /**
     * Captures theme/icon classes from the block's attributes before render so
     * applyButtonThemeClass can merge them into the submit button wrapper.
     *
     * Registered once on render_block_data; applyButtonThemeClass is registered
     * once in init() and clears the property after each consumption, preventing
     * filter accumulation across multiple GF blocks on the same page.
     */
    public function prepareButtonThemeClass(array $block): array
    {
        if ('gravityforms/form' !== $block['blockName']) {
            return $block;
        }

        $attrs = $block['attrs'] ?? [];
        $classes = [];

        if (!empty($attrs['theme'])) {
            $classes[] = 'has-theme-' . sanitize_html_class($attrs['theme']);
        }

        if (!empty($attrs['icon'])) {
            $classes[] = 'has-icon';
            $classes[] = 'has-icon-' . sanitize_html_class($attrs['icon']);
        }

        $this->pendingButtonClasses = $classes;

        return $block;
    }

    /**
     * Merges pending theme/icon classes into the submit button wrapper class list,
     * then clears the property to prevent carry-over to subsequent GF blocks.
     */
    public function applyButtonThemeClass(array $existing): array
    {
        $classes = $this->pendingButtonClasses;
        $this->pendingButtonClasses = [];
        return array_merge($existing, $classes);
    }

    /**
     * Converts pagination <input> elements to <button> elements so
     * pseudo-elements and inner markup are available for styling.
     *
     * Preserves all original attributes and classes. Only fires on
     * <input> elements — if GF is configured to render link-type buttons
     * (already <button>), the markup passes through unchanged.
     */
    public function replacePaginationButton(string $button_html, array $form): string
    {
        if (!preg_match('/<input\b/i', $button_html)) {
            return $button_html;
        }

        $p = new WP_HTML_Tag_Processor($button_html);
        $p->next_tag('input');

        $text = $p->get_attribute('value') ?? '';

        // Extract all attributes except value and type
        $skip = ['value', 'type'];
        $attrs = [];
        foreach ($p->get_attribute_names_with_prefix('') as $name) {
            if (!in_array($name, $skip, true)) {
                $value = $p->get_attribute($name);
                if ($value !== null) {
                    $attrs[] = sprintf('%s="%s"', $name, esc_attr($value));
                }
            }
        }
        $attrs_string = $attrs ? ' ' . implode(' ', $attrs) : '';

        return sprintf(
            '<div class="gform_page_button_wrapper"><button type="button"%s>%s</button></div>',
            $attrs_string,
            esc_html($text),
        );
    }
}
