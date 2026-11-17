/* OldBear Expansion: sticky elements and motion effects (scrolling and mouse). */
(function () {
	'use strict';

	const clamp = (v, min, max) => Math.min(max, Math.max(min, v));
	const size = (value, fallback) => {
		const n = parseFloat(value && typeof value === 'object' ? value.size : value);
		return Number.isFinite(n) ? n : fallback;
	};
	const range = (value, start, end) => {
		const sizes = value && value.sizes ? value.sizes : {};
		return [size(sizes.start, start), size(sizes.end, end)];
	};
	/** 0..1 ratio for a direction, from a 0..1 position inside the range. */
	const shape = (direction, t) => {
		switch (direction) {
			case 'in-out': return 1 - t;
			case 'in-out-in': return Math.abs(2 * t - 1);
			case 'out-in-out': return 1 - Math.abs(2 * t - 1);
			default: return t; // out-in
		}
	};
	const device = () => (window.elementorFrontend && elementorFrontend.getCurrentDeviceMode ? elementorFrontend.getCurrentDeviceMode() : 'desktop');
	const onDevice = (list) => {
		if (!Array.isArray(list) || !list.length) { return true; }
		const mode = device();
		// Additional breakpoints fall back to the closest of the three classic ones.
		const base = /mobile/.test(mode) ? 'mobile' : (/tablet/.test(mode) ? 'tablet' : 'desktop');
		return list.indexOf(mode) !== -1 || list.indexOf(base) !== -1;
	};
	const deviceSetting = (settings, key) => {
		if (window.elementorFrontend && elementorFrontend.getCurrentDeviceSetting) { return elementorFrontend.getCurrentDeviceSetting(settings, key); }
		return settings[key];
	};

	function Sticky(el, settings) {
		this.el = el;
		this.s = settings;
		this.side = settings.sticky === 'bottom' ? 'bottom' : 'top';
		this.active = false;
		this.spacer = null;
		this.onScroll = this.update.bind(this);
		this.onResize = () => { this.deactivate(); this.update(); };
		window.addEventListener('scroll', this.onScroll, { passive: true });
		window.addEventListener('resize', this.onResize);
		el.classList.add('elementor-sticky');
		this.update();
	}
	Sticky.prototype.offset = function () {
		let offset = parseFloat(deviceSetting(this.s, 'sticky_offset')) || 0;
		const bar = document.getElementById('wpadminbar');
		if (this.side === 'top' && bar && getComputedStyle(bar).position === 'fixed') { offset += bar.offsetHeight; }
		return offset;
	};
	Sticky.prototype.update = function () {
		const enabled = onDevice(this.s.sticky_on);
		if (!enabled) { this.deactivate(); this.el.classList.remove('elementor-sticky--effects'); return; }
		const offset = this.offset();
		const effects = parseFloat(deviceSetting(this.s, 'sticky_effects_offset')) || 0;
		this.el.classList.toggle('elementor-sticky--effects', window.scrollY > effects);

		if (this.s.sticky_parent === 'yes') {
			this.el.style.position = 'sticky';
			this.el.style[this.side] = offset + 'px';
			this.el.style.zIndex = '99';
			const rect = this.el.getBoundingClientRect();
			const stuck = this.side === 'top' ? Math.abs(rect.top - offset) < 1 : Math.abs(window.innerHeight - rect.bottom - offset) < 1;
			this.el.classList.toggle('elementor-sticky--active', stuck);
			return;
		}
		const ref = (this.active ? this.spacer : this.el).getBoundingClientRect();
		const should = this.side === 'top' ? ref.top <= offset : ref.bottom >= window.innerHeight - offset;
		if (should && !this.active) { this.activate(offset); }
		else if (!should && this.active) { this.deactivate(); }
		else if (this.active) { this.place(offset); }
	};
	Sticky.prototype.activate = function (offset) {
		const rect = this.el.getBoundingClientRect();
		this.spacer = document.createElement('div');
		this.spacer.className = 'elementor-sticky__spacer';
		this.spacer.setAttribute('aria-hidden', 'true');
		this.spacer.style.height = rect.height + 'px';
		this.el.parentNode.insertBefore(this.spacer, this.el);
		this.active = true;
		this.el.classList.add('elementor-sticky--active');
		this.el.style.position = 'fixed';
		this.el.style.zIndex = '99';
		this.place(offset);
	};
	Sticky.prototype.place = function (offset) {
		const rect = this.spacer.getBoundingClientRect();
		this.el.style.width = rect.width + 'px';
		this.el.style.left = rect.left + 'px';
		this.el.style.marginTop = '0';
		this.el.style[this.side] = offset + 'px';
	};
	Sticky.prototype.deactivate = function () {
		if (!this.active) { return; }
		this.active = false;
		if (this.spacer) { this.spacer.remove(); this.spacer = null; }
		this.el.classList.remove('elementor-sticky--active');
		['position', 'width', 'left', 'top', 'bottom', 'zIndex', 'marginTop'].forEach((p) => { this.el.style[p] = ''; });
	};
	Sticky.prototype.destroy = function () {
		this.deactivate();
		window.removeEventListener('scroll', this.onScroll);
		window.removeEventListener('resize', this.onResize);
		this.el.classList.remove('elementor-sticky', 'elementor-sticky--effects', 'elementor-sticky--active');
		['position', 'top', 'bottom', 'zIndex'].forEach((p) => { this.el.style[p] = ''; });
	};

	function Motion(el, settings) {
		this.el = el;
		this.s = settings;
		this.target = el.querySelector(':scope > .elementor-widget-container') || el;
		this.mouse = { x: 0, y: 0 };
		this.frame = 0;
		this.request = () => { if (!this.frame) { this.frame = requestAnimationFrame(() => { this.frame = 0; this.apply(); }); } };
		this.onMove = (e) => { this.mouse = { x: e.clientX / window.innerWidth - 0.5, y: e.clientY / window.innerHeight - 0.5 }; this.request(); };
		window.addEventListener('scroll', this.request, { passive: true });
		window.addEventListener('resize', this.request);
		if (settings.motion_fx_motion_fx_mouse === 'yes') { window.addEventListener('mousemove', this.onMove, { passive: true }); }
		this.apply();
	}
	Motion.prototype.progress = function () {
		if (this.s.motion_fx_range === 'page') {
			const max = document.documentElement.scrollHeight - window.innerHeight;
			return max > 0 ? clamp(window.scrollY / max * 100, 0, 100) : 0;
		}
		const rect = this.el.getBoundingClientRect();
		return clamp((window.innerHeight - rect.top) / (window.innerHeight + rect.height) * 100, 0, 100);
	};
	Motion.prototype.apply = function () {
		const s = this.s;
		const p = 'motion_fx_';
		const style = this.target.style;
		if (!onDevice(s[p + 'devices'])) { style.transform = ''; style.opacity = ''; style.filter = ''; return; }
		const transforms = [];
		let opacity = '';
		let filter = '';
		if (s[p + 'motion_fx_scrolling'] === 'yes') {
			const progress = this.progress();
			const t = (key, start, end) => { const [a, b] = range(s[p + key], start, end); return b > a ? clamp((progress - a) / (b - a), 0, 1) : 0; };
			const sign = (key) => (s[p + key + '_direction'] === 'positive' ? 1 : -1);
			['translateY', 'translateX'].forEach((axis) => {
				if (s[p + axis + '_effect'] !== 'yes') { return; }
				const move = (t(axis + '_affectedRange', 0, 100) - 0.5) * size(s[p + axis + '_speed'], 4) * 50 * sign(axis);
				transforms.push(axis + '(' + move.toFixed(1) + 'px)');
			});
			if (s[p + 'rotateZ_effect'] === 'yes') {
				const deg = (t('rotateZ_affectedRange', 0, 100) - 0.5) * size(s[p + 'rotateZ_speed'], 1) * 36 * sign('rotateZ');
				transforms.push('rotateZ(' + deg.toFixed(1) + 'deg)');
			}
			if (s[p + 'scale_effect'] === 'yes') {
				const ratio = shape(s[p + 'scale_direction'], t('scale_range', 20, 80));
				const min = clamp(1 - size(s[p + 'scale_speed'], 4) / 10, 0.05, 1);
				transforms.push('scale(' + (min + (1 - min) * ratio).toFixed(3) + ')');
			}
			if (s[p + 'opacity_effect'] === 'yes') {
				const ratio = shape(s[p + 'opacity_direction'], t('opacity_range', 20, 80));
				const min = clamp(1 - size(s[p + 'opacity_level'], 10) / 10, 0, 1);
				opacity = (min + (1 - min) * ratio).toFixed(3);
			}
			if (s[p + 'blur_effect'] === 'yes') {
				const ratio = shape(s[p + 'blur_direction'], t('blur_range', 20, 80));
				filter = 'blur(' + (size(s[p + 'blur_level'], 7) * (1 - ratio)).toFixed(2) + 'px)';
			}
		}
		if (s[p + 'motion_fx_mouse'] === 'yes') {
			const dir = (key) => (s[p + key + '_direction'] === 'negative' ? -1 : 1);
			if (s[p + 'mouseTrack_effect'] === 'yes') {
				const k = size(s[p + 'mouseTrack_speed'], 1) * 20 * dir('mouseTrack');
				transforms.push('translate(' + (this.mouse.x * k).toFixed(1) + 'px,' + (this.mouse.y * k).toFixed(1) + 'px)');
			}
			if (s[p + 'tilt_effect'] === 'yes') {
				const k = size(s[p + 'tilt_speed'], 4) * 5 * dir('tilt');
				transforms.unshift('perspective(800px)', 'rotateX(' + (-this.mouse.y * k).toFixed(2) + 'deg)', 'rotateY(' + (this.mouse.x * k).toFixed(2) + 'deg)');
			}
		}
		style.transform = transforms.join(' ');
		style.opacity = opacity;
		style.filter = filter;
		if (transforms.length) { style.willChange = 'transform'; }
	};
	Motion.prototype.destroy = function () {
		window.removeEventListener('scroll', this.request);
		window.removeEventListener('resize', this.request);
		window.removeEventListener('mousemove', this.onMove);
		if (this.frame) { cancelAnimationFrame(this.frame); }
		['transform', 'opacity', 'filter', 'willChange'].forEach((k) => { this.target.style[k] = ''; });
	};

	/** Settings of an element: the editor model while editing, data-settings on the live site. */
	const readSettings = (el) => {
		const cid = el.getAttribute('data-model-cid');
		if (cid && window.elementorFrontend && elementorFrontend.config && elementorFrontend.config.elements && elementorFrontend.config.elements.data[cid]) {
			const model = elementorFrontend.config.elements.data[cid];
			const all = model.toJSON ? model.toJSON() : model.attributes || {};
			return all;
		}
		try { return JSON.parse(el.getAttribute('data-settings') || '{}') || {}; } catch (e) { return {}; }
	};

	const instances = new WeakMap();
	const init = (el) => {
		const old = instances.get(el);
		if (old) { old.forEach((i) => i.destroy()); }
		const settings = readSettings(el);
		const list = [];
		if (settings.sticky === 'top' || settings.sticky === 'bottom') { list.push(new Sticky(el, settings)); }
		if (settings.motion_fx_motion_fx_scrolling === 'yes' || settings.motion_fx_motion_fx_mouse === 'yes') { list.push(new Motion(el, settings)); }
		instances.set(el, list);
	};

	const boot = () => {
		if (!window.elementorFrontend || !elementorFrontend.hooks) {
			document.querySelectorAll('[data-settings]').forEach(init);
			return;
		}
		elementorFrontend.hooks.addAction('frontend/element_ready/global', ($scope) => init($scope[0]));
		if (elementorFrontend.isEditMode && elementorFrontend.isEditMode() && window.elementor && elementor.channels) {
			elementor.channels.editor.on('change', (view) => {
				const name = view && view.model ? view.model.get('name') : '';
				if (!/^(sticky|motion_fx_)/.test(name || '')) { return; }
				const container = view.container || (view.options && view.options.container);
				const el = container && container.view ? container.view.el : null;
				if (el) { init(el); }
			});
		}
	};
	if (window.elementorFrontend && elementorFrontend.hooks) { boot(); }
	else { window.addEventListener('elementor/frontend/init', boot); }
})();
