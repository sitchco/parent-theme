<?php

namespace Sitchco\Parent\Modules\ContentSlider;

use Sitchco\Framework\Module;
use Sitchco\Utils\Cache;
use Sitchco\Utils\Logger;

/**
 * Content Slider Module
 *
 * Provides a generic slider container block that uses Kadence Section blocks (kadence/column)
 * as slides. All assets are managed at the block level via block.json and .asset.php files.
 *
 * This module ensures Kadence Splide dependencies are registered for block.json dependencies.
 *
 * Requirements:
 * - Kadence Blocks plugin (provides kadence/column blocks and Splide library)
 * - ACF Pro (for slider settings fields)
 */
class ContentSlider extends Module
{
    public const HOOK_SUFFIX = 'content-slider';

    /**
     * Slide sizing modes.
     *
     * `per_page` divides the visible track by a slide *count*, so a slide's width
     * is a function of the viewport and its shape changes at every breakpoint.
     * `fixed_width` gives every slide the same authored width and shows however
     * many of them fit.
     *
     * The first entry is the fallback for absent or unrecognized values, which is
     * what keeps blocks saved before this field existed rendering as they did.
     */
    public const SIZING_MODES = ['per_page', 'fixed_width'];

    /**
     * Ceiling on the fixed-width floor, as a share of the visible track.
     *
     * A minimum width wide enough to overflow a phone is the one authoring mistake
     * this mode can make: the Production Slider shipped a flat 360px slide in
     * February and overflowed a 375px viewport. Capping the floor at a share of the
     * track makes that unauthorable rather than merely documented — the slide gives
     * up its minimum before it gives up fitting on screen.
     */
    protected const ANCHOR_FLOOR_SHARE = 85;

    protected const ANCHOR_DEFAULTS = ['min' => 280, 'ideal' => 25, 'max' => 420];

    /**
     * Splide options a variation may not set.
     *
     * Block fields own sizing; variations own everything else — gap, padding,
     * focus/trim, navigation presentation.
     */
    public const VARIATION_RESERVED_OPTIONS = ['perPage', 'fixedWidth', 'fixedHeight', 'heightRatio', 'autoWidth'];
    /**
     * Module initialization
     *
     * The block assets are self-contained and loaded via block.json.
     * Dependencies on Kadence Splide are declared in the block's .asset.php files.
     */
    public function init(): void
    {
        // Register Kadence Splide scripts early for our block.json dependencies
        if (class_exists('Kadence_Blocks_Testimonials_Block')) {
            add_action(
                'init',
                function () {
                    $instance = \Kadence_Blocks_Testimonials_Block::get_instance();
                    // Explicitly call register_scripts to ensure Splide dependencies are registered
                    // (the Kadence abstract block only registers scripts lazily on enqueue)
                    $instance->register_scripts();
                },
                5,
            );
        }

        // Register block style variations and hook the variations filter
        // Priority 15 ensures sitchco/content-slider block type exists (registered at init:10)
        add_action(
            'init',
            function () {
                $variations = $this->scanVariations();
                foreach ($variations as $slug => $config) {
                    register_block_style('sitchco/content-slider', [
                        'name' => $slug,
                        'label' => $config['title'],
                    ]);
                }
                add_filter(static::hookName('variations'), fn(array $v) => array_merge($v, $variations), 5);
            },
            15,
        );
    }

