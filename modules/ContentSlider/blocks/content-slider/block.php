<?php
/**
 * Expected:
 * @var array $context
 */

$fields = $context['fields'] ?? [];
$blockData = $context['block'] ?? [];

$sliderId = 'slider-' . ($blockData['id'] ?? uniqid());
$alignClass = !empty($blockData['align']) ? 'align' . $blockData['align'] : '';
$equalHeightClass = !empty($fields['equal_height']) ? 'has-equal-height-slides' : '';
$className = trim(($blockData['className'] ?? '') . ' ' . $alignClass . ' ' . $equalHeightClass);

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
        1024 => ['perPage' => (int) ($fields['per_view_desktop'] ?? 3)],
        768 => ['perPage' => (int) ($fields['per_view_tablet'] ?? 2)],
        0 => ['perPage' => (int) ($fields['per_view_mobile'] ?? 1)],
    ],
];

// Merge advanced options from JSON field
if (!empty($fields['advanced_options'])) {
    $advancedConfig = json_decode($fields['advanced_options'], true);
    if (is_array($advancedConfig)) {
        $sliderConfig = array_merge($sliderConfig, $advancedConfig);
    }
}

$context['slider'] = [
    'id' => $sliderId,
    'class' => trim('sc-content-slider kb-splide splide ' . $className),
    'config' => $sliderConfig,
];

$context['wrapper_attributes'] = [
    'style' => implode('; ', [
        '--slides-per-view-desktop: ' . (int) ($fields['per_view_desktop'] ?? 3),
        '--slides-per-view-tablet: ' . (int) ($fields['per_view_tablet'] ?? 2),
        '--slides-per-view-mobile: ' . (int) ($fields['per_view_mobile'] ?? 1),
    ]),
];
