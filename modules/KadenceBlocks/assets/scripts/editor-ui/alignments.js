import { stretchWide, stretchFullWidth, alignNone } from '@wordpress/icons';

export const ALIGNMENTS = [
    {
        value: undefined,
        label: 'None',
        icon: alignNone,
    },
    {
        value: 'wide',
        label: 'Wide',
        icon: stretchWide,
    },
    {
        value: 'xwide',
        label: 'Extra Wide',
        icon: stretchWide,
    },
    {
        value: 'full',
        label: 'Full',
        icon: stretchFullWidth,
    },
];
