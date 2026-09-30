<?php

/**
 * A fixture layer holding the override mistakes no other fixture covers.
 *
 * The parent fixture pins its own problem count, so putting these there would move a number several
 * tests read. They are kept apart for that reason, and used alone, never layered. Seven problems in
 * all — pinned by testEveryOverrideMistakeIsFlaggedWithItsFallback.
 *
 * What they have in common is that each one would otherwise pass for something legal: `=> false` is
 * a removal idiom one level up, and a marker that is not `true` still reads as permission.
 */

return [
    'animations' => [
        // `allowed` cannot be unset. The merge has already replaced any inherited list with this
        // scalar by the time the resolver sees it, so every option is left unrestricted.
        'test/allowed-false' => ['animation-tester' => ['allowed' => false]],

        // The same one level down, leaving that one option unrestricted.
        'test/allowed-option-false' => ['animation-tester' => ['allowed' => ['color' => false]]],

        // `defaults` cannot be unset either; no defaults are applied.
        'test/defaults-false' => ['animation-tester' => ['defaults' => false]],

        // A known animation mapped to something that is neither `true` nor an override array.
        'test/scalar-animation' => ['animation-tester' => 'yes'],

        /* A forgotten nesting level. Without a marker check this permits the literal value "brand"
           and loses the palette entirely. Two problems: the bad marker, then the empty list it
           leaves behind. */
        'test/nested-allowed' => [
            'animation-tester' => ['allowed' => ['color' => ['brand' => ['purple', 'green']]]],
        ],

        // A permitted value marked with something that is not a marker; its sibling still applies.
        'test/odd-marker' => [
            'animation-tester' => ['allowed' => ['color' => ['purple' => 0, 'green' => true]]],
        ],
    ],
];
