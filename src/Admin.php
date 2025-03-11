<?php

namespace Sitchco\Parent;

use JsonException;
use Sitchco\Framework\Core\Module;
use Sitchco\Support\PageOrder;
use Sitchco\Utils\Hooks;
use Sitchco\Utils\Template;

/**
 * class Admin
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
        'removeCustomPageSorting'
    ];

    public function init(): void
    {
        add_filter('show_admin_bar', '__return_false');
        add_action('admin_menu', fn() => remove_menu_page('edit.php'));
        $this->page_order = new PageOrder();
    }

    public function adminEnqueueScripts(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'adminAssets'], 100);
    }

    /**
     * @throws JsonException
     */
    public function enableAdminEditorStyle(): void
    {
        if (!current_theme_supports( 'editor-style')) {
            add_theme_support('editor-style');
        }
        add_editor_style(Template::getAssetPath('styles/admin-editor.css'));
    }

    public function enableAdminBar(): void
    {
        add_filter('show_admin_bar', '__return_true');
    }

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
        )
        );
    }

    public function removeComments(): void
    {
        add_action('admin_menu', fn() => remove_menu_page('edit-comments.php'));
    }

    /**
     * @throws JsonException
     */
    public function adminAssets(): void
    {
        wp_enqueue_style(Hooks::name('parent-theme/admin/css'), Template::getAssetPath('styles/admin.css'), false, null);
        wp_enqueue_script(Hooks::name('parent-theme/admin/js'), Template::getAssetPath('scripts/admin.js'));
    }

    public function removeCustomPageSorting(): void
    {
        $this->page_order->disable();
    }
}