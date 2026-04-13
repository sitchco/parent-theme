<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Parent\Modules\Patterns\PatternContentSanitizer;
use Sitchco\Parent\Modules\Patterns\SavePatternsToTheme;
use Sitchco\Tests\TestCase;
use WP_REST_Request;

class SavePatternsToThemeTest extends TestCase
{
    protected SavePatternsToTheme $service;
    private string $patternsDir;
    private array $createdFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SavePatternsToTheme(new PatternContentSanitizer());
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

    // --- slug generation (tested through savePatternToTheme) ---

    /**
     * @dataProvider generateSlugProvider
     */
    public function testGenerateSlug(string $title, string $expectedFilename): void
    {
        $post = $this->factory()->post->create_and_get([
            'post_type' => 'wp_block',
            'post_title' => $title,
            'post_content' => '<p>Content</p>',
        ]);

        $result = $this->service->savePatternToTheme($post->ID);
        $this->assertSame('created', $result);

        $filepath = $this->patternsDir . '/' . $expectedFilename;
        $this->createdFiles[] = $filepath;
        $this->assertFileExists($filepath);
    }

    public static function generateSlugProvider(): array
    {
        return [
            'basic title' => ['My Pattern', 'my-pattern.php'],
            'leading numbers stripped' => ['123 Numbered Title', 'numbered-title.php'],
            'empty title falls back' => ['', 'pattern.php'],
            'only numbers falls back' => ['123', 'pattern.php'],
        ];
    }

    public function testGenerateSlugDeduplicatesWithinBatch(): void
    {
        $post1 = $this->factory()->post->create_and_get([
            'post_type' => 'wp_block',
            'post_title' => 'Duplicate Title',
            'post_content' => '<p>First</p>',
        ]);
        $post2 = $this->factory()->post->create_and_get([
            'post_type' => 'wp_block',
            'post_title' => 'Duplicate Title',
            'post_content' => '<p>Second</p>',
        ]);

        $request = new WP_REST_Request('POST', '/theme/v1/save-patterns');
        $request->set_param('pattern_ids', [$post1->ID, $post2->ID]);

        $response = $this->service->handleRestRequest($request);
        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['created']);

        $file1 = $this->patternsDir . '/duplicate-title.php';
        $file2 = $this->patternsDir . '/duplicate-title-2.php';
        $this->createdFiles[] = $file1;
        $this->createdFiles[] = $file2;
        $this->assertFileExists($file1);
        $this->assertFileExists($file2);
    }

    // --- formatPatternFile (tested through savePatternToTheme) ---

    public function testFormatPatternFileGeneratesCorrectHeaders(): void
    {
        $post = $this->factory()->post->create_and_get([
            'post_type' => 'wp_block',
            'post_title' => 'Test Pattern',
            'post_content' => '<p>Hello World</p>',
        ]);

        $this->service->savePatternToTheme($post->ID);

        $filepath = $this->patternsDir . '/test-pattern.php';
        $this->createdFiles[] = $filepath;
        $this->assertFileExists($filepath);

        $content = file_get_contents($filepath);
        $this->assertStringContainsString('Title: Test Pattern', $content);
        $this->assertStringContainsString('Slug:', $content);
        $this->assertStringContainsString('/test-pattern', $content);
        $this->assertStringContainsString("Source Post ID: {$post->ID}", $content);
        $this->assertStringNotContainsString('Hello World', $content);
    }

    public function testFormatPatternFileStripsPhpTags(): void
    {
        $post = $this->factory()->post->create_and_get([
            'post_type' => 'wp_block',
            'post_title' => 'PHP Pattern',
            'post_content' => '<?php echo "test"; ?><p>Content</p>',
        ]);

        $this->service->savePatternToTheme($post->ID);

        $filepath = $this->patternsDir . '/php-pattern.php';
        $this->createdFiles[] = $filepath;
        $this->assertFileExists($filepath);

        $content = file_get_contents($filepath);
        $phpTagCount = substr_count($content, '<?php');
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

        // Use handleRestRequest for the second save — it resets usedSlugs,
        // simulating a separate request as happens in production.
        $request = new WP_REST_Request('POST', '/theme/v1/save-patterns');
        $request->set_param('pattern_ids', [$post->ID]);
        $response = $this->service->handleRestRequest($request);
        $data = $response->get_data();

        $this->assertCount(1, $data['unchanged']);

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

        wp_update_post(['ID' => $postId, 'post_content' => '<p>Updated content here now</p>']);

        // Use handleRestRequest for the second save — it resets usedSlugs.
        $request = new WP_REST_Request('POST', '/theme/v1/save-patterns');
        $request->set_param('pattern_ids', [$postId]);
        $response = $this->service->handleRestRequest($request);
        $data = $response->get_data();

        $this->assertCount(1, $data['updated']);

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