    /**
     * Build the Splide config array for a Content Slider block.
     *
     * Pure assembly of $sliderConfig from ACF field values, kept here (rather than
     * inline in block.php) so the mode-derivation and variation precedence are
     * unit-testable. The runtime auto-downgrade in script.js remains the final
     * authority on `type`.
     *
     * `type`/`rewind` derive from the `slider_mode` field. A block with no saved
     * mode falls back to the `default_mode` filter: the platform default is the
     * neutral 'slide'; a child theme (e.g. Roundabout) can return 'loop'.
     *
     * Sizing comes from `sizing_mode` and is deliberately *not* filterable: a child
     * theme flipping the default would silently re-size every block that predates
     * the field, using width values those blocks never saved. Choosing a different
     * default for newly inserted blocks is the ACF field default's job.
     *
     * @param array $fields    ACF field values (keyed by field name).
     * @param array $blockData The block context array (uses `className` for variations).
     */
    public static function buildSliderConfig(array $fields, array $blockData = []): array
    {
        // Parent default is the platform-neutral 'slide'; a child theme overrides via filter.
        $mode = $fields['slider_mode'] ?? apply_filters(static::hookName('default_mode'), 'slide');

        $sliderConfig = [
            'type' => $mode === 'loop' ? 'loop' : 'slide',
            'rewind' => $mode === 'rewind',
            'autoplay' => !empty($fields['autoplay']),
            'interval' => (int) ($fields['autoplay_speed'] ?? 5000),
            'arrows' => !empty($fields['arrows']),
            'pagination' => !empty($fields['dots']),
            'gap' => 'var(--wp--custom--carousel-gap)',
            'perMove' => 1,
            'keyboard' => true,
            'accessibility' => true,
            'ariaLabel' => 'Content slider',
        ];

        $sliderConfig += static::buildSizingConfig($fields);

        // Merge variation overrides from block style selection
        $variationNames = wp_get_block_style_variation_name_from_class($blockData['className'] ?? '');
        if (!empty($variationNames)) {
            $variationSlug = $variationNames[0];
            $variations = apply_filters(static::hookName('variations'), []);
            if (!empty($variations[$variationSlug]['splide'])) {
                $overrides = static::withoutSizingOptions($variations[$variationSlug]['splide'], $variationSlug);
                $sliderConfig = array_replace_recursive($sliderConfig, $overrides);
            }
        }

        return $sliderConfig;
    }

    /**
     * Build the sizing half of the Splide config.
     *
     * The two modes are mutually exclusive by construction, not by precedence:
     * fixed-width emits no `perPage` and no breakpoint counts at all. That matters
     * because `perPage` is not inert once `fixedWidth` wins the width assignment —
     * Splide still reads it for the pagination dot count (`ceil(slides / perPage)`,
     * taken from the literal config and never measured), the `isEnough()` autoplay
     * gate, Controller index arithmetic, and the lazy-load radius. Omitting the key
     * lets script.js's own `perPage: 1` default stand, which is the value those
     * calculations want when every slide moves individually.
     *
     * @param array $fields ACF field values (keyed by field name).
     */
    protected static function buildSizingConfig(array $fields): array
    {
        // `??` catches absent and null but not a saved empty string, so validate
        // against the whitelist rather than trusting the stored value.
        $mode = $fields['sizing_mode'] ?? '';
        if (!in_array($mode, static::SIZING_MODES, true)) {
            $mode = static::SIZING_MODES[0];
        }

        if ($mode === 'fixed_width') {
            return ['fixedWidth' => static::buildSlideWidthAnchor($fields)];
        }

        // Splide reads breakpoints as max-widths and the narrower match wins, so the base
        // value is the widest tier and each key is the *top* of the tier below it: wide
        // 1441px and up, desktop 1025–1440, tablet 601–1024, mobile at most 600. The field
        // labels say exactly that; keep the two in step if either moves.
        //
        // The edges come from measuring the track rather than from device names. A count
        // divides the track, so a slide's width is whatever the viewport leaves over and a
        // band's width ratio *is* the slide's size range. Holding the 360:511 cards these
        // sliders carry to a usable 280–420px puts one-across at 345–515, two at 685–1015,
        // three at 1030–1530 and four from 1370 up — which is where 600/1024/1440 sit.
        //
        // per_view_wide postdates the other three, so blanks fall back to the desktop
        // count: a slider saved under the old three-tier config renders 1441px and up
        // exactly as it did before, and every band that does move moves to a lower count,
        // never a higher one, because no saved slider counts up as the screen narrows.
        return [
            'perPage' => (int) ($fields['per_view_wide'] ?? ($fields['per_view_desktop'] ?? 4)),
            'breakpoints' => [
                '1440' => ['perPage' => (int) ($fields['per_view_desktop'] ?? 3)],
                '1024' => ['perPage' => (int) ($fields['per_view_tablet'] ?? 2)],
                '600' => ['perPage' => (int) ($fields['per_view_mobile'] ?? 1)],
            ],
        ];
    }

