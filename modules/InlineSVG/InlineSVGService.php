<?php

namespace Sitchco\Parent\Modules\InlineSVG;

use Sitchco\Utils\Cache;

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
            'width' => null, // Explicit width in pixels for img-like behavior
            'max_width' => null, // Max width constraint in pixels
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

        // Get SVG content: try local file first, then fetch from remote URL
        $svg_content = $this->resolveSVGContent($img_uploaded_src, $block, $options);

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

        // Apply sizing styles for img-like behavior
        $styles = ['max-width: 100%', 'height: auto'];

        if ($options['width']) {
            $styles[] = "width: {$options['width']}px";
        }

        if ($options['max_width']) {
            $styles[0] = "max-width: {$options['max_width']}px";
        }

        $existing_style = $p_svg->get_attribute('style') ?? '';
        $new_style = implode('; ', $styles);

        if ($existing_style) {
            $new_style = rtrim($existing_style, ';') . '; ' . $new_style;
        }

        $p_svg->set_attribute('style', $new_style);

        // Replace the img tag with the inline SVG
        return preg_replace('#<img\b[^>]*>#i', $p_svg->get_updated_html(), $block_content);
    }

    /**
     * Resolve SVG content by trying local file first, then remote URL.
     */
    private function resolveSVGContent(string $img_src, array $block, array $options): string|false
    {
        // Custom resolver always takes priority
        if ($options['file_path_resolver']) {
            $file_path = call_user_func($options['file_path_resolver'], $img_src, $block);
            if ($file_path && file_exists($file_path)) {
                return file_get_contents($file_path);
            }
        }

        $isRemote = str_starts_with($img_src, 'https://') || str_starts_with($img_src, 'http://');

        // For external URLs (e.g. Cloudinary CDN), prefer the optimized remote version
        if ($isRemote && !$this->isLocalUploadUrl($img_src)) {
            $remote = $this->fetchRemoteSVG($img_src);
            if ($remote) {
                return $remote;
            }
        }

        // Resolve from local filesystem
        if (!isset($file_path)) {
            $file_path = $this->resolveLocalFilePath($img_src, $block);
        }
        if ($file_path && file_exists($file_path)) {
            return file_get_contents($file_path);
        }

        if ($isRemote) {
            return $this->fetchRemoteSVG($img_src);
        }

        return false;
    }

    private function isLocalUploadUrl(string $url): bool
    {
        return str_starts_with($url, wp_get_upload_dir()['baseurl']);
    }

    /**
     * Resolve the local file path for the SVG.
     * Tries attachment ID first, then falls back to URL-to-path conversion.
     */
    private function resolveLocalFilePath(string $img_src, array $block): ?string
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
     * Fetch SVG content from a remote URL with transient caching.
     */
    private function fetchRemoteSVG(string $url): string|false
    {
        return Cache::rememberTransient(
            'inline_svg_' . md5($url),
            function () use ($url) {
                $response = wp_safe_remote_get($url);
                if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                    return null;
                }

                $body = wp_remote_retrieve_body($response);
                return str_contains($body, '<svg') ? $body : null;
            },
            WEEK_IN_SECONDS,
            failureTtl: HOUR_IN_SECONDS,
        ) ?:
            false;
    }
}
