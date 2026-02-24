import extendImage from './editor-ui/heading.jsx';
import extendHeading from './editor-ui/image.jsx';
import extendButton from './editor-ui/button.jsx';

if (window.sitchco?.hooks) {
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
}
if (window.sitchco?.extendBlock) {
    [extendButton].forEach((m) => m(sitchco.extendBlock));
}

extendImage();

extendHeading();
