/**
 * Turns each animation's serialized controls into ExtendBlock fields.
 *
 * The controls are declared in PHP (AnimationModule::controls()) and arrive in the inline blob,
 * already validated and each carrying the block `attribute` its value is stored under. This builds
 * one field per control, all of them shown under the Animation select of the one `sitchco/animation`
 * registration, and narrows them per block from the resolved config:
 *
 * - **Visibility.** A control shows only while its animation is the selected one AND the block
 *   still allows that animation. `condition` gates output as well as visibility, so switching
 *   animations, choosing None, or keeping a stale "(unavailable)" value leaves nothing behind.
 * - **Options.** A select offers its own options, or whatever its `optionsFilter` hook returns,
 *   narrowed to the block's `allowed` list. The empty "no override" option always survives.
 * - **Defaults.** The block's config default, falling back to the control's own, through
 *   ExtendBlock's function `default`. A block nobody touched follows it; a picked value stays.
 *
 * No `className` or `attributes` yet: the controls write attributes and emit nothing, so saved
 * markup is unchanged. Routing values into the DOM is S6.
 *
 * Plain JS with no JSX and no @wordpress imports, so it is unit testable on its own. See
 * tests/js/animation-fields.test.js.
 */

/**
 * A select's options narrowed to a block's permitted values, in the select's own order.
 *
 * @param {Array<{label: string, value: string}>} options
 * @param {string[]|undefined} permitted - The block's `allowed` list for this control, if any
 * @returns {Array<{label: string, value: string}>}
 */
export function restrictOptions(options, permitted) {
    if (!permitted) {
        return options;
    }
    return options.filter(({ value }) => value === '' || permitted.includes(value));
}

/**
 * A map from the blob, or an empty one. PHP serializes an empty map as a JSON list, and a list
 * answers to `length` — so `[]` is read as `{}` rather than probed.
 *
 * @param {Object|Array|undefined} value
 * @returns {Object}
 */
function asMap(value) {
    return value && !Array.isArray(value) ? value : {};
}

/**
 * @param {Object}   fields          - window.sitchco.extendBlock.fields
 * @param {Object}   blob
 * @param {Object}   blob.blocks     - Block name => animation key => { key, label, allowed, defaults }
 * @param {Object}   blob.controls   - Animation key => serialized controls, each with its `attribute`
 * @param {Function} applyFilters    - sitchco.hooks.applyFilters, for `optionsFilter` selects
 * @returns {Object[]} Field definitions
 */
export function buildAnimationFields(fields, { blocks = {}, controls = {} }, applyFilters) {
    return Object.entries(asMap(controls)).flatMap(([key, animationControls]) =>
        animationControls.map((control) => {
            const entryFor = (blockName) => asMap(blocks)[blockName]?.[key];

            const field = {
                name: control.attribute,
                label: control.label,
                /* An animation's controls belong to it alone, and only while the block may still
                   use it. A value left behind by a switch, or by a config change that withdrew the
                   animation, is kept in the block but neither shown nor emitted. */
                condition: (attributes, { blockName } = {}) =>
                    attributes.animation === key && entryFor(blockName) !== undefined,
                default: ({ blockName } = {}) => {
                    const defaults = asMap(entryFor(blockName)?.defaults);
                    return Object.hasOwn(defaults, control.name) ? defaults[control.name] : control.default;
                },
            };

            for (const setting of ['help', 'min', 'max']) {
                if (control[setting] !== undefined) {
                    field[setting] = control[setting];
                }
            }

            if (control.type === 'select') {
                /* Resolved per render: the same registration serves every block, and a hook's
                   options exist only once editorInit has run. */
                field.options = ({ blockName } = {}) =>
                    restrictOptions(
                        control.options ?? applyFilters(control.optionsFilter, []),
                        asMap(entryFor(blockName)?.allowed)[control.name]
                    );
            }
            return fields[control.type](field);
        })
    );
}
