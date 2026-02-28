import extendForm from './editor-ui/form.jsx';

sitchco.editorReady(() => {
    [extendForm].forEach((m) => m(sitchco.extendBlock));
});
