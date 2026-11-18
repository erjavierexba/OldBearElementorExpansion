/* Popup runtime: automatic triggers, Elementor action links, animations and accessible focus. */
(function () {
	'use strict';
	const popups = new Map();
	const openStack = [];
	const countKey = (id) => 'obx-popup-' + id + '-count';
	const store = { get: (k) => { try { return localStorage.getItem(k); } catch (e) { return null; } }, set: (k, v) => { try { localStorage.setItem(k, v); } catch (e) { /* private mode */ } } };
	const settings = (el) => { if (!el._obx) { try { el._obx = JSON.parse(el.dataset.settings || '{}'); } catch (e) { el._obx = {}; } } return el._obx; };
	const device = () => (window.elementorFrontend && elementorFrontend.getCurrentDeviceMode ? elementorFrontend.getCurrentDeviceMode() : (innerWidth < 768 ? 'mobile' : (innerWidth < 1025 ? 'tablet' : 'desktop')));
	const animation = (s, key) => {
		const a = s.animation || {}, mode = device();
		if (/mobile/.test(mode) && a[key + '_mobile']) { return a[key + '_mobile']; }
		if (/mobile|tablet/.test(mode) && a[key + '_tablet']) { return a[key + '_tablet']; }
		return a[key] || '';
	};
	const focusable = (el) => Array.from(el.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]):not([type="hidden"]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])')).filter((n) => n.offsetParent !== null);
	const offKey = (id) => 'obx-popup-' + id + '-off';
	const canShow = (el) => {
		if (store.get(offKey(el.dataset.obxPopup))) { return false; }
		const s = settings(el), max = parseInt(s.timing && s.timing.times_count, 10) || 0;
		return !max || (parseInt(store.get(countKey(el.dataset.obxPopup)), 10) || 0) < max;
	};
	const open = (id, automatic) => {
		const el = popups.get(String(id)); if (!el || el.classList.contains('is-open') || !canShow(el)) { return; }
		const s = settings(el);
		if (automatic && s.avoidMultiple && openStack.length) { return; }
		const dialog = el.querySelector('.obx-popup__dialog');
		el._return = document.activeElement;
		el.classList.add('is-open'); el.setAttribute('aria-hidden', 'false'); openStack.push(String(id));
		if (s.preventScroll) { document.documentElement.classList.add('obx-popup-lock'); }
		const enter = animation(s, 'entrance_animation');
		if (dialog) { dialog.className = 'obx-popup__dialog' + (enter ? ' animated ' + enter : ''); }
		const key = countKey(id); store.set(key, String((parseInt(store.get(key), 10) || 0) + 1));
		const closeBtn = el.querySelector('.obx-popup__close');
		if (closeBtn && s.closeDelay > 0) { closeBtn.hidden = true; el._delay = setTimeout(() => { closeBtn.hidden = false; }, s.closeDelay * 1000); }
		if (s.closeAuto > 0) { el._auto = setTimeout(() => close(id), s.closeAuto * 1000); }
		if (s.a11y !== false) { const first = focusable(el)[0]; (first || dialog || el).focus({ preventScroll: true }); }
		document.dispatchEvent(new CustomEvent('elementor/popup/show', { detail: { id: parseInt(id, 10), instance: el } }));
	};
	const close = (id) => {
		const el = popups.get(String(id)); if (!el || !el.classList.contains('is-open') || el._closing) { return; }
		const s = settings(el), dialog = el.querySelector('.obx-popup__dialog'), leave = animation(s, 'exit_animation');
		clearTimeout(el._delay); clearTimeout(el._auto);
		const done = () => {
			el._closing = false; el.classList.remove('is-open'); el.setAttribute('aria-hidden', 'true');
			if (dialog) { dialog.className = 'obx-popup__dialog'; }
			const i = openStack.indexOf(String(id)); if (i !== -1) { openStack.splice(i, 1); }
			if (!document.querySelector('[data-obx-popup].is-open')) { document.documentElement.classList.remove('obx-popup-lock'); }
			if (s.a11y !== false && el._return && el._return.focus) { el._return.focus({ preventScroll: true }); }
			document.dispatchEvent(new CustomEvent('elementor/popup/hide', { detail: { id: parseInt(id, 10), instance: el } }));
		};
		if (dialog && leave) {
			el._closing = true;
			dialog.className = 'obx-popup__dialog animated reverse ' + leave;
			let finished = false; const end = () => { if (!finished) { finished = true; done(); } };
			dialog.addEventListener('animationend', end, { once: true }); setTimeout(end, 2000);
		} else { done(); }
	};
	const action = (href) => {
		if (!href || !href.startsWith('#elementor-action')) { return null; }
		try {
			const raw = decodeURIComponent(href.slice(1)).replace(/^elementor-action:?/, ''); const p = new URLSearchParams(raw);
			const name = p.get('action') || ''; const data = JSON.parse(decodeURIComponent(p.get('settings') || '{}'));
			return { name, data };
		} catch (e) { return null; }
	};
	const boot = () => {
		document.querySelectorAll('[data-obx-popup]').forEach((el) => {
			const id = String(el.dataset.obxPopup), s = settings(el), t = s.triggers || {}; popups.set(id, el);
			el.addEventListener('click', (e) => { if (e.target.closest('[data-popup-close]') && !(s.preventCloseOverlay && e.target.classList.contains('obx-popup__overlay'))) { close(id); } });
			el.addEventListener('keydown', (e) => {
				if (e.key !== 'Tab' || s.a11y === false) { return; }
				const items = focusable(el); if (!items.length) { e.preventDefault(); return; }
				const first = items[0], last = items[items.length - 1];
				if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); } else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
			});
			if (s.selector) { document.addEventListener('click', (e) => { let hit = null; try { hit = e.target.closest(s.selector); } catch (err) { return; } if (hit) { e.preventDefault(); open(id); } }); }
			if (t.page_load === 'yes') { setTimeout(() => open(id, true), Math.max(0, parseFloat(t.page_load_delay) || 0) * 1000); }
			if (t.scrolling === 'yes') { const fn = () => { const max = document.documentElement.scrollHeight - innerHeight; if (max > 0 && scrollY / max * 100 >= (parseFloat(t.scrolling_offset) || 50)) { open(id, true); removeEventListener('scroll', fn); } }; addEventListener('scroll', fn, { passive: true }); }
			if (t.exit_intent === 'yes') { const fn = (e) => { if (e.clientY <= 0) { open(id, true); document.removeEventListener('mouseout', fn); } }; document.addEventListener('mouseout', fn); }
			if (t.inactivity === 'yes') { let timer; const reset = () => { clearTimeout(timer); timer = setTimeout(() => open(id, true), Math.max(1, parseInt(t.inactivity_time, 10) || 30) * 1000); }; ['mousemove','keydown','touchstart'].forEach((n) => addEventListener(n, reset, { passive: true })); reset(); }
		});
		document.addEventListener('click', (e) => { const link = e.target.closest('a[href^="#elementor-action"]'); if (!link) { return; } const a = action(link.getAttribute('href')); if (!a || !a.name.startsWith('popup:')) { return; } e.preventDefault(); const id = a.data.id || a.data.popup; if (a.name === 'popup:close') { const owner = link.closest('[data-obx-popup]'); if (owner) { close(owner.dataset.obxPopup); } } else if (a.data.toggle && popups.get(String(id))?.classList.contains('is-open')) { close(id); } else { open(id); } });
		document.addEventListener('keydown', (e) => { if (e.key !== 'Escape') { return; } const id = openStack[openStack.length - 1]; const el = id && popups.get(id); if (el && !settings(el).preventCloseEsc) { close(id); } });
		window.elementorProFrontend = window.elementorProFrontend || {}; window.elementorProFrontend.modules = window.elementorProFrontend.modules || {}; window.elementorProFrontend.modules.popup = { showPopup: (o) => open(o.id), closePopup: (o) => { if (o && o.id && o.doNotShowAgain) { store.set(offKey(o.id), '1'); } close(o && o.id); } };
	};
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
