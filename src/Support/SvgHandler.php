<?php

namespace Sitchco\Parent\Support;

/**
 * Class Svg
 * @package Sitchco\Support
 *
 * TODO: create a test for this file!
 */
class SvgHandler
{
    public function init(): void
    {
        add_filter('upload_mimes', [$this, 'uploadMimes']);
        add_filter('wp_check_filetype_and_ext', [$this, 'checkFileType'], 10, 3);
        add_filter('wp_generate_attachment_metadata', [$this, 'generateAttachmentMetadata'], 10, 2);
        add_action('admin_init', [$this, 'svgCheck']);
        add_action('wp_body_open', [$this, 'addSvgSprite']);
    }

    /**
     * Adds SVG to the list of allowed mime types for upload.
     *
     * @param array $mimes The list of allowed mime types.
     * @return array The updated list of mime types.
     */
    public function uploadMimes(array $mimes): array
    {
        if (current_user_can('manage_options')) {
            $mimes['svg'] = 'text/html';
        }

        return $mimes;
    }

    /**
     * Validates SVG files during upload.
     *
     * @param array $parts The file type and extension data.
     * @param string $file The path to the uploaded file.
     * @param string $filename The name of the uploaded file.
     * @return array The updated file type and extension data.
     */
    public function checkFileType(array $parts, string $file, string $filename): array
    {
        if (pathinfo($filename, PATHINFO_EXTENSION) === 'svg') {
            $contents = file_get_contents($file);
            if (
                str_starts_with($contents, '<svg') && // Check if the file starts with SVG markup.
                !str_contains($contents, '<script') && // Ensure the file does not contain JavaScript.
                current_user_can('manage_options') // Restrict to administrators.
            ) {
                $parts['type'] = 'image/svg+xml';
                $parts['ext'] = 'svg';
            }
        }

        return $parts;
    }

    /**
     * Extracts width and height attributes from SVG files and updates attachment metadata.
     *
     * @param array $metadata The existing attachment metadata.
     * @param int $attachment_id The ID of the attachment.
     * @return array The updated attachment metadata.
     */
    public function generateAttachmentMetadata(array $metadata, int $attachment_id): array
    {
        $attachment = get_attached_file($attachment_id);

        if (pathinfo($attachment, PATHINFO_EXTENSION) === 'svg') {
            $contents = file_get_contents($attachment);

            if (preg_match('/\s*width\s*=\s*"([^"]*)"/', $contents, $matches)) {
                $metadata['width'] = (int)$matches[1];
            }

            if (preg_match('/\s*height\s*=\s*"([^"]*)"/', $contents, $matches)) {
                $metadata['height'] = (int)$matches[1];
            }
        }

        return $metadata;
    }

    /**
     * Checks if metadata for all existing SVG files needs to be updated.
     */
    public function svgCheck(): void
    {
        if (is_user_logged_in() && current_user_can('manage_options')) {
            $updated = get_option('sitchco_svgs_updated', false);
            if (!$updated) {
                $this->updateAllSvgMetadata();
                update_option('sitchco_svgs_updated', true);
            }
        }
    }

    /**
     * Updates metadata for all existing SVG files in the media library.
     */
    protected function updateAllSvgMetadata(): void
    {
        $args = [
            'post_type' => 'attachment',
            'post_mime_type' => 'image/svg+xml',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
        ];

        $svg_attachments = get_posts($args);
        foreach ($svg_attachments as $attachment) {
            $attachment_id = $attachment->ID;
            $metadata = wp_get_attachment_metadata($attachment_id);
            $updated_metadata = $this->generateAttachmentMetadata($metadata, $attachment_id);
            if (isset($updated_metadata['width']) && isset($updated_metadata['height'])) {
                wp_update_attachment_metadata($attachment_id, $updated_metadata);
            }
        }
    }

    /**
     * Outputs SVG sprite after the opening body tag
     *
     * @return void
     */
    public function addSvgSprite(): void
    {
        $sprite = get_theme_file_path('dist/images/sprite.svg');
        if (file_exists($sprite)) {
            echo file_get_contents($sprite);
        }
    }
}