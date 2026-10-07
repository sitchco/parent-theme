/**
 * What a number field stores for what its input reports.
 *
 * NumberControl reports a string, and `''` once the input is cleared. `Number('')` is 0, so
 * casting blindly turns "no value" into a value an author never chose — one that a 0-based
 * control would then emit. A cleared input stores the field's fallback instead, and so does
 * anything that isn't a finite number:
 *
 * - A literal default is the fallback. Storing it back keeps the value seen for the rest of the
 *   session the same as the one a reload gives: the attribute equals its registered default, so
 *   serialization omits it and the parser fills the same default back in.
 * - A function default has no fallback here, so `undefined` is stored and the attribute is
 *   removed. readFieldValue() then supplies the default, and on a static block
 *   contextualDefaultsToStore() stores it again. See utils/field-value.js.
 *
 * Plain JS, so it is unit testable on its own. See tests/js/number-value.test.js.
 *
 * @param {string|number|undefined|null} input    - What NumberControl's onChange passed
 * @param {number|undefined}             fallback - What a cleared input stores: the field's literal
 *                                                  default, or undefined for a function default
 * @returns {number|undefined}
 */
export function toNumberValue(input, fallback = undefined) {
    if (input === undefined || input === null || (typeof input === 'string' && input.trim() === '')) {
        return fallback;
    }

    const value = Number(input);
    return Number.isFinite(value) ? value : fallback;
}
