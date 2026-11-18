/* Editor: registers our nested widgets with Elementor's nested elements API (slides are containers). */
(function ($) {
    'use strict';
    const types = ['nested-carousel', 'off-canvas'];
    let done = false;
    const register = () => {
        const Base = window.elementor && elementor.modules && elementor.modules.elements && elementor.modules.elements.types.NestedElementBase;
        if (done || typeof Base !== 'function' || !elementor.elementsManager) { return; }
        const component = $e.components.get('nested-elements');
        const View = component && component.exports && component.exports.NestedView;
        done = true;
        types.forEach((type) => {
            class Type extends Base {
                getType() { return type; }
                getView() { return View || super.getView(); }
            }
            if (!elementor.elementsManager.elementTypes[type]) { elementor.elementsManager.registerElementType(new Type()); }
        });
    };
    // Elementor may load the nested base before this script listens: register then on init or as soon as it exists.
    $(window).on('elementor/nested-element-type-loaded', register);
    $(window).on('elementor:init', register);
    register();

    /* «Off-Canvas» dynamic tag: its select lists the Off-Canvas widgets of the open documents (element ID → name). */
    let timer = 0;
    const offCanvasOptions = () => {
        const tag = elementor.dynamicTags && elementor.dynamicTags.getConfig('tags')['off-canvas'];
        const control = tag && tag.controls && tag.controls.off_canvas;
        if (!control) { return; }
        const options = { '': control.options[''] || 'Elige un Off-Canvas' };
        const walk = (container) => {
            (container && container.children || []).forEach((child) => {
                if (child.model && child.model.get('widgetType') === 'off-canvas') { options[child.id] = child.settings.get('off_canvas_name') || 'Off-Canvas'; }
                walk(child);
            });
        };
        Object.values((elementor.documents && elementor.documents.documents) || {}).forEach((doc) => walk(doc.container));
        // Keep IDs saved in the edited document even when the widget is in another template.
        Object.keys(control.options).forEach((id) => { if (!(id in options)) { options[id] = control.options[id]; } });
        control.options = options;
    };
    const refresh = () => { clearTimeout(timer); timer = setTimeout(offCanvasOptions, 150); };
    $(window).on('elementor:init', () => {
        elementor.on('document:loaded', refresh);
        if (window.$e && $e.commands) {
            $e.commands.on('run:after', (component, command) => { if (/^document\/(elements|repeater)\//.test(command)) { refresh(); } });
        }
    });

    /* Site Logo «Change site logo» button: opens Site Settings → Site Identity, as in Pro. */
    $(window).on('elementor:init', () => {
        elementor.channels.editor.on('obx:site-logo:change', () => {
            Promise.resolve($e.run('panel/global/open')).then(() => $e.route('panel/global/settings-site-identity')).catch(() => {});
        });
    });
})(jQuery);
