import { describe, expect, it } from 'vitest';
import { nextExtendBlockClasses } from '../../modules/ExtendBlock/assets/scripts/includes/utils/extend-block-classes';

const NS = 'roundabout/pull-controls';

describe('nextExtendBlockClasses', () => {
    describe('creating a key', () => {
        it('creates it for a real class string', () => {
            expect(nextExtendBlockClasses(undefined, NS, 'pull-self-up')).toEqual({ [NS]: 'pull-self-up' });
        });

        /* The guard. `undefined !== ''` is true, so this used to write the namespace in on mount
           for any extension whose controls were all at their defaults — dirtying the post as soon
           as an author opened it, and persisting a key PHP cannot distinguish from an absent one. */
        it('does NOT create it for an empty class string', () => {
            expect(nextExtendBlockClasses(undefined, NS, '')).toBeNull();
        });

        it('does not create it just because other namespaces are present', () => {
            expect(nextExtendBlockClasses({ 'sitchco/kadence-row-subgrid': 'kb-subgrid-layout' }, NS, '')).toBeNull();
        });

        it('keeps sibling namespaces when it does create one', () => {
            const current = { 'sitchco/kadence-row-subgrid': 'kb-subgrid-layout' };

            expect(nextExtendBlockClasses(current, NS, 'pull-self-up')).toEqual({
                'sitchco/kadence-row-subgrid': 'kb-subgrid-layout',
                [NS]: 'pull-self-up',
            });
        });
    });

    describe('maintaining a key that already exists', () => {
        /* The half the guard deliberately leaves alone: once the key exists it keeps being
           written, so clearing a control back to None still empties the class on the front end.
           This is why the guard tests `in` rather than truthiness — a key holding '' is present. */
        it('writes an empty string when clearing a control', () => {
            expect(nextExtendBlockClasses({ [NS]: 'pull-self-up' }, NS, '')).toEqual({ [NS]: '' });
        });

        it('keeps maintaining a key that already holds an empty string', () => {
            expect(nextExtendBlockClasses({ [NS]: '' }, NS, 'pull-next-under')).toEqual({
                [NS]: 'pull-next-under',
            });
        });

        it('replaces one class string with another', () => {
            expect(nextExtendBlockClasses({ [NS]: 'pull-self-up' }, NS, 'pull-next-under')).toEqual({
                [NS]: 'pull-next-under',
            });
        });
    });

    describe('writing nothing', () => {
        it('returns null when the value is already correct', () => {
            expect(nextExtendBlockClasses({ [NS]: 'pull-self-up' }, NS, 'pull-self-up')).toBeNull();
        });

        it('returns null for an already-correct empty string, so it cannot loop', () => {
            expect(nextExtendBlockClasses({ [NS]: '' }, NS, '')).toBeNull();
        });
    });

    describe('tolerating what the attribute can actually hold', () => {
        it.each([
            ['undefined', undefined],
            ['null', null],
            ['an empty object', {}],
        ])('treats %s as no namespaces yet', (_label, current) => {
            expect(nextExtendBlockClasses(current, NS, 'pull-self-up')).toEqual({ [NS]: 'pull-self-up' });
            expect(nextExtendBlockClasses(current, NS, '')).toBeNull();
        });

        /* The legacy string format ExtendBlockModule still reads. Previously the first extension
           with nothing to write replaced the whole string with `{ns: ''}`, silently dropping those
           classes; now there is nothing to write, so it survives. */
        it('leaves a legacy string alone when there is nothing to write', () => {
            expect(nextExtendBlockClasses('legacy-a legacy-b', NS, '')).toBeNull();
        });
    });

    /* The shape every one of the 35 serialized occurrences in this project actually has: three
       namespaces, all present, most of them empty. Existing content must behave exactly as before
       — the guard only ever suppresses creation. */
    describe('against the shape already in saved content', () => {
        const saved = {
            'roundabout/border-radius-controls': '',
            'roundabout/pull-controls': '',
            'sitchco/kadence-row-subgrid': '',
        };

        it('leaves every already-empty key untouched', () => {
            for (const namespace of Object.keys(saved)) {
                expect(nextExtendBlockClasses(saved, namespace, '')).toBeNull();
            }
        });

        it('still lets an author set a class on any of them', () => {
            expect(nextExtendBlockClasses(saved, 'roundabout/border-radius-controls', 'rounded-t-sm')).toEqual({
                ...saved,
                'roundabout/border-radius-controls': 'rounded-t-sm',
            });
        });

        it('does not add a namespace the saved content never had', () => {
            expect(nextExtendBlockClasses(saved, 'roundabout/animation-controls', '')).toBeNull();
        });
    });
});
