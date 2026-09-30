<?php

/**
 * A fixture layer that switches the whole section off.
 *
 * `'animations' => false` is the documented `=> false` removal applied one level up, and it wins the
 * merge over any ancestor's section. FileRegistry::load() then finds a non-array at the key and
 * returns its `[]` default, so every block loses its animations at once — and silently, which is
 * why it is worth pinning. A different path from "the section was never declared", which is all
 * the no-fixture case covers.
 *
 * Layered over the parent fixture, whose malformed entries therefore go unlogged. Core still reads
 * and normalizes every file in the chain; it is only the resolver that never sees the section.
 */

return [
    'animations' => false,
];
