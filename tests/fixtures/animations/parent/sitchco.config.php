<?php

/**
 * Fixture config standing in for the most ancestral layer of the theme chain.
 *
 * Read through a real ConfigRegistry (see ConfigRegistryTester), so everything here goes through the
 * same normalization and merging the live config does. Block names are deliberately fictional: the
 * animations config never validates that a block exists, and fake names keep these cases from
 * depending on which block plugins happen to be installed.
 *
 * The animation keys are the test animations' own: `animation-tester` and `second-tester`.
 *
 * Seven of the entries below are deliberately malformed. Resolving this file therefore always
 * produces the aggregated config warning, with exactly seven problems in it — a count
 * testEveryConfigProblemIsReportedInASingleWarning pins, so an entry added here must either be
 * valid or move that number deliberately. Tests that need a clean, silent resolution use the
 * `clean` layer instead.
 */

return [
    'animations' => [
        // A bare list: both animations, no overrides, in the order written.
        'test/bare-list' => ['animation-tester', 'second-tester'],

        // The keyed equivalent of a bare list entry.
        'test/enabled-true' => ['animation-tester' => true],

        // An empty override array means "enabled, nothing overridden" — NOT removed.
        'test/empty-overrides' => ['animation-tester' => []],

        // The full override shape. `reverse` is a literal false default, not a removal.
        'test/overrides' => [
            'animation-tester' => [
                'allowed' => ['color' => ['purple', 'green', 'red']],
                'defaults' => ['opacity' => '30', 'speed' => 25, 'reverse' => false],
            ],
        ],

        // A single permitted value written as a scalar rather than a one-item list.
        'test/allowed-scalar' => ['animation-tester' => ['allowed' => ['color' => 'purple']]],

        /* Permits nothing, which is legal but almost certainly a mistake — one problem, from
           resolveAllowed(). The default is here to pin flagUnpermittedDefaults()' empty-list skip:
           one authoring mistake earns one problem, so this must not produce a second. */
        'test/allowed-empty' => [
            'animation-tester' => ['allowed' => ['color' => []], 'defaults' => ['color' => 'purple']],
        ],

        // Option names where `allowed` or `defaults` belongs — unguessable, so nothing is applied.
        'test/no-reserved-keys' => ['animation-tester' => ['color' => ['purple']]],

        // One reserved key and one stray: the reserved one still applies.
        'test/stray-key' => [
            'animation-tester' => [
                'allowed' => ['color' => ['purple']],
                'speed' => 25,
            ],
        ],

        // An array default: one value too many, and unrecoverable as a list by the time it arrives.
        'test/mangled-default' => [
            'animation-tester' => ['defaults' => ['color' => ['purple', 'green'], 'opacity' => '30']],
        ],

        // Numeric option values, to prove they survive normalization's key casting as strings.
        'test/numeric-allowed' => ['animation-tester' => ['allowed' => ['opacity' => ['10', '30', '50']]]],

        /* An int default against string-keyed permitted values. Normalization turns permitted values
         into array keys and PHP casts a numeric-string key to an int; it is the strval() in
         resolveAllowed() that casts them back, so the cross-check compares as strings or flags
         this wrongly. */
        'test/typed-default' => [
            'animation-tester' => ['allowed' => ['speed' => ['25', '50']], 'defaults' => ['speed' => 25]],
        ],

        /* A bool default on an option that does have an `allowed` list, pinning
           flagUnpermittedDefaults()' type skip. A literal setting has nothing to match against;
           without the skip, (string) false is '' and would be flagged. `reverse => false` on
           test/overrides pins nothing, because it has no `allowed` entry and skips on isset first. */
        'test/bool-default' => [
            'animation-tester' => ['allowed' => ['reverse' => ['on', 'off']], 'defaults' => ['reverse' => false]],
        ],

        // An animation no module provides, alongside one that exists.
        'test/unknown-animation' => ['no-such-animation' => true, 'animation-tester' => true],

        // A scalar where a list of animation keys belongs.
        'test/scalar-block' => 'animation-tester',

        // A bare block name, which normalizes to `true` and names no animations at all.
        'test/bare-block',

        // Everything below is what the child fixture acts on.
        'test/child-appends' => ['animation-tester' => true],
        'test/child-removes-animation' => ['animation-tester' => true, 'second-tester' => true],
        'test/child-removes-last-animation' => ['animation-tester' => true],
        'test/child-removes-block' => ['animation-tester' => true],
        'test/child-narrows-allowed' => ['animation-tester' => ['allowed' => ['color' => ['purple', 'green']]]],
        'test/child-overrides-default' => [
            'animation-tester' => ['defaults' => ['opacity' => '30', 'speed' => 25]],
        ],
        'test/child-clobbers-overrides' => ['animation-tester' => ['defaults' => ['opacity' => '30']]],
        'test/child-clobbers-overrides-keyed' => ['animation-tester' => ['defaults' => ['opacity' => '30']]],
        'test/child-keeps-overrides' => ['animation-tester' => ['defaults' => ['opacity' => '30']]],
        'test/child-widens-allowed' => ['animation-tester' => ['allowed' => ['color' => ['purple', 'green']]]],

        // A default that IS permitted here, and stops being permitted once the child narrows it.
        'test/child-orphans-default' => [
            'animation-tester' => [
                'allowed' => ['color' => ['purple', 'green']],
                'defaults' => ['color' => 'green'],
            ],
        ],
    ],
];
