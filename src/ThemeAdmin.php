<?php

namespace Sitchco\Parent;

use JsonException;
use Sitchco\Framework\Core\Module;
use Sitchco\Parent\Support\BlockPatternHandler;
use Sitchco\Parent\Support\PageOrderHandler;
use Sitchco\Parent\Support\SvgHandler;
use Sitchco\Utils\Hooks;
use Sitchco\Utils\Template;

/**
 * Class ThemeAdmin
 *
 * Handles administrative functionality for the theme, including enqueuing scripts,
 * managing the admin bar, enabling/disabling features, and more.
 *
 * @package Sitchco\Parent
 */
class ThemeAdmin extends Module
{
    protected PageOrderHandler $pageOrder;
    protected SvgHandler $svgHandler;

    const FEATURES = [
        'enableAdminEnqueueScripts',
        'enableAdminEditorStyle',
        'enableAdminBar',
        'enableDefaultPostType',
        'disableComments',
        'disableCustomPageSorting',
        'disableUserMetaBoxOrder',
        'disableCoreBlockPatterns',
    ];

    /**
     * Initializes the Admin module.
     *
     * Sets up hooks for disabling the admin bar, removing the default post type menu,
     * and enqueuing comment-reply scripts.
     */
    public function init(): void
    {
        add_filter('show_admin_bar', '__return_false');
        add_action('admin_menu', fn() => remove_menu_page('edit.php'));
        add_action('wp_enqueue_scripts', function() {
            if (is_single() && comments_open() && get_option('thread_comments')) {
                wp_enqueue_script('comment-reply');
            }
        });

        // TODO: temporary shim
        add_filter('acf/settings/load_json', function ($paths) {
            $parent_path = get_template_directory() . '/acf-json';
            array_unshift($paths, $parent_path);

            return $paths;
        });

        // TODO: no use of DI here?
        $this->pageOrder = new PageOrderHandler();
        $this->pageOrder->init();
        $this->svgHandler = new SvgHandler();
        $this->svgHandler->init();
    }

    /**
     * Enqueues admin scripts and styles.
     */
    public function enableAdminEnqueueScripts(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'adminAssets'], 100);
    }

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
        add_editor_style(Template::getAssetPath('styles/admin-editor.css'));
    }

    /**
     * Enables the admin bar.
     */
    public function enableAdminBar(): void
    {
        add_filter('show_admin_bar', '__return_true');
    }

    /**
     * Enables the default post type menu in the admin.
     */
    public function enableDefaultPostType(): void
    {
        add_action('admin_menu', fn() =>
        add_menu_page(
            'Posts',
            'Posts',
            'edit_posts',
            'edit.php',
            '',
            'dashicons-admin-post',
            5
        ));
    }

    /**
     * Removes the comments menu and disables comment-reply scripts.
     */
    public function disableComments(): void
    {
        add_action('admin_menu', fn() => remove_menu_page('edit-comments.php'));
        wp_dequeue_script('comment-reply');
    }

    /**
     * Disables custom page sorting functionality.
     */
    public function disableCustomPageSorting(): void
    {
        $this->pageOrder->disable();
    }

    /**
     * Enqueues admin assets (CSS and JS).
     *
     * @throws JsonException If there is an issue with JSON encoding/decoding.
     */
    public function adminAssets(): void
    {
        wp_enqueue_style(Hooks::name('parent-theme/admin/css'), Template::getAssetPath('styles/admin.css'), false, null);
        wp_enqueue_script(Hooks::name('parent-theme/admin/js'), Template::getAssetPath('scripts/admin.js'));
    }

    /**
     * Disables the user meta box order functionality.
     *
     * This method hooks into the `after_save_permalinks` action to trigger the deletion
     * of user meta box locations for all users.
     *
     * @return void
     */
    public function disableUserMetaBoxOrder(): void
    {
        add_action(Hooks::name('after_save_permalinks'), [$this, 'deleteUserMetaBoxLocations']);
    }

    /**
     * Deletes user meta box locations for all users.
     *
     * This method iterates through all users and removes any user meta keys that start with
     * 'meta-box-order', effectively resetting the meta box order for all users.
     *
     * @return void
     */
    public function deleteUserMetaBoxLocations(): void
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
     * Disables core block patterns.
     *
     * This method initializes an instance of `BlockPatternHandler` and calls its
     * `removeCorePatterns` method to disable core block patterns.
     *
     * @return void
     */
    public function disableCoreBlockPatterns(): void
    {
        (new BlockPatternHandler())->removeCorePatterns();
    }
}