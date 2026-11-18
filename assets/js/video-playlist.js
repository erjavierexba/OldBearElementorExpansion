/*
 * Video playlist runtime (Pro's markup): choosing a video in the list plays it beside the list with the
 * YouTube or Vimeo player API (Elementor's loaders) or a <video> element; on load and next autoplay,
 * watched marks, the image overlay, lazy loading, the content tabs under the video with «show more»,
 * folding the list on mobile and the scroll shadows of the list.
 */
(function () {
	'use strict';

	/** One video panel's player, created on first use and kept while the widget lives. */
	class Player {
		constructor(panel, playlist) {
			this.panel = panel;
			this.playlist = playlist;
			this.type = panel.dataset.videoType;
			this.url = panel.dataset.videoUrl || '';
			this.started = false;
			this.ready = null;
			this.api = null;
		}

		target() {
			let target = this.panel.querySelector(':scope > div:not(.elementor-custom-embed-image-overlay)');
			if (!target) { target = document.createElement('div'); this.panel.prepend(target); }
			return target;
		}

		/** Builds the player once; resolves when it can be controlled. */
		create(autoplay, muted) {
			if (this.ready) { return this.ready; }
			const utils = this.playlist.utils;
			const onEnded = () => this.playlist.ended(this);
			const onPlay = () => { this.started = true; };
			if ('youtube' === this.type && utils && utils.youtube) {
				const id = utils.youtube.getVideoIDFromURL(this.url);
				if (!id) { return (this.ready = Promise.resolve(null)); }
				const start = (this.url.match(/[?&](?:t|start)=(\d+)/) || [])[1];
				this.ready = new Promise((resolve) => {
					utils.youtube.onApiReady((YT) => {
						const api = new YT.Player(this.target(), {
							videoId: id,
							width: '100%',
							height: '100%',
							playerVars: { autoplay: autoplay ? 1 : 0, mute: muted ? 1 : 0, rel: 0, playsinline: 1, controls: 1, start: start ? +start : undefined },
							events: {
								onReady: () => { this.api = api; resolve(this); },
								onStateChange: (event) => {
									if (event.data === YT.PlayerState.PLAYING) { onPlay(); }
									if (event.data === YT.PlayerState.ENDED) { onEnded(); }
								},
							},
						});
					});
				});
				this.control = {
					play: () => this.api && this.api.playVideo(),
					pause: () => this.api && this.api.pauseVideo && this.api.pauseVideo(),
					destroy: () => this.api && this.api.destroy(),
				};
			} else if ('vimeo' === this.type && utils && utils.vimeo) {
				if (!utils.vimeo.getVideoIDFromURL(this.url)) { return (this.ready = Promise.resolve(null)); }
				this.ready = new Promise((resolve) => {
					utils.vimeo.onApiReady((Vimeo) => {
						const api = new Vimeo.Player(this.target(), { url: this.url, autoplay: !!autoplay, muted: !!muted, playsinline: true, dnt: true });
						api.on('play', onPlay);
						api.on('ended', onEnded);
						api.ready().then(() => { this.api = api; resolve(this); }, () => resolve(null));
					});
				});
				this.control = {
					play: () => this.api && this.api.play().catch(() => {}),
					pause: () => this.api && this.api.pause().catch(() => {}),
					destroy: () => this.api && this.api.destroy().catch(() => {}),
				};
			} else if ('hosted' === this.type && this.url) {
				const video = document.createElement('video');
				video.src = this.url;
				video.controls = true;
				video.playsInline = true;
				video.preload = 'metadata';
				video.muted = !!muted;
				video.setAttribute('controlsList', 'nodownload');
				video.addEventListener('play', onPlay);
				video.addEventListener('ended', onEnded);
				this.target().appendChild(video);
				this.api = video;
				this.control = {
					play: () => { const promise = video.play(); if (promise && promise.catch) { promise.catch(() => {}); } },
					pause: () => video.pause(),
					destroy: () => { video.pause(); video.removeAttribute('src'); video.load(); },
				};
				this.ready = Promise.resolve(this);
				if (autoplay) { this.control.play(); }
			} else {
				this.ready = Promise.resolve(null);
			}
			return this.ready;
		}

		play() { return this.create(true, false).then((self) => { if (self) { this.control.play(); } }); }
		pause() { if (this.api && this.control) { this.control.pause(); } }
		destroy() { if (this.api && this.control) { try { this.control.destroy(); } catch (e) { /* already gone */ } } this.api = null; }
	}

	class Playlist {
		constructor(widget, settings, frontend) {
			this.widget = widget;
			this.settings = settings || {};
			this.frontend = frontend;
			this.utils = frontend && frontend.utils;
			this.cleanup = [];
			this.players = new Map();
			this.root = widget.querySelector('.e-tabs');
			if (!this.root) { return; }
			this.list = this.root.querySelector('.e-tabs-items');
			this.itemsWrapper = this.root.querySelector('.e-tabs-items-wrapper');
			this.titles = Array.from(this.root.querySelectorAll('.e-tabs-items .e-tab-title'));
			this.panels = Array.from(this.root.querySelectorAll('.e-tabs-content-wrapper > .e-tab-content'));
			this.innerPanels = Array.from(this.root.querySelectorAll('.e-tabs-inner-tabs > .e-tab-content'));
			this.interacted = false;

			this.on(this.root, 'pointerdown', () => { this.interacted = true; }, true);
			this.on(this.root, 'keydown', () => { this.interacted = true; }, true);
			this.bindList();
			this.bindOverlay();
			this.bindInnerTabs();
			this.bindToggle();
			this.bindShadows();

			const first = this.titles[0];
			if (!first) { return; }
			this.select(first, { play: false, focus: false });
			this.start();
		}

		on(el, type, fn, options) {
			if (!el) { return; }
			el.addEventListener(type, fn, options);
			this.cleanup.push(() => el.removeEventListener(type, fn, options));
		}

		tabOf(title) { return title.dataset.tab; }
		panelOf(tab) { return this.panels.find((panel) => panel.dataset.tab === tab); }
		player(tab) {
			const panel = this.panelOf(tab);
			if (!panel) { return null; }
			if (!this.players.has(tab)) { this.players.set(tab, new Player(panel, this)); }
			return this.players.get(tab);
		}

		/** The first video, on load: autoplayed (muted, as browsers require) or ready to play, now or when seen. */
		start() {
			const tab = this.tabOf(this.active);
			const panel = this.panelOf(tab);
			const overlay = panel && panel.querySelector('.elementor-custom-embed-image-overlay');
			const autoplay = 'yes' === this.settings.autoplay_on_load;
			if (overlay && !autoplay) { return; }
			const create = () => {
				const player = this.player(tab);
				if (player && this.tabOf(this.active) === tab) { player.create(autoplay, autoplay); }
			};
			if ('yes' !== this.settings.lazy_load || !window.IntersectionObserver) { create(); return; }
			const observer = new IntersectionObserver((entries) => {
				if (!entries.some((entry) => entry.isIntersecting)) { return; }
				observer.disconnect();
				create();
			}, { rootMargin: '200px 0px' });
			observer.observe(this.root);
			this.cleanup.push(() => observer.disconnect());
		}

		/** Makes a video the current one: list state, panels (player and content tabs) and, if asked, playback. */
		select(title, { play = true, focus = false } = {}) {
			const previous = this.active;
			const tab = this.tabOf(title);
			if (previous && previous !== title) {
				const old = this.players.get(this.tabOf(previous));
				if (old) {
					if (old.started) { this.markWatched(previous); }
					old.pause();
				}
			}
			this.active = title;
			this.titles.forEach((item) => {
				const current = item === title;
				item.classList.toggle('e-active', current);
				item.setAttribute('aria-selected', current ? 'true' : 'false');
				item.setAttribute('tabindex', current ? '0' : '-1');
			});
			this.panels.forEach((panel) => { panel.hidden = panel.dataset.tab !== tab; });
			this.innerPanels.forEach((panel) => { panel.hidden = panel.dataset.tab !== tab; });
			const inner = this.innerPanels.find((panel) => panel.dataset.tab === tab);
			if (inner) { this.measureCollapsible(inner); }
			this.reveal(title);
			if (focus) { title.focus({ preventScroll: true }); }
			if (play) {
				this.removeOverlay(this.panelOf(tab));
				const player = this.player(tab);
				if (player) { player.play(); }
			}
		}

		/** Scrolls the list (not the page) so the current video is visible. */
		reveal(title) {
			if (!this.list) { return; }
			const top = title.offsetTop - this.list.offsetTop;
			const bottom = top + title.offsetHeight;
			if (top < this.list.scrollTop) { this.list.scrollTop = top; } else if (bottom > this.list.scrollTop + this.list.clientHeight) { this.list.scrollTop = bottom - this.list.clientHeight; }
			this.shadows();
		}

		markWatched(title) {
			if ('yes' === this.settings.show_watched_indication) { title.classList.add('watched-video'); }
		}

		/** A video ended: mark it and, with «Next up», play the next one in the list. */
		ended(player) {
			const title = this.titles.find((item) => this.tabOf(item) === player.panel.dataset.tab);
			if (title) { this.markWatched(title); }
			if ('yes' !== this.settings.autoplay_next || title !== this.active) { return; }
			const next = this.titles[this.titles.indexOf(title) + 1];
			if (!next) { return; }
			const muted = !this.interacted && 'yes' === this.settings.autoplay_on_load;
			this.select(next, { play: false });
			const nextPlayer = this.player(this.tabOf(next));
			if (nextPlayer) { nextPlayer.create(true, muted).then((self) => self && self.control.play()); }
		}

		bindList() {
			this.on(this.list, 'click', (event) => {
				const title = event.target.closest('.e-tab-title');
				if (title && this.list.contains(title)) { this.select(title); }
			});
			this.on(this.list, 'keydown', (event) => {
				const title = event.target.closest('.e-tab-title');
				if (!title) { return; }
				const index = this.titles.indexOf(title);
				const keys = { ArrowDown: index + 1, ArrowRight: index + 1, ArrowUp: index - 1, ArrowLeft: index - 1, Home: 0, End: this.titles.length - 1 };
				if (event.key in keys) {
					const target = this.titles[Math.max(0, Math.min(this.titles.length - 1, keys[event.key]))];
					event.preventDefault();
					if (target) { target.focus(); }
					return;
				}
				// The title's own button already turns Enter and Space into a click.
				if (('Enter' === event.key || ' ' === event.key) && event.target === title) { event.preventDefault(); this.select(title); }
			});
		}

		removeOverlay(panel) {
			const overlay = panel && panel.querySelector('.elementor-custom-embed-image-overlay');
			if (overlay) { overlay.remove(); }
		}

		bindOverlay() {
			const overlay = this.root.querySelector('.e-tabs-content-wrapper .elementor-custom-embed-image-overlay');
			if (!overlay) { return; }
			const play = () => {
				const panel = overlay.closest('.e-tab-content');
				const title = this.titles.find((item) => this.tabOf(item) === panel.dataset.tab);
				if (title) { this.select(title); }
			};
			this.on(overlay, 'click', play);
			this.on(overlay, 'keydown', (event) => { if ('Enter' === event.key || ' ' === event.key) { event.preventDefault(); play(); } });
		}

		/** Content tabs: titles above (desktop) and titles before each content (mobile) open the same content. */
		bindInnerTabs() {
			this.innerPanels.forEach((panel) => {
				const heads = Array.from(panel.querySelectorAll('.e-inner-tabs-wrapper .e-inner-tab-title'));
				const mobile = Array.from(panel.querySelectorAll('.e-inner-tabs-content-wrapper .e-tab-mobile-title'));
				const contents = Array.from(panel.querySelectorAll('.e-inner-tabs-content-wrapper .e-inner-tab-content'));
				const open = (index) => {
					[heads, mobile].forEach((list) => list.forEach((head, i) => head.classList.toggle('e-inner-tab-active', i === index)));
					contents.forEach((content, i) => {
						const active = i === index;
						content.classList.toggle('e-inner-tab-active', active);
						content.hidden = !active;
					});
					this.measureCollapsible(panel);
				};
				[heads, mobile].forEach((list) => list.forEach((head, index) => {
					this.on(head, 'click', (event) => { event.preventDefault(); open(index); });
					this.on(head, 'keydown', (event) => { if ('Enter' === event.key || ' ' === event.key) { event.preventDefault(); open(index); } });
				}));
				contents.forEach((content) => {
					const buttons = Array.from(content.querySelectorAll('.e-inner-tab-buttons button'));
					buttons.forEach((button) => this.on(button, 'click', (event) => {
						event.preventDefault();
						const full = content.classList.toggle('show-full-height');
						// The visible button carries «show-button»: «Show more» when folded, «Show less» when open.
						buttons.forEach((item, i) => item.classList.toggle('show-button', full ? i === 1 : i === 0));
						const focus = buttons[full ? 1 : 0];
						if (focus) { focus.focus(); }
					}));
				});
			});
			if (this.innerPanels.length && window.ResizeObserver) {
				const observer = new ResizeObserver(() => this.innerPanels.forEach((panel) => { if (!panel.hidden) { this.measureCollapsible(panel); } }));
				observer.observe(this.root);
				this.cleanup.push(() => observer.disconnect());
			}
		}

		/** «Show more» appears only when the folded text is taller than the folded height. */
		measureCollapsible(panel) {
			panel.querySelectorAll('.e-inner-tab-content.collapsible').forEach((content) => {
				if (content.hidden || content.classList.contains('show-full-height')) { return; }
				const text = content.querySelector('.e-inner-tab-text');
				if (text) { content.classList.toggle('show-inner-tab-buttons', text.scrollHeight > text.clientHeight + 1); }
			});
		}

		/** Mobile: the caret folds and unfolds the list. */
		bindToggle() {
			const toggle = this.root.querySelector('.e-tabs-toggle-videos-display-button');
			if (!toggle || !this.itemsWrapper) { return; }
			toggle.setAttribute('role', 'button');
			toggle.setAttribute('tabindex', '0');
			toggle.setAttribute('aria-expanded', 'true');
			toggle.removeAttribute('aria-hidden');
			toggle.setAttribute('aria-label', (this.root.querySelector('.e-tabs-title') || {}).textContent || 'Vídeos');
			const flip = () => {
				const folded = this.itemsWrapper.style.display !== 'none';
				this.itemsWrapper.style.display = folded ? 'none' : '';
				toggle.classList.toggle('rotate-up', folded);
				toggle.classList.toggle('rotate-down', !folded);
				toggle.setAttribute('aria-expanded', folded ? 'false' : 'true');
				if (!folded) { this.shadows(); }
			};
			this.on(toggle, 'click', flip);
			this.on(toggle, 'keydown', (event) => { if ('Enter' === event.key || ' ' === event.key) { event.preventDefault(); flip(); } });
			// Back on a wider screen the list is always shown.
			const config = this.frontend && this.frontend.config;
			const mobile = (config && config.responsive && config.responsive.activeBreakpoints.mobile) || { value: 767 };
			const query = window.matchMedia('(max-width:' + mobile.value + 'px)');
			const reset = () => {
				if (query.matches || this.itemsWrapper.style.display !== 'none') { return; }
				this.itemsWrapper.style.display = '';
				toggle.classList.remove('rotate-up');
				toggle.classList.add('rotate-down');
				toggle.setAttribute('aria-expanded', 'true');
			};
			if (query.addEventListener) { query.addEventListener('change', reset); this.cleanup.push(() => query.removeEventListener('change', reset)); }
		}

		/** Gradients at the top and bottom of the list while there are more videos that way. */
		shadows() {
			if (!this.list || !this.itemsWrapper) { return; }
			const { scrollTop, scrollHeight, clientHeight } = this.list;
			this.itemsWrapper.classList.toggle('top-shadow', scrollTop > 0);
			this.itemsWrapper.classList.toggle('bottom-shadow', scrollTop + clientHeight < scrollHeight - 1);
		}

		bindShadows() {
			if (!this.list) { return; }
			this.on(this.list, 'scroll', () => this.shadows(), { passive: true });
			if (window.ResizeObserver) {
				const observer = new ResizeObserver(() => this.shadows());
				observer.observe(this.list);
				this.cleanup.push(() => observer.disconnect());
			}
			this.shadows();
		}

		destroy() {
			this.cleanup.forEach((fn) => fn());
			this.cleanup = [];
			this.players.forEach((player) => player.destroy());
			this.players.clear();
		}
	}

	const mount = (widget, settings, frontend) => {
		if (widget.obxPlaylist) { widget.obxPlaylist.destroy(); }
		widget.obxPlaylist = new Playlist(widget, settings, frontend);
		return widget.obxPlaylist;
	};

	window.addEventListener('elementor/frontend/init', () => {
		const frontend = window.elementorFrontend;
		const Base = window.elementorModules && elementorModules.frontend && elementorModules.frontend.handlers && elementorModules.frontend.handlers.Base;
		if (!frontend || !Base || !frontend.elementsHandler || !frontend.elementsHandler.attachHandler) { return; }
		const Handler = Base.extend({
			onInit() {
				Base.prototype.onInit.apply(this, arguments);
				this.playlist = mount(this.$element[0], this.getElementSettings(), frontend);
			},
			onDestroy() {
				if (this.playlist) { this.playlist.destroy(); }
				Base.prototype.onDestroy.apply(this, arguments);
			},
		});
		frontend.elementsHandler.attachHandler('video-playlist', Handler);
	});
})();