    /**
     * Compose the fixed-width anchor as a single fluid CSS length.
     *
     * Splide assigns `fixedWidth` verbatim to `element.style.width`, so any CSS
     * expression is legal here and the ratio/shape work stays in CSS. A `clamp()`
     * is what removes the defect this mode exists to fix: with a count, slide width
     * jumps discontinuously when the breakpoint changes the divisor — one pixel of
     * viewport at 769px re-shapes every slide. A clamp has no integer to jump
     * between, so the width glides and the shape holds.
     *
     * The middle term is a percentage of the track rather than a `vw` unit
     * deliberately: percentages resolve against the list's content box, so they
     * respect the container's max-width, the page gutters, and the peek padding —
     * none of which `vw` can see. Splide's own CSS pins `.splide__slide` to
     * `flex-shrink: 0`, so a percentage width holds instead of collapsing.
     *
     * @param array $fields ACF field values (keyed by field name).
     */
    protected static function buildSlideWidthAnchor(array $fields): string
    {
        $defaults = static::ANCHOR_DEFAULTS;

        $min = max(1, (int) ($fields['slide_width_min'] ?? $defaults['min']));
        $max = max($min, (int) ($fields['slide_width_max'] ?? $defaults['max']));
        $ideal = min(100, max(1, (float) ($fields['slide_width_ideal'] ?? $defaults['ideal'])));

        // Trim the trailing zeros a float always brings: `25%`, not `25.00%`.
        $idealValue = rtrim(rtrim(number_format($ideal, 2, '.', ''), '0'), '.');

        return sprintf('clamp(min(%dpx, %d%%), %s%%, %dpx)', $min, static::ANCHOR_FLOOR_SHARE, $idealValue, $max);
    }

    /**
     * Drop sizing options from a variation's Splide overrides.
     *
     * The merge is `array_replace_recursive`, which adds without removing: a variation
     * setting `fixedWidth` over a count-mode block would leave `perPage` and every
     * breakpoint count in place, producing a config in both modes at once — Splide
     * would take slide width from one and its pagination dot count from the other.
     * Dropping the keys is cheaper than reconciling them, and no `variations/`
     * directory has ever existed in either repository to depend on the old behaviour.
     *
     * @param array  $splide A variation's Splide config overrides.
     * @param string $slug   The variation slug, for the warning.
     */
    protected static function withoutSizingOptions(array $splide, string $slug): array
    {
        $reserved = array_flip(static::VARIATION_RESERVED_OPTIONS);
        $dropped = array_keys(array_intersect_key($splide, $reserved));
        $splide = array_diff_key($splide, $reserved);

        foreach ($splide['breakpoints'] ?? [] as $width => $options) {
            if (!is_array($options)) {
                continue;
            }
            $dropped = array_merge($dropped, array_keys(array_intersect_key($options, $reserved)));
            $options = array_diff_key($options, $reserved);

            // A breakpoint whose only key was a count has nothing left to merge.
            if ($options) {
                $splide['breakpoints'][$width] = $options;
            } else {
                unset($splide['breakpoints'][$width]);
            }
        }

        if (isset($splide['breakpoints']) && !$splide['breakpoints']) {
            unset($splide['breakpoints']);
        }

        if ($dropped) {
            Logger::warning(
                sprintf(
                    'ContentSlider: variation "%s" set sizing options (%s), which block fields own. Ignored.',
                    $slug,
                    implode(', ', array_unique($dropped)),
                ),
            );
        }

        return $splide;
    }

    private function scanVariations(): array
    {
        return Cache::remember('content_slider_variations', function () {
            $variations = [];
            $dirs = array_unique(
                array_filter(
                    [
                        get_template_directory() . '/modules/ContentSlider/variations',
                        get_stylesheet_directory() . '/modules/ContentSlider/variations',
                    ],
                    'is_dir',
                ),
            );

            foreach ($dirs as $dir) {
                foreach (glob($dir . '/*.json') as $file) {
                    $slug = basename($file, '.json');
                    $data = json_decode(file_get_contents($file), true);
                    if (!is_array($data) || empty($data['title']) || !isset($data['splide'])) {
                        Logger::warning("ContentSlider: Invalid variation file skipped: {$file}");
                        continue;
                    }
                    $variations[$slug] = $data;
                }
            }

            return $variations;
        });
    }
}
