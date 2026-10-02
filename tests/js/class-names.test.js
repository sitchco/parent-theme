import { describe, expect, it } from 'vitest';
import {
    generateEditorFieldClasses,
    generateFieldClasses,
    prefixClassName,
    toClassList,
} from '../../modules/ExtendBlock/assets/scripts/includes/utils/class-names';

/**
 * The three field definitions responsive() produces for one base name, reduced to what the class
 * generators read. Tablet and mobile prefix their output; the editor cascade uses only the desktop
 * entry and the unprefixed originalClassName.
 */
function responsiveRadius() {
    const originalClassName = (value) => (value ? `rounded-${value}` : null);
    return ['', 'Tablet', 'Mobile'].map((suffix, index) => ({
        name: `radius${suffix}`,
        className: suffix ? (v) => (v ? `${suffix.toLowerCase()}:rounded-${v}` : null) : originalClassName,
        responsive: {
            baseName: 'radius',
            originalClassName,
            isDesktop: index === 0,
        },
    }));
}

describe('generateFieldClasses', () => {
    it('collects string and array results, skipping empties', () => {
        const fields = [
            {
                name: 'a',
                className: (v) => v,
            },
            {
                name: 'b',
                className: () => ['b-1', 'b-2'],
            },
            {
                name: 'c',
                className: () => null,
            },
            { name: 'd' },
        ];

        expect(generateFieldClasses(fields, { a: 'a-1' })).toEqual(['a-1', 'b-1', 'b-2']);
    });

    it('skips a field hidden by its condition, handing it the context', () => {
        const seen = [];
        const fields = [
            {
                name: 'color',
                className: (v) => `color-${v}`,
                condition: (attributes, context) => {
                    seen.push(context);
                    return attributes.animation === 'letter';
                },
            },
        ];

        expect(
            generateFieldClasses(
                fields,
                {
                    animation: 'fade',
                    color: 'red',
                },
                { blockName: 'core/group' }
            )
        ).toEqual([]);

        expect(
            generateFieldClasses(fields, {
                animation: 'letter',
                color: 'red',
            })
        ).toEqual(['color-red']);

        expect(seen[0]).toEqual({ blockName: 'core/group' });
    });

    it('hands className the output context', () => {
        const seen = [];
        const fields = [
            {
                name: 'theme',
                className: (value, context) => {
                    seen.push(context);
                    return `theme-${value}`;
                },
            },
        ];

        generateFieldClasses(fields, { theme: 'dark' }, { blockName: 'core/group' });

        expect(seen).toEqual([{ blockName: 'core/group' }]);
    });

    /* responsive() builds its tablet and mobile `className` with prefixClassName, and those are
       what save calls. The wrapper must not swallow the context on the way through. */
    it('hands a prefixed breakpoint className the output context', () => {
        const seen = [];
        const fields = [
            {
                name: 'radiusTablet',
                className: prefixClassName((value, context) => {
                    seen.push(context);
                    return `rounded-${value}`;
                }, 'tablet:'),
            },
        ];

        expect(generateFieldClasses(fields, { radiusTablet: 'md' }, { blockName: 'kadence/column' })).toEqual([
            'tablet:rounded-md',
        ]);

        expect(seen).toEqual([{ blockName: 'kadence/column' }]);
    });
});

describe('generateEditorFieldClasses', () => {
    const attributes = {
        radius: 'lg',
        radiusTablet: 'md',
        radiusMobile: '',
    };

    it('uses the desktop value by default', () => {
        expect(generateEditorFieldClasses(responsiveRadius(), attributes)).toEqual(['rounded-lg']);
    });

    it('cascades mobile to tablet to desktop, unprefixed', () => {
        const fields = responsiveRadius();

        expect(generateEditorFieldClasses(fields, attributes, {}, 'Tablet')).toEqual(['rounded-md']);
        // Mobile is empty, so it inherits the tablet value.
        expect(generateEditorFieldClasses(fields, attributes, {}, 'Mobile')).toEqual(['rounded-md']);
        expect(generateEditorFieldClasses(fields, { radius: 'lg' }, {}, 'Mobile')).toEqual(['rounded-lg']);
    });

    it('passes non-responsive fields through unchanged', () => {
        const fields = [
            {
                name: 'theme',
                className: (v) => `theme-${v}`,
            },
        ];

        expect(generateEditorFieldClasses(fields, { theme: 'dark' }, {}, 'Mobile')).toEqual(['theme-dark']);
    });

    /* The device is its own argument precisely so the condition cannot see it: condition gates
       output, and save has no device to give it. */
    it('hands condition the output context, never the device', () => {
        const seen = [];
        const fields = responsiveRadius().map((f) => ({
            ...f,
            condition: (attrs, context) => {
                seen.push(context);
                return true;
            },
        }));

        generateEditorFieldClasses(fields, attributes, { blockName: 'kadence/column' }, 'Tablet');

        expect(seen[0]).toEqual({ blockName: 'kadence/column' });
    });

    it('hands the responsive className the output context, never the device', () => {
        const seen = [];

        const originalClassName = (value, context) => {
            seen.push(context);
            return value ? `rounded-${value}` : null;
        };

        const fields = responsiveRadius().map((f) => ({
            ...f,
            responsive: {
                ...f.responsive,
                originalClassName,
            },
        }));

        generateEditorFieldClasses(fields, attributes, { blockName: 'kadence/column' }, 'Tablet');

        expect(seen).toEqual([{ blockName: 'kadence/column' }]);
    });

    it('hands a non-responsive className the output context', () => {
        const seen = [];
        const fields = [
            {
                name: 'theme',
                className: (value, context) => {
                    seen.push(context);
                    return `theme-${value}`;
                },
            },
        ];

        generateEditorFieldClasses(fields, { theme: 'dark' }, { blockName: 'core/group' }, 'Mobile');

        expect(seen).toEqual([{ blockName: 'core/group' }]);
    });
});

describe('toClassList', () => {
    it.each([
        [undefined, []],
        [null, []],
        [false, []],
        ['', []],
        [[''], []],
        [[null, false, ''], []],
        ['a  b', ['a', 'b']],
        [
            ['a', ['b c']],
            ['a', 'b', 'c'],
        ],
        [7, []],
    ])('normalizes %j to %j', (output, expected) => {
        expect(toClassList(output)).toEqual(expected);
    });
});
