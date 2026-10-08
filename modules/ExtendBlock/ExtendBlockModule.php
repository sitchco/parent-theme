<?php

namespace Sitchco\Parent\Modules\ExtendBlock;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;
use Sitchco\Modules\UIFramework\UIFramework;
use Sitchco\Utils\Logger;

class ExtendBlockModule extends Module
{
    public const HOOK_SUFFIX = 'extend-block';

    /**
     * The attribute names the wrapper-props filter writes: `data-*` and `aria-*`, nothing else.
     * The same allowlist as ALLOWED_NAME in assets/scripts/includes/utils/attributes.js, so the
     * server-rendered front end can never emit what the editor canvas refuses.
     */
    public const ATTRIBUTE_NAME_PATTERN = '/^(data|aria)-/';

    /** The style names the wrapper-props filter writes: CSS custom properties only. */
    public const STYLE_NAME_PATTERN = '/^--[A-Za-z0-9_-]+$/D';

    /**
     * Characters a style value may not contain. Any of them would let a value end its own
     * declaration and start another (`red; background: url(…)`), or break out of the attribute's
     * context. Mirrored by UNSAFE_VALUE in assets/scripts/includes/utils/styles.js.
     */
    public const UNSAFE_STYLE_VALUE_PATTERN = '/[;{}\\\\<>]/';

    /** Names already warned about this request, so a bad contributor logs once, not per block. */
    private array $warned = [];

    public function init(): void
    {
        $this->enqueueEditorUIAssets(function (ModuleAssets $assets) {
            $assets->enqueueScript(static::hookName(), 'extend-block.js', [
                'wp-hooks',
                'wp-compose',
                'wp-block-editor',
                'wp-components',
                'wp-data',
                'wp-element',
                UIFramework::hookName('editor'),
            ]);
        }, 1);

        // Inject extendBlockClasses into dynamic block output
        add_filter('render_block', [$this, 'injectExtendBlockClasses'], 10, 2);
        // Server-rendered wrapper attributes and custom properties, for every block
        add_filter('render_block', [$this, 'injectWrapperProps'], 10, 2);
    }

    /**
     * Injects extendBlockClasses attribute value into the rendered block HTML.
     *
     * For dynamic blocks (like Kadence blocks), the JavaScript class filters don't
     * affect the frontend output. This filter reads the extendBlockClasses attribute
     * (which is synced by JavaScript) and injects those classes into the block wrapper.
     *
     * The extendBlockClasses attribute is an object keyed by namespace, allowing
     * multiple extensions to contribute classes without overwriting each other.
     */
    public function injectExtendBlockClasses(string $block_content, array $block): string
    {
        // Skip if no extendBlockClasses attribute
        if (empty($block['attrs']['extendBlockClasses'])) {
            return $block_content;
        }

        $extendBlockClasses = $block['attrs']['extendBlockClasses'];

        // Handle both object format (new) and string format (legacy)
        if (is_array($extendBlockClasses)) {
            // Allow modules to exclude specific namespaces for a given block
            $extendBlockClasses = apply_filters(
                static::hookName('inject-classes'),
                $extendBlockClasses,
                $block['blockName'],
            );
            // Combine all class strings from all namespaces
            $classes = implode(' ', array_filter(array_map('strval', array_values($extendBlockClasses))));
        } else {
            // Legacy string format
            $classes = $extendBlockClasses;
        }

        $classes = preg_split('/\s+/', trim((string) $classes), -1, PREG_SPLIT_NO_EMPTY);
        if (!$classes) {
            return $block_content;
        }

        $processor = new \WP_HTML_Tag_Processor($block_content);
        if (!static::seekWrapper($processor)) {
            return $block_content;
        }

        // The processor escapes the class attribute it writes, so nothing is pre-escaped here:
        // esc_attr() first would escape twice.
        foreach ($classes as $class) {
            $processor->add_class($class);
        }

        return $processor->get_updated_html();
    }

