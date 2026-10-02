import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls, store as blockEditorStore } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { useEffect, useMemo } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { fieldsToAttributes } from './fields';
import { generateFieldClasses, toClassList } from './utils/class-names';
import { createSavePropsFilter } from './utils/save-props';
import { createEditorPropsBuilder } from './utils/editor-props';
import { nextExtendBlockClasses } from './utils/extend-block-classes';
import { useKadenceActiveTab, isKadenceBlock } from './hooks/use-kadence-active-tab';
import { resolveKadenceTab } from './utils/kadence-tabs';
import { contextualDefaultsToStore, readFieldValue } from './utils/field-value';

/**
 * Dynamic blocks that render server-side and need PHP filter treatment.
 * Most blocks are static (fully rendered by the editor), so we explicitly
 * opt-in only the ones that need special handling.
 */
const DYNAMIC_BLOCKS = ['kadence/rowlayout', 'kadence/accordion', 'gravityforms/form'];

/**
 * Checks if a block is a dynamic block (rendered server-side).
 *
 * @param {string} blockName - The block name to check
 * @returns {boolean}
 */
function isDynamicBlock(blockName) {
    return DYNAMIC_BLOCKS.includes(blockName);
}

/**
 * Checks if a block name matches the target blocks.
 *
 * @param {string} blockName - The block name to check
 * @param {string[]} targetBlocks - Array of target block names
 * @returns {boolean}
 */
function isTargetBlock(blockName, targetBlocks) {
    return targetBlocks.includes(blockName);
}

/**
 * Checks if any of the target blocks are Kadence blocks.
 *
 * @param {string[]} blocks - Array of block names
 * @returns {boolean}
 */
function hasKadenceBlocks(blocks) {
    return blocks.some(isKadenceBlock);
}

/**
 * Normalizes the config to always have a panels array.
 *
 * @param {Object} config - The extendBlock config
 * @returns {Object[]} Normalized panels array
 */
function normalizePanels(config) {
    const { panel, panels, fields } = config;
    if (panels) {
        return panels;
    }
    if (panel && fields) {
        return [
            {
                ...panel,
                fields,
            },
        ];
    }
    return [];
}

/**
 * Collects all fields from all panels.
 *
 * @param {Object[]} panels - Array of panel configurations
 * @returns {Object[]} All fields
 */
function collectAllFields(panels) {
    return panels.flatMap((p) => p.fields || []).flat();
}

/**
 * Creates the attribute registration filter.
 *
 * @param {string[]} targetBlocks - Block names to target
 * @param {Object[]} allFields - All field definitions
 * @param {boolean} includeClassesAttribute - Whether to add extendBlockClasses attribute for dynamic blocks
 * @returns {Function} Filter function
 */
function createAttributeFilter(targetBlocks, allFields, includeClassesAttribute = false) {
    return (settings, name) => {
        if (!isTargetBlock(name, targetBlocks)) {
            return settings;
        }

        const attributes = {
            ...settings.attributes,
            ...fieldsToAttributes(allFields),
        };
        // Add extendBlockClasses attribute for dynamic blocks
        // This is an object keyed by namespace to allow multiple extensions to contribute classes
        if (includeClassesAttribute && isDynamicBlock(name)) {
            attributes.extendBlockClasses = {
                type: 'object',
                default: {},
            };
        }
        return {
            ...settings,
            attributes,
        };
    };
}

/**
 * Creates the inspector controls filter.
 *
 * @param {string[]} targetBlocks - Block names to target
 * @param {Object[]} panels - Panel configurations
 * @param {string} [panels[].kadenceTab] - On Kadence blocks, which of Kadence's own tabs
 *   ('general' | 'style' | 'advanced') this panel renders on. Defaults to 'general', and falls
 *   back to it (with a console warning) on a block that doesn't draw the requested tab.
 *   Ignored for non-Kadence blocks and when `kadenceTabAware` is false.
 * @param {Object[]} allFields - All field definitions (for class sync)
 * @param {string} namespace - Unique namespace for this extension
 * @param {Object} options - Additional options
 * @param {Function} [options.shouldRender] - Custom render condition
 * @param {Function} [options.useSetup] - Custom setup hook
 * @param {boolean} [options.kadenceTabAware] - Whether to auto-detect Kadence tabs
 * @param {Function} [options.classGenerator] - Custom class generator override
 * @returns {Function} Higher-order component
 */
