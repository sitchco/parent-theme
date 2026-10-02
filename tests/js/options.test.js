import { describe, expect, it } from 'vitest';
import {
    resolveOptions,
    resolveResponsiveOptions,
    withStaleValue,
} from '../../modules/ExtendBlock/assets/scripts/includes/utils/options';

describe('resolveOptions', () => {
    it('passes a list straight through', () => {
        const options = [
            {
                label: 'None',
                value: '',
            },
        ];

        expect(resolveOptions({ options })).toBe(options);
    });

    it('calls the function form with the render context', () => {
        const field = {
            options: ({ blockName }) => [
                {
                    label: blockName,
                    value: blockName,
                },
            ],
        };

        expect(resolveOptions(field, { blockName: 'core/group' })).toEqual([
            {
                label: 'core/group',
                value: 'core/group',
            },
        ]);
    });

    /* resolveOptions passes along whatever context it is given. Responsive fields do not come
       through here: they are called (deviceType, context) — see resolveResponsiveOptions below. */
    it('passes the whole context through', () => {
        const field = {
            options: ({ deviceType }) => [
                {
                    label: deviceType,
                    value: deviceType,
                },
            ],
        };

        expect(
            resolveOptions(field, {
                blockName: 'kadence/column',
                deviceType: 'Tablet',
            })
        ).toEqual([
            {
                label: 'Tablet',
                value: 'Tablet',
            },
        ]);
    });

    it('defaults the context so a bare call cannot throw', () => {
        expect(resolveOptions({ options: (context) => Object.keys(context) })).toEqual([]);
    });

    it('returns undefined when a field declares no options', () => {
        expect(resolveOptions({})).toBeUndefined();
    });
});

describe('resolveResponsiveOptions', () => {
    it('passes a list straight through', () => {
        const options = [
            {
                label: 'None',
                value: '',
            },
        ];

        expect(resolveResponsiveOptions({ options })).toBe(options);
    });

    /* The signature themes/roundabout ships today. The parent theme deploys separately from the
       child, so breaking this would silently mis-resolve the border-radius options for as long as
       the two repos are out of step — `'Desktop' === undefined` is false, not an error. */
    it('calls a legacy function with the breakpoint string first', () => {
        const field = {
            options: (breakpoint) =>
                breakpoint === 'Desktop'
                    ? [
                          {
                              label: 'Desktop only',
                              value: 'd',
                          },
                      ]
                    : [
                          {
                              label: 'Other',
                              value: 'o',
                          },
                      ],
        };

        expect(resolveResponsiveOptions(field, { deviceType: 'Desktop' })).toEqual([
            {
                label: 'Desktop only',
                value: 'd',
            },
        ]);

        expect(resolveResponsiveOptions(field, { deviceType: 'Mobile' })).toEqual([
            {
                label: 'Other',
                value: 'o',
            },
        ]);
    });

    it('also offers the full context as a second argument', () => {
        const field = {
            options: (deviceType, { blockName }) => [
                {
                    label: `${blockName}:${deviceType}`,
                    value: 'x',
                },
            ],
        };

        expect(
            resolveResponsiveOptions(field, {
                blockName: 'kadence/rowlayout',
                deviceType: 'Tablet',
            })
        ).toEqual([
            {
                label: 'kadence/rowlayout:Tablet',
                value: 'x',
            },
        ]);
    });

    it('defaults the context so a bare call cannot throw', () => {
        expect(resolveResponsiveOptions({ options: (deviceType) => [deviceType] })).toEqual([undefined]);
    });
});

describe('withStaleValue', () => {
    const options = [
        {
            label: 'None',
            value: '',
        },
        {
            label: 'Fade Up',
            value: 'fade-up',
        },
    ];

    it('leaves the list alone when the value is empty', () => {
        expect(withStaleValue(options, '')).toBe(options);
        expect(withStaleValue(options, undefined)).toBe(options);
    });

    it('leaves the list alone when the value is offered', () => {
        expect(withStaleValue(options, 'fade-up')).toBe(options);
    });

    /* Otherwise SelectControl shows None while the attribute keeps the old value, and picking
       None does nothing because it already looks selected. */
    it('appends a value no option offers as a disabled entry', () => {
        expect(withStaleValue(options, 'parallax')).toEqual([
            ...options,
            {
                label: 'parallax (unavailable)',
                value: 'parallax',
                disabled: true,
            },
        ]);
    });

    it('does not mutate the list it was given', () => {
        withStaleValue(options, 'parallax');

        expect(options).toHaveLength(2);
    });

    it('passes a missing list through', () => {
        expect(withStaleValue(undefined, 'parallax')).toBeUndefined();
    });
});
