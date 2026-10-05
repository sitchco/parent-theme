<?php

/**
 * A fixture config in which nothing is wrong.
 *
 * The parent fixture is deliberately full of malformed entries, so every test reading it resolves
 * with the aggregated warning and none of them can tell a valid entry from one the resolver has
 * merely stopped complaining about. This layer covers the other half: a plain enable, an `allowed`
 * restriction and a `defaults` map, each in its ordinary form, resolving to a real map with nothing
 * logged. A regression that started flagging valid config would show up here and nowhere else.
 *
 * Used alone, never layered.
 */

return [
    'animations' => [
        'test/clean-bare' => ['animation-tester', 'second-tester'],
        'test/clean-overrides' => [
            'animation-tester' => [
                'allowed' => ['color' => ['purple', 'green']],
                'defaults' => ['color' => 'purple', 'opacity' => '30'],
            ],
            'second-tester' => ['defaults' => ['caption' => 42]],
        ],
    ],
];
