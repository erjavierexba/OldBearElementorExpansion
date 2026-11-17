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
})(jQuery);
