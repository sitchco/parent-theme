import { useSelect, useDispatch } from '@wordpress/data';
import { Dashicon, Button, ButtonGroup } from '@wordpress/components';
import { resolveResponsiveOptions } from './utils/options';
import { prefixClassName } from './utils/class-names';

const BREAKPOINTS = [
    {
        key: 'Desktop',
        suffix: '',
        prefix: '',
        icon: 'desktop',
        itemClass: 'kb-desk-tab',
    },
    {
        key: 'Tablet',
        suffix: 'Tablet',
        prefix: 'tablet:',
        icon: 'tablet',
        itemClass: 'kb-tablet-tab',
    },
    {
        key: 'Mobile',
        suffix: 'Mobile',
        prefix: 'mobile:',
        icon: 'smartphone',
        itemClass: 'kb-mobile-tab',
    },
];

/**
 * Wraps a field definition to add responsive (desktop/tablet/mobile) support.
 *
 * Returns an array of 3 field definitions — one per breakpoint. The desktop field
 * renders a device-toggle UI; tablet/mobile fields have render: null (hidden in
 * the inspector, but their attributes and className callbacks are active).
 *
 * @param {Object} fieldDef - A field definition from fields.select(), fields.toggle(), etc.
 * @returns {Object[]} Array of 3 field definitions
 *
 * An `options` function on a wrapped field is called `(deviceType, context)` — the breakpoint
 * string first, for backward compatibility, then the same context every other field gets.
 *
 * @example
 * responsive(fields.select({
 *     name: 'borderRadiusTop',
 *     label: 'Top Border Radius',
 *     options: [...],
 *     className: (value) => value ? `rounded-t-${value}` : null,
 * }))
 */
export function responsive(fieldDef) {
    // The breakpoint cascade reads raw values with `|| ''`, which would mask a function default
    // rather than apply it. Refused outright rather than half-working; see utils/field-value.js.
    if (typeof fieldDef.default === 'function') {
        throw new Error(`responsive() does not support a function default (field "${fieldDef.name}")`);
    }

    const { name, className: originalClassName, render: originalRender, ...rest } = fieldDef;
    return BREAKPOINTS.map(({ key, suffix, prefix }, index) => ({
        ...rest,
        name: `${name}${suffix}`,
        className: prefix ? prefixClassName(originalClassName, prefix) : originalClassName,
        responsive: {
            breakpoint: key,
            baseName: name,
            originalClassName,
            isDesktop: index === 0,
        },
        render:
            index === 0
                ? (props) => <ResponsiveFieldWrapper {...props} originalRender={originalRender} baseName={name} />
                : null,
    }));
}

/**
 * Renders a field with Kadence-style device toggle buttons (Desktop/Tablet/Mobile).
 * Reads and writes breakpoint-specific attributes based on the active preview device.
 */
function ResponsiveFieldWrapper({ field, originalRender, baseName, attributes, setAttributes, context }) {
    const deviceType = useSelect((select) => select('core/editor')?.getDeviceType?.() || 'Desktop', []);

    const { __experimentalSetPreviewDeviceType: setPreviewDeviceType } = useDispatch('core/edit-post');

    const attrName =
        deviceType === 'Tablet' ? `${baseName}Tablet` : deviceType === 'Mobile' ? `${baseName}Mobile` : baseName;

    const currentValue = attributes[attrName];
    const handleChange = (newValue) => setAttributes({ [attrName]: newValue });

    const responsiveContext = {
        ...context,
        deviceType,
    };

    /* Called `(deviceType, context)`, not `(context)`, so the breakpoint stays the first argument
       for the consuming themes that already pass `(breakpoint) => …`. See the note on
       resolveResponsiveOptions for what has to happen before that can be tidied up. */
    const resolvedOptions = resolveResponsiveOptions(field, responsiveContext);

    /* `name` is the breakpoint's attribute, not the Desktop one the field was spread from, so a
       custom render writing `setAttributes({ [field.name]: v })` edits the breakpoint on screen.
       `field.responsive.baseName` still carries the unsuffixed name. */
    const renderField = resolvedOptions
        ? {
              ...field,
              name: attrName,
              label: undefined,
              options: [
                  {
                      label: '',
                      value: '',
                  },
                  ...resolvedOptions,
              ],
          }
        : {
              ...field,
              name: attrName,
              label: undefined,
          };
    return (
        <div className="components-base-control kb-small-responsive-control">
            <div className="kadence-title-bar">
                <span className="kadence-control-title">{field.label}</span>
                <ButtonGroup className="kb-small-responsive-options" aria-label="Device">
                    {BREAKPOINTS.map(({ key, icon, itemClass }) => (
                        <Button
                            key={key}
                            className={`kb-responsive-btn ${itemClass}${key === deviceType ? ' is-active' : ''}`}
                            isSmall
                            aria-pressed={deviceType === key}
                            onClick={() => setPreviewDeviceType(key)}
                        >
                            <Dashicon icon={icon} />
                        </Button>
                    ))}
                </ButtonGroup>
            </div>
            <div className="kb-small-measure-control-inner">
                {originalRender({
                    field: renderField,
                    value: currentValue,
                    onChange: handleChange,
                    attributes,
                    setAttributes,
                    context: responsiveContext,
                })}
            </div>
        </div>
    );
}
