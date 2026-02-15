<?php
/**
 * Expected:
 * @var array $context
 */

use Sitchco\Parent\Modules\ContentSlider\ContentSlider;

$fields = $context['fields'] ?? [];
$blockData = $context['block'] ?? [];

$sliderId = 'slider-' . ($blockData['id'] ?? uniqid());
$alignClass = !empty($blockData['align']) ? 'align' . $blockData['align'] : '';
$verticalAlignment = $fields['vertical_alignment'] ?? 'stretch';
$alignmentClass = $verticalAlignment !== 'stretch' ? 'is-vertically-aligned-' . $verticalAlignment : '';
$className = trim(($blockData['className'] ?? '') . ' ' . $alignClass . ' ' . $alignmentClass);

$sliderConfig = [
    'type' => 'slide',
    'autoplay' => !empty($fields['autoplay']),
    'interval' => (int) ($fields['autoplay_speed'] ?? 5000),
    'arrows' => !empty($fields['arrows']),
    'pagination' => !empty($fields['dots']),
    'gap' => 'var(--wp--custom--carousel-gap)',
    'perPage' => (int) ($fields['per_view_desktop'] ?? 3),
    'perMove' => 1,
    'keyboard' => true,
    'accessibility' => true,
    'ariaLabel' => 'Content slider',
    'breakpoints' => [
        '1024' => ['perPage' => (int) ($fields['per_view_desktop'] ?? 3)],
        '768' => ['perPage' => (int) ($fields['per_view_tablet'] ?? 2)],
        '480' => ['perPage' => (int) ($fields['per_view_mobile'] ?? 1)],
    ],
];

// Merge variation overrides from block style selection
$variationNames = wp_get_block_style_variation_name_from_class($blockData['className'] ?? '');
if (!empty($variationNames)) {
    $variationSlug = $variationNames[0];
    $variations = apply_filters(ContentSlider::hookName('variations'), []);
    if (!empty($variations[$variationSlug]['splide'])) {
        $sliderConfig = array_replace_recursive($sliderConfig, $variations[$variationSlug]['splide']);
    }
}

$context['slider'] = [
    'id' => $sliderId,
    'class' => trim('sc-content-slider kb-splide splide ' . $className),
    'config' => $sliderConfig,
];

$context['wrapper_attributes'] = [
    'class' => $alignmentClass,
    'style' => implode('; ', [
        '--slides-per-view-desktop: ' . ($sliderConfig['perPage'] ?? 3),
        '--slides-per-view-tablet: ' . ($sliderConfig['breakpoints']['768']['perPage'] ?? 2),
        '--slides-per-view-mobile: ' . ($sliderConfig['breakpoints']['480']['perPage'] ?? 1),
    ]),
];
