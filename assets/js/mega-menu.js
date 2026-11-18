/* Menu (mega menu) runtime: layout per breakpoint, dropdowns on hover or click, stretched content, toggle and keyboard. */
(function () {
	'use strict';
	const editing = () => document.body.classList.contains('elementor-editor-active');
	const widgetOf = (nav) => nav.closest('.elementor-widget-mega-menu');
	const settingsOf = (nav) => {
		const widget = widgetOf(nav); if (!widget) { return {}; }
		const cid = widget.dataset.modelCid, data = cid && window.elementorFrontend && elementorFrontend.config && elementorFrontend.config.elements && elementorFrontend.config.elements.data[cid];
		if (data && data.attributes) { return data.toJSON ? data.toJSON() : data.attributes; }
		try { return JSON.parse(widget.dataset.settings || '{}'); } catch (e) { return {}; }
	};
	const items = (nav) => Array.from(nav.querySelectorAll(':scope > .obx-mm__wrapper > .obx-mm__heading > .obx-mm__item'));
	const contentOf = (item) => item.querySelector(':scope > .e-con');
	const buttonOf = (item) => item.querySelector(':scope > .obx-mm__title > .obx-mm__dropdown-icon');
	const toggleOf = (nav) => nav.querySelector(':scope > .obx-mm__toggle');
	const device = () => (window.elementorFrontend && elementorFrontend.getCurrentDeviceMode ? elementorFrontend.getCurrentDeviceMode() : (innerWidth < 768 ? 'mobile' : (innerWidth < 1025 ? 'tablet' : 'desktop')));
	const breakpoints = () => {
		try { return elementorFrontend.breakpoints.getActiveBreakpointsList({ withDesktop: true }); } catch (e) { return ['mobile', 'tablet', 'desktop']; }
	};
	// Below the chosen breakpoint (or always, with the «Dropdown» layout) the items live in the toggle's dropdown.
	const isDropdown = (s) => {
		if (s.item_layout === 'dropdown') { return true; }
		if (!s.breakpoint_selector || s.breakpoint_selector === 'none') { return false; }
		const list = breakpoints(), selected = list.indexOf(s.breakpoint_selector);
		return selected !== -1 && list.indexOf(device()) <= selected;
	};
	const hoverMode = (nav, s) => !editing() && s.open_on !== 'click' && nav.dataset.layout !== 'dropdown' && !matchMedia('(hover: none)').matches;

	// Full-width content and the dropdown box span the stretch container (Site Settings → Layout) or the page.
	const stretch = (nav) => {
		let selector = '';
		try { selector = (elementorFrontend.getKitSettings && elementorFrontend.getKitSettings('stretched_section_container')) || ''; } catch (e) { selector = ''; }
		const container = selector ? document.querySelector(selector) : null;
		const box = container ? container.getBoundingClientRect() : { left: 0, width: document.documentElement.clientWidth };
		const own = nav.getBoundingClientRect();
		nav.style.setProperty('--obx-mm-stretch-left', (box.left - own.left) + 'px');
		nav.style.setProperty('--obx-mm-stretch-width', box.width + 'px');
		nav.style.setProperty('--obx-mm-stretch-right', 'auto');
	};
	const boxHeight = (nav) => {
		const wrapper = nav.querySelector(':scope > .obx-mm__wrapper');
		if (wrapper) { nav.style.setProperty('--obx-mm-box-height', Math.max(120, innerHeight - wrapper.getBoundingClientRect().top) + 'px'); }
	};
	// «Fit to content»: next to its title (left, centred or right), kept inside the page.
	const position = (nav, item, s) => {
		const content = contentOf(item); if (!content) { return; }
		content.style.left = ''; nav.classList.remove('is-above');
		if (nav.dataset.layout === 'dropdown') { return; }
		const own = nav.getBoundingClientRect(), page = document.documentElement.clientWidth;
		if (s.content_width === 'fit_to_content') {
			const title = item.querySelector(':scope > .obx-mm__title').getBoundingClientRect(), width = content.offsetWidth;
			let left;
			if (s.content_horizontal_position === 'left') { left = title.left; } else if (s.content_horizontal_position === 'right') { left = title.right - width; } else { left = title.left + title.width / 2 - width / 2; }
			left = Math.max(0, Math.min(left, page - width));
			content.style.left = (left - own.left) + 'px';
		}
		// Not enough room below but enough above: open upwards.
		const rect = content.getBoundingClientRect();
		if (rect.bottom > innerHeight && rect.height < own.top) { nav.classList.add('is-above'); }
	};

	const close = (item) => {
		const content = contentOf(item), button = buttonOf(item), s = settingsOf(item.closest('.obx-mm'));
		item.classList.remove('is-open');
		if (button) { button.setAttribute('aria-expanded', 'false'); }
		if (content) { content.classList.remove('is-active', 'animated', s.open_animation || 'fadeIn'); }
	};
	const closeAll = (nav, except) => { items(nav).forEach((item) => { if (item !== except && item.classList.contains('is-open')) { close(item); } }); };
	const open = (item) => {
		const nav = item.closest('.obx-mm'), s = settingsOf(nav), content = contentOf(item), button = buttonOf(item);
		if (!content || !button) { return; }
		closeAll(nav, item);
		stretch(nav);
		item.classList.add('is-open'); button.setAttribute('aria-expanded', 'true');
		content.classList.add('is-active');
		if (s.open_animation) { content.classList.add('animated', s.open_animation); }
		position(nav, item, s);
		if (nav.dataset.layout === 'dropdown') { boxHeight(nav); }
	};
	const toggleItem = (item) => { if (item.classList.contains('is-open')) { close(item); } else { open(item); } };
	const setMenu = (nav, expanded) => {
		const toggle = toggleOf(nav); if (!toggle) { return; }
		toggle.setAttribute('aria-expanded', String(expanded));
		if (expanded) { stretch(nav); boxHeight(nav); } else { closeAll(nav); }
	};

	const layout = (nav) => {
		const s = settingsOf(nav), next = isDropdown(s) ? 'dropdown' : 'horizontal';
		if (nav.dataset.layout !== next) { closeAll(nav); setMenu(nav, false); nav.dataset.layout = next; }
		stretch(nav);
		const open = items(nav).find((item) => item.classList.contains('is-open'));
		if (open) { position(nav, open, s); }
	};

	// Items linking to an anchor on this page are «active» while their section is on screen.
	const anchors = (nav) => {
		if (!('IntersectionObserver' in window)) { return; }
		items(nav).forEach((item) => {
			const link = item.querySelector(':scope > .obx-mm__title > a.obx-mm__title-container'), title = item.querySelector(':scope > .obx-mm__title');
			if (!link || !title.classList.contains('is-anchor')) { return; }
			const url = new URL(link.href, location.href);
			if (url.pathname !== location.pathname || !url.hash) { return; }
			let target = null; try { target = document.querySelector(decodeURIComponent(url.hash)); } catch (e) { target = null; }
			if (!target) { return; }
			new IntersectionObserver((entries) => entries.forEach((entry) => { title.classList.toggle('is-current', entry.isIntersecting); }), { rootMargin: '-40% 0px -55% 0px' }).observe(target);
		});
	};
	// Drag to scroll the items when «Horizontal scroll» is on.
	const dragScroll = (nav) => {
		const heading = nav.querySelector(':scope > .obx-mm__wrapper > .obx-mm__heading'); if (!heading) { return; }
		let startX = 0, startLeft = 0, dragging = false, moved = false;
		heading.addEventListener('mousedown', (e) => { if (heading.scrollWidth <= heading.clientWidth) { return; } dragging = true; moved = false; startX = e.pageX; startLeft = heading.scrollLeft; });
		heading.addEventListener('mousemove', (e) => { if (!dragging) { return; } const dx = e.pageX - startX; if (Math.abs(dx) > 3) { moved = true; heading.classList.add('is-dragging'); heading.scrollLeft = startLeft - dx; } });
		const stop = () => { dragging = false; heading.classList.remove('is-dragging'); };
		heading.addEventListener('mouseup', stop); heading.addEventListener('mouseleave', stop);
		heading.addEventListener('click', (e) => { if (moved) { e.preventDefault(); e.stopPropagation(); moved = false; } }, true);
	};

	const init = (nav) => {
		if (nav.dataset.obxReady) { layout(nav); return; }
		nav.dataset.obxReady = '1';
		layout(nav); anchors(nav); dragScroll(nav);
		const toggle = toggleOf(nav);
		if (toggle) { toggle.addEventListener('click', () => setMenu(nav, toggle.getAttribute('aria-expanded') !== 'true')); }
		items(nav).forEach((item) => {
			const button = buttonOf(item), title = item.querySelector(':scope > .obx-mm__title');
			if (button) { button.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); toggleItem(item); }); }
			if (title) {
				title.addEventListener('click', (e) => {
					if (!button || e.target.closest('a.is-link')) { return; }
					const s = settingsOf(nav);
					if (editing() || s.open_on === 'click' || nav.dataset.layout === 'dropdown') { toggleItem(item); }
				});
			}
			item.addEventListener('mouseenter', () => { if (button && hoverMode(nav, settingsOf(nav))) { clearTimeout(item._leave); open(item); } });
			item.addEventListener('mouseleave', () => { if (hoverMode(nav, settingsOf(nav))) { clearTimeout(item._leave); item._leave = setTimeout(() => close(item), 150); } });
			// Keyboard users open the dropdown with its button and leave it with Tab.
			item.addEventListener('focusout', (e) => { if (!editing() && item.classList.contains('is-open') && !item.contains(e.relatedTarget) && nav.dataset.layout !== 'dropdown') { close(item); } });
		});
	};
	const initAll = (root) => {
		if (root.matches && root.matches('.obx-mm')) { init(root); }
		(root.querySelectorAll ? root.querySelectorAll('.obx-mm') : []).forEach(init);
	};

	document.addEventListener('keydown', (e) => {
		if (e.key !== 'Escape') { return; }
		document.querySelectorAll('.obx-mm .obx-mm__item.is-open').forEach((item) => { close(item); const button = buttonOf(item); if (button && item.contains(document.activeElement)) { button.focus(); } });
		document.querySelectorAll('.obx-mm > .obx-mm__toggle[aria-expanded="true"]').forEach((toggle) => { setMenu(toggle.parentElement, false); toggle.focus(); });
	});
	document.addEventListener('click', (e) => {
		if (editing()) { return; }
		document.querySelectorAll('.obx-mm').forEach((nav) => {
			if (nav.contains(e.target)) { return; }
			closeAll(nav);
			if (nav.dataset.layout === 'dropdown') { setMenu(nav, false); }
		});
	});
	let timer = 0;
	window.addEventListener('resize', () => { clearTimeout(timer); timer = setTimeout(() => document.querySelectorAll('.obx-mm').forEach(layout), 150); });

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', () => initAll(document)); } else { initAll(document); }
	window.addEventListener('elementor/frontend/init', () => {
		if (!window.elementorFrontend || !elementorFrontend.hooks) { return; }
		elementorFrontend.hooks.addAction('frontend/element_ready/mega-menu.default', ($scope) => initAll($scope[0] || $scope));
	});
})();
