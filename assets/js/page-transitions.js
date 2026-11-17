/* Page transitions: a layer that leaves when the page has loaded and comes back before following an internal link. */
(function () {
	'use strict';
	const root = document.documentElement;
	const reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
	const layer = () => document.querySelector('.obx-page-transition');
	const duration = (el) => parseFloat(getComputedStyle(el).getPropertyValue('--obx-pt-duration')) || 1500;
	const play = (el, name, done) => {
		if (!name || reduce) { done(); return; }
		el.style.animationName = '';
		el.className = el.className.replace(/\bobx-pt--[\w-]+/g, '').trim() + ' obx-pt--' + name;
		let finished = false; const end = () => { if (!finished) { finished = true; done(); } };
		el.addEventListener('animationend', end, { once: true }); setTimeout(end, duration(el) + 200);
	};
	const enter = () => {
		const el = layer(); if (!el) { root.classList.remove('obx-pt-loading'); return; }
		const name = el.dataset.entrance;
		el.classList.add('is-active');
		root.classList.remove('obx-pt-loading');
		play(el, name, () => { el.classList.remove('is-active'); el.className = el.className.replace(/\bobx-pt--[\w-]+/g, '').trim(); });
	};
	const internal = (a, e) => {
		if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return false; }
		if ((a.target && a.target !== '_self') || a.hasAttribute('download') || a.dataset.elementorOpenLightbox === 'yes' || a.closest('[data-e-disable-page-transition], .e-disable-page-transition')) { return false; }
		const href = a.getAttribute('href') || '';
		if (!href || href.charAt(0) === '#' || /^(mailto|tel|javascript):/i.test(href)) { return false; }
		let url; try { url = new URL(a.href, location.href); } catch (err) { return false; }
		if (url.origin !== location.origin || /\/wp-admin\/|\/wp-login\.php|\.(pdf|zip|jpe?g|png|gif|webp|svg|mp4|mp3)$/i.test(url.pathname)) { return false; }
		return !(url.pathname === location.pathname && url.search === location.search && url.hash);
	};
	const leave = (url) => {
		const el = layer(); if (!el || !el.dataset.exit || reduce) { location.href = url; return; }
		el.classList.add('is-active');
		play(el, el.dataset.exit, () => { location.href = url; });
	};
	document.addEventListener('click', (e) => {
		const a = e.target.closest && e.target.closest('a[href]');
		if (!internal(a, e)) { return; }
		const el = layer(); if (!el || !el.dataset.exit) { return; }
		e.preventDefault(); leave(a.href);
	});
	window.addEventListener('pageshow', (e) => { if (e.persisted) { const el = layer(); if (el) { el.classList.remove('is-active'); } root.classList.remove('obx-pt-loading'); } });
	if (document.readyState === 'complete') { enter(); } else { window.addEventListener('load', enter); }

	/** Editor preview: replay with the settings currently in the panel. */
	window.obxPageTransition = {
		preview(s) {
			const el = layer(); if (!el) { return; }
			const p = 'settings_page_transitions_';
			el.dataset.entrance = s[p + 'entrance_animation'] || ''; el.dataset.exit = s[p + 'exit_animation'] || '';
			const d = s[p + 'animation_duration']; if (d && d.size !== undefined && d.size !== '') { el.style.setProperty('--obx-pt-duration', d.size + 'ms'); }
			el.classList.add('is-active');
			const back = () => play(el, el.dataset.entrance, () => el.classList.remove('is-active'));
			if (el.dataset.exit) { play(el, el.dataset.exit, () => setTimeout(back, 400)); } else { setTimeout(back, 600); }
		}
	};
})();
