/*
 * Carousels with Elementor Pro's markup and keys (Reviews), on Elementor's own carousel handler and
 * Swiper: slides per view and per step for each device, space between slides, arrows, pagination,
 * autoplay with pause on hover or interaction, infinite loop, speed and lazy loading.
 */
(function () {
	'use strict';

	window.addEventListener('elementor/frontend/init', () => {
		const frontend = window.elementorFrontend;
		const handlers = window.elementorModules && elementorModules.frontend && elementorModules.frontend.handlers;
		if (!frontend || !handlers || !handlers.CarouselBase || !frontend.elementsHandler || !frontend.elementsHandler.attachHandler) { return; }
		const i18n = (frontend.config && frontend.config.i18n) || {};

		class ProCarousel extends handlers.CarouselBase {
			/** Slides per view when the setting is empty, per device. */
			slidesPerViewDefault() { return 1; }

			deviceSetting(key, device) {
				const settings = this.getElementSettings();
				return settings['desktop' === device ? key : key + '_' + device];
			}

			getSpaceBetween(device = null) {
				return Number(frontend.utils.controls.getResponsiveControlValue(this.getElementSettings(), 'space_between', 'size', device)) || 0;
			}

			getSwiperSettings() {
				const s = this.getElementSettings();
				const count = this.getSlidesCount();
				const breakpoints = frontend.config.responsive.activeBreakpoints;
				const perView = Math.min(count, +s.slides_per_view || this.slidesPerViewDefault('desktop')) || 1;
				const options = {
					grabCursor: true,
					initialSlide: this.getInitialSlide(),
					slidesPerView: perView,
					slidesPerGroup: +s.slides_to_scroll || 1,
					spaceBetween: this.getSpaceBetween(),
					loop: 'yes' === s.loop,
					speed: +s.speed || 500,
					handleElementorBreakpoints: true,
					breakpoints: {},
				};
				// Devices without a value take the next larger device's, as Elementor's own carousels.
				let last = perView;
				Object.keys(breakpoints).reverse().forEach((device) => {
					const value = Math.min(count, +this.deviceSetting('slides_per_view', device) || this.slidesPerViewDefault(device) || last) || 1;
					options.breakpoints[breakpoints[device].value] = {
						slidesPerView: value,
						slidesPerGroup: +this.deviceSetting('slides_to_scroll', device) || 1,
						spaceBetween: this.getSpaceBetween(device),
					};
					last = value;
				});
				if (options.loop) { options.loopedSlides = count; }
				if (s.effect && 'slide' !== s.effect) {
					options.effect = s.effect;
					if ('fade' === s.effect) { options.fadeEffect = { crossFade: true }; }
				}
				if ('yes' === s.autoplay) {
					options.autoplay = { delay: +s.autoplay_speed || 5000, disableOnInteraction: 'yes' === s.pause_on_interaction };
				}
				const element = this.$element[0];
				if ('yes' === s.show_arrows) {
					options.navigation = { prevEl: element.querySelector('.elementor-swiper-button-prev'), nextEl: element.querySelector('.elementor-swiper-button-next') };
				}
				if (s.pagination) {
					options.pagination = {
						el: element.querySelector('.swiper-pagination'),
						type: s.pagination,
						clickable: true,
						renderBullet: (index, className) => `<span class="${className}" role="button" tabindex="0" data-bullet-index="${index}" aria-label="${i18n.a11yCarouselPaginationBulletMessage || 'Ir a la diapositiva'} ${index + 1}"></span>`,
					};
				}
				if ('yes' === s.lazyload) { options.lazy = { loadPrevNext: true, loadPrevNextAmount: 1 }; }
				options.a11y = {
					enabled: true,
					prevSlideMessage: i18n.a11yCarouselPrevSlideMessage,
					nextSlideMessage: i18n.a11yCarouselNextSlideMessage,
					firstSlideMessage: i18n.a11yCarouselFirstSlideMessage,
					lastSlideMessage: i18n.a11yCarouselLastSlideMessage,
				};
				options.on = {
					slideChange: () => {
						if (this.a11ySetPaginationTabindex) { this.a11ySetPaginationTabindex(); }
						if (this.handleElementHandlers) { this.handleElementHandlers(); }
						if (this.a11ySetSlideAriaHidden) { this.a11ySetSlideAriaHidden(); }
					},
					init: () => {
						if (this.a11ySetPaginationTabindex) { this.a11ySetPaginationTabindex(); }
						if (this.a11ySetSlideAriaHidden) { this.a11ySetSlideAriaHidden('initialisation'); }
					},
				};
				return options;
			}

			getChangeableProperties() {
				return { pause_on_hover: 'pauseOnHover', autoplay_speed: 'delay', speed: 'speed' };
			}

			onElementChange(property) {
				if (0 === property.indexOf('space_between')) {
					if (!this.swiper) { return; }
					const match = property.match(/^space_between_(.+)$/);
					const device = match ? match[1] : 'desktop';
					const value = this.getSpaceBetween(device);
					if ('desktop' === device) { this.swiper.params.spaceBetween = value; } else if (this.swiper.params.breakpoints[frontend.config.responsive.activeBreakpoints[device].value]) { this.swiper.params.breakpoints[frontend.config.responsive.activeBreakpoints[device].value].spaceBetween = value; }
					this.swiper.update();
					return;
				}
				super.onElementChange(property);
			}
		}

		// Reviews show one review per view unless set otherwise, as Pro's.
		frontend.elementsHandler.attachHandler('reviews', ProCarousel);
	});
})();
