/* Off-Canvas runtime: links with the action off_canvas:{open|close|toggle}, animations, Esc/overlay closing and focus. */
(function () {
	'use strict';
	const openStack = [];
	const editing = () => document.body.classList.contains('elementor-editor-active');
	const panel = (id) => document.querySelector('[data-obx-off-canvas="' + (window.CSS && CSS.escape ? CSS.escape(String(id)) : String(id)) + '"]');
	const settings = (el) => {
		const widget = el.closest('[data-settings]');
		try { return JSON.parse((widget && widget.dataset.settings) || '{}'); } catch (e) { return {}; }
	};
	const device = () => (window.elementorFrontend && elementorFrontend.getCurrentDeviceMode ? elementorFrontend.getCurrentDeviceMode() : (innerWidth < 768 ? 'mobile' : (innerWidth < 1025 ? 'tablet' : 'desktop')));
	// Responsive values fall back from mobile to tablet to desktop, as Elementor does.
	const responsive = (s, key) => {
		const mode = device();
		if (/mobile/.test(mode) && s[key + '_mobile']) { return s[key + '_mobile']; }
		if (/mobile|tablet/.test(mode) && s[key + '_tablet']) { return s[key + '_tablet']; }
		return s[key] || '';
	};
	const focusable = (el) => Array.from(el.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]):not([type="hidden"]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])')).filter((n) => n.offsetParent !== null || n.getClientRects().length);
	const isOpen = (el) => el.getAttribute('aria-hidden') === 'false';
	const main = (el) => el.querySelector('.obx-off-canvas__main');
	const lock = () => { document.body.classList.toggle('obx-off-canvas-lock', openStack.some((id) => { const el = panel(id); return el && settings(el).prevent_scroll === 'yes'; })); };

	const open = (el) => {
		if (!el || (isOpen(el) && !el._closing)) { return; }
		clearTimeout(el._timer); el._closing = false;
		el._return = document.activeElement;
		el.removeAttribute('inert'); el.setAttribute('aria-hidden', 'false');
		openStack.push(el.dataset.obxOffCanvas); lock();
		const enter = responsive(settings(el), 'entrance_animation'), box = main(el);
		if (box) { box.className = 'obx-off-canvas__main' + (enter && enter !== 'none' ? ' animated ' + enter : ''); }
		// Widgets inside often transition «all» (Elementor buttons), so visibility turns visible a frame later.
		setTimeout(() => {
			if (!isOpen(el) || el.contains(document.activeElement)) { return; }
			const first = focusable(el.querySelector('.obx-off-canvas__content') || el)[0];
			if (first) { first.focus({ preventScroll: true }); }
		}, 50);
		el.dispatchEvent(new CustomEvent('obx/off-canvas/open', { bubbles: true }));
	};
	const close = (el) => {
		if (!el || !isOpen(el) || el._closing || (editing() && settings(el).editing_mode === 'yes')) { return; }
		const box = main(el), leave = responsive(settings(el), 'exit_animation');
		const done = () => {
			clearTimeout(el._timer); el._closing = false;
			el.setAttribute('aria-hidden', 'true'); el.setAttribute('inert', '');
			if (box) { box.className = 'obx-off-canvas__main'; }
			const i = openStack.lastIndexOf(el.dataset.obxOffCanvas); if (i !== -1) { openStack.splice(i, 1); }
			lock();
			if (el._return && el._return.focus && document.contains(el._return)) { el._return.focus({ preventScroll: true }); }
			el.dispatchEvent(new CustomEvent('obx/off-canvas/close', { bubbles: true }));
		};
		if (box && leave && leave !== 'none') {
			el._closing = true;
			box.className = 'obx-off-canvas__main animated reverse ' + leave;
			box.addEventListener('animationend', done, { once: true });
			const duration = parseFloat(getComputedStyle(box).animationDuration) || 1.5;
			el._timer = setTimeout(done, duration * 1000 + 200);
		} else { done(); }
	};
	const toggle = (el) => { if (el && isOpen(el) && !el._closing) { close(el); } else { open(el); } };

	// Elementor's action hash: #elementor-action:action=NAME&settings=BASE64(JSON).
	const parse = (href) => {
		let raw = String(href || '').replace(/^(#|%23)/, '');
		try { raw = decodeURIComponent(raw); } catch (e) { return null; }
		const match = raw.match(/^elementor-action:action=([^&]+)(?:&settings=(.*))?$/);
		if (!match) { return null; }
		let data = {};
		if (match[2]) { try { data = JSON.parse(atob(match[2])); } catch (e) { try { data = JSON.parse(match[2]); } catch (err) { data = {}; } } }
		return { name: match[1], data };
	};
	const run = (name, data, link) => {
		const mode = name.split(':')[1] || data.displayMode || 'open';
		const el = data.id ? panel(data.id) : (link && link.closest('[data-obx-off-canvas]'));
		if (!el) { return; }
		if (mode === 'close') { close(el); } else if (mode === 'toggle') { toggle(el); } else { open(el); }
	};
	document.addEventListener('click', (e) => {
		const link = e.target.closest && e.target.closest('a[href^="#elementor-action"], a[href^="%23elementor-action"]');
		if (link) {
			const action = parse(link.getAttribute('href'));
			if (action && action.name.startsWith('off_canvas:')) { e.preventDefault(); run(action.name, action.data, link); }
			return;
		}
		const overlay = e.target.closest && e.target.closest('.obx-off-canvas__overlay');
		if (overlay) {
			const el = overlay.closest('[data-obx-off-canvas]');
			if (el && settings(el).is_not_close_on_overlay !== 'yes') { close(el); }
		}
	});
	document.addEventListener('keydown', (e) => {
		if (e.key !== 'Escape' || !openStack.length) { return; }
		const el = panel(openStack[openStack.length - 1]);
		if (el && settings(el).is_not_close_on_esc_overlay !== 'yes') { close(el); }
	});
	// Keep the keyboard inside the open panel.
	document.addEventListener('keydown', (e) => {
		if (e.key !== 'Tab' || !openStack.length || editing()) { return; }
		const el = panel(openStack[openStack.length - 1]); if (!el) { return; }
		const items = focusable(el); if (!items.length) { return; }
		const first = items[0], last = items[items.length - 1];
		if (!el.contains(document.activeElement)) { e.preventDefault(); first.focus(); } else if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); } else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
	});
	window.obxOffCanvas = { open: (id) => open(panel(id)), close: (id) => close(panel(id)), toggle: (id) => toggle(panel(id)), parse };
})();
