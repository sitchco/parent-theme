// These stay synchronous (Kadence's own WP hooks, no sitchco dependency)
import './editor-ui/column-gap-defaults.js';
import './editor-ui/measure-css-variables.js';
import './editor-ui/overrides.js';
import './editor-ui/row-alignment-toolbar.jsx';
import './editor-ui/content-width-preset-control.jsx';
import extendKadenceColumnBackground from './editor-ui/kadence-column-background.jsx';
import extendKadenceColumnAlignment from './editor-ui/column-alignment-toolbar.jsx';
import extendKadenceRowSubgrid from './editor-ui/kadence-row-subgrid.jsx';

sitchco.editorReady(() => {
    [extendKadenceColumnBackground, extendKadenceColumnAlignment, extendKadenceRowSubgrid].forEach((m) =>
        m(sitchco.extendBlock)
    );
});
