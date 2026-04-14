<?php

namespace Sitchco\Parent\Modules\Patterns;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Save Patterns to Theme
 *
 * Adds a "Save to Theme" bulk action in the WordPress admin patterns interface
 * that exports selected wp_block patterns to the child theme's patterns directory.
 *
 * This service only activates in local environments for development use.
 */
class SavePatternsToTheme
{
    private const RESULT_CREATED = 'created';
    private const RESULT_UPDATED = 'updated';
    private const RESULT_UNCHANGED = 'unchanged';

    private string $patternsDir;

    /** @var array<string, true> Slugs used within the current batch to prevent collisions. */
    private array $usedSlugs = [];

    public function __construct(private readonly PatternContentSanitizer $sanitizer)
    {
        $this->patternsDir = get_stylesheet_directory() . '/patterns';
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

        if ($created || $updated) {
            $stylesheet = basename(get_stylesheet_directory());
            delete_transient('wp_theme_files_patterns-' . $stylesheet);
            $template = basename(get_template_directory());
            if ($template !== $stylesheet) {
                delete_transient('wp_theme_files_patterns-' . $template);
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
     * Save a single pattern to the theme's patterns directory.
     *
     * @return string Result constant or error message
     */
    public function savePatternToTheme(int $postId): string
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
        $themeName = basename(get_stylesheet_directory());
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
        $content = $this->sanitizer->sanitizePatternContent($content);
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
