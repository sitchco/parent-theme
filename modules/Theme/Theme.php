<?php

namespace Sitchco\Parent\Modules\Theme;

use Sitchco\Events\SavePermalinksRequestEvent;
use Sitchco\Framework\Module;
use Timber;
use Twig\TwigFunction;

class Theme extends Module
{
    const HOOK_SUFFIX = 'theme';

    const DEPENDENCIES = [
        \Sitchco\Modules\Timber::class
    ];

    const FEATURES = [
        'enableUserMetaBoxReorder'
    ];

    public function init(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets'], 100);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueAdminStyles']);
        add_action('after_setup_theme', [$this, 'themeSupports']);
        add_action('wp_body_open', [$this, 'addSvgSprite']);
        add_action( 'enqueue_block_assets', [$this, 'enqueueBlockAssets']);
        if(wp_get_environment_type() === 'local') {
            add_filter('the_content', [$this, 'contentFilterWarning']);
        }
        add_filter('timber/twig/functions', function ($functions) {
            $functions['include_with_context'] = [
                'callable' => [$this, 'includeWithContext'],
            ];
            return $functions;
        });
    }

    public function enqueueAssets(): void
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

    public function enqueueBlockAssets(): void
    {
        wp_enqueue_block_style(
            'core/heading',
            [
                'handle' => 'demo-heading-block-styles',
                'src'    => get_theme_file_uri( '/modules/Theme/block-styles/core/heading.css' ),
                'path'   => get_theme_file_path( '/modules/Theme/block-styles/core/heading.css' )
            ]
        );
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

    public function includeWithContext(string $template, array $additional_context = []): bool|string
    {
        $context = Timber::context();
        $context = array_merge($context, $additional_context);
        $template_key = str_replace('.twig', '', $template);
        $hookName = static::hookName('template-context', $template_key);
        $context = apply_filters($hookName, $context, $template_key);
        return Timber::compile($template, $context);
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

    public function contentFilterWarning($content)
    {
        if (!(did_action('wp_head') || is_admin() || wp_doing_ajax() || wp_is_serving_rest_request())) {
            error_log('Warning! You have applied the_content filter too early!');
            error_log(var_export(wp_debug_backtrace_summary(), true));
            add_action('wp_body_open', function () {
                echo "<div style=\"background: #fad0d0;border:1px solid #962121; color:#962121; border-radius: 12px;min-height: 30vh;display: flex;align-items: center;justify-content: center; margin: 30px;\"><p>Warning! You have applied the_content filter too early!</p></div>";
            });
        }

        return $content;
    }
}