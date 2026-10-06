/**
 * What a number field stores for what its input reports.
 *
 * NumberControl reports a string, and `''` once the input is cleared. `Number('')` is 0, so
 * casting blindly turns "no value" into a value an author never chose — one that a 0-based
 * control would then emit. A cleared input stores `undefined` instead, which removes the
 * attribute: the field falls back to its default, a registered one or a function default alike.
 * Anything that isn't a finite number is treated the same way.
 *
 * Plain JS, so it is unit testable on its own. See tests/js/number-value.test.js.
 *
 * @param {string|number|undefined|null} input - What NumberControl's onChange passed
 * @returns {number|undefined}
 */
export function toNumberValue(input) {
    if (input === undefined || input === null || (typeof input === 'string' && input.trim() === '')) {
        return undefined;
    }

    const value = Number(input);
    return Number.isFinite(value) ? value : undefined;
}
