import { generateEditorFieldClasses, generateFieldClasses, mergeClassNames, toClassList } from './class-names';
import { generateFieldAttributes, mergeAttributes } from './attributes';
import { generateFieldStyles, mergeStyles } from './styles';

/**
 * Creates the canvas prop builder — what `editor.BlockListBlock` adds to one block's wrapper.
 *
 * The counterpart of createSavePropsFilter in save-props.js, split out of extend-block.jsx for the
 * same reason: the filter there needs React and @wordpress/data, while what it adds is a pure
 * function of the block's props and the preview device, so it can be pinned by a node spec
 * (tests/js/editor-props.test.js).
 *
 * Precedence, and where save and canvas part ways. Between ExtendBlock registrations, the last one
 * wins a shared key in both phases (see the spread below). Against the block itself and core, it
 * differs by phase:
 *
 * - On save, core's block-support filter runs at priority 0 and save-props.js spreads over it, so
 *   the extension wins.
 * - In the canvas, everything core and the block add sits inside this filter and is applied after
 *   it: withBlockListBlockHooks spreads block-support props over our wrapperProps, then the
 *   block's own getEditWrapperProps is merged in, then useBlockProps spreads wrapperProps before
 *   the block's props and finally sets `id`, `role`, `aria-label`, `data-block`, `data-type`,
 *   `data-title` and `inert`. So the block, or core, wins.
 *
 * For any key the block or core also sets, then, the saved markup and the canvas will disagree.
 * That can't be fixed by reserving keys here, because getEditWrapperProps is per block. The rule
 * is instead: don't emit a key your target block sets itself. Within the `data-*` / `aria-*`
 * channel, the contested keys are `aria-label`, `data-block` / `data-type` / `data-title`, and
 * block-specific ones like `data-align` on an image.
 *
 * @param {Object[]} allFields - All field definitions
 * @param {Object} [generators]
 * @param {Function} [generators.classGenerator] - Custom class generator override
 * @param {Function} [generators.attributeGenerator] - Custom attribute generator override
 * @param {Function} [generators.styleGenerator] - Custom style generator override. The style
 *   channel exists only here, in the canvas; see utils/styles.js
 * @returns {Function} (props, deviceType) => `{ className?, wrapperProps? }`, or null when there is
 *   nothing to add
 */
export function createEditorPropsBuilder(allFields, { classGenerator, attributeGenerator, styleGenerator } = {}) {
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
        const newStyle = styleGenerator
            ? mergeStyles(styleGenerator(props.attributes, context))
            : generateFieldStyles(allFields, props.attributes, context);
        const hasAttributes = Object.keys(newAttributes).length > 0;
        const hasStyle = Object.keys(newStyle).length > 0;
        if (newClasses.length === 0 && !hasAttributes && !hasStyle) {
            return null;
        }

        const extraProps = {};
        if (newClasses.length > 0) {
            extraProps.className = mergeClassNames(props.className, newClasses);
        }
        if (hasAttributes || hasStyle) {
            /* Ours first, then whatever is already there — the reverse of save's spread, and
               that is what makes two registrations agree across phases. On save each filter
               spreads last, so the last registration wins a shared key. Here withFilters wraps
               each later registration OUTSIDE the earlier ones, so `props.wrapperProps` at this
               layer holds only what later registrations produced. Letting it win is therefore
               letting the last registration win. Core and the block are a different matter:
               they apply their props further in, after this, so they win in the canvas while
               we win on save. See the precedence note at the top of this function. */
            extraProps.wrapperProps = {
                ...newAttributes,
                ...props.wrapperProps,
            };
        }
        if (hasStyle) {
            // The same order one level down, so another registration's properties are kept.
            extraProps.wrapperProps.style = {
                ...newStyle,
                ...props.wrapperProps?.style,
            };
        }
        return extraProps;
    };
}
