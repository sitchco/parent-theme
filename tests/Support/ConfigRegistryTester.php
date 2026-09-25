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
     * ConfigRegistry refuses to cache a merge that the active theme's own sitchco.config.php did not
     * contribute to, to catch a mid-deploy degraded config. Both themes have one on disk during a
     * test run and no fixture directory can ever be one of them, so that guard would discard every
     * fixture merge and leave load() silently returning nothing. Fall back to the base rule.
     */
    protected function isMergedDataCacheable(?array $merged): bool
    {
        return !empty($merged);
    }
}
