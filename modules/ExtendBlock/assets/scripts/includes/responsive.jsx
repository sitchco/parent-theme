import { useSelect, useDispatch } from '@wordpress/data';
import { Dashicon, Button, ButtonGroup } from '@wordpress/components';

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
 * Wraps a className callback to prefix its output with a breakpoint prefix.
 */
function prefixClassName(classNameFn, prefix) {
    if (!classNameFn || !prefix) {
        return classNameFn;
    }
    return (value) => {
        const result = classNameFn(value);
        if (!result) {
            return result;
        }
        if (Array.isArray(result)) {
            return result.map((c) => `${prefix}${c}`);
        }
        return `${prefix}${result}`;
    };
}

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
 * @example
 * responsive(fields.select({
 *     name: 'borderRadiusTop',
 *     label: 'Top Border Radius',
 *     options: [...],
 *     className: (value) => value ? `rounded-t-${value}` : null,
 * }))
 */
export function responsive(fieldDef) {
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
function ResponsiveFieldWrapper({ field, originalRender, baseName, attributes, setAttributes }) {
    const deviceType = useSelect((select) => select('core/editor')?.getDeviceType?.() || 'Desktop', []);

    const { __experimentalSetPreviewDeviceType: setPreviewDeviceType } = useDispatch('core/edit-post');

    const attrName =
        deviceType === 'Tablet' ? `${baseName}Tablet` : deviceType === 'Mobile' ? `${baseName}Mobile` : baseName;

    const currentValue = attributes[attrName];
    const handleChange = (newValue) => setAttributes({ [attrName]: newValue });

    const resolvedOptions = typeof field.options === 'function' ? field.options(deviceType) : field.options;

    const renderField = resolvedOptions
        ? {
              ...field,
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
                })}
            </div>
        </div>
    );
}
