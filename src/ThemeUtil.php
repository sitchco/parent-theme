<?php

namespace Sitchco\Parent;

use Sitchco\Parent\Support\JsonManifest;

/**
 * Class ThemeUtil
 * @package Sitchco\Parent
 */
class ThemeUtil
{
    /**
     * Builds full dist path to theme asset.
     *
     * @param string $filename The filename of the asset.
     * @return string The full path to the asset.
     * @throws \JsonException
     */
    public static function getAssetPath(string $filename): string
    {
        static $manifest;

        $dist_path = get_stylesheet_directory_uri() . '/dist/';
        $dist_dir = get_stylesheet_directory() . '/dist/';
        $file_path = trailingslashit(trim(dirname($filename), '.'));
        $file = basename($filename);

        if (empty($manifest)) {
            $manifest_path = $dist_dir . 'assets.json';
            $manifest = new JsonManifest($manifest_path);
        }

        $manifest_paths = $manifest->get();
        $found_path = $manifest_paths[$file] ?? $manifest_paths[$filename] ?? false;

        if (!$found_path) {
            return $dist_path . $filename;
        }

        if (file_exists($dist_dir . $file_path . $found_path)) {
            return $dist_path . $file_path . $found_path;
        }

        return $dist_path . $found_path;
    }
}