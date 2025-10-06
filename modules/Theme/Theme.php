<?php

namespace Sitchco\Parent\Modules\Theme;

use Sitchco\Framework\Module;
use Sitchco\Framework\ModuleAssets;
use Sitchco\Modules\TimberModule;

class Theme extends Module
{
    const HOOK_SUFFIX = 'theme';

    const DEPENDENCIES = [TimberModule::class];

    const FEATURES = ['disableAdminBar'];

    public function init(): void
    {
        add_action('after_setup_theme', [$this, 'themeSupports']);
        add_filter('upload_mimes', [$this, 'allowSVGUploads']);
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
        });
        $this->enqueueEditorUIAssets(function (ModuleAssets $assets) {
            $assets->enqueueScript('parent-editor-ui', 'editor-ui.js', [
                'wp-blocks',
                'wp-element',
                'wp-hooks',
                'wp-components',
                'wp-compose',
                'wp-block-editor',
                'wp-rich-text',
            ]);
        });
        $this->enqueueBlockStyles(function (ModuleAssets $assets) {
            $assets->enqueueBlockStyle('core/media-text', 'block-media-text.css');
        });

        // TODO: create file at same level as Theme.php
        add_filter('register_block_type_args', [$this, 'addButtonThemeAttribute'], 10, 2);

        add_filter('block_type_metadata_settings', function ($settings, $metadata) {
            if ($metadata['name'] === 'core/image') {
                $settings['attributes']['inlineSvg'] = [
                    'type' => 'boolean',
                    'default' => false,
                ];
            }
            return $settings;
        }, 10, 2);

        add_filter('render_block_core/image', [$this, 'imageBlockInlineSVG'], 20, 2);
    }

    public function disableAdminBar(): void
    {
        add_filter('show_admin_bar', '__return_false');
    }

    public function allowSVGUploads($mimes)
    {
        $mimes['svg'] = 'image/svg+xml';
        return $mimes;
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
            error_log('Warning! You have applied the_content filter too early!');
            error_log(var_export(wp_debug_backtrace_summary(), true));
            add_action('wp_body_open', function () {
                echo "<div style=\"background: #fad0d0;border:1px solid #962121; color:#962121; border-radius: 12px;min-height: 30vh;display: flex;align-items: center;justify-content: center; margin: 30px;\"><p>Warning! You have applied the_content filter too early!</p></div>";
            });
        }

        return $content;
    }

    public function imageBlockInlineSVG(string $block_content, array $block): string {
        $p = new \WP_HTML_Tag_Processor($block_content);
        if (!$p->next_tag('img')) {
            return $block_content;
        }
        $img_uploaded_src = $p->get_attribute('src');
        if (!str_ends_with($img_uploaded_src, '.svg') || empty($block['attrs']['inlineSvg'])) {
            return $block_content;
        }

        $file_path = null;
        if (isset($block['attrs']['id'])) {
            $file_path = get_attached_file($block['attrs']['id']);
        }

        if (!$file_path || !file_exists($file_path)) {
            return $block_content;
        }

        $svg_content = file_get_contents($file_path);
        $p_svg = new \WP_HTML_Tag_Processor($svg_content);

        $p_svg->set_attribute('width', $p->get_attribute('width'));
        $p_svg->set_attribute('height', $p->get_attribute('height'));
        $p_svg->set_attribute('role', 'img');
        $p_svg->set_attribute('aria-label', $p->get_attribute('alt'));
        return preg_replace(
            '#<img\b[^>]*>#i',
            $p_svg->get_updated_html(),
            $block_content
        );
    }
}
