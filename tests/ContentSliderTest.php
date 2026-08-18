<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Parent\Modules\ContentSlider\ContentSlider;
use Sitchco\Tests\TestCase;
use Sitchco\Utils\Cache;

class ContentSliderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->container->get(ContentSlider::class);
    }

    public function testScanVariationsFindsValidFiles(): void
    {
        $variationsDir = get_template_directory() . '/modules/ContentSlider/variations';
        if (!is_dir($variationsDir)) {
            $this->markTestSkipped('Variations directory does not exist');
        }
        $variations = apply_filters(ContentSlider::hookName('variations'), []);
        $this->assertIsArray($variations);
        $this->assertNotEmpty($variations, 'Expected at least one variation file on disk');

        foreach ($variations as $slug => $config) {
            $this->assertIsString($slug);
            $this->assertArrayHasKey('title', $config);
            $this->assertArrayHasKey('splide', $config);
        }
    }

    public function testScanVariationsSkipsInvalidFiles(): void
    {
        $dir = get_template_directory() . '/modules/ContentSlider/variations';
        if (!is_dir($dir)) {
            $this->markTestSkipped('Variations directory does not exist');
        }

        // Create a file missing the required 'splide' key
        $invalidFile = $dir . '/test-bad-variation.json';
        file_put_contents($invalidFile, json_encode(['title' => 'Bad Variation']));

        // Clear the cache so scanVariations re-reads the filesystem
        Cache::forget('content_slider_variations');

        try {
            $variations = apply_filters(ContentSlider::hookName('variations'), []);
            $this->assertArrayNotHasKey('test-bad-variation', $variations);
        } finally {
            unlink($invalidFile);
            Cache::forget('content_slider_variations');
        }
    }

    public function testVariationsFilterMergesScannedVariations(): void
    {
        $variationsDir = get_template_directory() . '/modules/ContentSlider/variations';
        if (!is_dir($variationsDir)) {
            $this->markTestSkipped('Variations directory does not exist');
        }
        $filtered = apply_filters(ContentSlider::hookName('variations'), []);
        $this->assertIsArray($filtered);
        $this->assertNotEmpty($filtered, 'Expected variations to be merged into filter output');

        foreach ($filtered as $slug => $config) {
            $this->assertIsString($slug);
            $this->assertArrayHasKey('title', $config);
            $this->assertArrayHasKey('splide', $config);
        }
    }

    public function testEmptySlideGuardSkipsContentlessSlides(): void
    {
        // All four variants share ONE slider render: ACF's block store makes
        // get_fields() return false on subsequent renders of the same block in
        // a single process, so a per-case data provider cannot work here.
        $slides = [
            'text' => '<p>Hello</p>',
            'media' => '<img src="test.png" alt=""/>',
            // A full-bleed background-image card renders no text and no media
            // element; the platform marks background-bearing columns with
            // kt-column-has-bg (see KadenceBlocks module), which the guard
            // accepts as content.
            'background' =>
                '<div class="wp-block-kadence-column kt-column-has-bg"><div class="kt-inside-inner-col"></div></div>',
            'empty' =>
                '<div class="wp-block-kadence-column is-empty-slide"><div class="kt-inside-inner-col"></div></div>',
        ];

        $innerBlocks = implode('', array_map(fn($html) => "<!-- wp:html -->$html<!-- /wp:html -->", $slides));

        // ACF data mirrors the markup the editor saves (see the production-slider
        // pattern) — without saved field values get_fields() returns false and the
        // block cannot build its config. The values are arbitrary; only presence
        // matters.
        $markup =
            '<!-- wp:sitchco/content-slider {"name":"sitchco/content-slider","data":{"field_68f7cf60ba248":"0","field_68f7cf60badba":"1","field_68f7cf60bb1b4":"0","field_699224b6e08f1":"stretch","field_68f7cf60bc165":"3","field_68f7cf60bc526":"2","field_68f7cf60bc917":"1"},"mode":"preview"} -->' .
            $innerBlocks .
            '<!-- /wp:sitchco/content-slider -->';

        $html = do_blocks($markup);

        $this->assertSame(
            3,
            substr_count($html, 'class="splide__slide"'),
            'Text, media, and background slides should render',
        );
        $this->assertStringContainsString($slides['text'], $html);
        $this->assertStringContainsString($slides['media'], $html);
        $this->assertStringContainsString($slides['background'], $html);
        $this->assertStringNotContainsString(
            'is-empty-slide',
            $html,
            'A slide with no text, media, or background should be skipped',
        );
    }

    public function testSlideModeBuildsSlideTypeWithoutRewind(): void
    {
        $config = ContentSlider::buildSliderConfig(['slider_mode' => 'slide']);
        $this->assertSame('slide', $config['type']);
        $this->assertFalse($config['rewind']);
    }

    public function testRewindModeBuildsSlideTypeWithRewind(): void
    {
        $config = ContentSlider::buildSliderConfig(['slider_mode' => 'rewind']);
        $this->assertSame('slide', $config['type'], 'Rewind is a slide variant, not its own Splide type');
        $this->assertTrue($config['rewind']);
    }

    public function testLoopModeBuildsLoopTypeWithoutRewind(): void
    {
        $config = ContentSlider::buildSliderConfig(['slider_mode' => 'loop']);
        $this->assertSame('loop', $config['type']);
        $this->assertFalse($config['rewind']);
    }

    public function testAbsentSizingModeKeepsPerPageConfig(): void
    {
        // The guarantee for every block saved before the sizing field existed: ACF
        // reports the key as absent (it never backfills definitions into stored meta),
        // and absent has to mean the counts, unchanged.
        $config = ContentSlider::buildSliderConfig([
            'per_view_desktop' => 4,
            'per_view_tablet' => 2,
            'per_view_mobile' => 1,
        ]);

        $this->assertSame(4, $config['perPage']);
        $this->assertSame(4, $config['breakpoints']['1440']['perPage']);
        $this->assertSame(2, $config['breakpoints']['1024']['perPage']);
        $this->assertSame(1, $config['breakpoints']['600']['perPage']);
        $this->assertArrayNotHasKey('fixedWidth', $config);
    }

    public function testBreakpointKeysAreTheTopOfEachTier(): void
    {
        $config = ContentSlider::buildSliderConfig([
            'per_view_wide' => 5,
            'per_view_desktop' => 4,
            'per_view_tablet' => 3,
            'per_view_mobile' => 2,
        ]);

        // Splide reads these as max-widths and the narrower match wins, so each key is
        // the top of the tier below the base: wide from 1441px up, desktop 1025–1440,
        // tablet 601–1024, mobile at most 600 — the bands the field labels promise.
        $this->assertSame([1440, 1024, 600], array_keys($config['breakpoints']));
        $this->assertSame(5, $config['perPage']);
        $this->assertSame(4, $config['breakpoints'][1440]['perPage']);
        $this->assertSame(3, $config['breakpoints'][1024]['perPage']);
        $this->assertSame(2, $config['breakpoints'][600]['perPage']);
    }

    public function testWideTierFallsBackToTheDesktopCount(): void
    {
        // per_view_wide postdates the other three and ACF never backfills a definition
        // into stored meta, so every slider saved before it reports the key as absent.
        // Absent has to render 1441px and up exactly as it did under three tiers.
        $config = ContentSlider::buildSliderConfig([
            'per_view_desktop' => 4,
            'per_view_tablet' => 2,
            'per_view_mobile' => 1,
        ]);

        $this->assertSame(4, $config['perPage']);
        $this->assertSame(4, $config['breakpoints'][1440]['perPage']);
    }

    public function testWideTierIsIndependentOnceSaved(): void
    {
        $config = ContentSlider::buildSliderConfig([
            'per_view_wide' => 5,
            'per_view_desktop' => 3,
            'per_view_tablet' => 2,
            'per_view_mobile' => 1,
        ]);

        $this->assertSame(5, $config['perPage']);
        $this->assertSame(3, $config['breakpoints'][1440]['perPage']);
    }

    public function testWideningABandNeverRaisesACount(): void
    {
        // The whole backward-compatibility argument in one assertion. Each legacy value
        // keeps its slug but drives a wider band than it used to, so a viewport can only
        // land on the same count or the one below it — bigger slides, never smaller —
        // provided the saved counts never rise as the screen narrows. Every slider in
        // the wild satisfies that; this pins the reasoning to the shapes that exist.
        foreach ([[4, 2, 1], [4, 3, 1], [3, 2, 1]] as [$desktop, $tablet, $mobile]) {
            $config = ContentSlider::buildSliderConfig([
                'per_view_desktop' => $desktop,
                'per_view_tablet' => $tablet,
                'per_view_mobile' => $mobile,
            ]);

            $label = "{$desktop}/{$tablet}/{$mobile}";

            // Was the desktop count above 768; is now the tablet count above 1024.
            $this->assertLessThanOrEqual($desktop, $config['breakpoints'][1024]['perPage'], $label);
            // Was the tablet count above 480; is now the mobile count above 600.
            $this->assertLessThanOrEqual($tablet, $config['breakpoints'][600]['perPage'], $label);
            // Unchanged tiers.
            $this->assertSame($desktop, $config['perPage'], $label);
            $this->assertSame($desktop, $config['breakpoints'][1440]['perPage'], $label);
            $this->assertSame($tablet, $config['breakpoints'][1024]['perPage'], $label);
            $this->assertSame($mobile, $config['breakpoints'][600]['perPage'], $label);
        }
    }

    public function testVariationCannotOverrideSizing(): void
    {
        // `array_replace_recursive` adds without removing, so an unfiltered variation
        // would leave the block's counts in place beside its own `fixedWidth` and hand
        // Splide a config in both modes at once.
        $variations = fn() => [
            'test-sizing' => [
                'title' => 'Test Sizing',
                'splide' => [
                    'gap' => '2rem',
                    'perPage' => 6,
                    'fixedWidth' => '360px',
                    'breakpoints' => [
                        '1024' => ['perPage' => 5, 'gap' => '1rem'],
                        '600' => ['perPage' => 4],
                    ],
                ],
            ],
        ];

        $hook = ContentSlider::hookName('variations');
        add_filter($hook, $variations, 99);
        try {
            $config = ContentSlider::buildSliderConfig(
                ['per_view_desktop' => 3, 'per_view_tablet' => 2, 'per_view_mobile' => 1],
                ['className' => 'is-style-test-sizing'],
            );
        } finally {
            remove_filter($hook, $variations, 99);
        }

        $this->assertSame('2rem', $config['gap'], 'Non-sizing options still merge');
        $this->assertSame('1rem', $config['breakpoints'][1024]['gap'], 'So do non-sizing breakpoint options');
        $this->assertSame(3, $config['perPage'], 'Block fields keep sizing');
        $this->assertSame(2, $config['breakpoints'][1024]['perPage']);
        $this->assertSame(1, $config['breakpoints'][600]['perPage']);
        $this->assertArrayNotHasKey('fixedWidth', $config);
    }

    public function testVariationCannotReintroduceCountsOnAFixedWidthBlock(): void
    {
        $variations = fn() => [
            'test-counts' => ['title' => 'Test Counts', 'splide' => ['perPage' => 4, 'arrows' => false]],
        ];

        $hook = ContentSlider::hookName('variations');
        add_filter($hook, $variations, 99);
        try {
            $config = ContentSlider::buildSliderConfig(
                ['sizing_mode' => 'fixed_width'],
                ['className' => 'is-style-test-counts'],
            );
        } finally {
            remove_filter($hook, $variations, 99);
        }

        $this->assertSame('clamp(min(280px, 85%), 25%, 420px)', $config['fixedWidth']);
        $this->assertArrayNotHasKey('perPage', $config, 'A reintroduced count would drive the dot count');
        $this->assertFalse($config['arrows'], 'Non-sizing options are untouched');
    }

    public function testUnrecognizedSizingModeFallsBackToPerPage(): void
    {
        // `??` catches absent and null but not a saved empty string, which is what a
        // cleared select stores.
        foreach (['', 'aspect_ratio', null] as $stored) {
            $config = ContentSlider::buildSliderConfig(['sizing_mode' => $stored]);
            $this->assertSame(4, $config['perPage'], 'Unrecognized sizing mode should fall back to counts');
            $this->assertArrayNotHasKey('fixedWidth', $config);
        }
    }

    public function testFixedWidthModeEmitsAnchorAndDropsCounts(): void
    {
        // `perPage` is not inert once `fixedWidth` wins the width assignment: Splide
        // still derives the pagination dot count, the autoplay gate, and Controller
        // index arithmetic from it. Fixed-width mode has to omit the key, not merely
        // stop reading it.
        $config = ContentSlider::buildSliderConfig([
            'sizing_mode' => 'fixed_width',
            'per_view_desktop' => 4,
            'per_view_tablet' => 2,
            'per_view_mobile' => 1,
            'slide_width_min' => 300,
            'slide_width_ideal' => 25,
            'slide_width_max' => 420,
        ]);

        $this->assertSame('clamp(min(300px, 85%), 25%, 420px)', $config['fixedWidth']);
        $this->assertArrayNotHasKey('perPage', $config);
        $this->assertArrayNotHasKey('breakpoints', $config);
    }

    public function testFixedWidthAnchorFallsBackToDefaultsWhenFieldsAreAbsent(): void
    {
        $config = ContentSlider::buildSliderConfig(['sizing_mode' => 'fixed_width']);
        $this->assertSame('clamp(min(280px, 85%), 25%, 420px)', $config['fixedWidth']);
    }

    public function testFixedWidthAnchorRaisesAMaximumBelowItsMinimum(): void
    {
        $config = ContentSlider::buildSliderConfig([
            'sizing_mode' => 'fixed_width',
            'slide_width_min' => 400,
            'slide_width_max' => 200,
        ]);

        // An inverted pair is an authoring slip, not a reason to emit invalid CSS:
        // clamp() with max < min silently resolves to the max and would pin every
        // slide to 200px.
        $this->assertSame('clamp(min(400px, 85%), 25%, 400px)', $config['fixedWidth']);
    }

    public function testFixedWidthAnchorFormatsAndBoundsThePreferredWidth(): void
    {
        $anchor = fn($ideal) => ContentSlider::buildSliderConfig([
            'sizing_mode' => 'fixed_width',
            'slide_width_ideal' => $ideal,
        ])['fixedWidth'];

        $this->assertStringContainsString(', 33.33%,', $anchor(33.333), 'Fractional percentages survive');
        $this->assertStringContainsString(', 30%,', $anchor(30.0), 'Whole percentages carry no trailing zeros');
        $this->assertStringContainsString(', 100%,', $anchor(150), 'Preferred width cannot exceed the track');
        $this->assertStringContainsString(', 1%,', $anchor(0), 'Preferred width cannot be zero');
    }

    public function testFixedWidthFloorCannotOverflowASmallViewport(): void
    {
        // The February regression this mode has to make unauthorable: the Production
        // Slider shipped a flat 360px slide and overflowed a 375px phone. However wide
        // the authored minimum, the floor gives way to a share of the track first.
        $config = ContentSlider::buildSliderConfig([
            'sizing_mode' => 'fixed_width',
            'slide_width_min' => 1200,
            'slide_width_max' => 1600,
        ]);

        $this->assertSame('clamp(min(1200px, 85%), 25%, 1600px)', $config['fixedWidth']);
    }

    public function testAbsentModeFollowsDefaultModeFilter(): void
    {
        // A block with no saved `slider_mode` resolves its type from the
        // `default_mode` filter, so a child theme can pick the platform-wide default
        // (Roundabout returns 'loop' here, keeping legacy/unsaved sliders looping).
        // Use a late priority so these assertions hold regardless of any default
        // already registered by the active theme — the last filter to run wins.
        $hook = ContentSlider::hookName('default_mode');

        $toLoop = fn() => 'loop';
        add_filter($hook, $toLoop, 99);
        try {
            $this->assertSame('loop', ContentSlider::buildSliderConfig([])['type']);
        } finally {
            remove_filter($hook, $toLoop, 99);
        }

        $toSlide = fn() => 'slide';
        add_filter($hook, $toSlide, 99);
        try {
            $config = ContentSlider::buildSliderConfig([]);
            $this->assertSame('slide', $config['type']);
            $this->assertFalse($config['rewind']);
        } finally {
            remove_filter($hook, $toSlide, 99);
        }
    }
}
