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
 * Layered over the parent fixture, whose malformed entries must therefore go unread and unlogged.
 */

return [
    'animations' => false,
];
