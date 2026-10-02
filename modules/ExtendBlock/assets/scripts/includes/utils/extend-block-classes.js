/**
 * Decides what the `extendBlockClasses` attribute should become for one namespace.
 *
 * Dynamic blocks render server-side, so the JS class filters never reach the front end. Instead
 * each extension syncs its generated class string into a namespace-keyed object attribute, which
 * ExtendBlockModule::injectExtendBlockClasses() flattens onto the rendered markup.
 *
 * Pulled out of the effect that calls it so the decision can be tested on its own — the effect
 * itself needs a mounted BlockEdit and six @wordpress packages, while every rule that matters
 * lives here. Plain JS, no JSX, no imports.
 *
 * @param {Object|string|undefined} current     - The block's current extendBlockClasses value
 * @param {string}                  namespace   - The extension writing
 * @param {string}                  classString - Its generated classes, possibly ''
 * @returns {Object|null} The value to write, or null when nothing needs writing
 */
export function nextExtendBlockClasses(current, namespace, classString) {
    const currentClasses = typeof current === 'object' && current !== null ? current : {};
    // Already correct. Without this the effect would write on every render.
    if (currentClasses[namespace] === classString) {
        return null;
    }
    /* Never CREATE a key that would only ever hold an empty string.
     *
     * `undefined !== ''` is true, so an extension generating no classes — one whose controls are
     * all at their defaults, or one like sitchco/form that declares no className at all — used to
     * write its namespace in on mount. That dirtied the post the moment an author opened it,
     * added an undo level, and persisted a key worth nothing: PHP cannot tell an absent key from
     * one mapping to '', because array_values() drops the keys and array_filter() drops the
     * empties before anything is concatenated.
     *
     * Deliberately only the CREATE case. Once the key exists it keeps being maintained, so
     * clearing a control still writes '' and the class still disappears. That is also why this is
     * `namespace in currentClasses` rather than a truthiness test — a key holding '' is present
     * and must stay maintained, not be treated as missing and re-skipped.
     */
    if (classString === '' && !(namespace in currentClasses)) {
        return null;
    }
    /* A legacy string value is unreachable from here when there is nothing to write, so it now
       survives untouched rather than being silently replaced by `{namespace: ''}`. A real class
       still migrates it to the object form, losing it — pre-existing, and no content in this
       project uses the string format. */
    return {
        ...currentClasses,
        [namespace]: classString,
    };
}
