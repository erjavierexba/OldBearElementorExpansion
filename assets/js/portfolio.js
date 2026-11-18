/*
 * Portfolio runtime: the filter bar shows the items of one term, masonry moves each item up to the one
 * above it, and with a fixed item ratio images shorter than the ratio fill the height instead.
 */
(function () {
	'use strict';

	const ratioOf = (widget) => {
		// The item ratio is written by its control as the wrapper's ::after content, as Pro does.
		const content = getComputedStyle(widget, '::after').content || '';
		const value = parseFloat(content.replace(/["']/g, ''));
		return Number.isFinite(value) && value > 0 ? value : 0.66;
	};

	const visible = (grid) => Array.from(grid.children).filter((item) => item.matches('.elementor-portfolio-item') && item.style.display !== 'none');

	function Portfolio(widget, settings) {
		this.widget = widget;
		this.settings = settings || {};
		this.grid = widget.querySelector('.elementor-portfolio');
		this.cleanup = [];
		if (!this.grid) { return; }
		this.frame = 0;
		this.apply();
		this.filters();
		const schedule = () => this.schedule();
		if (window.ResizeObserver) {
			const observer = new ResizeObserver(schedule);
			observer.observe(this.grid);
			this.cleanup.push(() => observer.disconnect());
		}
		this.on(this.grid, 'load', schedule, true);
		this.on(window, 'resize', schedule);
	}

	Portfolio.prototype.on = function (el, type, fn, options) {
		el.addEventListener(type, fn, options);
		this.cleanup.push(() => el.removeEventListener(type, fn, options));
	};

	/** Masonry or fixed ratio, from the settings (the editor changes them without rendering again). */
	Portfolio.prototype.apply = function () {
		this.masonry = 'yes' === this.settings.masonry;
		this.grid.classList.toggle('elementor-posts-masonry', this.masonry);
		this.grid.classList.toggle('elementor-has-item-ratio', !this.masonry);
		this.schedule();
	};

	Portfolio.prototype.schedule = function () {
		if (!this.frame) { this.frame = requestAnimationFrame(() => { this.frame = 0; this.layout(); }); }
	};

	Portfolio.prototype.layout = function () {
		const items = visible(this.grid);
		this.grid.querySelectorAll(':scope > .elementor-portfolio-item').forEach((item) => { item.style.marginTop = ''; });
		if (!this.masonry) {
			const ratio = ratioOf(this.widget);
			this.grid.querySelectorAll('.elementor-post__thumbnail').forEach((thumb) => {
				const img = thumb.querySelector('img');
				const fit = !!img && img.naturalWidth > 0 && img.naturalHeight / img.naturalWidth < ratio;
				thumb.classList.toggle('elementor-fit-height', fit);
			});
			return;
		}
		const style = getComputedStyle(this.grid);
		const columns = style.gridTemplateColumns.split(' ').filter(Boolean).length || 1;
		if (columns < 2) { return; }
		const gap = parseFloat(style.rowGap) || 0;
		const bottoms = [];
		items.forEach((item, index) => {
			const column = index % columns;
			if (index >= columns) { item.style.marginTop = (bottoms[column] + gap - item.getBoundingClientRect().top) + 'px'; }
			bottoms[column] = item.getBoundingClientRect().bottom;
		});
	};

	Portfolio.prototype.filters = function () {
		const bar = this.widget.querySelector('.elementor-portfolio__filters');
		if (!bar) { return; }
		const select = (filter) => {
			if (!filter || filter.classList.contains('elementor-active')) { return; }
			bar.querySelectorAll('.elementor-portfolio__filter').forEach((item) => item.classList.toggle('elementor-active', item === filter));
			const value = filter.dataset.filter;
			this.grid.querySelectorAll(':scope > .elementor-portfolio-item').forEach((item) => {
				const show = '__all' === value || item.classList.contains('elementor-filter-' + value);
				const shown = item.style.display !== 'none';
				if (show === shown) { return; }
				item.style.display = show ? '' : 'none';
				if (show && item.animate && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
					item.animate([{ opacity: 0, transform: 'scale(0.9)' }, { opacity: 1, transform: 'scale(1)' }], { duration: 400, easing: 'ease-in-out' });
				}
			});
			this.schedule();
		};
		this.on(bar, 'click', (event) => select(event.target.closest('.elementor-portfolio__filter')));
		this.on(bar, 'keydown', (event) => {
			if ('Enter' !== event.key && ' ' !== event.key) { return; }
			const filter = event.target.closest('.elementor-portfolio__filter');
			if (filter) { event.preventDefault(); select(filter); }
		});
	};

	Portfolio.prototype.destroy = function () {
		cancelAnimationFrame(this.frame);
		this.cleanup.forEach((fn) => fn());
		this.cleanup = [];
	};

	const mount = (widget, settings) => {
		if (widget.obxPortfolio) { widget.obxPortfolio.destroy(); }
		widget.obxPortfolio = new Portfolio(widget, settings);
		return widget.obxPortfolio;
	};

	window.addEventListener('elementor/frontend/init', () => {
		const frontend = window.elementorFrontend;
		const Base = window.elementorModules && elementorModules.frontend && elementorModules.frontend.handlers && elementorModules.frontend.handlers.Base;
		if (!frontend || !Base || !frontend.elementsHandler || !frontend.elementsHandler.attachHandler) { return; }
		const Handler = Base.extend({
			onInit() {
				Base.prototype.onInit.apply(this, arguments);
				this.portfolio = mount(this.$element[0], this.getElementSettings());
				// Masonry, the item ratio and the gaps change without rendering again (also on undo/redo).
				const model = this.isEdit && frontend.config.elements && frontend.config.elements.data[this.getModelCID()];
				if (model && model.on) {
					this.settingsModel = model;
					this.onSettings = () => {
						if (!this.portfolio) { return; }
						this.portfolio.settings = this.getElementSettings();
						this.portfolio.apply();
						setTimeout(() => this.portfolio && this.portfolio.schedule(), 100);
					};
					model.on('change', this.onSettings);
				}
			},
			onDestroy() {
				if (this.settingsModel) { this.settingsModel.off('change', this.onSettings); }
				if (this.portfolio) { this.portfolio.destroy(); }
				Base.prototype.onDestroy.apply(this, arguments);
			},
		});
		frontend.elementsHandler.attachHandler('portfolio', Handler);
	});

	const fallback = () => {
		if (window.elementorFrontend) { return; }
		document.querySelectorAll('.elementor-widget-portfolio').forEach((widget) => {
			let settings = {};
			try { settings = JSON.parse(widget.dataset.settings || '{}'); } catch (e) { settings = {}; }
			if (!widget.obxPortfolio) { mount(widget, settings); }
		});
	};
	if (document.readyState === 'complete') { setTimeout(fallback); } else { window.addEventListener('load', fallback); }
})();
