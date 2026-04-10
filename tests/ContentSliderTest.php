<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Parent\Modules\ContentSlider\ContentSlider;
use Sitchco\Tests\TestCase;

class ContentSliderTest extends TestCase
{
    protected ContentSlider $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = $this->container->get(ContentSlider::class);
    }

    private function invokeScanVariations(): array
    {
        $reflection = new \ReflectionClass($this->module);
        $method = $reflection->getMethod('scanVariations');
        $method->setAccessible(true);
        return $method->invoke($this->module);
    }

    public function testScanVariationsFindsValidFiles(): void
    {
        $variationsDir = get_template_directory() . '/modules/ContentSlider/variations';
        if (!is_dir($variationsDir)) {
            $this->markTestSkipped('Variations directory does not exist');
        }
        $variations = $this->invokeScanVariations();
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
        wp_cache_delete('content_slider_variations');

        try {
            $variations = $this->invokeScanVariations();
            $this->assertArrayNotHasKey('test-bad-variation', $variations);
        } finally {
            unlink($invalidFile);
            wp_cache_delete('content_slider_variations');
        }
    }

    public function testVariationsFilterMergesScannedVariations(): void
    {
        $hookName = ContentSlider::hookName('variations');
        $filtered = apply_filters($hookName, []);
        $scanned = $this->invokeScanVariations();
        $this->assertIsArray($filtered);

        foreach ($scanned as $slug => $config) {
            $this->assertArrayHasKey($slug, $filtered, "Scanned variation '{$slug}' missing from filter output");
        }
    }
}
