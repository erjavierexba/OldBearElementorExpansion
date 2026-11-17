/**
 * OldBear Expansion: the Theme Builder inside Elementor's app (#/site-editor).
 * Elementor Free provides the shell (layout, site parts menu, cards, dialogs) through
 * window.elementorAppPackages and only registers a promotion route. This script registers the
 * working routes before the app boots and talks to our REST API (obx/v1/site-editor).
 */
(function () {
	'use strict';
	var pk = window.elementorAppPackages;
	var cfg = window.obxSiteEditor;
	if (!pk || !pk.router || !pk.appUi || !pk.siteEditor || !cfg || !window.React) { return; }

	var R = window.React;
	var h = R.createElement;
	var ui = pk.appUi;
	var se = pk.siteEditor;
	var router = pk.router;
	var TYPES_KEY = JSON.stringify({ unique_id: 'app_site_editor_template_types' });
	var ROOT = '/site-editor/templates';

	/* ---------- Template types (the site parts in the sidebar) ---------- */

	/** Our names and help for each site part (Elementor's documentation and video links are kept). */
	var PARTS = {
		header: ['Cabecera', '¿Qué es una plantilla de cabecera?', 'La cabecera se muestra en la parte superior de las páginas. Diséñala con Elementor sin depender de las limitaciones del tema.', 'Puedes crear varias cabeceras y asignar cada una a una zona distinta del sitio con sus condiciones.'],
		footer: ['Pie de página', '¿Qué es una plantilla de pie de página?', 'El pie de página se muestra al final de las páginas. Diséñalo con Elementor sin depender de las limitaciones del tema.', 'Puedes crear varios pies y asignar cada uno a una zona distinta del sitio con sus condiciones.'],
		'single-page': ['Página individual', '¿Qué es una plantilla de página individual?', 'Define el diseño de las páginas del sitio: cabecera de la página, título, contenido y lo que lo rodea.', 'Crea varias plantillas y asígnalas a páginas concretas o a páginas hijas de otra.'],
		'single-post': ['Entrada individual', '¿Qué es una plantilla de entrada individual?', 'Define el diseño de cada entrada del blog o de un tipo de contenido: título, imagen destacada, autor, contenido y entradas relacionadas.', 'Usa etiquetas dinámicas para que la plantilla se rellene con los datos de cada entrada.'],
		archive: ['Archivo', '¿Qué es una plantilla de archivo?', 'Define el diseño de las páginas que listan entradas: blog, categorías, etiquetas, autores y fechas.', 'Puedes crear plantillas distintas para cada categoría o tipo de contenido.'],
		'search-results': ['Resultados de búsqueda', '¿Qué es una plantilla de resultados de búsqueda?', 'Define el diseño de la página que muestra los resultados cuando un visitante busca en el sitio.', 'Añade un buscador y un mensaje para cuando no haya resultados.'],
		product: ['Producto individual', '¿Qué es una plantilla de producto?', 'Define el diseño de la página de cada producto de WooCommerce: galería, precio, botón de compra, descripción y productos relacionados.', 'Crea plantillas distintas para categorías de productos concretas.'],
		'product-archive': ['Archivo de productos', '¿Qué es una plantilla de archivo de productos?', 'Define el diseño de la tienda de WooCommerce y de las páginas que listan productos por categoría o etiqueta.', 'Puedes asignar un archivo distinto a cada categoría de productos.'],
		'error-404': ['Página 404', '¿Qué es una plantilla de página 404?', 'Define la página que ve un visitante cuando llega a una dirección que no existe.', 'Ayuda a quien se ha perdido con un buscador, enlaces útiles o tus últimas entradas.'],
		'loop-item': ['Elemento de bucle', '¿Qué es una plantilla de elemento de bucle?', 'Es la tarjeta que se repite en los widgets Loop Grid y Loop Carousel: diseñas una vez cómo se ve cada entrada, producto o término y el widget la repite con el contenido de cada uno.', 'Usa widgets de entrada y etiquetas dinámicas para que cada tarjeta muestre su propio título, imagen y datos.']
	};
	var LOOP_ITEM = { type: 'loop-item', icon: 'eicon-loop-builder', urls: {}, tooltip_data: { docs: cfg.templates_url, video_url: '' } };

	function thumbnail(part) {
		var file = { 'product-archive': 'products', 'loop-item': 'single-post' }[part] || part;
		return cfg.assets + file + '.svg';
	}

	function withRoutes(types) {
		var list = (Array.isArray(types) ? types : []).map(function (item) {
			return Object.assign({}, item, { type: item.type === 'products' ? 'product-archive' : item.type });
		}).filter(function (item) {
			return cfg.create[item.type] && (cfg.woocommerce || (item.type !== 'product' && item.type !== 'product-archive'));
		});
		if (!list.some(function (item) { return item.type === 'loop-item'; })) { list.push(LOOP_ITEM); }
		return list.map(function (item) {
			var part = PARTS[item.type];
			var tooltip = Object.assign({ docs: cfg.templates_url, video_url: '' }, item.tooltip_data);
			if (part) { tooltip = Object.assign(tooltip, { title: part[1], content: part[2], tip: part[3] }); }
			return Object.assign({}, item, {
				title: part ? part[0] : item.title,
				tooltip_data: tooltip,
				url: ROOT + '/' + item.type,
				urls: Object.assign({}, item.urls, { thumbnail: (item.urls && item.urls.thumbnail) || thumbnail(item.type), create: cfg.create[item.type] })
			});
		});
	}

	function ajax() { return window.elementorCommon && window.elementorCommon.ajax; }

	/** Elementor caches its own list of site parts; ours adds the routes, the create links and the loop item. */
	function prepareTypes() {
		var a = ajax();
		if (!a || !a.cache) { return; }
		var current = a.cache[TYPES_KEY];
		if (current && current.obx) { return; }
		var types = withRoutes(current);
		types.obx = true;
		a.cache[TYPES_KEY] = types;
	}

	function partTitle(part) { return PARTS[part] ? PARTS[part][0] : part; }

	/* ---------- Data ---------- */

	function api(path, options) {
		options = options || {};
		return fetch(cfg.rest + path, {
			method: options.method || 'GET',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': cfg.nonce, 'Content-Type': 'application/json' },
			body: options.body ? JSON.stringify(options.body) : undefined
		}).then(function (response) {
			return response.json().catch(function () { return null; }).then(function (json) {
				if (!response.ok) { throw new Error((json && json.message) || response.statusText); }
				return json;
			});
		});
	}

	var store = { items: null, error: '', pending: null, listeners: [] };
	function emit() { store.listeners.slice().forEach(function (listener) { listener(); }); }
	function refresh() {
		store.pending = api('/templates').then(function (items) {
			store.items = items || [];
			store.error = '';
		}).catch(function (error) {
			store.error = error.message;
			store.items = store.items || [];
		}).then(function () { store.pending = null; emit(); });
		return store.pending;
	}
	function useTemplates() {
		var state = R.useState(0);
		R.useEffect(function () {
			var listener = function () { state[1](function (n) { return n + 1; }); };
			store.listeners.push(listener);
			// Each screen loads fresh data (the editor keeps the app frame between openings).
			if (!store.pending) { refresh(); }
			return function () { store.listeners = store.listeners.filter(function (l) { return l !== listener; }); };
		}, []);
		return store;
	}

	function goTop(url) { (window.top || window).location.href = url; }

	/**
	 * Inside the editor's modal, the template being edited may be the one just changed here: update the
	 * editor's settings so its next save keeps these conditions and title instead of the old ones.
	 */
	function syncEditor(id, changes) {
		try {
			var top = window.top;
			if (!top || top === window || !top.elementor || !top.elementor.documents || !top.elementor.settings) { return; }
			var current = top.elementor.documents.getCurrent();
			if (!current || String(current.id) !== String(id)) { return; }
			var model = top.elementor.settings.page.model;
			if (changes.conditions) {
				var rows = changes.conditions.map(function (c) { return { _id: Math.random().toString(16).slice(2, 9), mode: c.mode, rule: c.rule, object_id: c.ids }; });
				var collection = model.get('obx_conditions');
				if (collection && collection.reset) { collection.reset(rows); } else { model.set('obx_conditions', rows); }
			}
			if (changes.title) { model.set('post_title', changes.title); }
		} catch (e) { /* The editor keeps working; the conditions are already saved. */ }
	}

	function inEditor() { try { return window.top !== window && !!window.top.$e; } catch (e) { return false; } }

	/* ---------- Small pieces ---------- */

	function Loading() {
		return h('div', { className: 'elementor-loading obx-site-editor__loading' },
			h('div', { className: 'elementor-loader-wrapper' },
				h('div', { className: 'elementor-loader' },
					h('div', { className: 'elementor-loader-boxes' },
						h('div', { className: 'elementor-loader-box' }), h('div', { className: 'elementor-loader-box' }),
						h('div', { className: 'elementor-loader-box' }), h('div', { className: 'elementor-loader-box' }))),
				h('div', { className: 'elementor-loading-title' }, 'Cargando')));
	}

	function go(props, to, replace) {
		if (props.navigate) { props.navigate(to, { replace: !!replace }); }
		else if (router.appHistory) { router.appHistory.navigate(to, { replace: !!replace }); }
	}

	function Redirect(props) {
		R.useEffect(function () { go(props, props.to, true); }, []);
		return null;
	}

	function PageHeader(props) {
		return h(ui.Grid, { container: true, className: 'page-header obx-site-editor__header', justify: 'space-between', alignItems: 'start' },
			h(ui.Grid, { item: true, sm: 8 },
				h(ui.Heading, { variant: 'h1' }, props.title),
				props.text ? h(ui.Text, null, props.text) : null),
			props.action ? h(ui.Grid, { item: true, container: true, justify: 'end', sm: 4 }, props.action) : null);
	}

	function Dots(props) {
		var open = R.useState(false);
		return h('div', { className: 'eps-popover__container obx-template__actions' },
			h(ui.Button, { text: 'Más acciones', hideText: true, icon: 'eicon-ellipsis-h', className: 'obx-template__toggle', onClick: function () { open[1](!open[0]); } }),
			open[0] ? h(ui.Popover, { closeFunction: function () { open[1](false); }, arrowPosition: 'end', className: 'obx-template__menu' },
				props.items.filter(Boolean).map(function (item) {
					return h('li', { key: item.text },
						h(ui.Button, { text: item.text, icon: item.icon, className: 'eps-popover__item' + (item.danger ? ' eps-popover__item--danger' : ''), onClick: item.onClick }));
				})) : null);
	}

	/* ---------- Conditions ---------- */

	function ruleOptions(location) {
		var list = cfg.rules[location === 'single' || location === 'archive' || location === 'error-404' ? location : 'header'] || [];
		var groups = [];
		var byName = {};
		list.forEach(function (rule) {
			var parts = rule.label.split(' · ');
			var group = parts.length > 1 ? parts[0] : '';
			var label = parts.length > 1 ? parts.slice(1).join(' · ') : rule.label;
			if (!group) { groups.push({ value: rule.value, label: label }); return; }
			if (!byName[group]) { byName[group] = { label: group, children: [] }; groups.push(byName[group]); }
			byName[group].children.push({ value: rule.value, label: label });
		});
		return { options: groups, list: list };
	}

	function ConditionsModal(props) {
		var item = props.item;
		var rules = ruleOptions(item.location);
		var initial = item.conditions.length ? item.conditions : [{ mode: 'include', rule: rules.list.length ? rules.list[0].value : 'general', ids: '' }];
		var rows = R.useState(initial.map(function (row, i) { return Object.assign({ key: 'r' + i }, row); }));
		var saving = R.useState(false);
		var error = R.useState('');
		var counter = R.useRef(initial.length);
		var needsIds = function (rule) { return rules.list.some(function (r) { return r.value === rule && r.ids; }); };
		var change = function (index, field, value) {
			rows[1](rows[0].map(function (row, i) { return i === index ? Object.assign({}, row, (function () { var o = {}; o[field] = value; return o; })()) : row; }));
		};
		var save = function () {
			saving[1](true);
			error[1]('');
			api('/templates/' + item.id, { method: 'POST', body: { conditions: rows[0].map(function (row) { return { mode: row.mode, rule: row.rule, ids: row.ids }; }) } })
				.then(function (saved) { if (saved) { syncEditor(item.id, { conditions: saved.conditions }); } return refresh(); })
				.then(function () { saving[1](false); props.onClose(); })
				.catch(function (e) { saving[1](false); error[1](e.message); });
		};
		return h(ui.Modal, { show: true, hideModal: props.onClose, title: 'Condiciones de visualización', icon: 'eicon-flow', className: 'obx-conditions' },
			h('div', { className: 'obx-conditions__intro' },
				h(ui.Heading, { variant: 'h2', tag: 'h2' }, '¿Dónde quieres mostrar «' + item.title + '»?'),
				h(ui.Text, { variant: 'sm' }, 'Define en qué partes del sitio se usa esta plantilla. Si varias coinciden, gana la condición más específica; las exclusiones siempre ganan.')),
			h('div', { className: 'obx-conditions__rows' },
				rows[0].map(function (row, index) {
					return h('div', { className: 'obx-conditions__row obx-conditions__row--' + row.mode, key: row.key },
						h(ui.Select, { className: 'obx-conditions__mode', value: row.mode, options: [{ value: 'include', label: 'Incluir' }, { value: 'exclude', label: 'Excluir' }], onChange: function (e) { change(index, 'mode', e.target.value); } }),
						h(ui.Select, { className: 'obx-conditions__rule', value: row.rule, options: rules.options, onChange: function (e) { change(index, 'rule', e.target.value); } }),
						h('input', { className: 'obx-conditions__ids', type: 'text', value: row.ids, placeholder: needsIds(row.rule) ? 'IDs (obligatorio): 12, 34' : 'Todos · o IDs: 12, 34', onChange: function (e) { change(index, 'ids', e.target.value); } }),
						h(ui.Button, { text: 'Eliminar condición', hideText: true, icon: 'eicon-close', className: 'obx-conditions__remove', onClick: function () { rows[1](rows[0].filter(function (_, i) { return i !== index; })); } }));
				})),
			h('div', { className: 'obx-conditions__add' },
				h(ui.Button, { text: 'Añadir condición', variant: 'outlined', color: 'secondary', size: 'sm', onClick: function () {
					counter.current += 1;
					rows[1](rows[0].concat([{ key: 'r' + counter.current, mode: 'include', rule: rules.list.length ? rules.list[0].value : 'general', ids: '' }]));
				} })),
			error[0] ? h('p', { className: 'obx-conditions__error' }, error[0]) : null,
			h('div', { className: 'obx-conditions__footer' },
				h(ui.Button, { text: saving[0] ? 'Guardando…' : 'Guardar y cerrar', variant: 'contained', color: 'primary', size: 'lg', onClick: saving[0] ? undefined : save })));
	}

	/* ---------- Template cards ---------- */

	function RenameDialog(props) {
		var title = R.useState(props.item.title);
		var submit = function () {
			api('/templates/' + props.item.id, { method: 'POST', body: { title: title[0] } }).then(function (saved) { if (saved) { syncEditor(props.item.id, { title: saved.title }); } return refresh(); }).then(props.onClose).catch(function (e) { window.alert(e.message); });
		};
		return h(ui.Dialog, { title: 'Cambiar nombre', onClose: props.onClose, onSubmit: function (e) { if (e && e.preventDefault) { e.preventDefault(); } submit(); }, dismissButtonText: 'Cancelar', dismissButtonOnClick: props.onClose, approveButtonText: 'Guardar', approveButtonOnClick: submit, approveButtonColor: 'primary' },
			h('input', { className: 'eps-input eps-input--block obx-rename', type: 'text', value: title[0], autoFocus: true, onChange: function (e) { title[1](e.target.value); } }));
	}

	function DeleteDialog(props) {
		var remove = function () {
			api('/templates/' + props.item.id, { method: 'DELETE' }).then(refresh).then(props.onClose).catch(function (e) { window.alert(e.message); });
		};
		return h(ui.Dialog, { title: '¿Mover «' + props.item.title + '» a la papelera?', text: 'La plantilla dejará de mostrarse en el sitio. Puedes restaurarla desde la papelera de plantillas.', onClose: props.onClose, dismissButtonText: 'Cancelar', dismissButtonOnClick: props.onClose, approveButtonText: 'Mover a la papelera', approveButtonOnClick: remove, approveButtonColor: 'danger' });
	}

	function TemplateCard(props) {
		var item = props.item;
		var dialog = R.useState('');
		var close = function () { dialog[1](''); };
		var status = item.status === 'publish' ? '' : (item.status === 'draft' ? 'Borrador' : item.status === 'private' ? 'Privada' : 'Pendiente');
		return h(R.Fragment, null,
			h(ui.Card, { className: 'e-site-part obx-template' + (item.active ? ' obx-template--active' : '') },
				h(ui.CardHeader, null,
					h(ui.Heading, { tag: 'h1', variant: 'text-sm', className: 'eps-card__headline', title: item.title }, item.title),
					status ? h('span', { className: 'obx-template__status' }, status) : null,
					h(Dots, { items: [
						{ text: 'Editar', icon: 'eicon-edit', onClick: function () { goTop(item.urls.edit); } },
						item.has_conditions ? { text: 'Editar condiciones', icon: 'eicon-flow', onClick: function () { dialog[1]('conditions'); } } : null,
						{ text: 'Vista previa', icon: 'eicon-preview-medium', onClick: function () { window.open(item.urls.preview, '_blank'); } },
						item.urls.export ? { text: 'Exportar', icon: 'eicon-sign-out', onClick: function () { goTop(item.urls.export); } } : null,
						{ text: 'Cambiar nombre', icon: 'eicon-pencil', onClick: function () { dialog[1]('rename'); } },
						{ text: 'Eliminar', icon: 'eicon-trash-o', danger: true, onClick: function () { dialog[1]('delete'); } }
					] })),
				h(ui.CardBody, null,
					h(ui.CardImage, { alt: item.title, src: thumbnail(item.part) },
						h(ui.CardOverlay, { className: 'obx-template__overlay' },
							h(ui.Button, { text: 'Editar plantilla', variant: 'contained', color: 'primary', size: 'sm', icon: 'eicon-edit', onClick: function () { goTop(item.urls.edit); } }),
							item.has_conditions ? h(ui.Button, { text: 'Condiciones', variant: 'outlined', size: 'sm', className: 'obx-template__overlay-secondary', onClick: function () { dialog[1]('conditions'); } }) : null))),
				h(ui.CardFooter, null,
					h('div', { className: 'obx-template__instances' },
						h('i', { className: 'obx-template__indicator' + (item.active ? ' is-active' : '') }),
						h('span', { className: 'obx-template__label' }, 'Instancias:'),
						h('span', { className: 'obx-template__value', title: item.instances }, item.has_conditions ? item.instances : (item.part === 'loop-item' ? 'Se usa en Loop Grid y Loop Carousel' : 'Todas las páginas no encontradas'))),
					h('div', { className: 'obx-template__meta' }, item.modified_human + (item.author ? ' · ' + item.author : '')))),
			dialog[0] === 'conditions' ? h(ConditionsModal, { item: item, onClose: close }) : null,
			dialog[0] === 'rename' ? h(RenameDialog, { item: item, onClose: close }) : null,
			dialog[0] === 'delete' ? h(DeleteDialog, { item: item, onClose: close }) : null);
	}

	function AddCard(props) {
		return h(ui.Card, { className: 'e-site-part obx-template obx-template--add' },
			h('a', { className: 'obx-template__add', href: cfg.create[props.part] },
				h('i', { className: 'eicon-plus', 'aria-hidden': true }),
				h('span', null, 'Añadir ' + partTitle(props.part).toLowerCase())));
	}

	function AddHover(item) {
		return h(ui.CardOverlay, { className: 'obx-site-part__overlay' },
			h('a', { className: 'obx-site-part__add', href: item.urls.create },
				h('i', { className: 'eicon-plus', 'aria-hidden': true }),
				h(ui.Button, { size: 'sm', color: 'primary', variant: 'contained', text: 'Añadir nueva' })));
	}

	function AddNewModal(props) {
		var a = ajax();
		var types = (a && a.cache && a.cache[TYPES_KEY]) || [];
		return h(ui.Modal, { show: true, hideModal: props.onClose, title: 'Añadir nueva plantilla', icon: 'eicon-plus', className: 'obx-add-new' },
			h(ui.Text, { variant: 'sm' }, 'Elige la parte del sitio que quieres diseñar. Se crea un borrador y se abre en el editor.'),
			h('div', { className: 'obx-add-new__grid' }, types.map(function (type) {
				return h('a', { key: type.type, className: 'obx-add-new__item', href: type.urls.create },
					h('img', { src: type.urls.thumbnail, alt: '' }),
					h('span', null, type.title));
			})));
	}

	/* ---------- Pages ---------- */

	function Screen(props) {
		prepareTypes();
		var add = R.useState(false);
		return h(se.Layout, {
			allPartsButton: h(se.AllPartsButton, { url: ROOT }),
			titleRedirectRoute: ROOT,
			headerButtons: [{ id: 'obx-add-new', text: 'Añadir nueva', icon: 'eicon-plus', className: 'eps-app__header-btn obx-header-add', onClick: function () { add[1](true); } }]
		}, h('section', { className: 'e-site-editor obx-site-editor' },
			props.children,
			add[0] ? h(AddNewModal, { onClose: function () { add[1](false); } }) : null));
	}

	function Grid(props) {
		return h(ui.CssGrid, { className: 'e-site-editor__site-parts obx-site-editor__grid', colMinWidth: 220, spacing: 25 }, props.children);
	}

	function AllParts() {
		var data = useTemplates();
		var body;
		if (data.items === null) { body = h(Loading); }
		else if (!data.items.length) {
			body = h(R.Fragment, null,
				h(PageHeader, { title: 'Empieza a personalizar cada parte de tu sitio', text: 'Diseña con Elementor la cabecera, el pie, las entradas, los archivos, la página 404 y más. Pasa el ratón por una parte y pulsa «Añadir nueva» para empezar.' }),
				h('hr', { className: 'eps-separator' }),
				h(se.SiteParts, { hoverElement: AddHover }));
		} else {
			body = h(R.Fragment, null,
				h(PageHeader, { title: 'Todas las partes', text: 'Cada plantilla se muestra en las partes del sitio que indiquen sus condiciones.' }),
				h('hr', { className: 'eps-separator' }),
				h(Grid, null, data.items.map(function (item) { return h(TemplateCard, { item: item, key: item.id }); })));
		}
		return h(Screen, null, data.error ? h(ui.Notice, { color: 'danger', label: 'Error', className: 'obx-site-editor__notice' }, data.error) : null, body);
	}

	function PartTemplates(props) {
		var data = useTemplates();
		var part = props.type;
		var title = partTitle(part);
		var items = (data.items || []).filter(function (item) { return item.part === part; });
		var body = data.items === null ? h(Loading) : h(R.Fragment, null,
			h(PageHeader, { title: title, action: h(ui.AddNewButton, { text: 'Añadir nueva', size: 'sm', onClick: function () { goTop(cfg.create[part]); } }) }),
			h('hr', { className: 'eps-separator' }),
			h(Grid, null, [h(AddCard, { part: part, key: 'add' })].concat(items.map(function (item) { return h(TemplateCard, { item: item, key: item.id }); }))));
		return h(Screen, null, data.error ? h(ui.Notice, { color: 'danger', label: 'Error', className: 'obx-site-editor__notice' }, data.error) : null, body);
	}

	function Conditions(props) {
		var data = useTemplates();
		var item = (data.items || []).filter(function (i) { return String(i.id) === String(props.id); })[0];
		if (data.items === null) { return h(Screen, null, h(Loading)); }
		if (!item) { return h(Redirect, { to: ROOT, navigate: props.navigate }); }
		var close = function () {
			// Opened by the editor after publishing: back to the editor.
			if (inEditor()) { window.top.$e.run('app/close'); return; }
			go(props, ROOT + '/' + item.part);
		};
		return h(Screen, null, h(PageHeader, { title: item.title }), h(ConditionsModal, { item: item, onClose: close }));
	}

	/* ---------- Routes ---------- */

	function toRoot(props) { return h(Redirect, { to: ROOT, navigate: props.navigate }); }

	var addRoute = router.addRoute.bind(router);
	var added = false;
	function ours() {
		if (added) { return; }
		added = true;
		addRoute({ path: '/site-editor', component: toRoot });
		addRoute({ path: ROOT, component: AllParts });
		addRoute({ path: ROOT + '/:type', component: PartTemplates });
		addRoute({ path: '/site-editor/conditions/:id', component: Conditions });
	}
	router.addRoute = function (route) {
		if (route && route.path === '/site-editor/promotion') {
			// Elementor's site editor module has just cached its site parts: complete them.
			prepareTypes();
			ours();
			return addRoute(Object.assign({}, route, { component: toRoot }));
		}
		return addRoute(route);
	};
	// Route order does not matter (the router ranks paths), so ours are in place even if Elementor stops registering the promotion.
	ours();
})();
