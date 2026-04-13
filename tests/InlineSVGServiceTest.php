<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Parent\Modules\InlineSVG\InlineSVGService;
use Sitchco\Tests\TestCase;

class InlineSVGServiceTest extends TestCase
{
    protected InlineSVGService $service;
    protected string $svgFixturePath;
    protected array $localFileOptions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->container->get(InlineSVGService::class);
        $this->svgFixturePath = dirname(__DIR__) . '/tests/fixtures/test-icon.svg';
        $this->localFileOptions = [
            'file_path_resolver' => fn() => $this->svgFixturePath,
        ];
    }

    protected function tearDown(): void
    {
        $this->restoreHttp();
        parent::tearDown();
    }

    private function makeBlock(array $attrs = []): array
    {
        return [
            'blockName' => 'kadence/image',
            'attrs' => $attrs,
            'innerBlocks' => [],
            'innerHTML' => '',
            'innerContent' => [''],
        ];
    }

    private function inlineSvg(string $html, array $block = [], array $options = []): string
    {
        return $this->service->replaceImageBlock(
            $html,
            $block ?: $this->makeBlock(),
            array_merge($this->localFileOptions, $options),
        );
    }

    private function getSvgAttribute(string $html, string $attribute): ?string
    {
        $p = new \WP_HTML_Tag_Processor($html);
        $p->next_tag('svg');
        return $p->get_attribute($attribute);
    }

    public function testReturnsUnchangedContentWhenNoImgTag(): void
    {
        $html = '<div class="wp-block-image"><p>No image here</p></div>';

        $result = $this->service->replaceImageBlock($html, $this->makeBlock());

        $this->assertEquals($html, $result);
    }

    public function testReturnsUnchangedContentForNonSvgImage(): void
    {
        $html = '<figure><img src="https://example.com/photo.jpg" alt="Photo" width="100" height="100"></figure>';

        $result = $this->service->replaceImageBlock($html, $this->makeBlock());

        $this->assertEquals($html, $result);
    }

    public function testReplacesImgWithInlineSvg(): void
    {
        $html = '<figure><img src="https://example.com/icon.svg" alt="Icon" width="48" height="48"></figure>';

        $result = $this->inlineSvg($html);

        $this->assertStringNotContainsString('<img', $result);
        $this->assertStringContainsString('<svg', $result);
    }

    public function testPreservesAltAsAriaLabel(): void
    {
        $html = '<figure><img src="https://example.com/icon.svg" alt="Close icon"></figure>';

        $result = $this->inlineSvg($html);

        $this->assertStringContainsString('aria-label="Close icon"', $result);
        $this->assertStringContainsString('role="img"', $result);
    }

    public function testPreservesWidthAndHeightFromImg(): void
    {
        $html = '<figure><img src="https://example.com/icon.svg" width="64" height="64"></figure>';

        $result = $this->inlineSvg($html);

        $this->assertEquals('64', $this->getSvgAttribute($result, 'width'));
        $this->assertEquals('64', $this->getSvgAttribute($result, 'height'));
    }

    /**
     * @dataProvider styleOptionsProvider
     */
    public function testAppliesStyleOptions(
        array $options,
        string $expectedStyle,
        ?string $unexpectedStyle = null,
    ): void {
        $html = '<figure><img src="https://example.com/icon.svg"></figure>';

        $result = $this->inlineSvg($html, options: $options);
        $style = $this->getSvgAttribute($result, 'style');

        $this->assertStringContainsString($expectedStyle, $style);
        if ($unexpectedStyle) {
            $this->assertStringNotContainsString($unexpectedStyle, $style);
        }
    }

    public static function styleOptionsProvider(): array
    {
        return [
            'default max-width and height' => [[], 'max-width: 100%; height: auto', null],
            'explicit width' => [['width' => 200], 'width: 200px', null],
            'max_width overrides default' => [['max_width' => 300], 'max-width: 300px', 'max-width: 100%'],
        ];
    }

    public function testMergesWithExistingSvgStyleAttribute(): void
    {
        $styledSvgPath = dirname(__DIR__) . '/tests/fixtures/test-icon-styled.svg';
        $html = '<figure><img src="https://example.com/icon.svg"></figure>';

        $result = $this->inlineSvg(
            $html,
            options: [
                'file_path_resolver' => fn() => $styledSvgPath,
            ],
        );
        $style = $this->getSvgAttribute($result, 'style');

        $this->assertStringContainsString('--test-color:red', $style);
        $this->assertStringContainsString('max-width: 100%', $style);
    }

    public function testSetsIdWithPrefixWhenBlockHasId(): void
    {
        $html = '<figure><img src="https://example.com/icon.svg"></figure>';

        $result = $this->inlineSvg($html, $this->makeBlock(['id' => 42]));

        $this->assertStringContainsString('id="inline-svg-42"', $result);
    }

    public function testCustomSvgIdPrefix(): void
    {
        $html = '<figure><img src="https://example.com/icon.svg"></figure>';

        $result = $this->inlineSvg($html, $this->makeBlock(['id' => 7]), [
            'svg_id_prefix' => 'logo-',
        ]);

        $this->assertStringContainsString('id="logo-7"', $result);
    }

    public function testFetchesRemoteSvgWhenResolvedPathDoesNotExist(): void
    {
        $remoteSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><rect width="16" height="16"/></svg>';
        $this->fakeHttp(fn() => ['response' => ['code' => 200], 'body' => $remoteSvg]);

        $html = '<figure><img src="https://cdn.example.com/icon.svg"></figure>';

        $result = $this->service->replaceImageBlock($html, $this->makeBlock(), [
            'file_path_resolver' => fn() => '/nonexistent/path.svg',
        ]);

        $this->assertStringContainsString('<svg', $result);
        $this->assertStringNotContainsString('<img', $result);
    }

    public function testReturnsUnchangedWhenRemoteSvgFetchFails(): void
    {
        $this->fakeHttp(fn() => ['response' => ['code' => 500], 'body' => 'Server Error']);

        $html = '<figure><img src="https://cdn.example.com/icon.svg"></figure>';

        $result = $this->service->replaceImageBlock($html, $this->makeBlock(), [
            'file_path_resolver' => fn() => '/nonexistent/path.svg',
        ]);

        $this->assertEquals($html, $result);
    }

    public function testReturnsUnchangedWhenRemoteResponseIsNotSvg(): void
    {
        $this->fakeHttp(fn() => ['response' => ['code' => 200], 'body' => '<html><body>Not an SVG</body></html>']);

        $html = '<figure><img src="https://cdn.example.com/icon.svg"></figure>';

        $result = $this->service->replaceImageBlock($html, $this->makeBlock(), [
            'file_path_resolver' => fn() => '/nonexistent/path.svg',
        ]);

        $this->assertEquals($html, $result);
    }
}
