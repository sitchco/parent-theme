import { describe, expect, it } from 'vitest';
import { toNumberValue } from '../../modules/ExtendBlock/assets/scripts/includes/utils/number-value.js';

describe('toNumberValue', () => {
    it('stores nothing for a cleared input, rather than 0', () => {
        expect(toNumberValue('')).toBeUndefined();
        expect(toNumberValue('  ')).toBeUndefined();
        expect(toNumberValue(undefined)).toBeUndefined();
        expect(toNumberValue(null)).toBeUndefined();
    });

    it('keeps a typed 0, which is a value an author chose', () => {
        expect(toNumberValue('0')).toBe(0);
        expect(toNumberValue(0)).toBe(0);
    });

    it('keeps fractions and negatives', () => {
        expect(toNumberValue('0.75')).toBe(0.75);
        expect(toNumberValue('-5')).toBe(-5);
    });

    it('stores nothing for input that is not a finite number', () => {
        expect(toNumberValue('fast')).toBeUndefined();
        expect(toNumberValue('Infinity')).toBeUndefined();
    });
});
