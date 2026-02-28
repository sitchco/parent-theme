<?php

namespace Sitchco\Parent\Modules\ExtendBlock;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;

class ExtendBlockModule extends Module
{
    public function init(): void
    {
        $this->enqueueEditorUIAssets(function (ModuleAssets $assets) {
            $assets->enqueueScript('extend-block', 'extend-block.js', [
                'wp-hooks',
                'wp-compose',
                'wp-block-editor',
                'wp-components',
                'wp-data',
                'wp-element',
                'sitchco/editor-ui-framework',
            ]);
        }, 1);

        // Inject extendBlockClasses into dynamic block output
        add_filter('render_block', [$this, 'injectExtendBlockClasses'], 10, 2);
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

        // Skip if classes string is empty
        if (empty(trim($classes))) {
            return $block_content;
        }

        // Sanitize the classes
        $classes = esc_attr($classes);

        // Inject classes into the first element's class attribute
        // Pattern prefix to skip leading whitespace and HTML comments
        $prefix = '(\s*(?:<!--.*?-->\s*)*)';

        // First, try to match an existing class attribute
        if (preg_match('/^' . $prefix . '<[^>]*\bclass=["\'][^"\']*["\'][^>]*>/is', $block_content)) {
            // Append to existing class attribute
            $block_content = preg_replace_callback(
                '/^(' . $prefix . '<[^>]*\bclass=["\'])([^"\']*)/is',
                function ($matches) use ($classes) {
                    $existing = $matches[3];
                    return $matches[1] . ($existing !== '' ? $existing . ' ' : '') . $classes;
                },
                $block_content,
                1,
            );
        } else {
            // No class attribute exists, add one after the tag name
            $block_content = preg_replace_callback(
                '/^(' . $prefix . '<[a-z][a-z0-9]*)/is',
                function ($matches) use ($classes) {
                    return $matches[1] . ' class="' . $classes . '"';
                },
                $block_content,
                1,
            );
        }

        return $block_content;
    }
}
