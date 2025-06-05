<?php

namespace Sitchco\Parent\Modules\Theme;

use Sitchco\Events\SavePermalinksRequestEvent;
use Sitchco\Framework\Module;

class Theme extends Module
{
    const HOOK_SUFFIX = 'theme';

    const FEATURES = [
        'enableUserMetaBoxReorder'
    ];

    public function init(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'assets'], 100);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueAdminStyles']);
        add_action('after_setup_theme', [$this, 'themeSupports']);
        add_action('wp_body_open', [$this, 'addSvgSprite']);
    }

    public function assets(): void
    {
        wp_enqueue_style(static::hookName('core'), $this->styleUrl('core.css'), false, null);
        $js_vars = apply_filters(static::hookName('global-js-vars'), [
            'ajax_url' => admin_url('admin-ajax.php'),
            'api_url' => trailingslashit(home_url(rest_get_url_prefix()))
        ]);
        wp_localize_script(static::hookName('js'), 'sitchcoTheme', $js_vars);
    }

    public function enqueueAdminStyles(): void
    {
        wp_enqueue_style(static::hookName('admin-block-editor'), $this->styleUrl('admin-editor.css'), false, null);
    }

    /**
     * Enqueues admin assets (CSS and JS).
     */
    public function adminAssets(): void
    {
        wp_enqueue_style(static::hookName('admin-css'), $this->styleUrl('styles/admin.css'), false, null);
        wp_enqueue_script(static::hookName('admin-js'), $this->scriptUrl('scripts/admin.js'));
    }

    /**
     * Adds theme supports.
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
        add_theme_support('wp-block-styles');

        register_nav_menus([
            'primary_navigation' => 'Primary Navigation',
            'footer_navigation' => 'Footer Navigation',
        ]);
    }

    /**
     * Outputs SVG sprite after the opening body tag
     */
    public function addSvgSprite(): void
    {
        $sprite = $this->path('dist/images/sprite.svg');
        if ($sprite->exists()) {
            echo file_get_contents($sprite);
        }
    }

    // FEATURES

    /**
     * Enables the user meta box order reset.
     *
     * This method hooks into the Save Permalinks async event hook to trigger the deletion
     * of user meta box locations for all users.
     */
    public function enableUserMetaBoxReorder(): void
    {
        add_action(SavePermalinksRequestEvent::hookName(), function() use (&$processed) {
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
}