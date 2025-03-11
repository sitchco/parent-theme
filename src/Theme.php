<?php

namespace Sitchco\Parent;

use Timber\Site;

/**
 * Class Theme
 *
 * @package Sitchco\Parent
 * @see https://github.com/timber/starter-theme/blob/2.x/src/StarterSite.php
 */
class Theme extends Site
{
    const API_PREFIX = '';
    const RENAME_DEFAULT_POST_TYPE = '';

    const NAV_MENUS = [];

    const ADDITIONAL_THEME_SUPPORT = [];

    // TODO: are we moving away from EXTENSIONS?

    /**
     * Theme constructor.
     */
    public function __construct()
    {
        add_action('after_setup_theme', [$this, 'themeSupports']);
        parent::__construct();
        add_action('wp_body_open', [$this, 'afterOpeningBody']);
        // TODO: temporary shim
        add_filter('acf/settings/load_json', function($paths) {
            $parent_path = get_template_directory() . '/acf-json';
            array_unshift($paths, $parent_path);

            return $paths;
        });
        add_filter('should_load_remote_block_patterns', '__return_false');
        add_action('init', [$this, 'removeCoreBlockPatterns']);

        // TODO: is there a better place to put this? somewhere inside of sitchco-core/src/rest?
        if (!empty(static::API_PREFIX)) {
            add_filter('rest_url_prefix', function () { return static::API_PREFIX; });
        }

        // TODO: is there a better place to put this?
        if (!empty(static::RENAME_DEFAULT_POST_TYPE)) {
            add_filter('post_type_labels_post', [$this, 'renameDefaultPostType']);
        }
    }

    /**
     * Adds theme supports.
     * @return void
     */
    public function themeSupports(): void
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
        add_theme_support('editor-style');

        if (!empty(static::ADDITIONAL_THEME_SUPPORT)) {
            array_map('add_theme_support', static::ADDITIONAL_THEME_SUPPORT);
        }

        if (empty(static::NAV_MENUS)) {
            register_nav_menus(static::NAV_MENUS);
        }
    }

    /**
     * Outputs SVG sprite after the opening body tag
     * @return void
     */
    public function afterOpeningBody(): void
    {
        $sprite = get_theme_file_path('dist/images/sprite.svg');
        if (file_exists($sprite)) {
            echo file_get_contents($sprite);
        }
    }

    /**
     * Removes all core block patterns.
     * @return void
     */
    public function removeCoreBlockPatterns(): void
    {
        $registry = \WP_Block_Patterns_Registry::get_instance();
        $patterns = $registry->get_all_registered();

        foreach ($patterns as $pattern) {
            $registry->unregister($pattern['name']);
        }
    }

    /**
     * Renames the default 'post' post type.
     *
     * @param $labels
     * @return object
     */
    public function renameDefaultPostType($labels): object
    {
        return (object) array_map(function ($l) {
            return str_ireplace('Post', static::RENAME_DEFAULT_POST_TYPE, $l);
        }, (array) $labels);
    }
}