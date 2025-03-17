<?php

namespace Sitchco\Parent\Support;

use Sitchco\Framework\Core\Module;
use Sitchco\Utils\Hooks;
use WP_Query;

/**
 * Class PageOrder
 * @package Sitchco\Support
 *
 * TODO: create a test class for this!
 */
class PageOrderHandler extends Module
{
    protected string $check_sort_transient = 'sit_page_sort';

    public function init(): void
    {
        add_action('admin_init', [$this, 'checkSortOrder']);
        add_action('save_post', [$this, 'flagSortOrder'], 10, 2);
        add_action('current_screen', [$this, 'checkCurrentScreen']);
    }

    /**
     * Flags the need to sort pages when a page or nav menu item is saved.
     *
     * @param int|string|null $post_id The ID of the post being saved, or 'force' to manually trigger sorting.
     * @param \WP_Post|null $post The post object being saved.
     * @return void
     */
    public function flagSortOrder(int|string $post_id = null, \WP_Post $post = null): void
    {
        if ($post_id === 'force' || ($post instanceof \WP_Post && ($post->post_type === 'page' || $post->post_type === 'nav_menu_item'))) {
            set_transient($this->check_sort_transient, 1);
        }
    }

    /**
     * Checks the current screen and flags sorting if on the page order admin screen.
     *
     * @param \WP_Screen $screen The current screen object.
     * @return void
     */
    public function checkCurrentScreen(\WP_Screen $screen): void
    {
        if ($screen->id === 'pages_page_order-page') {
            $this->flagSortOrder('force');
        }
    }

    /**
     * Checks if page sorting is needed and triggers the sorting process.
     *
     * @return void
     */
    public function checkSortOrder(): void
    {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        if (get_transient($this->check_sort_transient)) {
            delete_transient($this->check_sort_transient);
            $this->sortPagesByMenuOrder();
        }
    }

    /**
     * Sorts pages based on their order in specified menus.
     *
     * @return void
     */
    public function sortPagesByMenuOrder(): void
    {
        $menu_order = $this->getPageOrderFromMenus();
        $this->updatePageOrder($menu_order);
    }

    /**
     * Retrieves the page order based on their presence in specified menus.
     *
     * Example Usage:
     *
     * Menu priority filters use a strpos match against the menu slug, so 'header' would match 'header-nav' and 'utility-header' etc.
     *
     * Set items from the primary nav at the beginning of the page list:
     * add_filter('backstage/page_autosort_high_menu_priorities', fn() => ['primary']);
     *
     * Set items from the footer nav at the end of the page list:
     * add_filter('backstage/page_autosort_menu_priorities', fn() => ['footer']);
     *
     * Sort the items that don't exist in any matched nav menu descending by publish date:
     * add_filter('backstage/page_autosort_default', fn() => ['orderby' => 'date', 'order' => 'DESC']);
     *
     * @return array An array of page IDs in the desired order.
     */
    protected function getPageOrderFromMenus(): array
    {
        $highPageIDs = $this->getPageIDsFromMenuList(apply_filters(Hooks::name('page_autosort_high_menu_priorities'), [
            'primary'
        ]));
        $lowPageIDs = $this->getPageIDsFromMenuList(apply_filters(Hooks::name('page_autosort_menu_priorities'), [
            'header',
            'footer',
        ]));
        $lowPageIDs = array_values(array_diff($lowPageIDs, $highPageIDs));

        $query_args = array_merge(apply_filters(Hooks::name('page_autosort_default'), [
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ]), [
            'post_type' => 'page',
            'posts_per_page' => -1,
            'post__not_in' => array_values(array_merge($highPageIDs, $lowPageIDs)), // Exclude pages found in menus
            'post_status' => 'any'
        ]);
        $query = new WP_Query($query_args);

        return array_values(array_merge($highPageIDs, wp_list_pluck($query->posts, 'ID') ?: [], $lowPageIDs));
    }

    /**
     * Updates the menu order of pages in the database.
     *
     * @param array $page_order An array of page IDs in the desired order.
     * @return void
     */
    protected function updatePageOrder(array $page_order): void
    {
        global $wpdb;
        foreach ($page_order as $order => $page_id) {
            // Get the current menu_order value
            $current_order = $wpdb->get_var($wpdb->prepare(
                "SELECT menu_order FROM $wpdb->posts WHERE ID = %d",
                $page_id
            ));

            // Update only if the order has changed
            if ($current_order != $order) {
                $wpdb->update(
                    $wpdb->posts,
                    ['menu_order' => $order],
                    ['ID' => $page_id]
                );
                clean_post_cache($page_id);
            }
        }
    }

    /**
     * Retrieves page IDs from a list of menus based on matching slugs.
     *
     * @param array $list An array of menu slugs to match.
     * @return array An array of page IDs found in the specified menus.
     */
    protected function getPageIDsFromMenuList(array $list): array
    {
        if (empty($list)) {
            return [];
        }
        $menus = wp_get_nav_menus();
        $menuList = [];
        foreach ($list as $match) {
            foreach ($menus as $menu) {
                if (str_contains($menu->slug, $match)) {
                    $menuList[] = $menu;
                }
            }
        }
        $pageIDs = array_map(function ($menu) {
            $items = array_filter(wp_get_nav_menu_items($menu->term_id),
                fn($item) => 'post_type' === $item->type && 'page' === $item->object);

            return array_map(fn($item) => $item->object_id, $items);
        }, $menuList);

        return array_values(array_unique(array_merge(...$pageIDs)));
    }
}