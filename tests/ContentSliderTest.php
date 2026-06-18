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
