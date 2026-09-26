<?php

/**
 * Fixture config standing in for a child theme layering over the parent fixture.
 *
 * Every entry here exists to exercise one merge behaviour against its counterpart in
 * ../parent/sitchco.config.php. Nothing here is malformed.
 */

return [
    'animations' => [
        // Adds a block the parent never mentioned.
        'test/child-adds-block' => ['second-tester' => true],

        // Adds an animation to an existing block; it lands after the parent's.
        'test/child-appends' => ['second-tester' => true],

        // The removal idiom: merging is additive, so a key can only be turned off, never deleted.
        'test/child-removes-animation' => ['second-tester' => false],

        // Removing the block's only animation leaves the block with nothing to offer.
        'test/child-removes-last-animation' => ['animation-tester' => false],

        // Removal at the block level.
        'test/child-removes-block' => false,

        // Drops one value from a palette the parent set.
        'test/child-narrows-allowed' => ['animation-tester' => ['allowed' => ['color' => ['green' => false]]]],

        // Overrides one default; the parent's sibling defaults survive.
        'test/child-overrides-default' => ['animation-tester' => ['defaults' => ['opacity' => '50']]],

        /* The destructive-merge trap, in both its forms. normalizeData() rewrites the bare list
           into `animation-tester => true`, so these two entries are identical by the time
           mergeRecursiveDistinct sees them, and a scalar replaces an array — the parent's defaults
           are discarded either way. The keyed form is NOT the safe alternative. */
        'test/child-clobbers-overrides' => ['animation-tester'],
        'test/child-clobbers-overrides-keyed' => ['animation-tester' => true],

        // The way to re-state an inherited animation and keep its overrides: two arrays merge.
        'test/child-keeps-overrides' => ['animation-tester' => []],

        // Narrows the palette out from under the parent's default, which is no longer offered.
        'test/child-orphans-default' => ['animation-tester' => ['allowed' => ['color' => ['green' => false]]]],
    ],
];
