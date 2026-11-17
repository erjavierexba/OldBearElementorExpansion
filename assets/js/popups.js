/* Popup runtime: automatic triggers and Elementor action links. */
(function () {
	'use strict';
	const popups = new Map();
	const countKey = (id) => 'obx-popup-' + id + '-count';
	const settings = (el) => { try { return JSON.parse(el.dataset.settings || '{}'); } catch (e) { return {}; } };
	const canShow = (el) => {
		const s = settings(el), max = parseInt(s.timing && s.timing.times_count, 10) || 0;
		return !max || (parseInt(localStorage.getItem(countKey(el.dataset.obxPopup)), 10) || 0) < max;
	};
	const open = (id) => {
		const el = popups.get(String(id)); if (!el || el.classList.contains('is-open') || !canShow(el)) { return; }
		el.classList.add('is-open'); el.setAttribute('aria-hidden', 'false');
		const s = settings(el); if (s.preventScroll) { document.documentElement.classList.add('obx-popup-lock'); }
		const key = countKey(id); localStorage.setItem(key, String((parseInt(localStorage.getItem(key), 10) || 0) + 1));
		const focus = el.querySelector('input:not([type="hidden"]),button,a[href],[tabindex]:not([tabindex="-1"])'); if (focus) { focus.focus(); }
		document.dispatchEvent(new CustomEvent('elementor/popup/show', { detail: { id: parseInt(id, 10), instance: el } }));
	};
	const close = (id) => {
		const el = popups.get(String(id)); if (!el || !el.classList.contains('is-open')) { return; }
		el.classList.remove('is-open'); el.setAttribute('aria-hidden', 'true'); document.documentElement.classList.remove('obx-popup-lock');
		document.dispatchEvent(new CustomEvent('elementor/popup/hide', { detail: { id: parseInt(id, 10), instance: el } }));
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
			if (s.selector) { document.addEventListener('click', (e) => { if (e.target.closest(s.selector)) { e.preventDefault(); open(id); } }); }
			if (t.page_load === 'yes') { setTimeout(() => open(id), Math.max(0, parseFloat(t.page_load_delay) || 0) * 1000); }
			if (t.scrolling === 'yes') { const fn = () => { const max = document.documentElement.scrollHeight - innerHeight; if (max > 0 && scrollY / max * 100 >= (parseFloat(t.scrolling_offset) || 50)) { open(id); removeEventListener('scroll', fn); } }; addEventListener('scroll', fn, { passive: true }); }
			if (t.exit_intent === 'yes') { const fn = (e) => { if (e.clientY <= 0) { open(id); document.removeEventListener('mouseout', fn); } }; document.addEventListener('mouseout', fn); }
			if (t.inactivity === 'yes') { let timer; const reset = () => { clearTimeout(timer); timer = setTimeout(() => open(id), Math.max(1, parseInt(t.inactivity_time, 10) || 30) * 1000); }; ['mousemove','keydown','touchstart'].forEach((n) => addEventListener(n, reset, { passive: true })); reset(); }
		});
		document.addEventListener('click', (e) => { const link = e.target.closest('a[href^="#elementor-action"]'); if (!link) { return; } const a = action(link.getAttribute('href')); if (!a || !a.name.startsWith('popup:')) { return; } e.preventDefault(); const id = a.data.id || a.data.popup; if (a.name === 'popup:close') { const owner = link.closest('[data-obx-popup]'); if (owner) { close(owner.dataset.obxPopup); } } else if (a.data.toggle && popups.get(String(id))?.classList.contains('is-open')) { close(id); } else { open(id); } });
		document.addEventListener('keydown', (e) => { if (e.key !== 'Escape') { return; } document.querySelectorAll('[data-obx-popup].is-open').forEach((el) => { if (!settings(el).preventCloseEsc) { close(el.dataset.obxPopup); } }); });
		window.elementorProFrontend = window.elementorProFrontend || {}; window.elementorProFrontend.modules = window.elementorProFrontend.modules || {}; window.elementorProFrontend.modules.popup = { showPopup: (o) => open(o.id), closePopup: (o) => close(o && o.id) };
	};
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
