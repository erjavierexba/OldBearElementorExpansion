/*
 * Global widgets in the editor, with Elementor Pro's data format:
 * - «Guardar como global» (context menu) saves the widget as a `widget` library template and links it;
 * - the «Globales» tab of the panel lists the templates to drag in;
 * - a linked widget is the original widget plus `templateID`: it opens locked (Editar / Desvincular),
 *   its changes reach every copy on the page, and publishing updates the template for the whole site.
 */
(function ($) {
	'use strict';

	const config = window.obxGlobalWidgets || {};
	/** template id => { title, widgetType, settings } */
	const templates = Object.assign({}, config.templates || {});
	/** template id => id of the last changed container using it (sent on publish) */
	let changed = {};
	/** containers whose locked panel was dismissed with «Editar» */
	const unlocked = new Set();
	let panelWidgets = null;

	const clone = (value) => elementorCommon.helpers.cloneObject(value || {});
	const templateOf = (container) => container && container.model && container.model.get('templateID') ? String(container.model.get('templateID')) : '';
	const containersOf = (args) => (args.containers || [args.container]).filter(Boolean);

	const panelModel = (id, template) => new Backbone.Model({
		id,
		elType: 'widget',
		widgetType: template.widgetType,
		title: template.title,
		icon: (elementor.widgetsCache[template.widgetType] || {}).icon || 'eicon-global-settings',
		categories: [],
		keywords: [],
		custom: { templateID: id },
	});

	const addPanelWidget = (id) => {
		if (!panelWidgets || !templates[id] || !elementor.widgetsCache[templates[id].widgetType]) { return; }
		panelWidgets.add(panelModel(id, templates[id]), { merge: true });
	};

	const forEachLinked = (id, callback) => {
		const preview = elementor.getPreviewContainer && elementor.getPreviewContainer();
		if (preview) { preview.forEachChildrenRecursive((container) => { if (templateOf(container) === id) { callback(container); } }); }
	};

	/** Copies the changed widget's settings to the other copies of the same template on the page. */
	const syncFrom = (source) => {
		const id = templateOf(source);
		if (!id || !source.settings) { return; }
		templates[id] = Object.assign({}, templates[id], { widgetType: source.model.get('widgetType'), settings: source.settings.toJSON({ remove: 'default' }) });
		forEachLinked(id, (container) => {
			if (container === source || container.id === source.id) { return; }
			['settings', 'dynamic', 'globals'].forEach((name) => {
				const from = source[name], to = container[name];
				if (from instanceof Backbone.Model && to instanceof Backbone.Model) {
					Object.entries(from.attributes).forEach(([key, value]) => to.set(key, value));
				}
			});
			container.render();
		});
	};

	const onChange = (args) => {
		containersOf(args).forEach((container) => {
			// Repeater items and inner commands point at the widget through their parent.
			let target = container;
			while (target && !templateOf(target) && target.parent && target.type !== 'widget') { target = target.parent; }
			if (!templateOf(target)) { return; }
			changed[templateOf(target)] = target.id;
			setTimeout(() => syncFrom(target));
		});
	};

	/** On publish, each changed template is saved with the settings of its last edited copy. */
	const saveTemplates = () => {
		const list = [];
		Object.entries(changed).forEach(([id, containerId]) => {
			const container = elementor.getContainer(containerId);
			if (!container) { return; }
			list.push({
				id,
				source: 'local',
				type: 'widget',
				content: JSON.stringify([{ id, elType: 'widget', widgetType: container.model.get('widgetType'), settings: container.settings.toJSON({ remove: 'default' }), templateID: id }]),
			});
		});
		if (!list.length) { return; }
		const sent = changed;
		changed = {};
		elementorCommon.ajax.addRequest('update_templates', {
			data: { templates: list },
			error: () => { changed = Object.assign({}, sent, changed); },
		});
	};

	/** Drafts and autosaves keep their own copy; publishing updates the templates. */
	const onSave = (args) => {
		if (!['publish', 'private'].includes(args.status)) { return; }
		const doc = args.document || elementor.documents.getCurrent();
		if (!doc || !doc.config || !doc.config.panel || doc.config.panel.has_elements) { saveTemplates(); }
	};

	/** Replaces a widget by a copy linked to the template just saved from it. */
	const link = (container, data) => {
		const id = String(data.template_id);
		const widgetType = container.model.get('widgetType');
		templates[id] = { title: data.title, widgetType, settings: container.settings.toJSON({ remove: 'default' }) };
		addPanelWidget(id);
		const historyId = $e.internal('document/history/start-log', { type: 'add', title: elementor.helpers.getModelLabel(container.model), subTitle: data.title });
		$e.run('document/elements/create', {
			container: container.parent,
			model: { id: elementorCommon.helpers.getUniqueId(), elType: 'widget', widgetType, templateID: id },
			options: { at: container.model.collection.indexOf(container.model) },
		});
		$e.run('document/elements/delete', { container });
		$e.internal('document/history/end-log', { id: historyId });
		$e.route('panel/elements/global');
	};

	/** Turns a linked copy back into a normal widget with the same settings. */
	const unlink = (container) => {
		const historyId = $e.internal('document/history/start-log', { type: 'remove', title: elementor.helpers.getModelLabel(container.model) });
		$e.run('document/elements/create', {
			container: container.parent,
			model: {
				id: elementorCommon.helpers.getUniqueId(),
				elType: 'widget',
				widgetType: container.model.get('widgetType'),
				settings: clone(container.settings.toJSON({ remove: 'default' })),
			},
			options: { at: container.model.collection.indexOf(container.model), edit: true },
		});
		$e.run('document/elements/delete', { container });
		$e.internal('document/history/end-log', { id: historyId });
	};

	const defineClasses = () => {
		const ElementModel = elementor.modules.elements.models.Element;
		const WidgetView = elementor.modules.elements.views.Widget;

		const Model = ElementModel.extend({
			initSettings() {
				// Dropped from the panel: the copy starts with the template's settings.
				const template = templates[String(this.get('templateID'))];
				if (template && $e.commands.is('document/elements/create')) {
					this.set('settings', clone(template.settings));
				}
				ElementModel.prototype.initSettings.apply(this, arguments);
			},
		});

		const View = WidgetView.extend({
			className() {
				return WidgetView.prototype.className.apply(this, arguments) + ' elementor-global-widget elementor-global-' + this.model.get('templateID');
			},
			addInlineEditingAttributes() {},
			getContextMenuGroups() {
				return WidgetView.prototype.getContextMenuGroups.apply(this, arguments).filter((group) => 'save' !== group.name);
			},
			getContainer() {
				if (this.container) { return this.container; }
				const container = WidgetView.prototype.getContainer.apply(this, arguments);
				container.label = container.label + ' (global)';
				return container;
			},
			render() {
				WidgetView.prototype.render.apply(this, arguments);
				setTimeout(() => { const el = this.el && this.el.querySelector('.elementor-inline-editing'); if (el) { el.classList.remove('elementor-inline-editing'); } });
			},
		});

		const LockedPage = Marionette.ItemView.extend({
			id: 'elementor-panel-global-widget',
			template: '#tmpl-elementor-panel-global-widget',
			ui: { edit: '#elementor-global-widget-locked-edit .elementor-button', unlink: '#elementor-global-widget-locked-unlink .elementor-button' },
			events: { 'click @ui.edit': 'onEdit', 'click @ui.unlink': 'onUnlink' },
			onEdit() {
				const view = this.getOption('editedView');
				unlocked.add(view.getContainer().id);
				$e.route('panel/editor/content', { model: view.model, view });
			},
			onUnlink() {
				const view = this.getOption('editedView');
				elementorCommon.dialogsManager.createWidget('confirm', {
					id: 'elementor-global-widget-unlink-dialog',
					headerMessage: 'Desvincular el widget',
					message: 'El widget dejará de ser global y volverá a ser un widget normal.',
					position: { my: 'center center', at: 'center center' },
					strings: { confirm: 'Desvincular', cancel: 'Cancelar' },
					onConfirm: () => unlink(view.getContainer()),
				}).show();
			},
		});

		const pages = elementor.modules.layouts.panel.pages.elements.views;
		const NoTemplates = pages.Global.extend({
			template: '#tmpl-elementor-panel-global-widget-no-templates',
			id: 'elementor-panel-global-widget-no-templates',
			className: 'elementor-nerd-box elementor-panel-nerd-box e-responsive-panel-stretch',
		});
		const TemplatesView = pages.Elements.extend({
			id: 'elementor-global-templates',
			getEmptyView() { return this.collection.length ? null : NoTemplates; },
			onFilterEmpty() {},
		});

		return { Model, View, LockedPage, TemplatesView };
	};

	const init = () => {
		const classes = defineClasses();
		panelWidgets = new Backbone.Collection();
		Object.keys(templates).forEach(addPanelWidget);

		elementor.hooks.addFilter('element/model', (DefaultModel, attrs) => (attrs && attrs.templateID ? classes.Model : DefaultModel));
		elementor.hooks.addFilter('element/view', (DefaultView, model) => (model && model.get('templateID') ? classes.View : DefaultView));
		elementor.hooks.addFilter('panel/elements/regionViews', (regionViews) => {
			// The widgets' config is ready by the time the panel is built.
			Object.keys(templates).forEach(addPanelWidget);
			Object.assign(regionViews.global, { view: classes.TemplatesView, options: { collection: panelWidgets } });
			return regionViews;
		});
		// «Guardar como global» works instead of showing Pro's promotion.
		elementor.hooks.addFilter('elements/widget/contextMenuGroups', (groups, view) => {
			const group = _.findWhere(groups, { name: 'save' });
			const action = group && _.findWhere(group.actions, { name: 'save' });
			// Built while the view is constructed: the model may still be only in its options.
			const model = view.model || (view.options && view.options.model);
			if (action && model && model.get('templateID')) { return groups.filter((item) => item !== group); }
			if (action) {
				action.title = 'Guardar como global';
				action.callback = () => view.save();
				delete action.shortcut;
				delete action.promotionURL;
			}
			return groups;
		});

		const type = {
			showInLibrary: false,
			saveDialog: {
				title: 'Guarda el widget como widget global',
				description: 'Podrás añadir este widget global en varios sitios de la web y editarlo desde un único lugar.',
			},
			prepareSavedData(data) { data.widgetType = data.content[0].widgetType; return data; },
			ajaxParams: {
				success(data) {
					elementor.templates.layout.hideModal();
					const view = elementor.templates.layout.modalContent && elementor.templates.layout.modalContent.currentView;
					const container = view && view.model && elementor.getContainer(view.model.id);
					if (container) { link(container, data); }
				},
			},
		};
		const existing = elementor.templates.getTemplateTypes('widget');
		if (existing) { $.extend(true, existing, type); } else { elementor.templates.registerTemplateType('widget', type); }

		$e.commands.on('run:after', (component, command, args) => {
			if (!args) { return; }
			if (/^document\/(elements\/(settings|set-settings|reset-style|reset-settings)|repeater\/|dynamic\/|globals\/)/.test(command)) { onChange(args); }
			if ('document/save/publish' === command || 'document/save/update' === command) { onSave(Object.assign({ status: 'publish' }, args)); }
		});
		// The save itself is an internal command; whichever is seen first sends the changes once.
		if ($e.commandsInternal) {
			$e.commandsInternal.on('run:after', (component, command, args) => { if ('document/save/save' === command && args) { onSave(args); } });
		}
		// A linked widget opens its locked panel until «Editar» is pressed.
		$e.routes.on('run:after', (component, route, args) => {
			if (!/^panel\/editor\/(?!global$)/.test(route) || !args || !args.view) { return; }
			const container = args.view.getContainer && args.view.getContainer();
			if (!templateOf(container)) { unlocked.clear(); return; }
			if (unlocked.has(container.id)) { return; }
			unlocked.clear();
			$e.route('panel/editor/global', { model: args.model, view: args.view });
		});
		let pageAdded = false;
		elementor.on('preview:loaded', () => {
			if (pageAdded) { return; }
			pageAdded = true;
			elementor.getPanelView().addPage('globalWidget', { view: classes.LockedPage });
			$e.routes.register('panel/editor', 'global', (args) => elementor.getPanelView().setPage('globalWidget', 'Edición global', { editedView: args.view }));
		});
	};

	$(window).on('elementor:init', init);
})(jQuery);