function createInspectorFilter(targetBlocks, panels, allFields, namespace, options = {}) {
    const { shouldRender, useSetup, kadenceTabAware, classGenerator } = options;
    return createHigherOrderComponent((BlockEdit) => {
        return (props) => {
            if (!isTargetBlock(props.name, targetBlocks)) {
                return <BlockEdit {...props} />;
            }

            const { attributes, setAttributes } = props;
            const isDynamic = isDynamicBlock(props.name);

            // Identifies which block is being rendered, so one registration can serve several
            // blocks whose options differ. Stable for the life of the component. The render
            // context is for drawing a control; everything that decides output — condition,
            // className, attributes and the generators — gets the output context, which is the
            // same `{ blockName }` save sees. See the note at the top of utils/attributes.js.
            const context = {
                blockName: props.name,
                clientId: props.clientId,
            };
            const outputContext = { blockName: props.name };

            // Extract only the field attribute values to avoid depending on full attributes object
            // Effective values, so a function default that changes what a field emits counts too.
            const fieldValues = allFields.map((field) => readFieldValue(field, attributes, outputContext));

            // Memoize the class string based only on relevant field values
            const classString = useMemo(() => {
                const newClasses = classGenerator
                    ? toClassList(classGenerator(attributes, outputContext))
                    : generateFieldClasses(allFields, attributes, outputContext);
                return newClasses.join(' ');
            }, fieldValues);

            // Sync generated classes to extendBlockClasses attribute for dynamic blocks
            // Each extension stores its classes under its namespace key
            useEffect(() => {
                if (!isDynamic) {
                    return;
                }

                // Every rule about what to write lives in nextExtendBlockClasses, including why an
                // extension with nothing to say must not create its key. null means leave it alone.
                const nextClasses = nextExtendBlockClasses(attributes.extendBlockClasses, namespace, classString);
                if (nextClasses) {
                    setAttributes({ extendBlockClasses: nextClasses });
                }
            }, [classString, isDynamic, namespace, setAttributes, attributes.extendBlockClasses]);

            // A static block stores its function defaults once they apply, so its saved markup
            // never depends on a default that can change later. See utils/field-value.js.
            const { __unstableMarkNextChangeAsNotPersistent: markNotPersistent } = useDispatch(blockEditorStore);
            const defaultsToStore = isDynamic ? null : contextualDefaultsToStore(allFields, attributes, outputContext);
            const defaultsKey = defaultsToStore ? JSON.stringify(defaultsToStore) : '';
            useEffect(() => {
                if (!defaultsToStore) {
                    return;
                }

                // Folded into the change that made the field apply, so undo cannot step back to a
                // state this effect would immediately fill in again.
                markNotPersistent();
                setAttributes(defaultsToStore);
            }, [defaultsKey]);

            // Auto-detect Kadence tab if enabled and this is a Kadence block
            const isKadence = kadenceTabAware && isKadenceBlock(props.name);
            const kadenceTab = useKadenceActiveTab(
                isKadence
                    ? props
                    : {
                          clientId: null,
                          name: '',
                      }
            );

            // Run custom setup hook if provided
            const setupResult = useSetup ? useSetup(props) : {};
            // Check custom render condition
            if (shouldRender && !shouldRender(props, setupResult)) {
                return <BlockEdit {...props} />;
            }

            // Kadence's General/Style/Advanced bar is not Gutenberg's tab system — all three
            // tabs render into the same default inspector group, toggled by React state in the
            // `kadenceblocks/data` store. So tab targeting is ours to do: a panel declares the
            // tab it belongs to and is simply not rendered on the others. Defaults to 'general',
            // which is where every panel lived before `kadenceTab` existed. A block that doesn't
            // draw the requested tab gets the panel on 'general' instead (see utils/kadence-tabs.js).
            const activeKadenceTab = kadenceTab.activeTab || 'general';
            return (
                <>
                    <BlockEdit {...props} />
                    {panels.map((panel, panelIndex) => {
                        if (
                            isKadence &&
                            resolveKadenceTab(props.name, panel.kadenceTab ?? 'general', panel.title) !==
                                activeKadenceTab
                        ) {
                            return null;
                        }
                        // Keyed by the original index, so returning null above doesn't shift
                        // the keys of the panels that do render.
                        return (
                            <InspectorControls key={panelIndex} group={panel.group || 'settings'}>
                                <PanelBody title={panel.title} initialOpen={panel.initialOpen ?? true}>
                                    {panel.fields?.flat().map((field) => {
                                        if (!field.render) {
                                            return null;
                                        }
                                        if (field.condition && !field.condition(attributes, outputContext)) {
                                            return null;
                                        }

                                        const value = readFieldValue(field, attributes, outputContext);
                                        const onChange = (newValue) => setAttributes({ [field.name]: newValue });
                                        return (
                                            <field.render
                                                key={field.name}
                                                field={field}
                                                value={value}
                                                onChange={onChange}
                                                attributes={attributes}
                                                setAttributes={setAttributes}
                                                context={context}
                                            />
                                        );
                                    })}
                                </PanelBody>
                            </InspectorControls>
                        );
                    })}
                </>
            );
        };
    }, 'withExtendedBlockControls');
}

