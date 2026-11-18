/*
 * Lottie widget runtime: loads the JSON with the bundled lottie-web player and plays it on arriving
 * to the viewport, on click (optionally opening the link after a delay), on hover, bound to the
 * scroll or right away; with loop count, speed, start/end points, reverse and lazy loading.
 */
(function () {
	'use strict';
	const config = window.obxLottie || {};
	const num = (value, fallback) => { const n = parseFloat(value); return Number.isFinite(n) ? n : fallback; };
	const size = (value, fallback) => num(value && typeof value === 'object' ? value.size : value, fallback);
	const clamp = (n, min, max) => Math.min(max, Math.max(min, n));

	const pathOf = (s) => {
		const url = 'external_url' === s.source ? (s.source_external_url || {}).url : (s.source_json || {}).url;
		return url || config.defaultAnimationUrl || '';
	};

	/** Zone of the viewport (Bottom → Top, in %) where the animation is active. */
	const zone = (s) => {
		const sizes = (s.viewport || {}).sizes || {};
		const start = clamp(num(sizes.start, 0), 0, 100), end = clamp(num(sizes.end, 100), 0, 100);
		return { start: Math.min(start, end), end: Math.max(start, end) };
	};

	const hoverArea = (widget, s) => {
		const selector = { column: '.elementor-column', section: '.elementor-section', container: '.e-con' }[s.hover_area];
		return (selector && widget.closest(selector)) || widget.querySelector('.e-lottie__container') || widget;
	};

	function Player(widget, s) {
		this.widget = widget;
		this.s = s;
		this.cleanup = [];
		this.target = widget.querySelector('.e-lottie__animation');
		if (!this.target || !window.lottie) { return; }
		if ('yes' === s.lazyload && 'IntersectionObserver' in window) {
			const observer = new IntersectionObserver((entries) => {
				if (entries.some((e) => e.isIntersecting)) { observer.disconnect(); this.load(); }
			}, { rootMargin: '200px 0px' });
			observer.observe(widget);
			this.cleanup.push(() => observer.disconnect());
		} else {
			this.load();
		}
	}

	Player.prototype.on = function (el, type, fn, options) {
		el.addEventListener(type, fn, options);
		this.cleanup.push(() => el.removeEventListener(type, fn, options));
	};

	Player.prototype.load = function () {
		const path = pathOf(this.s);
		if (!path) { return; }
		// A canvas has no intrinsic proportions (an SVG has its viewBox) and resizing it after the first
		// frame loses lottie's cached drawing styles: the file is read first to size the box.
		if ('canvas' === this.s.renderer && window.fetch) {
			fetch(path, { credentials: 'same-origin' })
				.then((response) => (response.ok ? response.json() : Promise.reject(new Error(response.status))))
				.then((data) => { if (!this.destroyed) { this.start(data, path); } })
				.catch(() => {});
			return;
		}
		this.start(null, path);
	};

	Player.prototype.start = function (data, path) {
		const s = this.s, scroll = 'bind_to_scroll' === s.trigger;
		const times = Math.round(num(s.number_of_times, 0));
		// An empty or zero count loops forever; a count is played that many times.
		this.loop = !scroll && 'yes' === s.loop ? (times > 0 ? times : true) : false;
		this.reverse = !scroll && 'on_hover' !== s.trigger && 'yes' === s.reverse_animation;
		this.target.innerHTML = '';
		const params = {
			container: this.target,
			renderer: 'canvas' === s.renderer ? 'canvas' : 'svg',
			loop: true === this.loop,
			autoplay: false,
			rendererSettings: { preserveAspectRatio: 'xMidYMid meet', progressiveLoad: true },
		};
		if (data) {
			if (data.w > 0 && data.h > 0) { this.target.style.aspectRatio = data.w + ' / ' + data.h; }
			// Image files are relative to the JSON's folder, as when lottie loads the path itself.
			(data.assets || []).forEach((asset) => {
				if (asset.p && asset.e !== 1 && !/^(data:|https?:|\/)/.test(asset.u || '')) {
					try { asset.u = new URL(asset.u || '', new URL(path, document.baseURI)).href; } catch (e) { /* keep it */ }
				}
			});
			params.animationData = data;
		} else {
			params.path = path;
		}
		this.anim = window.lottie.loadAnimation(params);
		this.anim.addEventListener('DOMLoaded', () => this.ready());
	};

	Player.prototype.ready = function () {
		const s = this.s, anim = this.anim;
		const total = anim.totalFrames;
		const start = clamp(size(s.start_point, 0), 0, 100), end = clamp(size(s.end_point, 100), 0, 100);
		this.first = Math.round(total * Math.min(start, end) / 100);
		this.last = Math.max(this.first + 1, Math.round(total * Math.max(start, end) / 100));
		if (this.first > 0 || this.last < total) { anim.setSegment(this.first, this.last); }
		this.length = this.last - this.first;
		if ('bind_to_scroll' !== s.trigger) { anim.setSpeed(clamp(size(s.play_speed, 1), 0.1, 5)); }
		this.played = 0;
		anim.addEventListener('complete', () => {
			this.played += 1;
			if (typeof this.loop === 'number' && this.played < this.loop) { this.restart(); }
		});
		this.rest();
		const trigger = s.trigger || 'arriving_to_viewport';
		({ arriving_to_viewport: () => this.viewport(), on_click: () => this.click(), on_hover: () => this.hover(), bind_to_scroll: () => this.scroll() }[trigger] || (() => this.restart()))();
	};

	/** First frame of the playing direction (the end when reversed). */
	Player.prototype.rest = function () {
		this.anim.setDirection(this.reverse ? -1 : 1);
		this.anim.goToAndStop(this.reverse ? this.length - 1 : 0, true);
	};

	Player.prototype.restart = function () {
		this.rest();
		this.anim.play();
	};

	Player.prototype.play = function () {
		if (this.anim.isPaused && this.finished()) { this.played = 0; this.restart(); return; }
		this.anim.setDirection(this.reverse ? -1 : 1);
		this.anim.play();
	};

	Player.prototype.finished = function () {
		const frame = this.anim.currentFrame;
		return this.reverse ? frame <= 0 : frame >= this.length - 1;
	};

	Player.prototype.viewport = function () {
		if (!('IntersectionObserver' in window)) { this.play(); return; }
		const z = zone(this.s);
		const observer = new IntersectionObserver((entries) => {
			entries.forEach((entry) => {
				if (entry.isIntersecting) { this.play(); } else { this.anim.pause(); }
			});
		}, { rootMargin: `-${100 - z.end}% 0px -${z.start}% 0px` });
		observer.observe(this.widget);
		this.cleanup.push(() => observer.disconnect());
	};

	Player.prototype.click = function () {
		const s = this.s;
		const link = this.widget.querySelector('.e-lottie__container__link');
		const delay = Math.round(num(s.link_timeout, 0));
		this.on(this.widget, 'click', (event) => {
			if (link && delay > 0 && link.contains(event.target)) {
				event.preventDefault();
				const href = link.getAttribute('href'), target = link.getAttribute('target');
				clearTimeout(this.timer);
				this.timer = setTimeout(() => { if ('_blank' === target) { window.open(href, '_blank', 'noopener'); } else { window.location.href = href; } }, delay);
			}
			this.played = 0;
			this.restart();
		});
		this.cleanup.push(() => clearTimeout(this.timer));
	};

	Player.prototype.hover = function () {
		const s = this.s, area = hoverArea(this.widget, s);
		this.on(area, 'mouseenter', () => { this.anim.setDirection(1); this.anim.play(); });
		this.on(area, 'mouseleave', () => {
			if ('reverse' === s.on_hover_out) { this.anim.setDirection(-1); this.anim.play(); return; }
			if ('pause' === s.on_hover_out) { this.anim.pause(); return; }
			this.rest();
		});
	};

	/** The frame follows the scroll: through the viewport zone, or through the whole page. */
	Player.prototype.scroll = function () {
		const s = this.s, z = zone(s);
		let pending = false;
		const update = () => {
			pending = false;
			const vh = window.innerHeight || document.documentElement.clientHeight;
			let progress;
			if ('page' === s.effects_relative_to) {
				const max = Math.max(1, document.documentElement.scrollHeight - vh);
				const page = (window.scrollY / max) * 100;
				progress = z.end > z.start ? (page - z.start) / (z.end - z.start) : 0;
			} else {
				const rect = this.widget.getBoundingClientRect();
				const bottom = vh * (1 - z.start / 100), top = vh * (1 - z.end / 100);
				progress = (bottom - rect.top) / Math.max(1, bottom - top + rect.height);
			}
			this.anim.goToAndStop(clamp(progress, 0, 1) * (this.length - 1), true);
		};
		const request = () => { if (!pending) { pending = true; requestAnimationFrame(update); } };
		this.on(window, 'scroll', request, { passive: true });
		this.on(window, 'resize', request);
		update();
	};

	Player.prototype.destroy = function () {
		this.destroyed = true;
		this.cleanup.forEach((fn) => fn());
		this.cleanup = [];
		if (this.anim) { this.anim.destroy(); this.anim = null; }
	};

	const mount = (widget, settings) => {
		if (widget.obxLottie) { widget.obxLottie.destroy(); }
		widget.obxLottie = new Player(widget, settings);
		return widget.obxLottie;
	};

	const settingsOf = (widget) => { try { return JSON.parse(widget.dataset.settings || '{}'); } catch (e) { return {}; } };

	window.addEventListener('elementor/frontend/init', () => {
		const frontend = window.elementorFrontend;
		const Base = window.elementorModules && elementorModules.frontend && elementorModules.frontend.handlers && elementorModules.frontend.handlers.Base;
		if (!frontend || !Base || !frontend.elementsHandler || !frontend.elementsHandler.attachHandler) { return; }
		// The settings without a re-render («render_type: none») are applied by mounting again.
		const Handler = Base.extend({
			onInit() {
				Base.prototype.onInit.apply(this, arguments);
				this.player = mount(this.$element[0], this.getElementSettings());
			},
			onElementChange() {
				clearTimeout(this.remount);
				this.remount = setTimeout(() => { this.player = mount(this.$element[0], this.getElementSettings()); }, 300);
			},
			onDestroy() {
				clearTimeout(this.remount);
				if (this.player) { this.player.destroy(); }
				Base.prototype.onDestroy.apply(this, arguments);
			},
		});
		frontend.elementsHandler.attachHandler('lottie', Handler);
	});

	// Without Elementor's frontend (e.g. a cached page that dropped it), each widget mounts itself.
	const fallback = () => {
		if (window.elementorFrontend) { return; }
		document.querySelectorAll('.elementor-widget-lottie').forEach((widget) => { if (!widget.obxLottie) { mount(widget, settingsOf(widget)); } });
	};
	if (document.readyState === 'complete') { setTimeout(fallback); } else { window.addEventListener('load', fallback); }
})();
