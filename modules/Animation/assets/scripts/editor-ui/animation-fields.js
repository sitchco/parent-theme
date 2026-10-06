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
 * - **Options.** A select offers its own options, or whatever its `optionsFilter` hook returns
 *   (normalized as PHP normalizes static ones; see normalizeHookOptions()), narrowed to the
 *   block's `allowed` list. The empty "no override" option always survives.
 * - **Defaults.** The block's config default, falling back to the control's own, through
 *   ExtendBlock's function `default`. A dynamic block nobody touched follows it; a static block
 *   stores it once its animation is chosen; a picked value stays. See utils/field-value.js.
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
 * The label every animation select gives its empty option, whatever the options called it.
 *
 * `''` means "no override": the block emits nothing for the control, and the animation's own
 * stylesheet default applies. On a block whose config sets a default, that is not the config
 * default — choosing it stores `''`, and the block stops following the config default. A shared
 * palette's "Default" would read as the block's default, so the framework, which owns what `''`
 * means here, names it. The shared `theme.color-options` palette is left as it is for every
 * other control that uses it.
 */
export const EMPTY_OPTION_LABEL = 'Animation default';

/**
 * Options with the empty one relabelled; see EMPTY_OPTION_LABEL.
 *
 * @param {Array<{label: string, value: string}>} options
 * @returns {Array<{label: string, value: string}>}
 */
export function labelEmptyOption(options) {
    return options.map((option) =>
        option.value === ''
            ? {
                  ...option,
                  label: EMPTY_OPTION_LABEL,
              }
            : option
    );
}

const warnedFilters = new Set();

/**
 * A hook's options in the form a static select's arrive in from PHP.
 *
 * PHP checks a static option list before sending it, but it cannot see what an `optionsFilter` hook
 * returns, so this does the same normalizing for those:
 *
 * - Every value is a string, which is what `allowed` lists and stored values are compared with
 *   `===` against. A palette entry declared as `30` would otherwise never match `allowed` and show
 *   a stored `'30'` as "(unavailable)".
 * - There is exactly one empty option, first if the hook had to have one added. Without it the
 *   select opens on the hook's first entry with nothing stored, so choosing that entry fires no
 *   change and it can never be stored. A second `''` would be two options meaning one thing.
 * - A hook that returns nothing still leaves the empty option, so the control stays on screen and
 *   says something is missing, with a one-time warning naming the hook, rather than vanishing.
 *
 * @param {*}      options    - Whatever applyFilters returned
 * @param {string} filterName - The hook, for the warning
 * @returns {Array<{label: string, value: string}>}
 */
export function normalizeHookOptions(options, filterName) {
    const list = Array.isArray(options)
        ? options.filter((option) => option && typeof option === 'object' && option.value != null)
        : [];
    if (!list.length && !warnedFilters.has(filterName)) {
        warnedFilters.add(filterName);
        console.warn(
            `[animation] The '${filterName}' hook returned no options, so its control offers only the animation default.`
        );
    }

    let hasEmpty = false;
    const normalized = [];

    for (const option of list) {
        const value = String(option.value);
        if (value === '') {
            if (hasEmpty) {
                continue;
            }

            hasEmpty = true;
        }

        normalized.push({
            ...option,
            value,
        });
    }
    return hasEmpty
        ? normalized
        : [
              {
                  label: EMPTY_OPTION_LABEL,
                  value: '',
              },
              ...normalized,
          ];
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

            for (const setting of ['help', 'min', 'max', 'step']) {
                if (control[setting] !== undefined) {
                    field[setting] = control[setting];
                }
            }

            if (control.type === 'select') {
                /* Resolved per render: the same registration serves every block, and a hook's
                   options exist only once editorInit has run. */
                field.options = ({ blockName } = {}) =>
                    labelEmptyOption(
                        restrictOptions(
                            control.options ??
                                normalizeHookOptions(applyFilters(control.optionsFilter, []), control.optionsFilter),
                            asMap(entryFor(blockName)?.allowed)[control.name]
                        )
                    );
            }
            return fields[control.type](field);
        })
    );
}
