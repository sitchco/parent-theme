<?php

use Sitchco\Utils\Hooks;

/**
 * Functions and definitions
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 * @link https://github.com/timber/starter-theme
 */

function render_twig_block_with_context(array $block, callable $contextBuilder): void
{
    $template_path = trailingslashit($block['path']) . 'block.twig';

    if (!file_exists($template_path)) {
        error_log("Twig template $template_path does not exist", E_USER_WARNING);
        return;
    }

    $context = $contextBuilder($block);

    if (!is_array($context)) {
        return;
    }
    $context['block'] = $block;
    $blockNameParts = explode('/', $block['name']);
    $blockName = array_pop($blockNameParts);
    $context = apply_filters(Hooks::name('theme/template-context/block', $blockName), $context);
    $context = apply_filters(Hooks::name('theme/template-context/block'), $context, $block);

    Timber::render($template_path, $context);
}
