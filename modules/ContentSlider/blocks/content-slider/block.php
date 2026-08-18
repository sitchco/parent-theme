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

$sliderConfig = ContentSlider::buildSliderConfig($fields, $blockData);

// Read the mode off the assembled config rather than the raw field, so the class
// always describes what Splide was actually handed.
$isFixedWidth = isset($sliderConfig['fixedWidth']);
$sizingClass = $isFixedWidth ? 'is-sizing-fixed-width' : '';

$className = trim(($blockData['className'] ?? '') . ' ' . $alignClass . ' ' . $alignmentClass . ' ' . $sizingClass);

$context['slider'] = [
    'id' => $sliderId,
    'class' => trim('sc-content-slider kb-splide splide ' . $className),
    'config' => $sliderConfig,
];

// Editor-only custom properties: block.twig emits the wrapper attributes on the
// preview branch alone. Each mode publishes the value its own preview grid reads,
// and neither publishes the other's — a `perPage` fallback under fixed-width
// sizing would render a column count nothing on the frontend controls.
$sizingProperties = $isFixedWidth
    ? ['--slide-width-anchor: ' . $sliderConfig['fixedWidth']]
    : [
        '--slides-per-view-desktop: ' . ($sliderConfig['perPage'] ?? 3),
        '--slides-per-view-tablet: ' . ($sliderConfig['breakpoints']['768']['perPage'] ?? 2),
        '--slides-per-view-mobile: ' . ($sliderConfig['breakpoints']['480']['perPage'] ?? 1),
    ];

$context['wrapper_attributes'] = [
    'class' => trim($alignmentClass . ' ' . $sizingClass),
    'style' => implode('; ', $sizingProperties),
];
