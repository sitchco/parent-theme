<?php

namespace Sitchco\Parent;

use Timber\Site;

/**
 * class Theme
 * @package Sitchco\Parent
 * @see https://github.com/timber/starter-theme/blob/2.x/src/StarterSite.php
 */
class Theme extends Site
{
    public function __construct()
    {
        add_action('after_setup_theme', [$this, 'theme_supports']);
        parent::__construct();
        add_action('wp_body_open', [$this, 'after_opening_body']);
        // TODO: temporary shim
        add_filter('acf/settings/load_json', function($paths) {
            $parent_path = get_template_directory() . '/acf-json';
            array_unshift($paths, $parent_path);

            return $paths;
        });
        add_filter('should_load_remote_block_patterns', '__return_false');
        add_action('init', [$this, 'remove_core_block_patterns']);
    }

    public function theme_supports(): void
    {
        add_theme_support('align-wide');
        add_theme_support('body-open');
        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        add_theme_support('html5', [
            'search-form',
            'comment-form',
            'comment-list',
            'gallery',
            'caption',
            'script',
            'style'
        ]);
        add_theme_support('responsive-embeds');
        add_theme_support('automatic-feed-links');
        add_theme_support('post-formats', [
            'aside',
            'image',
            'video',
            'quote',
            'link',
            'gallery',
            'audio'
        ]);
        add_theme_support('menus');
    }

    public function after_opening_body(): void
    {
        $sprite = get_theme_file_path('dist/images/sprite.svg');
        if (file_exists($sprite)) {
            echo file_get_contents($sprite);
        }
    }

    public function remove_core_block_patterns(): void
    {
        $registry = \WP_Block_Patterns_Registry::get_instance();
        $patterns = $registry->get_all_registered();

        foreach ($patterns as $pattern) {
            $registry->unregister($pattern['name']);
        }
    }
}