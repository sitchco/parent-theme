<?php

/**
 * A fixture layer that configures blocks with no wrapper of their own.
 *
 * `core/block` (a synced pattern) and `core/pattern` render their inner blocks and nothing around
 * them, so anything the framework writes to "their" wrapper would land on the first inner block
 * instead. `core/more` and `core/nextpage` render only a comment. The resolver refuses each with a
 * problem; the group beside them resolves as usual.
 */

return [
    'animations' => [
        'core/block' => ['animation-tester'],
        'core/pattern' => ['animation-tester'],
        'core/more' => ['animation-tester'],
        'core/nextpage' => ['animation-tester'],
        'core/group' => ['animation-tester'],
    ],
];
