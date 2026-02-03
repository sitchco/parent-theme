import './editor-ui/column-gap-defaults.js';
import './editor-ui/measure-css-variables.js';
import './editor-ui/overrides.js';

import extendKadenceColumnBackground from './editor-ui/kadence-column-background.jsx';
import extendKadenceRowSubgrid from './editor-ui/kadence-row-subgrid.jsx';
import extendKadenceAccordionIcons from './editor-ui/kadence-accordion-icons.jsx';

if (window.sitchco?.extendBlock) {
    [extendKadenceColumnBackground, extendKadenceRowSubgrid, extendKadenceAccordionIcons].forEach((m) =>
        m(sitchco.extendBlock)
    );
}
