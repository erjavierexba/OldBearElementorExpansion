/* Form integrations in the editor: «Cargar desde…» fetches lists, groups, tags and fields of the service. */
(function () {
	'use strict';
	const cfg = window.obxIntegrations || { services: {} };
	const toast = (message) => { try { window.elementor.notifications.showToast({ message }); } catch (e) { window.console && console.info(message); } };

	/* Control view of the open panel by control name. */
	const controlView = (name) => {
		const page = window.elementor && elementor.getPanelView && elementor.getPanelView().getCurrentPageView();
		if (!page || !page.children) { return null; }
		if (typeof page.getControlViewByName === 'function') { try { const v = page.getControlViewByName(name); if (v) { return v; } } catch (e) { /* older API */ } }
		let found = null;
		page.children.each((child) => { if (!found && child.model && child.model.get('name') === name) { found = child; } });
		return found;
	};
	/* Keeps the new options when the panel is opened again. */
	const remember = (name, key, value) => {
		const cache = window.elementor && elementor.widgetsCache && elementor.widgetsCache.form;
		if (cache && cache.controls && cache.controls[name]) { cache.controls[name][key] = value; }
	};
	const setOptions = (name, options, empty) => {
		const merged = Object.assign(empty ? { '': empty } : {}, options || {});
		remember(name, 'options', merged);
		const view = controlView(name);
		if (view) { view.model.set('options', merged); view.render(); }
	};
	const escapeHtml = (text) => String(text).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
	const setHelp = (name, fields) => {
		const items = Object.entries(fields || {}).map(([id, label]) => '<code>' + escapeHtml(id) + '</code> ' + escapeHtml(label));
		const raw = items.length ? 'Campos disponibles: ' + items.join(', ') : 'Este servicio no tiene campos adicionales.';
		remember(name, 'raw', raw);
		const view = controlView(name);
		if (view) { view.model.set('raw', raw); view.render(); }
	};

	const fetchLists = (view) => {
		const name = view.model.get('name') || '';
		const service = name.replace(/_fetch$/, '');
		const def = cfg.services[service];
		const container = view.options && view.options.container;
		const settings = (container && container.settings) || view.elementSettingsModel;
		if (!def || !settings) { return; }
		const get = (key) => String(settings.get(key) || '');
		const body = { list: get(def.list) };
		if (get(def.source) === 'custom') { Object.entries(def.credentials).forEach(([part, key]) => { body[part] = get(key); }); }
		const button = view.$el && view.$el.find('button');
		if (button) { button.prop('disabled', true); }
		fetch(cfg.rest + service, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce }, body: JSON.stringify(body) })
			.then((response) => response.json().then((data) => (response.ok ? data : Promise.reject(data))))
			.then((data) => {
				setOptions(def.list, data.lists, 'Elige…');
				Object.entries(def.options || {}).forEach(([control, kind]) => setOptions(control, data[kind]));
				setHelp(def.help, data.fields);
				toast(body.list ? 'Listas y campos actualizados.' : 'Listas actualizadas. Elige una y vuelve a cargar para ver sus campos.');
			})
			.catch((error) => toast((error && error.message) || 'No se pudo conectar con el servicio.'))
			.finally(() => { if (button) { button.prop('disabled', false); } });
	};

	const listen = () => { if (window.elementor && elementor.channels && elementor.channels.editor) { elementor.channels.editor.on('obx:integration:fetch', fetchLists); } };
	if (window.elementor && elementor.channels) { listen(); } else { window.addEventListener('elementor/init', listen); }
})();
