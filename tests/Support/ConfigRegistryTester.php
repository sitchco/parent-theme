<?php

namespace Sitchco\Parent\Tests\Support;

use Sitchco\Framework\ConfigRegistry;
use Sitchco\Support\FilePath;

/**
 * A ConfigRegistry reading fixture directories instead of the live theme chain.
 *
 * Replaces the config SOURCE rather than the result, so a test still goes through the real
 * parseFile -> normalizeData -> mergeRecursiveDistinct pipeline. That matters here: normalization
 * rewrites the `animations` section in ways the resolver is built around, and a double that simply
 * returned a hand-normalized array would be asserting the resolver's assumptions about core rather
 * than what core actually does. Core lives in its own repository, so these are exactly the tests
 * that should break if normalizeData() ever changes.
 *
 * Base paths are preset, so initializeBasePaths() never runs: no path filters to register, no
 * ordering to get right, and core's own sitchco.config.php stays out of the merge. Pass one
 * directory per layer, most ancestral first, to exercise a parent/child override chain.
 */
class ConfigRegistryTester extends ConfigRegistry
{
    /** Its own key, so fixture merges never touch the live `sitchco_config` cache. */
    public const CACHE_KEY = 'sitchco_config_tester';

    public function __construct(string ...$fixtureDirs)
    {
        $this->basePaths = array_map(fn(string $dir) => FilePath::create($dir), $fixtureDirs);
        $this->clearCache();
    }

    /**
     * Clear before reading, because the cache key is a constant and therefore shared.
     *
     * WP_UnitTestCase flushes the object cache between tests, so nothing leaks from one test to the
     * next. Within one test it would: two testers over different fixture layers would have the
     * second load() served the first one's merge. Clearing in the constructor is not enough, since
     * both are built before either is read.
     */
    public function load(?string $key = null, array $default = []): array
    {
        $this->clearCache();

        return parent::load($key, $default);
    }

    /**
     * ConfigRegistry refuses to cache a merge that the active theme's own sitchco.config.php did not
     * contribute to, to catch a mid-deploy degraded config. Both themes have one on disk during a
     * test run and no fixture directory can ever be one of them, so that guard would discard every
     * fixture merge and leave load() returning the empty default — noisily, since FileRegistry logs
     * "Discarding a non-empty but incomplete config merge" at WARNING each time, which only
     * tests/phpunit.php pinning SITCHCO_LOG_LEVEL to ERROR keeps out of the output. Fall back to the
     * base rule.
     */
    protected function isMergedDataCacheable(?array $merged): bool
    {
        return !empty($merged);
    }
}
