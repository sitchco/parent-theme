/**
 * Block Extensions
 *
 * A declarative API for extending Gutenberg blocks with custom attributes,
 * inspector controls, and class generation.
 *
 * @example
 * const { extendBlock, fields } = sitchco.extendBlock;
 *
 * extendBlock({
 *     blocks: ['core/button'],
 *     namespace: 'mytheme/button',
 *     panel: { title: 'Theme Options', group: 'styles' },
 *     fields: [
 *         fields.select({
 *             name: 'theme',
 *             label: 'Theme',
 *             options: [
 *                 { label: 'Default', value: '' },
 *                 { label: 'Purple', value: 'purple' },
 *             ],
 *             className: (value) => value ? `has-theme-${value}` : null,
 *         }),
 *     ],
 * });
 */

import { extendBlock, extendBlockClasses } from './includes/extend-block.jsx';
import { fields, fieldsToAttributes } from './includes/fields.jsx';
import { classNames, generateFieldClasses, mergeClassNames } from './includes/utils/class-names';
import { useKadenceActiveTab, isKadenceBlock } from './includes/hooks/use-kadence-active-tab';

window.sitchco = window.sitchco || {};

window.sitchco.extendBlock = {
    extendBlock,
    extendBlockClasses,
    fields,
    fieldsToAttributes,
    classNames,
    generateFieldClasses,
    mergeClassNames,
    useKadenceActiveTab,
    isKadenceBlock,
};
