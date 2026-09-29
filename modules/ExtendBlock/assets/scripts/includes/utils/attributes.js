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
 * The render context is NOT the same shape in every phase — it carries what the phase actually
 * knows:
 *
 *     save (blocks.getSaveContent.extraProps)  { blockName }
 *     inspector (editor.BlockEdit)             { blockName, clientId }
 *     canvas (editor.BlockListBlock)           { blockName, clientId, deviceType }
 *
 * So a callback that decides class or attribute OUTPUT must depend only on `blockName` and the
 * block's own attributes. Branch on `deviceType` or `clientId` and the editor and the saved
 * markup will disagree, silently — the save path has neither. Those two are for rendering a
 * control, not for deciding what it emits.
 *
 * One exception, and it is only about argument order: a field wrapped in responsive() still gets
 * its `options` called as `(deviceType, context)` rather than `(context)`. See
 * resolveResponsiveOptions in utils/options.js.
 */

/**
 * Prop names the attribute channel never emits.
 *
 * `class`, `className` and `style` belong to the class channel, which runs alongside this one
 * and writes `className` last. Letting a generator return one of them would either be silently
 * overwritten or silently clobber the classes, depending on whether any class happened to be
 * generated — so they are dropped here, at the one point every attribute passes through,
 * rather than guarded for at each call site.
 */
const RESERVED = ['class', 'className', 'style'];

/**
 * Merges attribute objects left to right, dropping what must not be emitted.
 *
 * `undefined` and `null` are dropped rather than rendered, which is what makes "no animation
 * selected" add nothing at all: a generator returns `{ 'data-animation': undefined }` and the
 * result is an empty object, so the caller leaves serialized output untouched.
 *
 * @param {...(Object|null|undefined)} sources
 * @returns {Object}
 */
export function mergeAttributes(...sources) {
    const merged = {};

    for (const source of sources) {
        if (!source) {
            continue;
        }

        for (const [name, value] of Object.entries(source)) {
            if (RESERVED.includes(name) || value === undefined || value === null) {
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
 * @param {Object} [context]  - Render context; see the note at the top of this file
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

        generated.push(field.attributes(attributes[field.name], context));
    }
    return mergeAttributes(...generated);
}
