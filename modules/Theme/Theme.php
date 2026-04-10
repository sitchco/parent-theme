<?php

namespace Sitchco\Parent\Modules\Theme;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;
use Sitchco\Modules\TimberModule;
use Sitchco\Modules\UIFramework\UIFramework;
use Sitchco\Modules\UIModal\ModalData;
use Sitchco\Modules\UIModal\UIModal;
use Sitchco\Parent\Modules\ExtendBlock\ExtendBlockModule;
use Sitchco\Utils\Logger;

class Theme extends Module
{
    public const HOOK_SUFFIX = 'parent-theme';

    const DEPENDENCIES = [ExtendBlockModule::class, TimberModule::class];

    const FEATURES = ['disableAdminBar'];

    public function init(): void
    {
        add_action('after_setup_theme', [$this, 'themeSupports']);
        if (wp_get_environment_type() === 'local') {
            add_filter('the_content', [$this, 'contentFilterWarning']);
        }
        $this->enqueueFrontendAssets(function (ModuleAssets $assets) {
            $assets->enqueueStyle(static::hookName('core'), 'core.css');
            $assets->inlineScriptData(
                static::hookName('core'),
                'sitchcoTheme',
                apply_filters(static::hookName('global-js-vars'), [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'api_url' => trailingslashit(home_url(rest_get_url_prefix())),
                ]),
            );
        });
        $this->enqueueEditorPreviewAssets(function (ModuleAssets $assets) {
            $assets->enqueueStyle(static::hookName('admin-block-editor'), 'admin-editor.css');
            $assets->enqueueScript(static::hookName('editor-preview'), 'editor-preview.js', [UIFramework::hookName()]);
        });
        $this->enqueueEditorUIAssets(function (ModuleAssets $assets) {
            $assets->enqueueScript(static::hookName('editor-ui'), 'editor-ui.js', [
                'wp-blocks',
                'wp-element',
                'wp-hooks',
                'wp-components',
                'wp-compose',
                'wp-block-editor',
                'wp-rich-text',
                UIFramework::hookName('editor'),
                ExtendBlockModule::hookName(),
            ]);
            $assets->inlineScriptData(static::hookName('editor-ui'), 'themeSettings', wp_get_global_settings());
        });
        $this->enqueueBlockStyles(function (ModuleAssets $assets) {
            $assets->enqueueBlockStyle('core/media-text', 'block-media-text.css');
        });

        // TODO: create file at same level as Theme.php
        add_filter('register_block_type_args', [$this, 'addButtonThemeAttribute'], 10, 2);

        add_filter(UIModal::hookName('content-attributes'), [$this, 'modalContentAttributes'], 10, 2);
    }

    public function disableAdminBar(): void
    {
        add_filter('show_admin_bar', '__return_false');
    }

    /**
     * Adds custom attributes to the core/button block.
     *
     * @param array  $args      Array of arguments for registering a block type.
     * @param string $block_name Name of the block type.
     * @return array The modified arguments.
     */
    public function addButtonThemeAttribute(array $args, string $block_name): array
    {
        if ('core/button' === $block_name) {
            $args['attributes']['theme'] = [
                'type' => 'string',
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

    // FEATURES

    public function contentFilterWarning($content)
    {
        if (!(did_action('wp_head') || is_admin() || wp_doing_ajax() || wp_is_serving_rest_request())) {
            Logger::warning('You have applied the_content filter too early!');
            Logger::debug(wp_debug_backtrace_summary());
            add_action('wp_body_open', function () {
                echo "<div style=\"background: #fad0d0;border:1px solid #962121; color:#962121; border-radius: 12px;min-height: 30vh;display: flex;align-items: center;justify-content: center; margin: 30px;\"><p>Warning! You have applied the_content filter too early!</p></div>";
            });
        }

        return $content;
    }

    public function modalContentAttributes(array $attrs, ModalData $modalData): array
    {
        if ($modalData->type === 'video') {
            return $attrs;
        }
        $attrs['class'] = array_merge((array) ($attrs['class'] ?? []), ['is-layout-constrained', 'has-global-padding']);
        return $attrs;
    }
}
