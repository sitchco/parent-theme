<?php

namespace Sitchco\Parent\Modules\SyncedPatterns\Commands;

use Sitchco\Parent\Modules\SyncedPatterns\PatternSync;
use WP_CLI;

/**
 * WP-CLI commands for synced patterns.
 */
class SyncCommand
{
    private PatternSync $sync;

    public function __construct(PatternSync $sync)
    {
        $this->sync = $sync;
    }

    /**
     * Sync theme patterns to wp_block posts.
     *
     * ## OPTIONS
     *
     * [<slug>]
     * : Specific pattern slug to sync. If omitted, syncs all patterns.
     *
     * [--force]
     * : Force sync even if strategy is 'preserve' or 'manual'.
     *
     * ## EXAMPLES
     *
     *     # Sync all patterns
     *     wp synced-patterns sync
     *
     *     # Sync specific pattern
     *     wp synced-patterns sync roundabout/icon-grid
     *
     *     # Force sync all patterns
     *     wp synced-patterns sync --force
     *
     * @when after_wp_load
     */
    public function sync(array $args, array $assocArgs): void
    {
        $force = WP_CLI\Utils\get_flag_value($assocArgs, 'force', false);

        if (!empty($args[0])) {
            // Sync specific pattern
            $slug = $args[0];
            WP_CLI::log("Syncing pattern: {$slug}");

            $result = $this->sync->syncBySlug($slug, $force);
            $this->outputResult($slug, $result);
            return;
        }

        // Sync all patterns
        WP_CLI::log('Syncing all theme patterns...');
        $results = $this->sync->syncAll($force);

        // Output results
        if (!empty($results['created'])) {
            WP_CLI::success('Created: ' . implode(', ', $results['created']));
        }

        if (!empty($results['updated'])) {
            WP_CLI::success('Updated: ' . implode(', ', $results['updated']));
        }

        if (!empty($results['skipped'])) {
            WP_CLI::log('Skipped: ' . implode(', ', $results['skipped']));
        }

        if (!empty($results['errors'])) {
            foreach ($results['errors'] as $slug => $message) {
                WP_CLI::error("Error syncing {$slug}: {$message}", false);
            }
        }

        $total = count($results['created']) + count($results['updated']);
        WP_CLI::success("Sync complete. {$total} pattern(s) synced.");
    }

    /**
     * Show sync status for all theme patterns.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format. Options: table, json, csv. Default: table.
     *
     * ## EXAMPLES
     *
     *     # Show status table
     *     wp synced-patterns status
     *
     *     # Output as JSON
     *     wp synced-patterns status --format=json
     *
     * @when after_wp_load
     */
    public function status(array $args, array $assocArgs): void
    {
        $format = $assocArgs['format'] ?? 'table';
        $status = $this->sync->getStatus();

        if (empty($status)) {
            WP_CLI::log('No synced patterns found in theme.');
            return;
        }

        $rows = [];
        foreach ($status as $slug => $info) {
            $rows[] = [
                'slug' => $slug,
                'status' => $info['status'],
                'message' => $info['message'],
                'post_id' => $info['post_id'] ?? '-',
            ];
        }

        WP_CLI\Utils\format_items($format, $rows, ['slug', 'status', 'message', 'post_id']);
    }

    /**
     * List all synced patterns from theme files.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format. Options: table, json, csv. Default: table.
     *
     * ## EXAMPLES
     *
     *     wp synced-patterns list
     *
     * @when after_wp_load
     */
    public function list(array $args, array $assocArgs): void
    {
        $format = $assocArgs['format'] ?? 'table';
        $patterns = $this->sync->getThemePatterns();

        if (empty($patterns)) {
            WP_CLI::log('No synced patterns found in theme. Add "Synced: true" to pattern headers.');
            return;
        }

        $rows = [];
        foreach ($patterns as $pattern) {
            $rows[] = [
                'slug' => $pattern['headers']['slug'],
                'title' => $pattern['headers']['title'],
                'strategy' => $pattern['headers']['syncStrategy'],
                'file' => basename($pattern['file']),
            ];
        }

        WP_CLI\Utils\format_items($format, $rows, ['slug', 'title', 'strategy', 'file']);
    }

    /**
     * Output result for a single pattern sync.
     */
    private function outputResult(string $slug, array $result): void
    {
        switch ($result['status']) {
            case 'created':
                WP_CLI::success("Created pattern '{$slug}' (Post ID: {$result['post_id']})");
                break;
            case 'updated':
                WP_CLI::success("Updated pattern '{$slug}' (Post ID: {$result['post_id']})");
                break;
            case 'skipped':
                WP_CLI::log("Skipped '{$slug}': {$result['message']}");
                break;
            case 'error':
                WP_CLI::error("Error syncing '{$slug}': {$result['message']}");
                break;
        }
    }
}
