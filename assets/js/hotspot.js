/* Hotspot runtime: tooltips on click or hover (per device), direction of the slide/fade animations and sequenced entrance. */
(function () {
	'use strict';
	const settingsOf = (widget) => {
		const cid = widget.dataset.modelCid, data = cid && window.elementorFrontend && elementorFrontend.config && elementorFrontend.config.elements && elementorFrontend.config.elements.data[cid];
		if (data && data.attributes) { return data.toJSON ? data.toJSON() : data.attributes; }
		try { return JSON.parse(widget.dataset.settings || '{}'); } catch (e) { return {}; }
	};
	const device = () => (window.elementorFrontend && elementorFrontend.getCurrentDeviceMode ? elementorFrontend.getCurrentDeviceMode() : (innerWidth < 768 ? 'mobile' : (innerWidth < 1025 ? 'tablet' : 'desktop')));
	const breakpoints = () => {
		try { return elementorFrontend.breakpoints.getActiveBreakpointsList({ withDesktop: true }); } catch (e) { return ['mobile', 'tablet', 'desktop']; }
	};
	/** Responsive value for the current device: its own, else the next larger device's, else desktop's. */
	const responsive = (s, key) => {
		const list = breakpoints(), desktop = list.indexOf('desktop');
		let i = list.indexOf(device());
		if (i === -1) { i = desktop; }
		const step = i < desktop ? 1 : -1;
		for (let j = i; j !== desktop + step && j >= 0 && j < list.length; j += step) {
			const name = list[j] === 'desktop' ? key : key + '_' + list[j];
			if (s[name] !== undefined && s[name] !== '' && s[name] !== null) { return s[name]; }
		}
		return s[key];
	};
	const tooltipOf = (spot) => spot.querySelector(':scope > .e-hotspot__tooltip, :scope > .e-hotspot__direction-mask > .e-hotspot__tooltip');
	const open = (spot, yes) => {
		const tip = tooltipOf(spot);
		if (!tip) { return; }
		tip.classList.toggle('e-hotspot--active', yes);
		spot.querySelector('.e-hotspot__button')?.setAttribute('aria-expanded', yes ? 'true' : 'false');
	};

	const init = (widget) => {
		const container = widget.querySelector('.elementor-widget-container') || widget;
		const s = settingsOf(widget);
		const spots = Array.from(container.querySelectorAll(':scope > .e-hotspot'));
		const trigger = () => responsive(s, 'tooltip_trigger') || 'click';
		// Direction animations start from the side the tooltip opens to (its own position overrides the widget's).
		const side = responsive(s, 'tooltip_position') || 'top';
		spots.forEach((spot) => {
			const tip = tooltipOf(spot);
			if (!tip) { return; }
			Array.from(tip.classList).filter((c) => c.startsWith('e-hotspot--tooltip-animation-from-')).forEach((c) => tip.classList.remove(c));
			if (!Array.from(tip.classList).some((c) => c.startsWith('e-hotspot--override-tooltip-animation-from-'))) { tip.classList.add('e-hotspot--tooltip-animation-from-' + side); }
			const button = spot.querySelector('.e-hotspot__button');
			if (button && !spot.matches('a')) { button.setAttribute('role', 'button'); button.setAttribute('tabindex', '0'); button.setAttribute('aria-expanded', 'false'); }
		});
		// The editor re-renders the container: listeners go on each new one.
		if (container.dataset.obxHotspot) { return; }
		container.dataset.obxHotspot = '1';
		container.addEventListener('click', (event) => {
			if (trigger() !== 'click') { return; }
			const spot = event.target.closest('.e-hotspot');
			if (!spot || spot.parentElement !== container || event.target.closest('.e-hotspot__tooltip')) { return; }
			if (spot.matches('a') && !tooltipOf(spot)) { return; }
			if (spot.matches('a')) { event.preventDefault(); }
			const tip = tooltipOf(spot), shown = tip && tip.classList.contains('e-hotspot--active');
			container.querySelectorAll(':scope > .e-hotspot').forEach((other) => open(other, false));
			open(spot, !shown);
		});
		container.addEventListener('keydown', (event) => {
			if (event.key !== 'Enter' && event.key !== ' ' && event.key !== 'Escape') { return; }
			const spot = event.target.closest('.e-hotspot');
			if (!spot || !event.target.matches('.e-hotspot__button')) { return; }
			event.preventDefault();
			open(spot, event.key !== 'Escape' && !tooltipOf(spot)?.classList.contains('e-hotspot--active'));
		});
		container.addEventListener('mouseover', (event) => {
			if (trigger() !== 'mouseenter') { return; }
			const spot = event.target.closest('.e-hotspot');
			if (spot && spot.parentElement === container) { open(spot, true); }
		});
		container.addEventListener('mouseout', (event) => {
			if (trigger() !== 'mouseenter') { return; }
			const spot = event.target.closest('.e-hotspot');
			if (spot && !spot.contains(event.relatedTarget)) { open(spot, false); }
		});
		document.addEventListener('click', (event) => {
			if (trigger() === 'click' && !container.contains(event.target)) { container.querySelectorAll(':scope > .e-hotspot').forEach((spot) => open(spot, false)); }
		});
		// Sequenced entrance: the hotspots appear one after another once the image is on screen.
		if (s.hotspot_sequenced_animation === 'yes' && spots.length) {
			const total = Number((s.hotspot_sequenced_animation_duration || {}).size) || 1000;
			const step = total / spots.length;
			spots.forEach((spot) => { spot.style.opacity = '0'; });
			const run = () => spots.forEach((spot, i) => { spot.style.animation = `obx-hotspot-in ${Math.min(step, 1000)}ms ease ${Math.round(step * i)}ms both`; spot.style.opacity = ''; });
			if ('IntersectionObserver' in window) {
				const observer = new IntersectionObserver((entries) => { if (entries.some((e) => e.isIntersecting)) { observer.disconnect(); run(); } });
				observer.observe(container);
			} else { run(); }
		}
	};
	const initAll = (root) => {
		const scope = root && root.querySelectorAll ? root : document;
		if (scope.matches && scope.matches('.elementor-widget-hotspot')) { init(scope); }
		scope.querySelectorAll('.elementor-widget-hotspot').forEach(init);
	};
	let timer = 0;
	window.addEventListener('resize', () => { clearTimeout(timer); timer = setTimeout(() => document.querySelectorAll('.elementor-widget-hotspot').forEach(init), 200); });
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', () => initAll(document)); } else { initAll(document); }
	window.addEventListener('elementor/frontend/init', () => {
		if (!window.elementorFrontend || !elementorFrontend.hooks) { return; }
		elementorFrontend.hooks.addAction('frontend/element_ready/hotspot.default', ($scope) => initAll($scope[0] || $scope));
	});
})();
