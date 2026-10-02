import { describe, expect, it } from 'vitest';
import { createEditorPropsBuilder } from '../../modules/ExtendBlock/assets/scripts/includes/utils/editor-props';

/**
 * What the canvas filter adds to a block's wrapper. Save is pinned by save-props.test.js; this is
 * the other half, and the two must agree for anything that isn't preview-only.
 */
const group = (attributes, extra = {}) => ({
    name: 'core/group',
    attributes,
    ...extra,
});

const animationField = {
    name: 'animation',
    className: (value) => (value ? `has-${value}` : null),
    attributes: (value) => ({ 'data-animation': value || undefined }),
};

/** responsive()'s three fields, reduced to what the editor cascade reads. */
function responsiveRadius() {
    const originalClassName = (value) => (value ? `rounded-${value}` : null);
    return ['', 'Tablet', 'Mobile'].map((suffix, index) => ({
        name: `radius${suffix}`,
        className: originalClassName,
        responsive: {
            baseName: 'radius',
            originalClassName,
            isDesktop: index === 0,
        },
    }));
}

describe('createEditorPropsBuilder', () => {
    it('adds nothing when no field emits anything', () => {
        const build = createEditorPropsBuilder([animationField]);

        expect(build(group({ animation: '' }))).toBeNull();
    });

    it('merges classes onto className and attributes onto wrapperProps', () => {
        const build = createEditorPropsBuilder([animationField]);

        expect(build(group({ animation: 'fade-up' }, { className: 'x' }))).toEqual({
            className: 'x has-fade-up',
            wrapperProps: { 'data-animation': 'fade-up' },
        });
    });

    /* Swapping the context and device arguments would hand the cascade an object for a device
       and fall through to the desktop value. */
    it('gives a responsive field the preview device', () => {
        const build = createEditorPropsBuilder(responsiveRadius());
        const attributes = {
            radius: 'lg',
            radiusTablet: 'md',
        };

        expect(build(group(attributes), 'Tablet')).toEqual({ className: 'rounded-md' });
        expect(build(group(attributes))).toEqual({ className: 'rounded-lg' });
    });

    it('gives the generators only the block name, whatever the device', () => {
        const seen = [];
        const build = createEditorPropsBuilder([], {
            classGenerator: (attributes, context) => {
                seen.push(context);
                return [];
            },
            attributeGenerator: (attributes, context) => {
                seen.push(context);
                return {};
            },
        });

        build(group({}, { clientId: 'abc' }), 'Mobile');

        expect(seen).toEqual([{ blockName: 'core/group' }, { blockName: 'core/group' }]);
    });

    /* withFilters wraps each later registration outside the earlier ones, so the wrapperProps an
       inner layer receives were produced by the later registrations. Letting them win is what
       makes the last registration win here, as it does on save. */
    it('lets the wrapperProps already present win a shared key', () => {
        const build = createEditorPropsBuilder([], {
            attributeGenerator: () => ({
                'data-x': 'inner',
                'data-y': 'inner',
            }),
        });

        expect(build(group({}, { wrapperProps: { 'data-x': 'outer' } }))).toEqual({
            wrapperProps: {
                'data-x': 'outer',
                'data-y': 'inner',
            },
        });
    });

    it('stacks two registrations on one block, last registration winning', () => {
        const first = createEditorPropsBuilder([], {
            classGenerator: () => ['first'],
            attributeGenerator: () => ({ 'data-x': 'first' }),
        });
        const second = createEditorPropsBuilder([], {
            classGenerator: () => ['second'],
            attributeGenerator: () => ({ 'data-x': 'second' }),
        });

        // The later registration is the outer layer, so it runs first and the earlier one sees its output.
        const outer = second(group({}));
        const inner = first(group({}, outer));

        expect(inner).toEqual({
            className: 'second first',
            wrapperProps: { 'data-x': 'second' },
        });
    });
});
