<?php

/**
 * A fixture layer holding the overrides that are only wrong once read against the animations' own
 * controls — see AnimationTester::controls() and SecondAnimationTester::controls().
 *
 * Kept apart from the parent fixture, whose problem count is pinned, and used alone, never layered.
 * Fourteen problems in all, pinned by testEveryControlMismatchIsFlaggedWithItsFallback; the two entries
 * marked valid must stay silent.
 */

return [
    'animations' => [
        // Two problems: neither half names a control AnimationTester has.
        'test/unknown-control' => [
            'animation-tester' => ['allowed' => ['glyph' => ['x']], 'defaults' => ['glyph' => 'x']],
        ],

        // One problem: teal is not among the color select's options. Purple survives.
        'test/value-not-offered' => ['animation-tester' => ['allowed' => ['color' => ['purple', 'teal']]]],

        // Two problems, as the list form would give: teal is not offered, and so nothing is permitted.
        'test/scalar-not-offered' => ['animation-tester' => ['allowed' => ['color' => 'teal']]],

        // One problem: '' is always offered, so permitting only it permits nothing.
        'test/allowed-empty-string' => ['animation-tester' => ['allowed' => ['color' => '']]],

        // Valid: tint's options come from a JS hook, so its permitted values cannot be checked here.
        'test/filtered-options' => ['second-tester' => ['allowed' => ['tint' => ['anything']]]],

        // Three problems: a string for a toggle, a word for a number, a bool for a select.
        'test/default-wrong-type' => [
            'animation-tester' => ['defaults' => ['reverse' => 'yes', 'color' => true]],
            'second-tester' => ['defaults' => ['speed' => 'fast']],
        ],

        // Valid: a numeric string is cast for a number control.
        'test/number-default-cast' => ['second-tester' => ['defaults' => ['speed' => '25']]],

        // One problem: narrowing direction to `down` excludes the control's own default, `up`.
        'test/own-default-excluded' => ['second-tester' => ['allowed' => ['direction' => ['down']]]],

        // One problem: teal is not among the color select's options, with no `allowed` in play.
        'test/default-not-offered' => ['animation-tester' => ['defaults' => ['color' => 'teal']]],

        // One problem, not two: teal is not offered at all, which says more than that `allowed`
        // excludes it.
        'test/default-not-offered-restricted' => [
            'animation-tester' => ['allowed' => ['color' => ['purple']], 'defaults' => ['color' => 'teal']],
        ],

        // One problem: 150 is past the speed control's max of 100.
        'test/default-out-of-range' => ['second-tester' => ['defaults' => ['speed' => 150]]],

        // One problem: numeric, but it casts to INF, which json_encode() cannot write.
        'test/default-infinite' => ['second-tester' => ['defaults' => ['speed' => '1e999']]],
    ],
];
