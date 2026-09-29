import { describe, expect, it } from 'vitest';
import {
    resolveOptions,
    resolveResponsiveOptions,
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

    /* Responsive fields resolve through the same helper, with deviceType folded into the same
       context object rather than passed as a second, incompatible signature. */
    it('carries deviceType in the same context', () => {
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
