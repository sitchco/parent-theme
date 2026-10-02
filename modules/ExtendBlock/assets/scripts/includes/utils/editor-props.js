import { generateEditorFieldClasses, generateFieldClasses, mergeClassNames, toClassList } from './class-names';
import { generateFieldAttributes, mergeAttributes } from './attributes';

/**
 * Creates the canvas prop builder — what `editor.BlockListBlock` adds to one block's wrapper.
 *
 * The counterpart of createSavePropsFilter in save-props.js, split out of extend-block.jsx for the
 * same reason: the filter there needs React and @wordpress/data, while what it adds is a pure
 * function of the block's props and the preview device, so it can be pinned by a node spec
 * (tests/js/editor-props.test.js).
 *
 * @param {Object[]} allFields - All field definitions
 * @param {Object} [generators]
 * @param {Function} [generators.classGenerator] - Custom class generator override
 * @param {Function} [generators.attributeGenerator] - Custom attribute generator override
 * @returns {Function} (props, deviceType) => `{ className?, wrapperProps? }`, or null when there is
 *   nothing to add
 */
export function createEditorPropsBuilder(allFields, { classGenerator, attributeGenerator } = {}) {
    const hasResponsiveFields = allFields.some((f) => f.responsive);
    return (props, deviceType = 'Desktop') => {
        // The same context save builds, so a callback cannot make the canvas and the saved
        // markup disagree. The preview device is not part of it: only the responsive class
        // cascade needs it, and that takes it as its own argument.
        const context = { blockName: props.name };
        const newClasses = classGenerator
            ? toClassList(classGenerator(props.attributes, context))
            : hasResponsiveFields
              ? generateEditorFieldClasses(allFields, props.attributes, context, deviceType)
              : generateFieldClasses(allFields, props.attributes, context);
        const newAttributes = attributeGenerator
            ? mergeAttributes(attributeGenerator(props.attributes, context))
            : generateFieldAttributes(allFields, props.attributes, context);
        const hasAttributes = Object.keys(newAttributes).length > 0;
        if (newClasses.length === 0 && !hasAttributes) {
            return null;
        }

        const extraProps = {};
        if (newClasses.length > 0) {
            extraProps.className = mergeClassNames(props.className, newClasses);
        }
        if (hasAttributes) {
            /* Ours first, then whatever is already there — the reverse of save's spread, and
               that is what makes the two agree. On save each filter spreads last, so the last
               registration wins a shared key. Here withFilters wraps each later registration
               OUTSIDE the earlier ones, so `props.wrapperProps` at this layer holds only what
               later registrations produced (core's own BlockListBlock filters register with
               block-editor, before any theme script, so they sit inside us). Letting it win
               is therefore letting the last registration win. */
            extraProps.wrapperProps = {
                ...newAttributes,
                ...props.wrapperProps,
            };
        }
        return extraProps;
    };
}
