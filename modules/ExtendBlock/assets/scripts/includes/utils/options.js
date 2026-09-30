/**
 * Resolves a field's `options`, which may be a list or a function of the render context.
 *
 * The function form exists because one extendBlock() registration can serve several blocks whose
 * choices differ — the animation select lists only the animations its block is configured for.
 * A plain field is called with the inspector's render context, `{ blockName, clientId }`. A field
 * wrapped in responsive() goes through resolveResponsiveOptions below instead, which adds
 * `deviceType` and keeps the older `(deviceType, context)` signature.
 *
 * Unlike the className/attributes callbacks, options may read the whole render context: they
 * decide what a control offers, never what the block emits, so they are only ever resolved in the
 * editor and never on the save path.
 *
 * Plain JS with no JSX and no @wordpress imports, so it is unit testable on its own.
 *
 * @param {Object} field      - A field definition
 * @param {Object} [context]  - { blockName, clientId }
 * @returns {Array|undefined}
 */
export function resolveOptions(field, context = {}) {
    return typeof field.options === 'function' ? field.options(context) : field.options;
}

/**
 * The same, for fields wrapped in responsive() — which still call back with the breakpoint string.
 *
 * TRANSITIONAL. Before the render context existed, a responsive field's options function was
 * called as `(breakpoint) => …`, and `themes/roundabout` uses that signature today. The two themes
 * are separate repos and separate deploys, so a parent theme that simply switched to the context
 * object would break the child's border-radius controls for however long the two are out of step —
 * and break them silently, since `'Desktop' === undefined` is merely false, not an error.
 *
 * So the breakpoint stays the first argument and the context is appended as a second. A field
 * written today can take `(deviceType, { blockName }) => …` and lose nothing in the migration.
 *
 * TO REMOVE: once no `options:` function in any consuming theme reads the first argument, delete
 * this and have ResponsiveFieldWrapper call resolveOptions(field, responsiveContext) instead. The
 * only current caller is themes/roundabout's kadence-border-radius-controls.jsx.
 *
 * @param {Object} field      - A field definition
 * @param {Object} [context]  - { blockName, clientId, deviceType }
 * @returns {Array|undefined}
 */
export function resolveResponsiveOptions(field, context = {}) {
    return typeof field.options === 'function' ? field.options(context.deviceType, context) : field.options;
}

/**
 * Keeps a saved value visible when the options no longer offer it.
 *
 * A value can outlive its option: config stops allowing an animation on a block, a module is
 * deactivated, or another control shares the attribute. SelectControl, given a value no option
 * matches, shows the first option — usually None — while the attribute keeps the old value, and
 * because None already looks selected, choosing it does nothing. Appending the stale value as a
 * disabled option shows the author what is actually stored and lets None clear it.
 *
 * @param {Array|undefined} options - Resolved options
 * @param {*}               value   - The attribute's current value
 * @returns {Array|undefined} The same list when the value is empty or offered, else a copy with
 *   the value appended as a disabled option
 */
export function withStaleValue(options, value) {
    if (!options || value === '' || value === undefined || value === null) {
        return options;
    }
    if (options.some((option) => option.value === value)) {
        return options;
    }
    return [
        ...options,
        {
            label: `${value} (unavailable)`,
            value,
            disabled: true,
        },
    ];
}