/**
 * Creates the editor block list props filter — the canvas counterpart of the save filter.
 *
 * Note the asymmetry with the save path: `editor.BlockListBlock` forwards only `className` and
 * `wrapperProps` to the DOM, so arbitrary props passed at the top level are dropped silently.
 * Attributes therefore go through `wrapperProps`. Core and the block apply their own wrapper
 * props after ours, so on any key they also set they win in the canvas, while the extension
 * wins on save. See the precedence note in utils/editor-props.js.
 *
 * @param {string[]} targetBlocks - Block names to target
 * @param {Object[]} allFields - All field definitions
 * @param {Object} [generators]
 * @param {Function} [generators.classGenerator] - Custom class generator override
 * @param {Function} [generators.attributeGenerator] - Custom attribute generator override
 * @returns {Function} Higher-order component
 */
function createEditorPropsFilter(targetBlocks, allFields, generators = {}) {
    const hasResponsiveFields = allFields.some((f) => f.responsive);
    const buildProps = createEditorPropsBuilder(allFields, generators);
    return createHigherOrderComponent((BlockListBlock) => {
        return (props) => {
            if (!isTargetBlock(props.name, targetBlocks)) {
                return <BlockListBlock {...props} />;
            }

            const deviceType = useSelect(
                (select) => {
                    if (!hasResponsiveFields) {
                        return 'Desktop';
                    }
                    return select('core/editor')?.getDeviceType?.() || 'Desktop';
                },
                [hasResponsiveFields]
            );
            return <BlockListBlock {...props} {...buildProps(props, deviceType)} />;
        };
    }, 'withExtendedBlockProps');
}

