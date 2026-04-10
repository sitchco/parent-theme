<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Parent\Modules\Patterns\SavePatternsToTheme;
use Sitchco\Tests\TestCase;

class SavePatternsToThemeTest extends TestCase
{
    protected SavePatternsToTheme $service;
    private string $patternsDir;
    private array $createdFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->container->get(SavePatternsToTheme::class);
        $this->service->resetUsedSlugs();
        $this->patternsDir = get_stylesheet_directory() . '/patterns';
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    // --- generateSlug ---

    /**
     * @dataProvider generateSlugProvider
     */
    public function testGenerateSlug(string $title, string $expected): void
    {
        $this->assertSame($expected, $this->service->generateSlug($title));
    }

    public static function generateSlugProvider(): array
    {
        return [
            'basic title' => ['My Pattern', 'my-pattern'],
            'leading numbers stripped' => ['123 Numbered Title', 'numbered-title'],
            'empty title falls back' => ['', 'pattern'],
            'only numbers falls back' => ['123', 'pattern'],
            'dashes only falls back' => ['---', 'pattern'],
        ];
    }

    public function testGenerateSlugDeduplicatesWithinBatch(): void
    {
        $first = $this->service->generateSlug('Same Title');
        $second = $this->service->generateSlug('Same Title');
        $third = $this->service->generateSlug('Same Title');

        $this->assertSame('same-title', $first);
        $this->assertSame('same-title-2', $second);
        $this->assertSame('same-title-3', $third);
    }

    public function testResetUsedSlugsClearsBatchState(): void
    {
        $this->service->generateSlug('Test');
        $this->service->resetUsedSlugs();
        $result = $this->service->generateSlug('Test');
        $this->assertSame('test', $result);
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
        $result = $this->service->sanitizePatternContent($input);
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
        $result = $this->service->sanitizePatternContent($input);
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
        $result = $this->service->sanitizePatternContent($input);
        $this->assertStringContainsString('href="#anchor"', $result);
    }

    public function testSanitizePatternContentReplacesButtonText(): void
    {
        $input = '<a class="wp-block-button__link" href="#">Click Here</a>';
        $result = $this->service->sanitizePatternContent($input);
        $this->assertStringNotContainsString('Click Here', $result);
    }

    // --- generateLoremIpsum ---

    public function testGenerateLoremIpsumReturnsCorrectWordCount(): void
    {
        $result = $this->service->generateLoremIpsum(5);
        $this->assertSame(5, str_word_count($result));
    }

    public function testGenerateLoremIpsumStartsWithLoremIpsum(): void
    {
        $result = $this->service->generateLoremIpsum(5);
        $this->assertSame('Lorem ipsum dolor sit amet', $result);
    }

    public function testGenerateLoremIpsumReturnsEmptyForZero(): void
    {
        $this->assertSame('', $this->service->generateLoremIpsum(0));
    }

    public function testGenerateLoremIpsumWrapsAroundPool(): void
    {
        $result = $this->service->generateLoremIpsum(70);
        $this->assertSame(70, str_word_count($result));
    }

    // --- countWords ---

    /**
     * @dataProvider countWordsProvider
     */
    public function testCountWords(string $input, int $expected): void
    {
        $this->assertSame($expected, $this->service->countWords($input));
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

    // --- formatPatternFile ---

    public function testFormatPatternFileGeneratesCorrectHeaders(): void
    {
        $post = $this->factory()->post->create_and_get([
            'post_type' => 'wp_block',
            'post_title' => 'Test Pattern',
            'post_content' => '<p>Hello World</p>',
        ]);

        $result = $this->service->formatPatternFile($post, 'test-pattern');
        $this->assertStringContainsString('Title: Test Pattern', $result);
        $this->assertStringContainsString('Slug:', $result);
        $this->assertStringContainsString('/test-pattern', $result);
        $this->assertStringContainsString("Source Post ID: {$post->ID}", $result);
        $this->assertStringNotContainsString('Hello World', $result);
    }

    public function testFormatPatternFileStripsPhpTags(): void
    {
        $post = $this->factory()->post->create_and_get([
            'post_type' => 'wp_block',
            'post_title' => 'PHP Pattern',
            'post_content' => '<?php echo "test"; ?><p>Content</p>',
        ]);

        $result = $this->service->formatPatternFile($post, 'php-pattern');
        $phpTagCount = substr_count($result, '<?php');
        $this->assertSame(1, $phpTagCount);
    }

    // --- savePatternToTheme ---

    public function testSavePatternToThemeCreatesFile(): void
    {
        $post = $this->factory()->post->create_and_get([
            'post_type' => 'wp_block',
            'post_title' => 'Save Test Pattern',
            'post_content' => '<p>Some content</p>',
        ]);

        $result = $this->service->savePatternToTheme($post->ID);
        $this->assertSame('created', $result);

        $filepath = $this->patternsDir . '/save-test-pattern.php';
        $this->createdFiles[] = $filepath;
        $this->assertFileExists($filepath);
    }

    public function testSavePatternToThemeReturnsUnchangedOnSecondCall(): void
    {
        $post = $this->factory()->post->create_and_get([
            'post_type' => 'wp_block',
            'post_title' => 'Unchanged Test',
            'post_content' => '<p>Same content</p>',
        ]);

        $this->service->savePatternToTheme($post->ID);
        $this->service->resetUsedSlugs();
        $result = $this->service->savePatternToTheme($post->ID);
        $this->assertSame('unchanged', $result);

        $this->createdFiles[] = $this->patternsDir . '/unchanged-test.php';
    }

    public function testSavePatternToThemeReturnsUpdatedOnContentChange(): void
    {
        $postId = $this->factory()->post->create([
            'post_type' => 'wp_block',
            'post_title' => 'Update Test',
            'post_content' => '<p>Original</p>',
        ]);

        $this->service->savePatternToTheme($postId);
        $this->service->resetUsedSlugs();

        wp_update_post(['ID' => $postId, 'post_content' => '<p>Updated content here now</p>']);

        $result = $this->service->savePatternToTheme($postId);
        $this->assertSame('updated', $result);

        $this->createdFiles[] = $this->patternsDir . '/update-test.php';
    }

    public function testSavePatternToThemeRejectsInvalidPost(): void
    {
        $result = $this->service->savePatternToTheme(999999);
        $this->assertSame('Invalid pattern post', $result);
    }

    public function testSavePatternToThemeRejectsNonWpBlockPost(): void
    {
        $postId = $this->factory()->post->create(['post_type' => 'post']);
        $result = $this->service->savePatternToTheme($postId);
        $this->assertSame('Invalid pattern post', $result);
    }
}
