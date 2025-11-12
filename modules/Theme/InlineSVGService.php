<?php

namespace Sitchco\Parent\Modules\Theme;

class InlineSVGService
{
    /**
     * Inline an SVG by replacing an img tag with the SVG content.
     *
     * @param string $block_content The HTML content of the block
     * @param array $block The block data
     * @param array $options Configuration options for the inlining process
     * @return string The modified block content with inline SVG
     */
    public function replaceImageBlock(string $block_content, array $block, array $options = []): string
    {
        $defaults = [
            'file_path_resolver' => null, // Callable that returns file path given (src, block)
            'svg_id_prefix' => 'inline-svg-', // Prefix for SVG id attribute
        ];

        $options = array_merge($defaults, $options);

        // Find the img tag
        $p = new \WP_HTML_Tag_Processor($block_content);

        $found_img = false;
        while ($p->next_tag()) {
            if ('IMG' === $p->get_tag()) {
                $found_img = true;
                break;
            }
        }

        if (!$found_img) {
            return $block_content;
        }

        $img_uploaded_src = $p->get_attribute('src');

        // Check if it's an SVG
        if (!str_ends_with($img_uploaded_src, '.svg')) {
            return $block_content;
        }

        // Get the file path
        if ($options['file_path_resolver']) {
            $file_path = call_user_func($options['file_path_resolver'], $img_uploaded_src, $block);
        } else {
            $file_path = $this->resolveFilePath($img_uploaded_src, $block);
        }

        if (!$file_path || !file_exists($file_path)) {
            return $block_content;
        }

        // Get SVG content
        $svg_content = $this->getSVGContent($file_path);

        if (!$svg_content) {
            return $block_content;
        }

        // Process the SVG content
        $p_svg = new \WP_HTML_Tag_Processor($svg_content);
        $p_svg->next_tag('svg');

        // Set attributes from the original img tag
        $width = $p->get_attribute('width');
        $height = $p->get_attribute('height');
        $alt = $p->get_attribute('alt');

        if ($width) {
            $p_svg->set_attribute('width', $width);
        }
        if ($height) {
            $p_svg->set_attribute('height', $height);
        }

        $p_svg->set_attribute('role', 'img');

        if ($alt) {
            $p_svg->set_attribute('aria-label', $alt);
        }

        // Set optional id attribute
        if ($options['svg_id_prefix'] && isset($block['attrs']['id'])) {
            $p_svg->set_attribute('id', $options['svg_id_prefix'] . $block['attrs']['id']);
        }

        // Replace the img tag with the inline SVG
        return preg_replace('#<img\b[^>]*>#i', $p_svg->get_updated_html(), $block_content);
    }

    /**
     * Resolve the file path for the SVG.
     * Tries attachment ID first, then falls back to URL-to-path conversion.
     *
     * @param string $img_src The source URL of the image
     * @param array $block The block data
     * @return string|null The resolved file path or null if not found
     */
    private function resolveFilePath(string $img_src, array $block): ?string
    {
        // Try to get file path from attachment ID first (for core/image blocks)
        if (isset($block['attrs']['id'])) {
            $file_path = get_attached_file($block['attrs']['id']);
            if ($file_path && file_exists($file_path)) {
                return $file_path;
            }
        }

        // Fallback: convert URL to file path (for core/site-logo and other blocks)
        $upload_dir = wp_upload_dir();
        return str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $img_src);
    }

    /**
     * Get SVG content from file.
     *
     * @param string $file_path The file system path to the SVG file
     * @return string|false The SVG content or false on failure
     */
    private function getSVGContent(string $file_path): false|string
    {
        return file_get_contents($file_path);
    }
}
