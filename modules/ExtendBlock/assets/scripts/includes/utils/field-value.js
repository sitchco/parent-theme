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
 * - A block nobody has touched stores nothing, so it follows the default wherever it currently
 *   points. Changing a theme-level default re-renders every untouched block with the new value.
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
