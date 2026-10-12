import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * How extendBlock() wires `saveOutput` into Gutenberg: which filters it registers, and whether a
 * static block stores its function defaults.
 *
 * Gutenberg is not loaded. Each @wordpress module is stubbed with just enough to run the
 * registration and one render of the inspector filter's component: addFilter records, the HOC
 * factory returns its component as is, and effects run as soon as they are declared.
 */
const addFilter = vi.fn();

vi.mock('@wordpress/hooks', () => ({ addFilter }));
vi.mock('@wordpress/compose', () => ({ createHigherOrderComponent: (fn) => fn }));
vi.mock('@wordpress/block-editor', () => ({
    InspectorControls: () => null,
    store: {},
}));

vi.mock('@wordpress/components', () => ({ PanelBody: () => null }));
vi.mock('@wordpress/element', () => ({
    useEffect: (effect) => effect(),
    useMemo: (factory) => factory(),
    useRef: (current) => ({ current }),
}));

vi.mock('@wordpress/data', () => ({
    useDispatch: () => ({ __unstableMarkNextChangeAsNotPersistent: () => {} }),
    useSelect: () => ({}),
}));

const { extendBlock } = await import('../../modules/ExtendBlock/assets/scripts/includes/extend-block.jsx');

/** A field whose default is a function of the block, the kind a static block stores. */
const speed = {
    name: 'speed',
    type: 'select',
    attributeType: 'string',
    default: () => 'fast',
    render: () => null,
};

function register(saveOutput) {
    extendBlock({
        blocks: ['core/group'],
        namespace: 'test/wiring',
        panel: { title: 'Wiring' },
        fields: [speed],
        attributeGenerator: () => ({}),
        saveOutput,
    });
}

const registered = () => addFilter.mock.calls.map(([hook]) => hook);

/**
 * Renders the inspector filter's component once for an untouched static block, and returns what
 * it stored. The JSX compiles to React.createElement, so a stand-in React is provided for the
 * one call; the elements it returns are not under test.
 */
function storedOnMount() {
    const [, , filter] = addFilter.mock.calls.find(([hook]) => hook === 'editor.BlockEdit');
    const setAttributes = vi.fn();

    vi.stubGlobal('React', {
        createElement: () => null,
        Fragment: 'fragment',
    });

    try {
        filter(() => null)({
            name: 'core/group',
            clientId: 'a',
            attributes: {},
            setAttributes,
        });
    } finally {
        vi.unstubAllGlobals();
    }
    return setAttributes.mock.calls.map(([stored]) => stored);
}

describe('extendBlock saveOutput wiring', () => {
    beforeEach(() => {
        addFilter.mockClear();
    });

    it('registers the save filter by default', () => {
        register(undefined);

        expect(registered()).toContain('blocks.getSaveContent.extraProps');
        expect(registered()).toContain('editor.BlockListBlock');
    });

    it('registers no save filter under saveOutput: false, and keeps the canvas filter', () => {
        register(false);

        expect(registered()).not.toContain('blocks.getSaveContent.extraProps');
        expect(registered()).toContain('editor.BlockListBlock');
    });

    it('stores a static block’s function default when it saves output', () => {
        register(true);

        expect(storedOnMount()).toEqual([{ speed: 'fast' }]);
    });

    it('stores nothing under saveOutput: false, so the block keeps following the default', () => {
        register(false);

        expect(storedOnMount()).toEqual([]);
    });
});
