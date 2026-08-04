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
