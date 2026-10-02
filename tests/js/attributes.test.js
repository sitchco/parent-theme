import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    generateFieldAttributes,
    mergeAttributes,
} from '../../modules/ExtendBlock/assets/scripts/includes/utils/attributes';

/** A field that emits one data attribute from its own value. */
function attributeField(name, attributeName, extra = {}) {
    return {
        name,
        attributes: (value) => ({ [attributeName]: value || undefined }),
        ...extra,
    };
}

describe('mergeAttributes', () => {
    beforeEach(() => {
        vi.spyOn(globalThis.console, 'warn').mockImplementation(() => {});
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('merges left to right, with later sources winning', () => {
        expect(
            mergeAttributes(
                {
                    'data-a': '1',
                    'data-b': '2',
                },
                { 'data-b': '3' }
            )
        ).toEqual({
            'data-a': '1',
            'data-b': '3',
        });
    });

    it('drops undefined and null, so an unset option adds nothing', () => {
        expect(
            mergeAttributes({
                'data-a': undefined,
                'data-b': null,
            })
        ).toEqual({});
    });

    it('keeps falsy values that are real, which is why the guard is not truthiness', () => {
        expect(
            mergeAttributes({
                'data-speed': 0,
                'data-reverse': false,
                'data-label': '',
            })
        ).toEqual({
            'data-speed': 0,
            'data-reverse': false,
            'data-label': '',
        });
    });

    it('drops the names the class channel owns', () => {
        expect(
            mergeAttributes({
                class: 'a',
                className: 'b',
                style: 'c',
                'data-keep': 'd',
            })
        ).toEqual({
            'data-keep': 'd',
        });
    });

    /* The channel is applied to the saved element with cloneElement, so an open one would let a
       generator replace the content, print raw HTML, or serialize an inline handler. */
    it('emits only data- and aria- attributes', () => {
        expect(
            mergeAttributes({
                children: 'replaced',
                dangerouslySetInnerHTML: { __html: '<b>raw</b>' },
                onClick: 'alert(1)',
                Class: 'second-class',
                id: 'x',
                'data-x': '1',
                'aria-x': '2',
            })
        ).toEqual({
            'data-x': '1',
            'aria-x': '2',
        });
    });

    it('warns once per dropped name', () => {
        mergeAttributes({ 'once-only': '1' });
        mergeAttributes({ 'once-only': '2' });

        expect(globalThis.console.warn).toHaveBeenCalledTimes(1);
        expect(globalThis.console.warn.mock.calls[0][0]).toContain("'once-only'");
    });

    it('drops an unset disallowed name without warning', () => {
        mergeAttributes({ 'never-set': undefined });

        expect(globalThis.console.warn).not.toHaveBeenCalled();
    });

    it('skips a source that is not a plain object', () => {
        expect(mergeAttributes('data-x', ['data-y'], 7, { 'data-a': '1' })).toEqual({ 'data-a': '1' });
    });

    it('ignores null and undefined sources', () => {
        expect(mergeAttributes(null, undefined, { 'data-a': '1' })).toEqual({ 'data-a': '1' });
    });
});

describe('generateFieldAttributes', () => {
    it('returns an empty object when no field emits anything', () => {
        const fields = [attributeField('animation', 'data-animation'), { name: 'other' }];

        expect(generateFieldAttributes(fields, { animation: '' })).toEqual({});
    });

    it('ignores fields with no attributes callback', () => {
        const fields = [
            {
                name: 'theme',
                className: (value) => `has-theme-${value}`,
            },
        ];

        expect(generateFieldAttributes(fields, { theme: 'purple' })).toEqual({});
    });

    it('emits from the field value', () => {
        const fields = [attributeField('animation', 'data-animation')];

        expect(generateFieldAttributes(fields, { animation: 'letter' })).toEqual({
            'data-animation': 'letter',
        });
    });

    /* The gate that makes switching animations safe: a stale value left behind by a control
       the author can no longer see must stop emitting, exactly as it does for classes. */
    it('suppresses a field whose condition is false, stale value and all', () => {
        const fields = [
            attributeField('animation', 'data-animation'),
            attributeField('parallaxSpeed', 'data-speed', {
                condition: (attributes) => attributes.animation === 'parallax',
            }),
        ];

        expect(
            generateFieldAttributes(fields, {
                animation: 'letter',
                parallaxSpeed: '25',
            })
        ).toEqual({
            'data-animation': 'letter',
        });

        expect(
            generateFieldAttributes(fields, {
                animation: 'parallax',
                parallaxSpeed: '25',
            })
        ).toEqual({
            'data-animation': 'parallax',
            'data-speed': '25',
        });
    });

    it('hands the context to both the condition and the generator', () => {
        const seen = [];
        const fields = [
            {
                name: 'animation',
                condition: (attributes, context) => {
                    seen.push(['condition', context.blockName]);
                    return true;
                },
                attributes: (value, context) => {
                    seen.push(['attributes', context.blockName]);
                    return { 'data-block': context.blockName };
                },
            },
        ];

        expect(generateFieldAttributes(fields, { animation: 'letter' }, { blockName: 'core/group' })).toEqual({
            'data-block': 'core/group',
        });

        expect(seen).toEqual([
            ['condition', 'core/group'],
            ['attributes', 'core/group'],
        ]);
    });

    it('lets a later field win on a key collision', () => {
        const fields = [
            {
                name: 'a',
                attributes: () => ({ 'data-x': 'first' }),
            },
            {
                name: 'b',
                attributes: () => ({ 'data-x': 'second' }),
            },
        ];

        expect(generateFieldAttributes(fields, {})).toEqual({ 'data-x': 'second' });
    });

    it('works without a context argument', () => {
        const fields = [attributeField('animation', 'data-animation')];

        expect(generateFieldAttributes(fields, { animation: 'letter' })).toEqual({
            'data-animation': 'letter',
        });
    });
});
