/**
 * Reading a field's value, for fields whose default depends on the block.
 *
 * A field's `default` is normally a literal, and Gutenberg owns it: fieldsToAttributes() registers
 * it as the attribute default, the block's attributes always carry a value, and serialization
 * omits the attribute while it equals that default. But one extendBlock() registration can serve
 * several blocks, and a registered default is global — so a default that has to differ per block
 * cannot live there.
 *
 * Such a field declares `default` as a function of the output context instead:
 *
 *     fields.select({ name: 'fadeUpAnimationSpeed', default: ({ blockName }) => speedFor(blockName), … })
 *
 * The attribute is then registered with no default at all, so it stays `undefined` until an author
 * picks something, and every read — the inspector's value, the class and attribute channels, the
 * dynamic-block class sync — goes through readFieldValue() below, which supplies the default in its
 * place. The function gets the output context, `{ blockName }`, and nothing richer: a default
 * decides what the block emits, so it must resolve the same way in save as in the editor.
 *
 * What that means for content, stated because it is easy to miss:
 * - On a dynamic block, a field nobody has touched stores nothing, so it follows the default
 *   wherever it currently points. Changing a theme-level default re-renders every such block with
 *   the new value.
 * - On a static block, the editor stores the default as soon as the field applies (no condition,
 *   or its condition passes); see contextualDefaultsToStore() below. A static block's saved markup
 *   is checked against what save() produces from its stored attributes on every editor load, so
 *   save() must not depend on a default that can move: markup saved under one default would fail
 *   validation once the default changed. Stored, the value stays put, and a later change to the
 *   default reaches only blocks that have not stored one yet.
 * - A value an author picks is stored, even when it happens to equal the default, and from then
 *   on it stays put whatever the default does.
 *
 * Not available inside responsive(): the breakpoint cascade reads raw values with `|| ''` to fall
 * through to the next breakpoint, which would mask a function default rather than apply it.
 *
 * Plain JS with no JSX and no @wordpress imports, so it is unit testable on its own. See
 * tests/js/field-value.test.js.
 */

/**
 * Whether a field's default depends on the block it is rendered on.
 *
 * @param {Object} field - A field definition
 * @returns {boolean}
 */
export function hasContextualDefault(field) {
    return typeof field.default === 'function';
}

/**
 * A field's effective value on one block.
 *
 * The stored value whenever there is one. Only an attribute that is genuinely absent falls back
 * to a function default: `''`, `0` and `false` are values an author chose, and are returned as is.
 *
 * @param {Object} field      - A field definition
 * @param {Object} attributes - Block attributes
 * @param {Object} [context]  - Output context, `{ blockName }`
 * @returns {*}
 */
export function readFieldValue(field, attributes, context = {}) {
    const stored = attributes[field.name];
    if (stored !== undefined || !hasContextualDefault(field)) {
        return stored;
    }
    return field.default(context);
}

/**
 * The function defaults a static block should store now, as an attributes object; null if none.
 *
 * Covers each field with a function default that has no stored value and applies to the block —
 * no condition, or one that passes — so a field gated on another (an animation's controls on the
 * animation) is stored only once it takes effect, and a block never fills up with values for
 * fields that emit nothing. Conditions see the defaults stored alongside them, so a field gated on
 * another function-default field is caught in the same pass.
 *
 * @param {Object[]} fields     - Field definitions
 * @param {Object}   attributes - Block attributes
 * @param {Object}   [context]  - Output context, `{ blockName }`
 * @returns {Object|null}
 */
export function contextualDefaultsToStore(fields, attributes, context = {}) {
    const pending = {};
    let found = false;

    for (const field of fields) {
        if (!hasContextualDefault(field) || attributes[field.name] !== undefined) {
            continue;
        }
        if (
            field.condition &&
            !field.condition(
                {
                    ...attributes,
                    ...pending,
                },
                context
            )
        ) {
            continue;
        }

        pending[field.name] = field.default(context);
        found = true;
    }
    return found ? pending : null;
}
