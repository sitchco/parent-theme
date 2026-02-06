<?php

namespace Sitchco\Parent\Modules\SavePatternsToTheme;

use Sitchco\Framework\Module;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Save Patterns to Theme Module
 *
 * Adds a "Save to Theme" bulk action in the WordPress admin patterns interface
 * that exports selected wp_block patterns to the child theme's patterns directory.
 *
 * This module only activates in local environments for development use.
 */
class SavePatternsToThemeModule extends Module
{
    private const RESULT_CREATED = 'created';
    private const RESULT_UPDATED = 'updated';
    private const RESULT_UNCHANGED = 'unchanged';

    private const PLACEHOLDER_IMAGE = 'https://cdn.sitch.co/rtc/placeholder-image.png';
    private const PLACEHOLDER_VIDEO = 'https://cdn.sitch.co/rtc/placeholder-video.mp4';

    private const LOREM_POOL = [
        'lorem',
        'ipsum',
        'dolor',
        'sit',
        'amet',
        'consectetur',
        'adipiscing',
        'elit',
        'sed',
        'do',
        'eiusmod',
        'tempor',
        'incididunt',
        'ut',
        'labore',
        'et',
        'dolore',
        'magna',
        'aliqua',
        'enim',
        'ad',
        'minim',
        'veniam',
        'quis',
        'nostrud',
        'exercitation',
        'ullamco',
        'laboris',
        'nisi',
        'aliquip',
        'ex',
        'ea',
        'commodo',
        'consequat',
        'duis',
        'aute',
        'irure',
        'in',
        'reprehenderit',
        'voluptate',
        'velit',
        'esse',
        'cillum',
        'fugiat',
        'nulla',
        'pariatur',
        'excepteur',
        'sint',
        'occaecat',
        'cupidatat',
        'non',
        'proident',
        'sunt',
        'culpa',
        'qui',
        'officia',
        'deserunt',
        'mollit',
        'anim',
        'id',
        'est',
        'laborum',
        'cras',
    ];

    private string $patternsDir;

    /** @var array<string, true> Slugs used within the current batch to prevent collisions. */
    private array $usedSlugs = [];

    public function __construct()
    {
        $this->patternsDir = get_stylesheet_directory() . '/patterns';
    }

    public function init(): void
    {
        // Only activate in local environment
        if (wp_get_environment_type() !== 'local') {
            return;
        }

        // Register REST API endpoint
        add_action('rest_api_init', [$this, 'registerRestRoute']);

        // Enqueue admin scripts for the patterns page
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
    }

    /**
     * Register REST API route for saving patterns.
     */
    public function registerRestRoute(): void
    {
        register_rest_route('theme/v1', '/save-patterns', [
            'methods' => 'POST',
            'callback' => [$this, 'handleRestRequest'],
            'permission_callback' => function () {
                return current_user_can('edit_theme_options');
            },
            'args' => [
                'pattern_ids' => [
                    'required' => true,
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                ],
            ],
        ]);
    }

    /**
     * Handle the REST API request to save patterns.
     */
    public function handleRestRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $patternIds = $request->get_param('pattern_ids');
        $this->usedSlugs = [];

        $created = [];
        $updated = [];
        $unchanged = [];
        $errors = [];

        foreach ($patternIds as $postId) {
            $result = $this->savePatternToTheme((int) $postId);
            $post = get_post($postId);
            $title = $post ? $post->post_title : "ID: {$postId}";

            switch ($result) {
                case self::RESULT_CREATED:
                    $created[] = $title;
                    break;
                case self::RESULT_UPDATED:
                    $updated[] = $title;
                    break;
                case self::RESULT_UNCHANGED:
                    $unchanged[] = $title;
                    break;
                default:
                    // Error message string
                    $errors[] = "{$title}: {$result}";
                    break;
            }
        }

