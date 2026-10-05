import { describe, expect, it } from 'vitest';
import { fieldsToAttributes } from '../../modules/ExtendBlock/assets/scripts/includes/utils/fields-to-attributes';

describe('fieldsToAttributes', () => {
    it('registers a literal default with its type', () => {
        expect(
            fieldsToAttributes([
                {
                    name: 'tone',
                    attributeType: 'string',
                    default: 'warm',
                },
            ])
        ).toEqual({
            tone: {
                type: 'string',
                default: 'warm',
            },
        });
    });

    it('registers a function default with its type only, so the attribute stays undefined until stored', () => {
        const attributes = fieldsToAttributes([
            {
                name: 'speed',
                attributeType: 'number',
                default: () => 25,
            },
        ]);

        expect(attributes).toEqual({ speed: { type: 'number' } });
        expect(Object.hasOwn(attributes.speed, 'default')).toBe(false);
    });

    it('keeps a falsy literal default', () => {
        expect(
            fieldsToAttributes([
                {
                    name: 'reverse',
                    attributeType: 'boolean',
                    default: false,
                },
            ])
        ).toEqual({
            reverse: {
                type: 'boolean',
                default: false,
            },
        });
    });

    it('refuses a field with no name', () => {
        expect(() => fieldsToAttributes([{ attributeType: 'string' }])).toThrow('missing required "name"');
    });
});
