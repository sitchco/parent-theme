<?php

namespace Sitchco\Parent\Tests;

use PHPUnit\Framework\TestCase;
use Sitchco\Parent\Modules\Patterns\PatternContentSanitizer;

class PatternContentSanitizerTest extends TestCase
{
    private PatternContentSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new PatternContentSanitizer();
    }

    // --- sanitizePatternContent ---

    /**
     * @dataProvider sanitizePatternContentProvider
     */
    public function testSanitizePatternContent(
        string $input,
        string $containsExpected,
        ?string $notContainsExpected = null,
    ): void {
        $result = $this->sanitizer->sanitizePatternContent($input);
        $this->assertStringContainsString($containsExpected, $result);
        if ($notContainsExpected !== null) {
            $this->assertStringNotContainsString($notContainsExpected, $result);
        }
    }

    public static function sanitizePatternContentProvider(): array
    {
        $placeholder = 'https://cdn.sitch.co/rtc/placeholder-image.png';
        $videoPlaceholder = 'https://cdn.sitch.co/rtc/placeholder-video.mp4';
        return [
            'bgImg URL replaced' => [
                '"bgImg":"https://example.com/image.jpg"',
                '"bgImg":"' . $placeholder . '"',
                'example.com',
            ],
            'bgImgID replaced with 0' => ['"bgImgID":42', '"bgImgID":0'],
            'video local URL replaced' => [
                '"local":"https://example.com/video.mp4"',
                '"local":"' . $videoPlaceholder . '"',
                'example.com',
            ],
            'youtube URL cleared' => ['"youTube":"https://youtube.com/watch?v=abc"', '"youTube":""', 'youtube.com'],
            'vimeo URL cleared' => ['"vimeo":"https://vimeo.com/123"', '"vimeo":""', 'vimeo.com'],
            'img src replaced' => [
                '<img src="https://example.com/photo.jpg" alt="Photo">',
                'src="' . $placeholder . '"',
                'example.com/photo.jpg',
            ],
            'alt text cleared' => [
                '<img src="placeholder.jpg" alt="A beautiful photo">',
                'alt=""',
                'A beautiful photo',
            ],
            'http href replaced with #' => ['<a href="https://example.com/page">Link</a>', 'href="#"', 'example.com'],
            'heading id removed' => [
                '<h2 id="my-heading" class="wp-block-heading">Title</h2>',
                '<h2 class="wp-block-heading">',
                'id="my-heading"',
            ],
            'anchor target removed' => [
                '<a href="#" target="_blank" rel="noopener">Link</a>',
                '<a href="#"',
                'target="_blank"',
            ],
        ];
    }

    public function testSanitizePatternContentReplacesTextWithLoremIpsum(): void
    {
        $input = '<p>This is some real content text</p>';
        $result = $this->sanitizer->sanitizePatternContent($input);
        $this->assertStringContainsString('<p>', $result);
        $this->assertStringContainsString('</p>', $result);
        $this->assertStringNotContainsString('This is some real content text', $result);
        preg_match('/<p>(.+?)<\/p>/', $result, $matches);
        $this->assertNotEmpty($matches[1]);
        $this->assertTrue(ctype_upper($matches[1][0]));
    }

    public function testSanitizePatternContentPreservesNonHttpHrefs(): void
    {
        $input = '<a href="#anchor">Link</a>';
        $result = $this->sanitizer->sanitizePatternContent($input);
        $this->assertStringContainsString('href="#anchor"', $result);
    }

    public function testSanitizePatternContentReplacesButtonText(): void
    {
        $input = '<a class="wp-block-button__link" href="#">Click Here</a>';
        $result = $this->sanitizer->sanitizePatternContent($input);
        $this->assertStringNotContainsString('Click Here', $result);
    }

    // --- generateLoremIpsum ---

    public function testGenerateLoremIpsumReturnsCorrectWordCount(): void
    {
        $result = $this->sanitizer->generateLoremIpsum(5);
        $this->assertSame(5, str_word_count($result));
    }

    public function testGenerateLoremIpsumStartsWithLoremIpsum(): void
    {
        $result = $this->sanitizer->generateLoremIpsum(5);
        $this->assertSame('Lorem ipsum dolor sit amet', $result);
    }

    public function testGenerateLoremIpsumReturnsEmptyForZero(): void
    {
        $this->assertSame('', $this->sanitizer->generateLoremIpsum(0));
    }

    public function testGenerateLoremIpsumWrapsAroundPool(): void
    {
        $result = $this->sanitizer->generateLoremIpsum(70);
        $this->assertSame(70, str_word_count($result));
    }

    // --- countWords ---

    /**
     * @dataProvider countWordsProvider
     */
    public function testCountWords(string $input, int $expected): void
    {
        $this->assertSame($expected, $this->sanitizer->countWords($input));
    }

    public static function countWordsProvider(): array
    {
        return [
            'plain text' => ['hello world', 2],
            'with HTML tags' => ['<strong>hello</strong> world', 2],
            'empty string' => ['', 0],
            'HTML entities decoded' => ['hello&nbsp;world test', 3],
            'only tags' => ['<br/>', 0],
            'nested tags' => ['<p><em>one</em> <strong>two</strong></p>', 2],
        ];
    }
}
