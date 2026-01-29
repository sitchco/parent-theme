import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import { fieldsToAttributes } from './fields';
import { generateFieldClasses, mergeClassNames } from './utils/class-names';
import { useKadenceActiveTab, isKadenceBlock } from './hooks/use-kadence-active-tab';

/**
 * Checks if a block is a dynamic block (rendered server-side).
 * Currently identifies Kadence blocks as dynamic.
 *
 * @param {string} blockName - The block name to check
 * @returns {boolean}
 */
function isDynamicBlock(blockName) {
    return isKadenceBlock(blockName);
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
    return panels.flatMap((p) => p.fields || []);
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

            // Sync generated classes to extendBlockClasses attribute for dynamic blocks
            // Each extension stores its classes under its namespace key
            useEffect(() => {
                if (!isDynamic) {
                    return;
                }
                const newClasses = classGenerator
                    ? classGenerator(attributes)
                    : generateFieldClasses(allFields, attributes);
                const classString = newClasses.join(' ');

                const currentClasses = attributes.extendBlockClasses || {};
                if (currentClasses[namespace] !== classString) {
                    setAttributes({
                        extendBlockClasses: {
                            ...currentClasses,
                            [namespace]: classString,
                        },
                    });
                }
            }, [attributes, isDynamic, setAttributes]);

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
            // Check Kadence tab visibility (only show on general tab)
            if (isKadence && !kadenceTab.isGeneralTab) {
                return <BlockEdit {...props} />;
            }
            // Check custom render condition
            if (shouldRender && !shouldRender(props, setupResult)) {
                return <BlockEdit {...props} />;
            }
            return (
                <>
                    <BlockEdit {...props} />
                    {panels.map((panel, panelIndex) => (
                        <InspectorControls key={panelIndex} group={panel.group || 'settings'}>
                            <PanelBody title={panel.title} initialOpen={panel.initialOpen ?? true}>
                                {panel.fields?.map((field) => {
                                    const value = attributes[field.name];
                                    const onChange = (newValue) => setAttributes({ [field.name]: newValue });
                                    return (
                                        <field.render
                                            key={field.name}
                                            field={field}
                                            value={value}
                                            onChange={onChange}
                                        />
                                    );
                                })}
                            </PanelBody>
                        </InspectorControls>
                    ))}
                </>
            );
        };
    }, 'withExtendedBlockControls');
}

/**
 * Creates the save content class filter.
 *
 * @param {string[]} targetBlocks - Block names to target
 * @param {Object[]} allFields - All field definitions
 * @param {Function} [classGenerator] - Custom class generator override
 * @returns {Function} Filter function
 */
function createSaveClassFilter(targetBlocks, allFields, classGenerator) {
    return (props, blockType, attributes) => {
        if (!isTargetBlock(blockType.name, targetBlocks)) {
            return props;
        }

        const newClasses = classGenerator ? classGenerator(attributes) : generateFieldClasses(allFields, attributes);
        if (newClasses.length === 0) {
            return props;
        }
        return {
            ...props,
            className: mergeClassNames(props.className, newClasses),
        };
    };
}

/**
 * Creates the editor block list class filter.
 *
 * @param {string[]} targetBlocks - Block names to target
 * @param {Object[]} allFields - All field definitions
 * @param {Function} [classGenerator] - Custom class generator override
 * @returns {Function} Higher-order component
 */
function createEditorClassFilter(targetBlocks, allFields, classGenerator) {
    return createHigherOrderComponent((BlockListBlock) => {
        return (props) => {
            if (!isTargetBlock(props.name, targetBlocks)) {
                return <BlockListBlock {...props} />;
            }

            const newClasses = classGenerator
                ? classGenerator(props.attributes)
                : generateFieldClasses(allFields, props.attributes);
            if (newClasses.length === 0) {
                return <BlockListBlock {...props} />;
            }

            const mergedClassName = mergeClassNames(props.className, newClasses);
            return <BlockListBlock {...props} className={mergedClassName} />;
        };
    }, 'withExtendedBlockClasses');
}

/**
 * Extends one or more Gutenberg blocks with custom attributes, inspector controls, and classes.
 *
 * For Kadence blocks (kadence/*), inspector controls automatically only appear on the "General" tab.
 * Set `kadenceTabAware: false` to disable this behavior.
 *
 * @param {Object} config - Extension configuration
 * @param {string|string[]} config.blocks - Block name(s) to extend
 * @param {string} config.namespace - Unique namespace for hook registration
 * @param {Object} [config.panel] - Single panel configuration (shorthand)
 * @param {Object[]} [config.panels] - Multiple panel configurations
 * @param {Object[]} [config.fields] - Fields for single panel (used with config.panel)
 * @param {Function} [config.shouldRender] - Custom condition for rendering controls
 * @param {Function} [config.useSetup] - Custom setup hook for complex logic
 * @param {Function} [config.classGenerator] - Override default class generation
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
    const { blocks: blocksConfig, namespace, shouldRender, useSetup, classGenerator, kadenceTabAware } = config;
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

    // Only register attribute and class filters if we have fields
    if (allFields.length > 0) {
        // 1. Register attributes (include extendBlockClasses for dynamic blocks)
        addFilter(
            'blocks.registerBlockType',
            `${namespace}/add-attributes`,
            createAttributeFilter(blocks, allFields, hasDynamicBlocks)
        );

        // 3. Add classes to saved content (for static blocks)
        addFilter(
            'blocks.getSaveContent.extraProps',
            `${namespace}/add-save-classes`,
            createSaveClassFilter(blocks, allFields, classGenerator)
        );

        // 4. Add classes in editor
        addFilter(
            'editor.BlockListBlock',
            `${namespace}/add-editor-classes`,
            createEditorClassFilter(blocks, allFields, classGenerator)
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
 * @param {Function} config.classGenerator - Class generator (attributes) => string[]
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
        createSaveClassFilter(blocks, [], classGenerator)
    );

    addFilter(
        'editor.BlockListBlock',
        `${namespace}/add-editor-classes`,
        createEditorClassFilter(blocks, [], classGenerator)
    );
}