    /**
     * Writes server-rendered `data-*` / `aria-*` attributes and CSS custom properties onto a
     * block's wrapper element.
     *
     * Runs for every block, static and dynamic. A module contributes through the
     * `wrapper-props` filter:
     *
     *     add_filter(ExtendBlockModule::hookName('wrapper-props'), function (array $props, array $block) {
     *         $props['attributes']['data-density'] = 'compact';
     *         $props['style']['--density-gap'] = '0.5rem';
     *         return $props;
     *     }, 10, 2);
     *
     * This is the front-end half of an extension registered with `saveOutput: false`, which
     * produces the same output in the editor canvas and never touches a block's saved markup, so
     * it carries no block-validation risk.
     *
     * - Attribute names outside `data-*` / `aria-*` are dropped, as in the editor.
     * - Style names must be custom properties (`--*`). Values are strings or numbers; one
     *   containing `;`, `{`, `}`, `\`, `<` or `>` is dropped.
     * - `null` and `''` mean "unset" and are dropped, so a contributor can write its keys
     *   unconditionally.
     * - Style properties are appended to the wrapper's existing `style`, so on a name the block
     *   already declares, ours comes last and wins.
     *
     * Each dropped name is logged once per request. When nothing survives, the content is
     * returned untouched, byte for byte.
     */
    public function injectWrapperProps(string $block_content, array $block): string
    {
        if ($block_content === '' || empty($block['blockName'])) {
            return $block_content;
        }

        $props = apply_filters(
            static::hookName('wrapper-props'),
            [
                'attributes' => [],
                'style' => [],
            ],
            $block,
        );
        $attributes = $this->allowedAttributes($props['attributes'] ?? []);
        $style = $this->allowedStyle($props['style'] ?? []);
        if (!$attributes && !$style) {
            return $block_content;
        }

        $processor = new \WP_HTML_Tag_Processor($block_content);
        if (!static::seekWrapper($processor)) {
            return $block_content;
        }

        foreach ($attributes as $name => $value) {
            $processor->set_attribute($name, $value);
        }
        if ($style) {
            $processor->set_attribute('style', static::mergeStyle($processor->get_attribute('style'), $style));
        }

        return $processor->get_updated_html();
    }

    /**
     * Moves the processor onto the block's wrapper: the first tag, provided nothing but
     * whitespace and comments comes before it. Content that opens with text has no wrapper to
     * write to.
     */
    public static function seekWrapper(\WP_HTML_Tag_Processor $processor): bool
    {
        while ($processor->next_token()) {
            $type = $processor->get_token_type();
            if ($type === '#tag') {
                return !$processor->is_tag_closer();
            }
            if ($type === '#comment' || $type === '#funky-comment') {
                continue;
            }
            if ($type === '#text' && trim($processor->get_modifiable_text()) === '') {
                continue;
            }

            return false;
        }

        return false;
    }

    /**
     * An existing style attribute with custom properties appended, one `name:value` per
     * declaration.
     *
     * @param string|true|null $existing What get_attribute('style') returned
     * @param array<string, string> $style
     */
    private static function mergeStyle(string|bool|null $existing, array $style): string
    {
        // Trailing semicolons and whitespace in any order (`color: red ; `), so ours join cleanly.
        $existing = is_string($existing) ? trim(rtrim($existing, "; \t\n\r")) : '';
        $declarations = array_map(fn($name, $value) => "{$name}:{$value}", array_keys($style), $style);

        return implode(';', array_filter([$existing, ...$declarations], fn($part) => $part !== '')) . ';';
    }

    /**
     * @return array<string, string>
     */
    private function allowedAttributes(mixed $attributes): array
    {
        $allowed = [];

        foreach (is_array($attributes) ? $attributes : [] as $name => $value) {
            $value = static::scalarValue($value);
            if ($value === null) {
                continue;
            }
            if (!preg_match(static::ATTRIBUTE_NAME_PATTERN, (string) $name)) {
                $this->warnDropped(
                    "attribute:{$name}",
                    "Dropped the '{$name}' attribute: extensions can only emit data-* and aria-* attributes.",
                );
                continue;
            }

            $allowed[$name] = $value;
        }

        return $allowed;
    }

    /**
     * @return array<string, string>
     */
    private function allowedStyle(mixed $style): array
    {
        $allowed = [];

        foreach (is_array($style) ? $style : [] as $name => $value) {
            $value = is_bool($value) ? null : static::scalarValue($value);
            if ($value === null) {
                continue;
            }
            if (!preg_match(static::STYLE_NAME_PATTERN, (string) $name)) {
                $this->warnDropped(
                    "style:{$name}",
                    "Dropped the '{$name}' style: extensions can only emit CSS custom properties (--*).",
                );
                continue;
            }
            if (preg_match(static::UNSAFE_STYLE_VALUE_PATTERN, $value)) {
                $this->warnDropped(
                    "style-value:{$name}",
                    "Dropped the '{$name}' style: its value contains ; { } \\ < or >.",
                );
                continue;
            }

            $allowed[$name] = $value;
        }

        return $allowed;
    }

    /**
     * A prop value as the string it renders as, or null for "unset": null, '', or anything that
     * is not a scalar. Booleans render as 'true' / 'false', as React renders them on a data-*
     * attribute, so the server and the canvas agree.
     */
    private static function scalarValue(mixed $value): ?string
    {
        $value = match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) && is_finite($value) => (string) $value,
            is_string($value) => $value,
            default => null,
        };

        return $value === '' ? null : $value;
    }

    private function warnDropped(string $key, string $message): void
    {
        if (isset($this->warned[$key])) {
            return;
        }

        $this->warned[$key] = true;
        Logger::warning("[extendBlock] {$message}");
    }
}
