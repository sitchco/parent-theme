<?php

namespace Sitchco\Parent\Modules\SyncedPatterns;

use Sitchco\Framework\Module;
use Sitchco\Parent\Modules\SyncedPatterns\Commands\SyncCommand;

/**
 * Synced Patterns Module
 *
 * Synchronizes theme pattern files (with "Synced: true" header) to wp_block posts,
 * enabling version-controlled patterns that behave as synced patterns in the CMS.
 *
 * This module is "smart" - it only activates when patterns with "Synced: true" exist.
 * If no patterns are marked for syncing, the module remains dormant with no overhead.
 *
 * ## Usage
 *
 * Add "Synced: true" to any pattern file header:
 *
 * ```php
 * <?php
 * /**
 *  * Title: My Pattern
 *  * Slug: mytheme/my-pattern
 *  * Categories: featured
 *  * Synced: true
 *  * Sync-Strategy: overwrite
 *  * /
 * ?>
 * <!-- wp:paragraph -->
 * <p>Pattern content here</p>
 * <!-- /wp:paragraph -->
 * ```
 *
 * ## Sync Strategies
 *
 * - `overwrite` (default): Theme always wins, CMS edits are overwritten on sync
 * - `preserve`: Only create if missing, never overwrite CMS edits
 * - `manual`: Require --force flag to sync
 *
 * ## WP-CLI Commands
 *
 * - `wp synced-patterns sync` - Sync all patterns
 * - `wp synced-patterns sync <slug>` - Sync specific pattern
 * - `wp synced-patterns sync --force` - Force sync (ignore strategy)
 * - `wp synced-patterns status` - Show sync status
 * - `wp synced-patterns list` - List all synced patterns from theme
 */
class SyncedPatternsModule extends Module
{
    private PatternParser $parser;
    private PatternSync $sync;
    private ?bool $hasSyncedPatterns = null;

    public function __construct()
    {
        $this->parser = new PatternParser();
        $this->sync = new PatternSync($this->parser);
    }

    public function init(): void
    {
        // Always register WP-CLI commands (they handle empty state gracefully)
        if (defined('WP_CLI') && WP_CLI) {
            $this->registerCliCommands();
        }

        // Only register hooks if there are synced patterns
        if (!$this->hasSyncedPatterns()) {
            return;
        }

        // Auto-sync on theme switch
        add_action('after_switch_theme', [$this, 'onThemeSwitch']);

        // Add admin notice if patterns need syncing
        add_action('admin_notices', [$this, 'maybeShowSyncNotice']);
    }

    /**
     * Check if any synced patterns exist (cached).
     */
    private function hasSyncedPatterns(): bool
    {
        if ($this->hasSyncedPatterns === null) {
            $this->hasSyncedPatterns = $this->sync->hasSyncedPatterns();
        }

        return $this->hasSyncedPatterns;
    }

    /**
     * Register WP-CLI commands.
     */
    private function registerCliCommands(): void
    {
        $command = new SyncCommand($this->sync);

        \WP_CLI::add_command('synced-patterns sync', [$command, 'sync']);
        \WP_CLI::add_command('synced-patterns status', [$command, 'status']);
        \WP_CLI::add_command('synced-patterns list', [$command, 'list']);
    }

    /**
     * Handle theme switch - auto-sync patterns.
     */
    public function onThemeSwitch(): void
    {
        // Only sync patterns that don't require manual intervention
        $patterns = $this->sync->getThemePatterns();

        foreach ($patterns as $pattern) {
            $strategy = $pattern['headers']['syncStrategy'];

            // Skip manual strategy patterns on auto-sync
            if ($strategy === 'manual') {
                continue;
            }

            $this->sync->syncPattern($pattern, false);
        }
    }

    /**
     * Show admin notice if patterns need syncing.
     */
    public function maybeShowSyncNotice(): void
    {
        // Only show to users who can manage options
        if (!current_user_can('manage_options')) {
            return;
        }

        // Only show on relevant admin pages
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, ['themes', 'appearance_page_gutenberg-edit-site', 'site-editor'])) {
            return;
        }

        $status = $this->sync->getStatus();
        $needsSync = array_filter($status, fn($s) => $s['status'] === 'not_synced' || $s['status'] === 'outdated');

        if (empty($needsSync)) {
            return;
        }

        $count = count($needsSync);
        $message = sprintf(
            _n(
                '%d synced pattern needs to be updated. Run <code>wp synced-patterns sync</code> to sync.',
                '%d synced patterns need to be updated. Run <code>wp synced-patterns sync</code> to sync.',
                $count
            ),
            $count
        );

        printf(
            '<div class="notice notice-warning"><p>%s</p></div>',
            wp_kses($message, ['code' => []])
        );
    }

    /**
     * Get the PatternSync instance (for external use).
     */
    public function getSync(): PatternSync
    {
        return $this->sync;
    }
}
