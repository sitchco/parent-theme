<?php

use Sitchco\Utils\Hooks;

/**
 * Functions and definitions
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 * @link https://github.com/timber/starter-theme
 */

function compile_with_context(string $template, array $context, $filter_key = null): bool|string
{
    if (!$filter_key) {
        $filter_key = str_replace('.twig', '', $template);
    }
    $hookName = Hooks::name('theme/template-context', $filter_key);
    $context = apply_filters($hookName, $context, $filter_key);
    return Timber::compile($template, $context);
}

function load_block_context(array $context, $path): array
{
    $context_file = trailingslashit($path) . 'block.php';
    if (file_exists($context_file)) {
        include $context_file;
    }
    return $context;
}

function acf_block_render_callback(array $block): void
{
    $context = Timber::context();
    $context['post'] = Timber::get_post();
    $context['block']  = $block;
    $context['fields'] = get_fields();
    // Parent theme context inclusion
    $context = load_block_context($context, $block['path']);
    // Child theme context inclusion
    $path_parts = explode('/modules/', $block['path']);
    if (isset($path_parts[1])) {
        $child_path = get_stylesheet_directory() . '/modules/' . $path_parts[1];
        $context = load_block_context($context, $child_path);
    }

    $template_path = trailingslashit($block['path']) . 'block.twig';

    if (!file_exists($template_path)) {
        trigger_error("Twig template $template_path does not exist", E_USER_WARNING);
        return;
    }

    $blockNameParts = explode('/', $block['name']);
    $blockName = array_pop($blockNameParts);
    echo compile_with_context($template_path, $context, "block/$blockName");
}

function include_with_context(string $template, array $additional_context = []): string
{
    $context = Timber::context();
    $context = array_merge($context, $additional_context);
    return compile_with_context($template, $context, "block");
}

add_filter('timber/twig/functions', function ($functions) {
    $functions['include_with_context'] = [
        'callable' => 'include_with_context',
    ];
    return $functions;
});