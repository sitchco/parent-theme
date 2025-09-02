<?php

namespace Sitchco\Parent\Modules\Theme;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;
use Sitchco\Modules\TimberModule;

class Theme extends Module
{
    const HOOK_SUFFIX = 'theme';

    const DEPENDENCIES = [TimberModule::class];

    public function init(): void
    {
        add_action('after_setup_theme', [$this, 'themeSupports']);
        add_action('wp_body_open', [$this, 'addSvgSprite']);
        if (wp_get_environment_type() === 'local') {
            add_filter('the_content', [$this, 'contentFilterWarning']);
        }
        $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
            $assets->enqueueStyle('core', 'core.css');
            $assets->inlineScriptData(
                'core',
                'sitchcoTheme',
                apply_filters(static::hookName('global-js-vars'), [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'api_url' => trailingslashit(home_url(rest_get_url_prefix())),
                ])
            );
        });
        $this->enqueueEditorPreviewAssets(function (ModuleAssets $assets) {
            $assets->enqueueStyle('admin-block-editor', 'admin-editor.css');
            $assets->enqueueScript('editor-preview', 'editor-preview.js', ['sitchco/ui-framework']);
            $assets->enqueueScript('theme-main', 'main.js', ['wp-blocks', 'wp-element', 'wp-hooks', 'wp-components', 'wp-compose', 'wp-block-editor']);
        });
        $this->enqueueEditorUIAssets(function(ModuleAssets $assets) {
            $assets->enqueueScript('parent-editor-ui', 'editor-ui.js', ['wp-blocks', 'wp-element', 'wp-hooks', 'wp-components', 'wp-compose', 'wp-block-editor', 'wp-rich-text']);
        });
        $this->enqueueEditorUIAssets(function(ModuleAssets $assets) {
            $assets->enqueueScript('parent-editor-ui', 'editor-ui.js', ['wp-blocks', 'wp-element', 'wp-hooks', 'wp-components', 'wp-compose', 'wp-block-editor', 'wp-rich-text']);
        });
        $this->enqueueBlockStyles(function (ModuleAssets $assets) {
            $assets->enqueueBlockStyle('core/media-text', 'block-media-text.css');
        });

        // TODO: put this somewhere else, it definitely feels part of the Theme module,
        //       but is there a Controller class that can be built to handle this?
        add_filter('register_block_type_args', [$this, 'add_button_attributes'], 10, 2);
    }

    /**
     * Adds custom attributes to the core/button block.
     * TODO: consider moving this to a Controller class.
     *
     * @param array  $args      Array of arguments for registering a block type.
     * @param string $block_name Name of the block type.
     * @return array The modified arguments.
     */
    public function add_button_attributes(array $args, string $block_name): array
    {
        if ('core/button' === $block_name) {
            // Renamed 'sitchcoTheme' to 'theme'
            $args['attributes']['theme'] = [
                'type'    => 'string',
                'default' => '',
            ];
        }
        return $args;
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