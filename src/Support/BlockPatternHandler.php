<?php

namespace Sitchco\Parent\Support;

/**
 * Class BlockPatternHandler
 *
 * Handles the registration and removal of block patterns in the WordPress editor.
 *
 * @package Sitchco\Parent\Support
 *
 * TODO: create test file for this!
 */
class BlockPatternHandler
{
    /**
     * Initializes the BlockPatternHandler.
     *
     * This method can be used to set up any necessary hooks or actions.
     *
     * @return void
     */
    public function init(): void
    {
        // Initialization logic can be added here if needed.
    }

    /**
     * Removes core block patterns and registered patterns.
     *
     * This method disables remote block patterns and removes all registered patterns
     * by hooking into the appropriate WordPress actions and filters.
     *
     * @return void
     */
    public function removeCorePatterns(): void
    {
        // Disable remote block patterns.
        add_filter('should_load_remote_block_patterns', '__return_false');

        // Remove registered patterns on initialization.
        add_action('init', [$this, 'removeRegisteredPatterns']);
    }

    /**
     * Removes all registered block patterns.
     *
     * This method retrieves all registered block patterns and unregisters them
     * using the `WP_Block_Patterns_Registry` class.
     *
     * @return void
     */
    public function removeRegisteredPatterns(): void
    {
        // Get the block patterns registry instance.
        $registry = \WP_Block_Patterns_Registry::get_instance();

        // Retrieve all registered patterns.
        $patterns = $registry->get_all_registered();

        // Unregister each pattern.
        foreach ($patterns as $pattern) {
            $registry->unregister($pattern['name']);
        }
    }
}