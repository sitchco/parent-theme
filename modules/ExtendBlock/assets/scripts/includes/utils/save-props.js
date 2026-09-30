import { generateFieldClasses, mergeClassNames } from './class-names';
import { generateFieldAttributes, mergeAttributes } from './attributes';

/**
 * Creates the save content props filter — classes and arbitrary attributes, for static blocks.
 *
 * `blocks.getSaveContent.extraProps` merges whatever is returned onto the saved wrapper
 * element, so an attribute needs no special treatment here beyond being spread.
 *
 * This filter decides block validation for every existing extendBlock() caller: whatever it adds
 * becomes part of the serialized markup the editor compares against on load. It lives in a plain
 * .js util, apart from the JSX filters in extend-block.jsx, so that property can be pinned by a
 * node spec (tests/js/save-props.test.js) — every helper it calls is pure.
 *
 * @param {string[]} targetBlocks - Block names to target
 * @param {Object[]} allFields - All field definitions
 * @param {Object} [generators]
 * @param {Function} [generators.classGenerator] - Custom class generator override
 * @param {Function} [generators.attributeGenerator] - Custom attribute generator override
 * @returns {Function} Filter function (props, blockType, attributes) => props
 */
export function createSavePropsFilter(targetBlocks, allFields, { classGenerator, attributeGenerator } = {}) {
    return (props, blockType, attributes) => {
        if (!targetBlocks.includes(blockType.name)) {
            return props;
        }

        const context = { blockName: blockType.name };
        const newClasses = classGenerator
            ? classGenerator(attributes, context)
            : generateFieldClasses(allFields, attributes, context);
        const newAttributes = attributeGenerator
            ? mergeAttributes(attributeGenerator(attributes, context))
            : generateFieldAttributes(allFields, attributes, context);
        const hasAttributes = Object.keys(newAttributes).length > 0;
        // Nothing to add means the props object is handed back untouched, so serialized output
        // is byte-identical to a block that was never extended. Existing content keeps
        // validating only because this stays a strict no-op.
        if (newClasses.length === 0 && !hasAttributes) {
            return props;
        }

        // className is assigned after the attribute spread simply because the two channels write
        // the same object. Keeping them from colliding is mergeAttributes' job, not this one's:
        // it drops `class`, `className` and `style` before they ever get here.
        const nextProps = {
            ...props,
            ...newAttributes,
        };
        if (newClasses.length > 0) {
            nextProps.className = mergeClassNames(props.className, newClasses);
        }
        return nextProps;
    };
}
