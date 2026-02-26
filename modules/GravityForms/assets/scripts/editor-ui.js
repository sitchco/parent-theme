import extendForm from './editor-ui/form.jsx';

if (window.sitchco?.extendBlock) {
    [extendForm].forEach((m) => m(sitchco.extendBlock));
}