/**
 * Extends one or more Gutenberg blocks with custom attributes, inspector controls, and classes.
 *
 * For Kadence blocks (kadence/*), inspector controls appear on the "General" tab by default.
 * Give a panel `kadenceTab: 'style'` or `'advanced'` to put it on one of the other two. Blocks
 * that don't draw that tab (the Buttons container, Spacer, form fields, …) get the panel on
 * 'General' instead, with a one-time console warning.
 * Set `kadenceTabAware: false` to opt out of tab targeting entirely and always render.
 *
 * @param {Object} config - Extension configuration
 * @param {string|string[]} config.blocks - Block name(s) to extend
 * @param {string} config.namespace - Unique namespace for hook registration
 * @param {Object} [config.panel] - Single panel configuration (shorthand)
 * @param {string} [config.panel.kadenceTab] - Kadence tab to render on: 'general' (default),
 *   'style', or 'advanced'
 * @param {Object[]} [config.panels] - Multiple panel configurations
 * @param {Object[]} [config.fields] - Fields for single panel (used with config.panel)
 * @param {Function} [config.shouldRender] - Custom condition for rendering controls
 * @param {Function} [config.useSetup] - Custom setup hook for complex logic
 * @param {Function} [config.classGenerator] - Override default class generation:
 *   (attributes, { blockName }) => string[]. Only takes effect alongside fields or an
 *   attributeGenerator; for classes alone, use extendBlockClasses().
 * @param {Function} [config.attributeGenerator] - Override default attribute generation:
 *   (attributes, { blockName }) => Object of `data-*` / `aria-*` attributes; any other name is
 *   dropped. They are merged onto a static block's saved wrapper and, in the editor canvas, onto
 *   wrapperProps. A dynamic block's front end does not get them until S7 — only classes are
 *   synced to it. Return `undefined` for anything unset: `''`, `0` and `false` are kept and
 *   serialize. See the rules at the top of utils/attributes.js.
 *
 * `condition`, `className`, `attributes` and both generators receive `{ blockName }` in every
 * phase — save, inspector and canvas alike — so none of them can make the editor preview and the
 * saved markup disagree. Only `render` and `options` see the richer render context.
 * @param {boolean} [config.kadenceTabAware] - Auto-detect Kadence tabs (default: true for kadence/* blocks)
 *
 * @example
 * // Single panel
 * extendBlock({
 *     blocks: ['core/button'],
 *     namespace: 'mytheme/button',
 *     panel: { title: 'Theme', group: 'styles' },
 *     fields: [
 *         fields.select({ name: 'theme', label: 'Theme', options: [...] }),
 *     ],
 * });
 *
 * @example
 * // Kadence block (automatically tab-aware)
 * extendBlock({
 *     blocks: ['kadence/rowlayout'],
 *     namespace: 'mytheme/rowlayout',
 *     panel: { title: 'Custom Settings', group: 'settings' },
 *     fields: [...],
 * });
 *
 * @example
 * // Kadence block, panel placed on Kadence's "Style" tab.
 * // Keep group: 'settings' — group: 'styles' fills WP's own Styles slot, which makes core
 * // push a second native "Settings | Styles" tab bar above Kadence's.
 * extendBlock({
 *     blocks: ['kadence/rowlayout', 'kadence/column'],
 *     namespace: 'mytheme/animations',
 *     panel: { title: 'Animations', group: 'settings', kadenceTab: 'style' },
 *     fields: [...],
 * });
 *
 * @example
 * // Emitting data attributes alongside (or instead of) classes
 * extendBlock({
 *     blocks: ['core/group'],
 *     namespace: 'mytheme/animation',
 *     panel: { title: 'Animation', group: 'settings' },
 *     fields: [
 *         fields.select({
 *             name: 'animation',
 *             label: 'Animation',
 *             options: ({ blockName }) => optionsFor(blockName),
 *             attributes: (value) => ({ 'data-animation': value || undefined }),
 *         }),
 *     ],
 * });
 *
 * @example
 * // Kadence block with tab awareness disabled
 * extendBlock({
 *     blocks: ['kadence/column'],
 *     namespace: 'mytheme/column',
 *     kadenceTabAware: false,
 *     panel: { title: 'Always Visible', group: 'settings' },
 *     fields: [...],
 * });
 */
export function extendBlock(config) {
    const {
        blocks: blocksConfig,
        namespace,
        shouldRender,
        useSetup,
        classGenerator,
        attributeGenerator,
        kadenceTabAware,
    } = config;
    if (!namespace) {
        throw new Error('extendBlock requires a namespace');
    }

    // Normalize blocks to array
    const blocks = Array.isArray(blocksConfig) ? blocksConfig : [blocksConfig];

    // Auto-enable Kadence tab awareness if any target blocks are Kadence blocks
    // Can be explicitly disabled with kadenceTabAware: false
    const enableKadenceTabAware = kadenceTabAware ?? hasKadenceBlocks(blocks);

    // Normalize panels
    const panels = normalizePanels(config);

    // Collect all fields
    const allFields = collectAllFields(panels);

    // Check if any target blocks are dynamic (need extendBlockClasses attribute)
    const hasDynamicBlocks = blocks.some(isDynamicBlock);
    // 1. Register attributes (include extendBlockClasses for dynamic blocks). Only fields
    //    produce block attributes, so a generator-only extension registers nothing here.
    if (allFields.length > 0) {
        addFilter(
            'blocks.registerBlockType',
            `${namespace}/add-attributes`,
            createAttributeFilter(blocks, allFields, hasDynamicBlocks)
        );
    }
    // 3 & 4. Emit classes and attributes into saved content and the editor canvas.
    //    `classGenerator` alone still belongs to extendBlockClasses(), unchanged — it is
    //    `attributeGenerator` that earns a registration without fields, because there is no
    //    attributes-only path through the field list.
    if (allFields.length > 0 || attributeGenerator) {
        addFilter(
            'blocks.getSaveContent.extraProps',
            `${namespace}/add-save-props`,
            createSavePropsFilter(blocks, allFields, {
                classGenerator,
                attributeGenerator,
            })
        );

        addFilter(
            'editor.BlockListBlock',
            `${namespace}/add-editor-props`,
            createEditorPropsFilter(blocks, allFields, {
                classGenerator,
                attributeGenerator,
            })
        );
    }
    // 2. Add inspector controls (only if we have panels with fields)
    if (panels.length > 0 && panels.some((p) => p.fields?.length > 0)) {
        addFilter(
            'editor.BlockEdit',
            `${namespace}/add-controls`,
            createInspectorFilter(blocks, panels, allFields, namespace, {
                shouldRender,
                useSetup,
                kadenceTabAware: enableKadenceTabAware,
                classGenerator,
            })
        );
    }
}

