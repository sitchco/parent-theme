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

$sliderConfig = ContentSlider::buildSliderConfig($fields, $blockData);

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
