<?php

namespace Sitchco\Parent\Modules\Theme;

use Sitchco\Events\SavePermalinksRequestEvent;
use Sitchco\Framework\Module;
use Sitchco\Modules\TimberModule;

class Theme extends Module
{
    const HOOK_SUFFIX = 'theme';

    const DEPENDENCIES = [TimberModule::class];

    public function init(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets'], 100);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueAdminStyles']);
        add_action('after_setup_theme', [$this, 'themeSupports']);
        add_action('wp_body_open', [$this, 'addSvgSprite']);
        add_action('enqueue_block_assets', [$this, 'enqueueBlockAssets']);
        if (wp_get_environment_type() === 'local') {
            add_filter('the_content', [$this, 'contentFilterWarning']);
        }
    }

    public function enqueueAssets(): void
    {
        $this->enqueueStyle(static::hookName('core'), $this->styleUrl('core.css'));
        $js_vars = apply_filters(static::hookName('global-js-vars'), [
            'ajax_url' => admin_url('admin-ajax.php'),
            'api_url' => trailingslashit(home_url(rest_get_url_prefix())),
        ]);
        wp_localize_script(static::hookName('js'), 'sitchcoTheme', $js_vars);
    }

    public function enqueueAdminStyles(): void
    {
        $this->enqueueStyle(static::hookName('admin-block-editor'), $this->styleUrl('admin-editor.css'));
    }

    public function enqueueBlockAssets(): void
    {
        $this->enqueueBlockStyle('core/media-text', [
            'handle' => static::hookName('core/media-text'),
            'src' => $this->styleUrl('block-media-text.css'),
            'path' => $this->path('assets/styles/block-media-text.css'),
        ]);
    }

    /**
     * Enqueues admin assets (CSS and JS).
     */
    public function adminAssets(): void
    {
        $this->enqueueStyle(static::hookName('admin-css'), $this->styleUrl('styles/admin.css'));
        $this->enqueueScript(static::hookName('admin-js'), $this->scriptUrl('scripts/admin.js'));
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
            'style',
        ]);
        add_theme_support('responsive-embeds');
        add_theme_support('automatic-feed-links');
        add_theme_support('post-formats', ['aside', 'image', 'video', 'quote', 'link', 'gallery', 'audio']);
        add_theme_support('menus');
        add_theme_support('editor-style');
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
