<?php

namespace Sitchco\Parent;

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

    /**
     * Theme constructor.
     */
    public function __construct()
    {
        parent::__construct();

        // frontend related filters, perhaps another opportunity for a ThemeFrontEnd module?
        add_action('wp_enqueue_scripts', [$this, 'assets'], 100);
        add_filter('body_class', [$this, 'cleanupBodyClass']);
        add_filter('wp_targeted_link_rel', [$this, 'removeNoReferrerFromLinks']);

        // filters/action hooks that are dependant upon a non-boolean constants
        add_action('after_setup_theme', [$this, 'themeSupports']);
        if (!empty(static::API_PREFIX)) {
            add_filter('rest_url_prefix', function () {
                return static::API_PREFIX;
            });
        }
        if (!empty(static::RENAME_DEFAULT_POST_TYPE)) {
            add_filter('post_type_labels_post', [$this, 'renamePostLabels']);
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

    /**
     * Renames the default 'post' post type.
     *
     * TODO: all labels change except the menu_name, what could be the conflict?
     *
     * @param $labels
     * @return object
     */
    public function renamePostLabels($labels): object
    {
        return (object) array_map(function ($l) {
            return str_ireplace('Post', static::RENAME_DEFAULT_POST_TYPE, $l);
        }, (array) $labels);
    }

    /**
     * Remove noreferrer attribute from links
     *
     * @param $rel_values
     * @return array|string|null
     */
    public function removeNoReferrerFromLinks($rel_values): array|string|null
    {
        return preg_replace('/noreferrer\s*/i', '', $rel_values);
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

        if (!empty(static::NAV_MENUS)) {
            register_nav_menus(static::NAV_MENUS);
        }
    }
}