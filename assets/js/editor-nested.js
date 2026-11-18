/* Editor: registers our nested widgets with Elementor's nested elements API (slides are containers). */
(function ($) {
    'use strict';
    const types = ['nested-carousel'];
    let done = false;
    const register = () => {
        const Base = window.elementor && elementor.modules.elements.types.NestedElementBase;
        if (done || typeof Base !== 'function') { return; }
        const component = $e.components.get('nested-elements');
        const View = component && component.exports && component.exports.NestedView;
        done = true;
        types.forEach((type) => {
            class Type extends Base {
                getType() { return type; }
                getView() { return View || super.getView(); }
            }
            elementor.elementsManager.registerElementType(new Type());
        });
    };
    $(window).on('elementor/nested-element-type-loaded', register);

    /* Site Logo «Change site logo» button: opens Site Settings → Site Identity, as in Pro. */
    $(window).on('elementor:init', () => {
        elementor.channels.editor.on('obx:site-logo:change', () => {
            Promise.resolve($e.run('panel/global/open')).then(() => $e.route('panel/global/settings-site-identity')).catch(() => {});
        });
    });
})(jQuery);
