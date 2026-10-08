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
 *   (held to the rules PHP validates static ones by, entry by entry; see normalizeHookOptions()),
 *   narrowed to the block's `allowed` list. The empty "no override" option always survives.
 * - **Defaults.** The block's config default, falling back to the control's own, through
 *   ExtendBlock's function `default`. A dynamic block nobody touched follows it; a static block
 *   stores it once its animation is chosen; a picked value stays. See utils/field-value.js.
 *
 * - **Output.** A control with `css` emits its CSS value as its `cssProperty`
 *   (`--{key}-animation-{name}`) onto the block in the editor canvas, through ExtendBlock's style
 *   channel. Saved markup is never touched: the registration uses `saveOutput: false`, and the
 *   front end gets the same properties from PHP (AnimationFrameworkModule::wrapperProps()). A value
 *   the block's `allowed` list no longer permits emits nothing, as it does there.
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
 * Whether one hook entry is an option PHP would accept in a static list: an object with a
 * non-empty string label and a value that is a string or a finite number. Other keys pass as they
 * are; hooks are trusted theme code, and SelectControl takes `children` from the label anyway.
 *
 * @param {*} option
 * @returns {boolean}
 */
function isOption(option) {
    if (!option || typeof option !== 'object') {
        return false;
    }

    const { label, value } = option;
    return (
        typeof label === 'string' &&
        label !== '' &&
        (typeof value === 'string' || (typeof value === 'number' && Number.isFinite(value)))
    );
}

/**
 * Warns once per hook that it left its control with only the animation default.
 *
 * @param {string} filterName
 * @param {string} reason
 */
function warnOnce(filterName, reason) {
    if (warnedFilters.has(filterName)) {
        return;
    }

    warnedFilters.add(filterName);
    console.warn(`[animation] The '${filterName}' hook ${reason}, so its control offers only the animation default.`);
}

/**
 * A hook's options in the form a static select's arrive in from PHP.
 *
 * PHP validates a static option list before sending it, and drops the whole control when the list
 * breaks a rule (AnimationControlValidator::isOptionList()). It cannot see what an `optionsFilter`
 * hook returns, so this applies the same rules here, entry by entry rather than all or nothing:
 * the hook is a shared palette that parent and child themes both add to, and one bad entry should
 * not cost the control every good one.
 *
 * - An entry PHP would refuse is dropped: one without a non-empty string label, or whose value is
 *   not a string or finite number (`true`, `NaN` and objects would otherwise arrive as 'true',
 *   'NaN' and '[object Object]').
 * - Every value is a string, which is what `allowed` lists and stored values are compared with
 *   `===` against. A palette entry declared as `30` would otherwise never match `allowed` and show
 *   a stored `'30'` as "(unavailable)".
 * - No two values are alike once cast. The first wins, so a child theme re-adding a parent's
 *   value, or adding `30` beside `'30'`, cannot produce two options the select cannot tell apart.
 * - There is exactly one empty option, first if the hook had to have one added. Without it the
 *   select opens on the hook's first entry with nothing stored, so choosing that entry fires no
 *   change and it can never be stored.
 * - A hook that leaves nothing usable still leaves the empty option, so the control stays on screen
 *   and says something is missing, with a one-time warning naming the hook, rather than vanishing.
 *
 * @param {*}      options    - Whatever applyFilters returned
 * @param {string} filterName - The hook, for the warning
 * @returns {Array<{label: string, value: string}>}
 */
export function normalizeHookOptions(options, filterName) {
    const returned = Array.isArray(options) ? options : [];
    const seen = new Set();
    const normalized = [];

    for (const option of returned) {
        if (!isOption(option)) {
            continue;
        }

        const value = String(option.value);
        if (seen.has(value)) {
            continue;
        }

        seen.add(value);
        normalized.push({
            ...option,
            value,
        });
    }

    if (!returned.length) {
        warnOnce(filterName, 'returned no options');
    } else if (!normalized.length) {
        warnOnce(
            filterName,
            `returned ${returned.length} option${returned.length > 1 ? 's' : ''}, none with a non-empty string label and a string or finite number value`
        );
    }
    return seen.has('')
        ? normalized
        : [
              {
                  label: EMPTY_OPTION_LABEL,
                  value: '',
              },
              ...normalized,
          ];
}

/** What a `css` template's placeholder is replaced with. Mirrors AnimationControl::CSS_PLACEHOLDER. */
const CSS_PLACEHOLDER = '{value}';

/**
 * The CSS value one of a control's values emits, or null for none. The JS twin of
 * AnimationControl::cssValue(), held to the same answers by tests/fixtures/animation-css-cases.json.
 *
 * Null for the empty value, for a value of the wrong type, for a static select's value it does not
 * offer, and for a control without `css`. A toggle's false is a value: it emits its `off` CSS.
 *
 * @param {Object} control - A serialized control
 * @param {*}      value
 * @returns {string|null}
 */
export function cssValue(control, value) {
    const { css } = control;
    if (control.type === 'toggle') {
        return typeof value === 'boolean' && css && typeof css === 'object'
            ? (css[value ? 'on' : 'off'] ?? null)
            : null;
    }

    let string = null;
    if (typeof value === 'string') {
        string = control.type === 'number' ? null : value;
    } else if (typeof value === 'number' && Number.isFinite(value)) {
        string = String(value);
    }
    if (string === null || string === '') {
        return null;
    }
    if (control.type === 'select' && control.options) {
        const option = control.options.find(({ value: optionValue }) => String(optionValue) === string);
        if (!option) {
            return null;
        }
        if (typeof option.css === 'string') {
            return option.css;
        }
    }
    return typeof css === 'string' ? css.split(CSS_PLACEHOLDER).join(string) : null;
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
            if (control.css !== undefined || control.options?.some((option) => option.css !== undefined)) {
                /* Gated by `condition` like everything else the field emits, so switching
                   animations or choosing None leaves no property behind. */
                field.style = (value, { blockName } = {}) => {
                    const permitted = asMap(entryFor(blockName)?.allowed)[control.name];
                    if (permitted && !permitted.includes(String(value))) {
                        return undefined;
                    }

                    const css = cssValue(control, value);
                    return css === null ? undefined : { [control.cssProperty]: css };
                };
            }

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
