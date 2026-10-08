/**
 * The attribute channel: arbitrary props a field or generator emits onto the block wrapper.
 *
 * Deliberately a sibling of class-names.js rather than part of it. The two channels answer to
 * different consumers — a stylesheet matches a class, while `data-*` attributes are read by JS
 * and by attribute selectors — and they reach the DOM by different routes: saved content
 * merges them as plain props, while the editor canvas takes them through `wrapperProps`.
 *
 * Plain JS on purpose, with no JSX and no @wordpress imports, so the logic below is unit
 * testable without a transform. See tests/js/attributes.test.js.
 *
 * What a generator author has to know:
 *
 * - Only `data-*` and `aria-*` attributes are emitted. Anything else is dropped, with a one-time
 *   console warning per name. The channel is for markup JS and attribute selectors read, and
 *   WordPress applies these props to the saved element with cloneElement, so an open channel
 *   would let `children` replace the block's content, `dangerouslySetInnerHTML` print unescaped,
 *   or a string `on*` value serialize as an inline handler. `class`, `className` and `style`
 *   fall outside it too; classes belong to the class channel.
 * - Return `undefined` (or `null`) for unset. Those are dropped; `''`, `0` and `false` are not,
 *   and WordPress serializes them — `''` as `data-x=""`, `0` as `data-x="0"`, `false` as
 *   `"false"`. The select and text fields default to `''` and the toggle to `false`, so the
 *   obvious `(v) => ({ 'data-x': v })` adds markup to every untouched block and breaks
 *   validation. Map each unset value to `undefined` explicitly; `v || undefined` is right for a
 *   select or text field, but would also drop a `0` or `false` that means something.
 * - A key the block or core also sets (`aria-label`, `data-block`, `data-align`, …) resolves
 *   differently by phase: the extension wins on save, the block wins in the canvas. Don't emit
 *   keys your target block sets itself. See the precedence note in utils/editor-props.js.
 * - Dynamic blocks get these attributes in the editor canvas only. Their front end is rendered by
 *   PHP, and only classes are synced to it (through `extendBlockClasses`). Server-rendered
 *   attributes go through ExtendBlockModule's `wrapper-props` filter instead.
 *
 * Every callback that decides OUTPUT — `condition`, a field's `className` or `attributes`, and
 * the `classGenerator` / `attributeGenerator` overrides — gets the same output context in every
 * phase: `{ blockName }`. Save knows nothing more, so the editor is not allowed to know more
 * either; if it did, a callback branching on the preview device or the clientId would make the
 * canvas and the saved markup disagree, silently. The richer render context, `{ blockName,
 * clientId }` plus `deviceType` inside responsive(), goes only to `render` and `options`, which
 * draw a control and never decide what the block emits.
 *
 * A function `default` is part of the same rule. It decides what an untouched block emits, so it
 * too gets `{ blockName }` only. The value handed to a field's `attributes` callback goes through
 * readFieldValue(), so the default is applied identically on save and in the canvas; `condition`
 * and the generators get the raw attributes, where such a field is `undefined` until stored. See
 * utils/field-value.js.
 */

import { readFieldValue } from './field-value';

/**
 * The names the attribute channel emits: `data-*` and `aria-*`, nothing else.
 *
 * An allowlist rather than a list of reserved names, because the keys that do harm on a saved
 * element (`children`, `dangerouslySetInnerHTML`, `on*`, a mis-cased `Class`) are open-ended,
 * while everything the animation framework plans to emit is `data-*`. Enforced here, at the one
 * point every attribute passes through, rather than guarded for at each call site.
 */
const ALLOWED_NAME = /^(data|aria)-/;

const warned = new Set();

function warnDropped(name) {
    if (warned.has(name)) {
        return;
    }

    warned.add(name);
    console.warn(
        `[extendBlock] Dropped the '${name}' attribute: extensions can only emit data-* and aria-* attributes.`
    );
}

function isPlainObject(value) {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const proto = Object.getPrototypeOf(value);
    return proto === Object.prototype || proto === null;
}

/**
 * Merges attribute objects left to right, dropping what must not be emitted.
 *
 * `undefined` and `null` are dropped rather than rendered, which is what makes "no animation
 * selected" add nothing at all: a generator returns `{ 'data-animation': undefined }` and the
 * result is an empty object, so the caller leaves serialized output untouched.
 *
 * A source that isn't a plain object is skipped whole: a string would otherwise spread into
 * numeric keys, and a generator returning `null` for "nothing" is the common case.
 *
 * @param {...(Object|null|undefined)} sources
 * @returns {Object}
 */
export function mergeAttributes(...sources) {
    const merged = {};

    for (const source of sources) {
        if (!isPlainObject(source)) {
            continue;
        }

        for (const [name, value] of Object.entries(source)) {
            if (value === undefined || value === null) {
                continue;
            }
            if (!ALLOWED_NAME.test(name)) {
                warnDropped(name);
                continue;
            }

            merged[name] = value;
        }
    }
    return merged;
}

/**
 * Generates wrapper attributes from fields based on their attribute values.
 *
 * Fields hidden by their `condition` contribute nothing, exactly as in generateFieldClasses —
 * that gate is what keeps a stale value left behind by a control the author can no longer see
 * from going on emitting its attribute. Preserving it here is what makes switching between two
 * animations safe on the attribute path as well as the class one.
 *
 * Later fields win on key collision, which is the same precedence the object spread at the
 * call sites already has.
 *
 * @param {Array}  fields     - Array of field definitions
 * @param {Object} attributes - Block attributes
 * @param {Object} [context]  - Output context, `{ blockName }`; see the note at the top of this file
 * @returns {Object} Attribute name => value, empty when nothing applies
 */
export function generateFieldAttributes(fields, attributes, context = {}) {
    const generated = [];

    for (const field of fields) {
        if (!field.attributes) {
            continue;
        }
        if (field.condition && !field.condition(attributes, context)) {
            continue;
        }

        generated.push(field.attributes(readFieldValue(field, attributes, context), context));
    }
    return mergeAttributes(...generated);
}
