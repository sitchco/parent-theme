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

    protected InlineSVGService $inlineSVGService;

    public function __construct(InlineSVGService $inlineSVGService)
    {
        $this->inlineSVGService = $inlineSVGService;
    }

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
                ]),
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
            $assets->inlineScriptData('parent-editor-ui', 'themeSettings', wp_get_global_settings());
        }, 1);
        $this->enqueueBlockStyles(function (ModuleAssets $assets) {
            $assets->enqueueBlockStyle('core/media-text', 'block-media-text.css');
        });

        // TODO: create file at same level as Theme.php
        add_filter('register_block_type_args', [$this, 'addButtonThemeAttribute'], 10, 2);

        add_filter(
            'block_type_metadata_settings',
            function ($settings, $metadata) {
                if ($metadata['name'] === 'core/image') {
                    $settings['attributes']['inlineSvg'] = [
                        'type' => 'boolean',
                        'default' => false,
                    ];
                }
                return $settings;
            },
            10,
            2,
        );

        add_filter('render_block_kadence/image', [$this, 'imageBlockInlineSVG'], 20, 2);
        add_filter('kadence_blocks_css_spacing_sizes', [$this, 'overrideKadenceBlockSpacingSizes']);
        add_filter('kadence_blocks_css_gap_sizes', [$this, 'overrideKadenceBlockSpacingSizes']);
        add_filter('kadence_blocks_css_font_sizes', [$this, 'overrideKadenceBlockFontSizes']);
        add_filter('option_kadence_blocks_config_blocks', [$this, 'overrideKadenceBlockConfigDefaults']);
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

    public function imageBlockInlineSVG(string $block_content, array $block): string
    {
        $attrs = $block['attrs'] ?? [];

        return $this->inlineSVGService->replaceImageBlock($block_content, $block, [
            'width' => $attrs['width'] ?? null,
            'max_width' => $attrs['imgMaxWidth'] ?? null,
        ]);
    }

    public function overrideKadenceBlockSpacingSizes(array $sizes): array
    {
        return $this->overrideKadenceBlockSizes(
            $sizes,
            'spacing',
            fn($settings) => $settings['spacing']['spacingSizes']['theme'] ?? [],
        );
    }

    public function overrideKadenceBlockFontSizes(array $sizes): array
    {
        return $this->overrideKadenceBlockSizes(
            $sizes,
            'font-size',
            fn($settings) => $settings['typography']['fontSizes']['theme'] ?? [],
        );
    }

    protected function overrideKadenceBlockSizes(array $sizes, string $type, callable $getThemeSizes): array
    {
        $filtered = array_filter($sizes, fn($slug) => in_array($slug, ['ss-auto', '0', 'none']), ARRAY_FILTER_USE_KEY);
        $theme_sizes = collect($getThemeSizes(wp_get_global_settings()))
            ->mapWithKeys(fn($size) => ["{$type}-{$size['slug']}" => "var(--wp--preset--{$type}--{$size['slug']})"])
            ->all();
        return $theme_sizes + $filtered;
    }

    public function overrideKadenceBlockConfigDefaults(mixed $config): mixed
    {
        return $config === '{}'
            ? json_encode(['kadence/tabs' => (object) [], 'kadence/accordion' => (object) []])
            : $config;
    }
}
