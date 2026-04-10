<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Parent\Modules\InlineSVG\InlineSVGModule;
use Sitchco\Tests\TestCase;

class InlineSVGModuleTest extends TestCase
{
    protected InlineSVGModule $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = $this->container->get(InlineSVGModule::class);
        $this->fakeHttpWithFixtureSvg();
    }

    protected function tearDown(): void
    {
        $this->restoreHttp();
        parent::tearDown();
    }

    private function fakeHttpWithFixtureSvg(): void
    {
        $svgPath = dirname(__DIR__) . '/tests/fixtures/test-icon.svg';
        $this->fakeHttp(
            fn() => [
                'response' => ['code' => 200],
                'body' => file_get_contents($svgPath),
            ],
        );
    }

    private function makeBlock(string $html, array $attrs = []): array
    {
        return [
            'blockName' => 'kadence/image',
            'attrs' => $attrs,
            'innerBlocks' => [],
            'innerHTML' => $html,
            'innerContent' => [$html],
        ];
    }

    public function testSvgUploadMimeTypeAllowed(): void
    {
        $mimes = apply_filters('upload_mimes', []);

        $this->assertArrayHasKey('svg', $mimes);
        $this->assertEquals('image/svg+xml', $mimes['svg']);
    }

    public function testBlockTypeMetadataSettingsAddsInlineSvgAttribute(): void
    {
        $settings = apply_filters(
            'block_type_metadata_settings',
            [
                'attributes' => [],
            ],
            ['name' => 'core/image'],
        );

        $this->assertArrayHasKey('inlineSvg', $settings['attributes']);
        $this->assertEquals('boolean', $settings['attributes']['inlineSvg']['type']);
        $this->assertFalse($settings['attributes']['inlineSvg']['default']);
    }

    public function testBlockTypeMetadataSettingsIgnoresOtherBlocks(): void
    {
        $settings = apply_filters(
            'block_type_metadata_settings',
            [
                'attributes' => [],
            ],
            ['name' => 'core/paragraph'],
        );

        $this->assertArrayNotHasKey('inlineSvg', $settings['attributes']);
    }

    public function testKadenceImageRenderFilterInlinesSvg(): void
    {
        $html = '<figure><img src="https://example.com/icon.svg" alt="Test"></figure>';
        $block = $this->makeBlock($html, ['width' => 32, 'imgMaxWidth' => 400]);

        $result = apply_filters('render_block_kadence/image', $html, $block);

        $this->assertStringContainsString('<svg', $result);
        $this->assertStringNotContainsString('<img', $result);
    }

    public function testImageBlockPassesWidthAndMaxWidthToService(): void
    {
        $html = '<figure><img src="https://example.com/icon.svg"></figure>';
        $block = $this->makeBlock($html, ['width' => 120, 'imgMaxWidth' => 500]);

        $result = apply_filters('render_block_kadence/image', $html, $block);

        $p = new \WP_HTML_Tag_Processor($result);
        $p->next_tag('svg');
        $style = $p->get_attribute('style');
        $this->assertStringContainsString('width: 120px', $style);
        $this->assertStringContainsString('max-width: 500px', $style);
    }
}
