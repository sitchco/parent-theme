<?php

/**
 * Blocks for the markup cases: one offering the markup animation beside one without markup, and
 * one that never offers it. Used alone, never layered.
 */
return [
    'animations' => [
        'test/markup' => ['markup-tester', 'animation-tester'],
        'test/no-markup' => ['animation-tester'],
    ],
];
