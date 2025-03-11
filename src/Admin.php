<?php

namespace Sitchco\Parent;

use JsonException;
use Sitchco\Framework\Core\Module;
use Sitchco\Support\PageOrder;
use Sitchco\Utils\Hooks;
use Sitchco\Utils\Template;

/**
 * Class Admin
 *
 * Handles administrative functionality for the theme, including enqueuing scripts,
 * managing the admin bar, enabling/disabling features, and more.
 *
 * @package Sitchco\Parent
 */
class Admin extends Module
{
    protected PageOrder $page_order;

    const FEATURES = [
        'adminEnqueueScripts',
        'enableAdminEditorStyle',
        'enableAdminBar',
        'enableDefaultPostType',
        'removeComments',
        'removeCustomPageSorting',
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
        $this->page_order = new PageOrder();
    }

    /**
     * Enqueues admin scripts and styles.
     */
    public function adminEnqueueScripts(): void
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
    public function removeComments(): void
    {
        add_action('admin_menu', fn() => remove_menu_page('edit-comments.php'));
        wp_dequeue_script('comment-reply');
    }

    /**
     * Disables custom page sorting functionality.
     */
    public function removeCustomPageSorting(): void
    {
        $this->page_order->disable();
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
}