        return new WP_REST_Response([
            'success' => empty($errors),
            'created' => $created,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'errors' => $errors,
        ]);
    }

    /**
     * Enqueue admin scripts for the patterns editor.
     */
    public function enqueueAdminScripts(string $hook): void
    {
        // Load on site editor where patterns are managed
        $validHooks = ['site-editor.php', 'appearance_page_gutenberg-edit-site'];

        if (!in_array($hook, $validHooks, true)) {
            return;
        }

        wp_enqueue_script(
            'save-patterns-to-theme',
            $this->path('assets/save-patterns.js')->url(),
            ['wp-dom-ready', 'wp-api-fetch', 'wp-data', 'wp-core-data'],
            filemtime($this->path('assets/save-patterns.js')->value()),
            true,
        );
    }

    /**
     * Save a single pattern to the theme's patterns directory.
     *
     * @return string Result constant or error message
     */
    private function savePatternToTheme(int $postId): string
    {
        $post = get_post($postId);

        if (!$post || $post->post_type !== 'wp_block') {
            return 'Invalid pattern post';
        }

        $slug = $this->generateSlug($post->post_title);

        // Resolve cross-request slug collisions with files owned by different posts
        $candidate = $slug;
        $counter = 2;
        while (file_exists($this->patternsDir . '/' . $candidate . '.php')) {
            $headers = get_file_data($this->patternsDir . '/' . $candidate . '.php', [
                'source_post_id' => 'Source Post ID',
            ]);
            if ((int) $headers['source_post_id'] === $postId || empty($headers['source_post_id'])) {
                break;
            }
            $candidate = $slug . '-' . $counter;
            $counter++;
        }
        $slug = $candidate;

        $filename = $slug . '.php';
        $filepath = $this->patternsDir . '/' . $filename;

        // Ensure patterns directory exists
        if (!is_dir($this->patternsDir)) {
            if (!mkdir($this->patternsDir, 0755, true)) {
                return 'Could not create patterns directory';
            }
        }

        // Clean up stale file if the pattern was previously saved under a different title
        $existingFile = $this->findExistingFileForPost($postId);
        if ($existingFile && $existingFile !== $filepath) {
            unlink($existingFile);
        }

        $newContent = $this->formatPatternFile($post, $slug);

        // Check if file exists and compare hashes
        if (file_exists($filepath)) {
            $existingContent = file_get_contents($filepath);

            if ($existingContent === $newContent) {
                return self::RESULT_UNCHANGED;
            }

            // Content differs, update the file
            if (file_put_contents($filepath, $newContent) === false) {
                return "Could not update file: {$filename}";
            }

            return self::RESULT_UPDATED;
        }

        // File doesn't exist, create it
        if (file_put_contents($filepath, $newContent) === false) {
            return "Could not write file: {$filename}";
        }

        return self::RESULT_CREATED;
    }

    /**
     * Generate a slug from the pattern title.
     */
    private function generateSlug(string $title): string
    {
        $slug = sanitize_title($title);
        // Remove any leading numbers and dashes that sanitize_title might leave
        $slug = preg_replace('/^[\d-]+/', '', $slug);
        $slug = $slug ?: 'pattern';

        // Deduplicate against slugs already used in this batch
        $candidate = $slug;
        $counter = 2;
        while (isset($this->usedSlugs[$candidate])) {
            $candidate = $slug . '-' . $counter;
            $counter++;
        }

        $this->usedSlugs[$candidate] = true;
        return $candidate;
    }

    /**
     * Format the pattern content as a PHP file with proper headers.
     */
    private function formatPatternFile(\WP_Post $post, string $slug): string
    {
        $themeName = wp_get_theme()->get('TextDomain') ?: 'theme';
        $title = str_replace('*/', '', $post->post_title);
        $fullSlug = "{$themeName}/{$slug}";

        // Extract categories (slugs) and keywords (names) from taxonomy
        $categories = str_replace('*/', '', $this->getPatternCategories($post->ID));
        $categoriesLine = $categories ? " * Categories: {$categories}\n" : '';
        $keywords = str_replace('*/', '', $this->getPatternKeywords($post->ID));
        $keywordsLine = $keywords ? " * Keywords: {$keywords}\n" : '';

        $header = <<<PHP
        <?php
        /**
         * Title: {$title}
         * Slug: {$fullSlug}
         * Source Post ID: {$post->ID}
         * Description:
        {$categoriesLine}{$keywordsLine} */
        ?>

        PHP;

        $content = str_replace(['<?php', '<?', '?>'], '', $post->post_content);
        $content = $this->sanitizePatternContent($content);
        return $header . $content . "\n";
    }

    /**
     * Get category slugs for a pattern post.
     */
    private function getPatternCategories(int $postId): string
    {
        $terms = wp_get_post_terms($postId, 'wp_pattern_category', ['fields' => 'slugs']);

        if (is_wp_error($terms) || empty($terms)) {
            return '';
        }

        return implode(', ', $terms);
    }

    /**
     * Get keywords from pattern categories.
     */
    private function getPatternKeywords(int $postId): string
    {
        $terms = wp_get_post_terms($postId, 'wp_pattern_category', ['fields' => 'names']);

        if (is_wp_error($terms) || empty($terms)) {
            return '';
        }

        return implode(', ', $terms);
    }

    /**
     * Sanitize pattern content by replacing text with Lorem Ipsum,
     * images with placeholder, and videos with placeholder.
     */
    private function sanitizePatternContent(string $content): string
    {
        // Step 1: Replace bgImg URLs in block JSON comments
        $content = preg_replace('/"bgImg":"[^"]*"/', '"bgImg":"' . self::PLACEHOLDER_IMAGE . '"', $content);
        $content = preg_replace('/"bgImgID":\d+/', '"bgImgID":0', $content);

        // Step 2: Replace video URLs in block JSON comments
        $content = preg_replace('/"local":"[^"]*"/', '"local":"' . self::PLACEHOLDER_VIDEO . '"', $content);
        $content = preg_replace('/"localID":"[^"]*"/', '"localID":""', $content);
        $content = preg_replace('/"youTube":"[^"]*"/', '"youTube":""', $content);
        $content = preg_replace('/"vimeo":"[^"]*"/', '"vimeo":""', $content);

        // Step 3: Replace <img src="..."> with placeholder image
        $content = preg_replace('/(<img\b[^>]*\bsrc=")[^"]*(")/i', '$1' . self::PLACEHOLDER_IMAGE . '$2', $content);

        // Step 4: Clear alt text on images
        $content = preg_replace('/(<img\b[^>]*\balt=")[^"]*(")/i', '$1$2', $content);

        // Step 5: Replace real href URLs (http/https) with #
        $content = preg_replace('/(<a\b[^>]*\bhref=")https?:\/\/[^"]*(")/i', '$1#$2', $content);

        // Step 6: Remove id attributes from heading tags
        $content = preg_replace('/(<h[1-6]\b[^>]*)\s+id="[^"]*"/i', '$1', $content);

        // Step 7: Remove target and rel attributes from <a> tags
        $content = preg_replace('/(<a\b[^>]*)\s+target="[^"]*"/i', '$1', $content);
        $content = preg_replace('/(<a\b[^>]*)\s+rel="[^"]*"/i', '$1', $content);

        // Step 8: Replace text content in p, li, h1-h6, figcaption
        $content = preg_replace_callback(
            '/(<(?:p|li|h[1-6]|figcaption)\b[^>]*>)(.*?)(<\/(?:p|li|h[1-6]|figcaption)>)/is',
            function ($matches) {
                $openTag = $matches[1];
                $inner = $matches[2];
                $closeTag = $matches[3];

                $wordCount = $this->countWords($inner);
                if ($wordCount === 0) {
                    return $openTag . $inner . $closeTag;
                }

                return $openTag . $this->generateLoremIpsum($wordCount) . $closeTag;
            },
            $content,
        );

        // Step 9: Replace button text in <a class="wp-block-button__link...">
        $content = preg_replace_callback(
            '/(<a\b[^>]*class="[^"]*wp-block-button__link[^"]*"[^>]*>)(.*?)(<\/a>)/is',
            function ($matches) {
                $openTag = $matches[1];
                $inner = $matches[2];
                $closeTag = $matches[3];

                $wordCount = $this->countWords($inner);
                if ($wordCount === 0) {
                    return $openTag . $inner . $closeTag;
                }

                return $openTag . $this->generateLoremIpsum($wordCount) . $closeTag;
            },
            $content,
        );

        return $content;
    }

    /**
     * Generate deterministic Lorem Ipsum text of the given word count.
     */
    private function generateLoremIpsum(int $wordCount): string
    {
        if ($wordCount <= 0) {
            return '';
        }

        $pool = self::LOREM_POOL;
        $poolSize = count($pool);
        $words = [];

        for ($i = 0; $i < $wordCount; $i++) {
            $words[] = $pool[$i % $poolSize];
        }

        $words[0] = ucfirst($words[0]);

        return implode(' ', $words);
    }

    /**
     * Count words in HTML content, decoding entities and stripping inline tags.
     */
    private function countWords(string $text): int
    {
        // Decode HTML entities first (e.g. &amp; → &)
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Strip inline HTML tags (br, strong, em, span, a, etc.)
        $text = strip_tags($text);

        // Count words
        $text = trim($text);
        if ($text === '') {
            return 0;
        }

        return str_word_count($text);
    }

    /**
     * Find an existing pattern file that was saved from a given post ID.
     */
    private function findExistingFileForPost(int $postId): ?string
    {
        $files = glob($this->patternsDir . '/*.php');
        if (!$files) {
            return null;
        }

        foreach ($files as $file) {
            $headers = get_file_data($file, ['source_post_id' => 'Source Post ID']);
            if ((int) $headers['source_post_id'] === $postId) {
                return $file;
            }
        }

        return null;
    }
}
