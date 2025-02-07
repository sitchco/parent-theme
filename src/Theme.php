<?php

namespace Sitchco\Parent;

use Timber\Site;

/**
 * @see https://github.com/timber/starter-theme/blob/2.x/src/StarterSite.php
 */
class Theme extends Site
{
    public function __construct()
    {
        add_action('after_setup_theme', [$this, 'theme_supports']);
        parent::__construct();
        // TODO: temporary shim
        add_filter('acf/settings/load_json', function($paths) {
            $parent_path = get_template_directory() . '/acf-json';
            array_unshift($paths, $parent_path);

            return $paths;
        });
    }

    public function theme_supports()
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
}