<?php

/**
 * A fixture layer that configures a block with no wrapper of its own.
 *
 * `core/block` (a synced pattern) renders its inner blocks and nothing around them, so anything the
 * framework writes to "its" wrapper would land on its first inner block instead. The resolver
 * refuses it with a problem; the group beside it resolves as usual.
 */

return [
    'animations' => [
        'core/block' => ['animation-tester'],
        'core/group' => ['animation-tester'],
    ],
];