/**
 * Extends blocks with only class generation (no controls).
 * Useful for blocks like kadence-column-background that detect existing attributes.
 *
 * @param {Object} config - Extension configuration
 * @param {string|string[]} config.blocks - Block name(s) to extend
 * @param {string} config.namespace - Unique namespace for hook registration
 * @param {Function} config.classGenerator - Class generator (attributes, { blockName }) => string[]
 *
 * @example
 * extendBlockClasses({
 *     blocks: ['kadence/column'],
 *     namespace: 'mytheme/column-bg',
 *     classGenerator: (attributes) => hasBackground(attributes) ? ['has-bg'] : [],
 * });
 */
export function extendBlockClasses(config) {
    const { blocks: blocksConfig, namespace, classGenerator } = config;
    if (!namespace) {
        throw new Error('extendBlockClasses requires a namespace');
    }
    if (!classGenerator) {
        throw new Error('extendBlockClasses requires a classGenerator function');
    }

    const blocks = Array.isArray(blocksConfig) ? blocksConfig : [blocksConfig];

    // Only register class filters
    addFilter(
        'blocks.getSaveContent.extraProps',
        `${namespace}/add-save-classes`,
        createSavePropsFilter(blocks, [], { classGenerator })
    );

    addFilter(
        'editor.BlockListBlock',
        `${namespace}/add-editor-classes`,
        createEditorPropsFilter(blocks, [], { classGenerator })
    );
}

/**
 * Extends blocks with only attribute generation (no controls, no classes).
 *
 * The attributes-only counterpart of extendBlockClasses(), for when a block's wrapper needs
 * data attributes derived from attributes it already has.
 *
 * On a dynamic block the attributes exist only in the editor canvas, since its front end is
 * rendered by PHP. That is true of full extendBlock() too until S7: today only classes are synced
 * to the server side.
 *
 * @param {Object} config - Extension configuration
 * @param {string|string[]} config.blocks - Block name(s) to extend
 * @param {string} config.namespace - Unique namespace for hook registration
 * @param {Function} config.attributeGenerator - (attributes, { blockName }) => Object
 *
 * @example
 * extendBlockAttributes({
 *     blocks: ['core/group'],
 *     namespace: 'mytheme/group-density',
 *     attributeGenerator: (attributes) => ({ 'data-density': attributes.density || undefined }),
 * });
 */
export function extendBlockAttributes(config) {
    const { blocks: blocksConfig, namespace, attributeGenerator } = config;
    if (!namespace) {
        throw new Error('extendBlockAttributes requires a namespace');
    }
    if (!attributeGenerator) {
        throw new Error('extendBlockAttributes requires an attributeGenerator function');
    }

    const blocks = Array.isArray(blocksConfig) ? blocksConfig : [blocksConfig];

    addFilter(
        'blocks.getSaveContent.extraProps',
        `${namespace}/add-save-attributes`,
        createSavePropsFilter(blocks, [], { attributeGenerator })
    );

    addFilter(
        'editor.BlockListBlock',
        `${namespace}/add-editor-attributes`,
        createEditorPropsFilter(blocks, [], { attributeGenerator })
    );
}
