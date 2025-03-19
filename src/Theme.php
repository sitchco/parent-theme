<?php

namespace Sitchco\Parent;

use Sitchco\Framework\Core\Module;
use Sitchco\Utils\Hooks;
use JsonException;

/**
 * Class Theme
 *
 * @package Sitchco\Parent
 * @see https://github.com/timber/starter-theme/blob/2.x/src/StarterSite.php
 *
 * TODO: Needs tests
 */
class Theme extends Module
{
    const FEATURES = [
        'enableAdminEditorStyle',
        'enableAdminBar',
        'enableUserMetaBoxReorder'
    ];

    /**
     * Theme constructor.
     */
    public function init(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'assets'], 100);
        add_action('admin_enqueue_scripts', [$this, 'adminAssets'], 100);
        add_action('after_setup_theme', [$this, 'themeSupports']);
        add_action('wp_body_open', [$this, 'addSvgSprite']);

        // TODO: temporary shim
        add_filter('acf/settings/load_json', function ($paths) {
            $parent_path = get_template_directory() . '/acf-json';
            array_unshift($paths, $parent_path);

            return $paths;
        });

        // Useful Timber filters (for reference)
//        add_filter('timber/context', [$this, 'addToContext']);
//        add_filter('timber/twig/filters', [$this, 'addFiltersToTwig']);
//        add_filter('timber/twig/functions', [$this, 'addFunctionsToTwig']);
//        add_filter('timber/twig/environment/options', [$this, 'updateTwigEnvironmentOptions']);
    }

    /**
     * @throws \JsonException
     */
    public function assets(): void
    {
        wp_enqueue_style(Hooks::name('theme/css'), ThemeUtil::getAssetPath('styles/main.css'), false, null);
        wp_enqueue_script(Hooks::name('theme/js'), ThemeUtil::getAssetPath('scripts/main.js'), ['jquery'], null, [
            'in_footer' => true
        ]);
        $js_vars = apply_filters('global_js_vars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'api_url' => trailingslashit(home_url(rest_get_url_prefix()))
        ]);
        wp_localize_script(Hooks::name('theme/js'), 'sit', $js_vars);

        if (is_single() && comments_open() && get_option('thread_comments')) {
            wp_enqueue_script('comment-reply');
        }
    }

    /**
     * Enqueues admin assets (CSS and JS).
     *
     * @throws JsonException If there is an issue with JSON encoding/decoding.
     */
    public function adminAssets(): void
    {
        wp_enqueue_style(Hooks::name('parent-theme/admin/css'), ThemeUtil::getAssetPath('styles/admin.css'), false, null);
        wp_enqueue_script(Hooks::name('parent-theme/admin/js'), ThemeUtil::getAssetPath('scripts/admin.js'));
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

        register_nav_menus([
            'primary_navigation' => 'Primary Navigation',
            'footer_navigation' => 'Footer Navigation',
        ]);
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

    // FEATURES

    /**
     * Enables the admin editor style.
     *
     * @throws JsonException If there is an issue with JSON encoding/decoding.
     */
    public function enableAdminEditorStyle(): void
    {
        if (!current_theme_supports('editor-style')) {
            add_theme_support('editor-style');
        }
        add_editor_style(ThemeUtil::getAssetPath('styles/admin-editor.css'));
    }

    /**
     * Enables the admin bar.
     */
    public function enableAdminBar(): void
    {
        add_filter('show_admin_bar', '__return_true');
    }

    /**
     * Disables the user meta box order functionality.
     *
     * This method hooks into the `after_save_permalinks` action to trigger the deletion
     * of user meta box locations for all users.
     *
     * @return void
     */
    public function enableUserMetaBoxReorder(): void
    {
        add_action(Hooks::name('after_save_permalinks'), function (): void {
            $users = get_users();
            foreach ($users as $user) {
                $user_meta = get_user_meta($user->ID);
                foreach ($user_meta as $meta_key => $meta_value) {
                    if (str_starts_with($meta_key, 'meta-box-order')) {
                        delete_user_meta($user->ID, $meta_key);
                    }
                }
            }
        });
    }

    // USEFUL TIMBER CONTEXT

//    /**
//     * This is where you add some context.
//     *
//     * @param array $context context['this'] Being the Twig's {{ this }}
//     */
//    public function addToContext(array $context): array
//    {
//        $context['foo']   = 'bar';
//        $context['stuff'] = 'I am a value set in your functions.php file';
//        $context['notes'] = 'These values are available everytime you call Timber::context();';
//        $context['menu']  = Timber::get_menu( 'primary_navigation' );
//        $context['site']  = $this;
//
//        return $context;
//    }
//
//    /**
//     * This would return 'foo bar!'.
//     *
//     * @param string $text being 'foo', then returned 'foo bar!'
//     */
//    public function myfoo(string $text ): string
//    {
//        $text .= ' bar!';
//        return $text;
//    }
//
//    /**
//     * This is where you can add your own functions to twig.
//     *
//     * @link https://timber.github.io/docs/v2/hooks/filters/#timber/twig/filters
//     * @param array $filters an array of Twig filters.
//     */
//    public function addFiltersToTwig(array $filters ): array
//    {
//        $additional_filters = [
//            'myfoo' => [
//                'callable' => [ $this, 'myfoo' ],
//            ],
//        ];
//
//        return array_merge( $filters, $additional_filters );
//    }
//
//
//    /**
//     * This is where you can add your own functions to twig.
//     *
//     * @link https://timber.github.io/docs/v2/hooks/filters/#timber/twig/functions
//     * @param array $functions an array of existing Twig functions.
//     */
//    public function addFunctionsToTwig(array $functions): array
//    {
//        $additional_functions = [
//            'get_theme_mod' => [
//                'callable' => 'get_theme_mod',
//            ],
//        ];
//
//        return array_merge( $functions, $additional_functions );
//    }
//
//    /**
//     * Updates Twig environment options.
//     *
//     * @see https://twig.symfony.com/doc/2.x/api.html#environment-options
//     *
//     * @param array $options an array of environment options
//     *
//     * @return array
//     */
//    public function updateTwigEnvironmentOptions(array $options): array
//    {
//        // $options['autoescape'] = true;
//        return $options;
//    }
}