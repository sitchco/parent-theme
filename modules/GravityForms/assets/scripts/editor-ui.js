if (window.sitchco?.hooks) {
    sitchco.hooks.addFilter('extendBlock.button', function (config) {
        config.blocks = [...config.blocks, 'gravityforms/form'];
        return config;
    });
}
