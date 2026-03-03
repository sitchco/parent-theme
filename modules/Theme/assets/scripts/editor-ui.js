import extendHeading from './editor-ui/heading.jsx';
import extendImage from './editor-ui/image.jsx';
import extendButton from './editor-ui/button.jsx';

// Phase 1: configure theme filters
sitchco.editorInit(() => {
    sitchco.hooks.addFilter(
        'theme.color-options',
        function (options) {
            return [
                ...options,
                {
                    label: 'Default',
                    value: '',
                },
                {
                    label: 'White',
                    value: 'white',
                },
            ];
        },
        5,
        'parent-theme'
    );

    sitchco.hooks.addFilter(
        'theme.icon-options',
        function (options) {
            return [
                ...options,
                {
                    label: 'Select Icon',
                    value: '',
                },
            ];
        },
        5,
        'parent-theme'
    );
});

// Phase 2: register components
sitchco.editorReady(() => {
    extendButton(sitchco.extendBlock);
    extendImage();
    extendHeading();
});
