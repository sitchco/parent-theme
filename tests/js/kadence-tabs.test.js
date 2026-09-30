import { afterEach, describe, expect, it, vi } from 'vitest';
import { resolveKadenceTab } from '../../modules/ExtendBlock/assets/scripts/includes/utils/kadence-tabs';

describe('resolveKadenceTab', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('keeps the requested tab on a block that draws it', () => {
        const warn = vi.spyOn(globalThis.console, 'warn').mockImplementation(() => {});

        expect(resolveKadenceTab('kadence/rowlayout', 'style')).toBe('style');
        expect(resolveKadenceTab('kadence/advancedbtn', 'advanced')).toBe('advanced');
        expect(warn).not.toHaveBeenCalled();
    });

    it('treats a block it does not know as having every tab', () => {
        const warn = vi.spyOn(globalThis.console, 'warn').mockImplementation(() => {});

        expect(resolveKadenceTab('kadence/some-future-block', 'style')).toBe('style');
        expect(warn).not.toHaveBeenCalled();
    });

    /* The Buttons container passes allowedTabs={['general', 'advanced']}, so the active tab can
       never be 'style' there, and a Style-tab panel used to vanish on every tab. */
    it('falls back to general on a block without the requested tab', () => {
        vi.spyOn(globalThis.console, 'warn').mockImplementation(() => {});

        expect(resolveKadenceTab('kadence/spacer', 'style')).toBe('general');
        expect(resolveKadenceTab('kadence/table-row', 'advanced')).toBe('general');
    });

    it('warns once per block and tab, naming both', () => {
        const warn = vi.spyOn(globalThis.console, 'warn').mockImplementation(() => {});

        resolveKadenceTab('kadence/lottie', 'style', 'Animation');
        resolveKadenceTab('kadence/lottie', 'style', 'Animation');

        expect(warn).toHaveBeenCalledTimes(1);
        expect(warn.mock.calls[0][0]).toContain('kadence/lottie');
        expect(warn.mock.calls[0][0]).toContain("'style'");
        expect(warn.mock.calls[0][0]).toContain('Animation');
    });
});
