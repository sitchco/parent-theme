<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Modules\Cloudinary\CloudinaryUrl;
use Sitchco\Parent\Modules\CloudinaryKadence\CloudinaryKadenceModule;
use Sitchco\Tests\TestCase;

class CloudinaryKadenceModuleTest extends TestCase
{
    protected CloudinaryKadenceModule $module;
    protected CloudinaryUrl $cloudinaryUrl;
    private string $uploadBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cloudinaryUrl = $this->container->get(CloudinaryUrl::class);
        $this->module = $this->container->get(CloudinaryKadenceModule::class);
        $this->uploadBase = wp_get_upload_dir()['baseurl'];
        if (!$this->cloudinaryUrl->isConfigured()) {
            $this->markTestSkipped('Cloudinary not configured');
        }
    }

    private function uploadUrl(string $path): string
    {
        return $this->uploadBase . '/' . $path;
    }

    private function assertCloudinaryUrl(string $url, string $expectedFilename): void
    {
        $this->assertStringStartsWith('https://res.cloudinary.com/', $url);
        $this->assertStringContainsString($expectedFilename, $url);
    }

    public function testInitRegistersFilters(): void
    {
        $this->module->init();
        $this->assertNotFalse(
            has_filter('kadence_blocks_rowlayout_render_block_attributes', [
                $this->module,
                'rewriteRowLayoutAttributes',
            ]),
        );
        $this->assertNotFalse(
            has_filter('kadence_blocks_column_render_block_attributes', [$this->module, 'rewriteColumnAttributes']),
        );
    }

    public function testRewriteRowBgImg(): void
    {
        $src = $this->uploadUrl('2024/01/hero.jpg');
        $result = $this->module->rewriteRowLayoutAttributes(['bgImg' => $src]);
        $this->assertCloudinaryUrl($result['bgImg'], 'hero.jpg');
    }

    public function testRewriteRowOverlayBgImg(): void
    {
        $src = $this->uploadUrl('2024/01/overlay.jpg');
        $result = $this->module->rewriteRowLayoutAttributes(['overlayBgImg' => $src]);
        $this->assertCloudinaryUrl($result['overlayBgImg'], 'overlay.jpg');
    }

    public function testRewriteRowBackgroundSlider(): void
    {
        $src1 = $this->uploadUrl('2024/01/slide1.jpg');
        $src2 = $this->uploadUrl('2024/01/slide2.jpg');
        $result = $this->module->rewriteRowLayoutAttributes([
            'backgroundSlider' => [['bgImg' => $src1], ['bgImg' => $src2]],
        ]);
        $this->assertCloudinaryUrl($result['backgroundSlider'][0]['bgImg'], 'slide1.jpg');
        $this->assertCloudinaryUrl($result['backgroundSlider'][1]['bgImg'], 'slide2.jpg');
    }

    public static function responsiveBackgroundKeysProvider(): array
    {
        return [
            'tabletBackground' => ['tabletBackground'],
            'mobileBackground' => ['mobileBackground'],
        ];
    }

    /**
     * @dataProvider responsiveBackgroundKeysProvider
     */
    public function testRewriteRowResponsiveBackgrounds(string $key): void
    {
        $src = $this->uploadUrl('2024/01/responsive-bg.jpg');
        $result = $this->module->rewriteRowLayoutAttributes([
            $key => [['bgImg' => $src]],
        ]);
        $this->assertCloudinaryUrl($result[$key][0]['bgImg'], 'responsive-bg.jpg');
    }

    public static function responsiveOverlayKeysProvider(): array
    {
        return [
            'tabletOverlay' => ['tabletOverlay'],
            'mobileOverlay' => ['mobileOverlay'],
        ];
    }

    /**
     * @dataProvider responsiveOverlayKeysProvider
     */
    public function testRewriteRowResponsiveOverlays(string $key): void
    {
        $src = $this->uploadUrl('2024/01/overlay.jpg');
        $result = $this->module->rewriteRowLayoutAttributes([
            $key => [['overlayBgImg' => $src]],
        ]);
        $this->assertCloudinaryUrl($result[$key][0]['overlayBgImg'], 'overlay.jpg');
    }

    public static function videoKeysProvider(): array
    {
        return [
            'backgroundVideo' => ['backgroundVideo'],
            'tabletBackgroundVideo' => ['tabletBackgroundVideo'],
            'mobileBackgroundVideo' => ['mobileBackgroundVideo'],
        ];
    }

    /**
     * @dataProvider videoKeysProvider
     */
    public function testRewriteRowVideoAttributes(string $key): void
    {
        $src = $this->uploadUrl('2024/01/video.mp4');
        $result = $this->module->rewriteRowLayoutAttributes([
            $key => [['local' => $src]],
        ]);
        $this->assertCloudinaryUrl($result[$key][0]['local'], 'video.mp4');
    }

    public static function columnAttributeKeysProvider(): array
    {
        return [
            'backgroundImg' => ['backgroundImg'],
            'backgroundImgHover' => ['backgroundImgHover'],
        ];
    }

    /**
     * @dataProvider columnAttributeKeysProvider
     */
    public function testRewriteColumnNestedAttributes(string $key): void
    {
        $src = $this->uploadUrl('2024/01/column-bg.jpg');
        $result = $this->module->rewriteColumnAttributes([
            $key => [['bgImg' => $src]],
        ]);
        $this->assertCloudinaryUrl($result[$key][0]['bgImg'], 'column-bg.jpg');
    }

    public function testEmptyAttributesPassThrough(): void
    {
        $this->assertEquals([], $this->module->rewriteRowLayoutAttributes([]));
        $this->assertEquals([], $this->module->rewriteColumnAttributes([]));
    }
}
