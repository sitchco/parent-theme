<?php

namespace Sitchco\Parent;

use Sitchco\Support\Svg;
use Sitchco\Utils\Hooks;
use Sitchco\Utils\Template;
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
    const EXTENSIONS = [
        Svg::class
    ];

    /**
     * Theme constructor.
     */
    public function __construct()
    {
        add_action('after_setup_theme', [$this, 'themeSupports']);
        parent::__construct();
        add_action('wp_enqueue_scripts', [$this, 'assets'], 100);
        add_action('wp_body_open', [$this, 'afterOpeningBody']);
        // TODO: temporary shim
        add_filter('acf/settings/load_json', function($paths) {
            $parent_path = get_template_directory() . '/acf-json';
            array_unshift($paths, $parent_path);

            return $paths;
        });
        add_filter('should_load_remote_block_patterns', '__return_false');
        add_action('init', [$this, 'removeCoreBlockPatterns']);
        add_action('sitchco/after_save_permalinks', [$this, 'resetMetaBoxLocations']);
        add_filter('body_class', [$this, 'cleanupBodyClass']);

        // TODO: is there a better place to put this? somewhere inside of sitchco-core/src/rest?
        if (!empty(static::API_PREFIX)) {
            add_filter('rest_url_prefix', function () { return static::API_PREFIX; });
        }

        // TODO: is there a better place to put this?
        if (!empty(static::RENAME_DEFAULT_POST_TYPE)) {
            add_filter('post_type_labels_post', [$this, 'renameDefaultPostType']);
        }

        // TODO: is there a better place to put this?
        foreach (static::EXTENSIONS as $extension) {
            if (class_exists($extension)) {
                $extension = new $extension;
                if (method_exists($extension, 'register')) {
                    $extension->register();
                }
            }
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
     * @throws \JsonException
     */
    public function assets(): void
    {
        wp_enqueue_style(Hooks::name('theme/css'), Template::getAssetPath('styles/main.css'), false, null);
        wp_enqueue_script(Hooks::name('theme/js'), Template::getAssetPath('scripts/main.js'), ['jquery'], null, [
            'in_footer' => true
        ]);
        $js_vars = apply_filters('global_js_vars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'api_url' => trailingslashit(home_url(rest_get_url_prefix()))
        ]);
        wp_localize_script(Hooks::name('theme/js'), 'sit', $js_vars);
        wp_dequeue_style('classic-theme-styles');
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

    /**
     * @return void
     */
    public function resetMetaBoxLocations(): void
    {
        $users = get_users();
        foreach ($users as $user) {
            $user_meta = get_user_meta($user->ID);
            foreach ($user_meta as $meta_key => $meta_value) {
                if (str_starts_with($meta_key, 'meta-box-order')) {
                    delete_user_meta($user->ID, $meta_key);
                }
            }
        }
    }

    /**
     * Update the body class
     *
     * @param array $classes
     * @return array
     */
    public function cleanupBodyClass(array $classes): array
    {
        $home_id_class = 'page-id-' . get_option('page_on_front');
        $remove_classes = [
            'page-template-default',
            $home_id_class
        ];

        return array_diff($classes, $remove_classes);
    }

}