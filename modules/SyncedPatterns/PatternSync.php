<?php

namespace Sitchco\Parent\Modules\SyncedPatterns;

/**
 * Handles synchronization of theme patterns to wp_block posts.
 */
class PatternSync
{
    private const META_SLUG = '_synced_pattern_slug';
    private const META_HASH = '_synced_pattern_hash';
    private const META_SOURCE = '_synced_pattern_source';

    private PatternParser $parser;

    public function __construct(PatternParser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Get all synced patterns from the active theme and parent theme.
     * Child theme patterns take precedence over parent theme patterns with the same slug.
     *
     * @return array Array of parsed pattern data
     */
    public function getThemePatterns(): array
    {
        $patterns = [];

        // Load parent theme patterns first
        $parentThemeDir = get_template_directory() . '/patterns';
        $childThemeDir = get_stylesheet_directory() . '/patterns';

        if ($parentThemeDir !== $childThemeDir && is_dir($parentThemeDir)) {
            $patterns = array_merge($patterns, $this->getPatternsFromDirectory($parentThemeDir));
        }

        // Then merge child theme patterns, allowing them to override parent patterns
        if (is_dir($childThemeDir)) {
            $patterns = array_merge($patterns, $this->getPatternsFromDirectory($childThemeDir));
        }

        // Filter to only synced patterns
        return array_filter($patterns, fn($pattern) => $this->parser->isSyncedPattern($pattern));
    }

    /**
     * Check if any synced patterns exist in the theme.
     */
    public function hasSyncedPatterns(): bool
    {
        return !empty($this->getThemePatterns());
    }

    /**
     * Get patterns from a specific directory.
     */
    private function getPatternsFromDirectory(string $directory): array
    {
        $patterns = [];
        $files = glob($directory . '/*.php');

        if ($files === false) {
            return $patterns;
        }

        foreach ($files as $file) {
            $pattern = $this->parser->parse($file);
            if ($pattern && !empty($pattern['headers']['slug'])) {
                $patterns[$pattern['headers']['slug']] = $pattern;
            }
        }

        return $patterns;
    }

    /**
     * Sync all theme patterns to wp_block posts.
     *
     * @param bool $force Force sync even if strategy is 'preserve'
     * @return array Results of sync operation
     */
    public function syncAll(bool $force = false): array
    {
        $patterns = $this->getThemePatterns();
        $results = [
            'created' => [],
            'updated' => [],
            'skipped' => [],
            'errors' => [],
        ];

        foreach ($patterns as $pattern) {
            $result = $this->syncPattern($pattern, $force);
            $slug = $pattern['headers']['slug'];

            switch ($result['status']) {
                case 'created':
                    $results['created'][] = $slug;
                    break;
                case 'updated':
                    $results['updated'][] = $slug;
                    break;
                case 'skipped':
                    $results['skipped'][] = $slug;
                    break;
                case 'error':
                    $results['errors'][$slug] = $result['message'];
                    break;
            }
        }

        return $results;
    }

    /**
     * Sync a specific pattern by slug.
     *
     * @param string $slug Pattern slug to sync
     * @param bool $force Force sync even if strategy is 'preserve'
     * @return array Result of sync operation
     */
    public function syncBySlug(string $slug, bool $force = false): array
    {
        $patterns = $this->getThemePatterns();

        if (!isset($patterns[$slug])) {
            return [
                'status' => 'error',
                'message' => "Pattern '{$slug}' not found in theme",
            ];
        }

        return $this->syncPattern($patterns[$slug], $force);
    }

    /**
     * Sync a single pattern to a wp_block post.
     */
    public function syncPattern(array $pattern, bool $force = false): array
    {
        $slug = $pattern['headers']['slug'];
        $strategy = $pattern['headers']['syncStrategy'];
        $existingPost = $this->findExistingPost($slug);

        // Check if sync is needed
        if ($existingPost) {
            $existingHash = get_post_meta($existingPost->ID, self::META_HASH, true);

            // If hashes match, no sync needed
            if ($existingHash === $pattern['hash']) {
                return [
                    'status' => 'skipped',
                    'message' => 'Content unchanged',
                    'post_id' => $existingPost->ID,
                ];
            }

            // Check sync strategy
            if (!$force && $strategy === 'preserve') {
                return [
                    'status' => 'skipped',
                    'message' => 'Preserve strategy - not overwriting CMS edits',
                    'post_id' => $existingPost->ID,
                ];
            }

            if (!$force && $strategy === 'manual') {
                return [
                    'status' => 'skipped',
                    'message' => 'Manual strategy - use --force to sync',
                    'post_id' => $existingPost->ID,
                ];
            }

            // Update existing post
            return $this->updatePost($existingPost->ID, $pattern);
        }

        // Create new post
        return $this->createPost($pattern);
    }

    /**
     * Find an existing wp_block post for a pattern slug.
     */
    private function findExistingPost(string $slug): ?\WP_Post
    {
        $posts = get_posts([
            'post_type' => 'wp_block',
            'post_status' => 'publish',
            'meta_key' => self::META_SLUG,
            'meta_value' => $slug,
            'posts_per_page' => 1,
        ]);

        return $posts[0] ?? null;
    }

    /**
     * Find all synced pattern posts in a single query.
     * Returns an associative array keyed by pattern slug.
     *
     * @return array<string, \WP_Post>
     */
    private function findAllSyncedPosts(): array
    {
        $posts = get_posts([
            'post_type' => 'wp_block',
            'post_status' => 'publish',
            'meta_key' => self::META_SLUG,
            'posts_per_page' => -1,
        ]);

        $indexed = [];
        foreach ($posts as $post) {
            $slug = get_post_meta($post->ID, self::META_SLUG, true);
            if ($slug) {
                $indexed[$slug] = $post;
            }
        }

        return $indexed;
    }

    /**
     * Create a new wp_block post for a pattern.
     */
    private function createPost(array $pattern): array
    {
        $postId = wp_insert_post([
            'post_type' => 'wp_block',
            'post_status' => 'publish',
            'post_title' => $pattern['headers']['title'],
            'post_content' => $pattern['content'],
        ], true);

        if (is_wp_error($postId)) {
            return [
                'status' => 'error',
                'message' => $postId->get_error_message(),
            ];
        }

        $this->updatePostMeta($postId, $pattern);

        return [
            'status' => 'created',
            'post_id' => $postId,
        ];
    }

    /**
     * Update an existing wp_block post.
     */
    private function updatePost(int $postId, array $pattern): array
    {
        $result = wp_update_post([
            'ID' => $postId,
            'post_title' => $pattern['headers']['title'],
            'post_content' => $pattern['content'],
        ], true);

        if (is_wp_error($result)) {
            return [
                'status' => 'error',
                'message' => $result->get_error_message(),
            ];
        }

        $this->updatePostMeta($postId, $pattern);

        return [
            'status' => 'updated',
            'post_id' => $postId,
        ];
    }

    /**
     * Update post meta for a synced pattern.
     */
    private function updatePostMeta(int $postId, array $pattern): void
    {
        update_post_meta($postId, self::META_SLUG, $pattern['headers']['slug']);
        update_post_meta($postId, self::META_HASH, $pattern['hash']);
        update_post_meta($postId, self::META_SOURCE, 'theme');

        // Store pattern categories for reference (or remove if empty)
        if (!empty($pattern['headers']['categories'])) {
            update_post_meta($postId, '_synced_pattern_categories', $pattern['headers']['categories']);
        } else {
            delete_post_meta($postId, '_synced_pattern_categories');
        }
    }

    /**
     * Get sync status for all theme patterns.
     * Uses a single batched query to fetch all synced posts.
     */
    public function getStatus(): array
    {
        $patterns = $this->getThemePatterns();
        $syncedPosts = $this->findAllSyncedPosts();
        $status = [];

        foreach ($patterns as $pattern) {
            $slug = $pattern['headers']['slug'];
            $existingPost = $syncedPosts[$slug] ?? null;

            if (!$existingPost) {
                $status[$slug] = [
                    'synced' => false,
                    'status' => 'not_synced',
                    'message' => 'Pattern not yet synced to database',
                ];
                continue;
            }

            $existingHash = get_post_meta($existingPost->ID, self::META_HASH, true);

            if ($existingHash === $pattern['hash']) {
                $status[$slug] = [
                    'synced' => true,
                    'status' => 'current',
                    'message' => 'Pattern is up to date',
                    'post_id' => $existingPost->ID,
                ];
            } else {
                $status[$slug] = [
                    'synced' => true,
                    'status' => 'outdated',
                    'message' => 'Theme pattern has changed, needs sync',
                    'post_id' => $existingPost->ID,
                    'strategy' => $pattern['headers']['syncStrategy'],
                ];
            }
        }

        return $status;
    }
}
