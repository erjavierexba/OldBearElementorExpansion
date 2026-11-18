/*
 * Video playlist, editor: «Get video data» fills the item's title, duration and thumbnail from the
 * YouTube or Vimeo address (looked up by the site through oEmbed).
 */
(function () {
	'use strict';

	const config = window.obxVideoPlaylist || {};

	const toast = (message) => {
		if (window.elementor && elementor.notifications && elementor.notifications.showToast) { elementor.notifications.showToast({ message }); }
	};

	const fetchData = (view) => {
		const container = view && (view.container || (view.options && view.options.container));
		if (!container || !container.settings || !config.rest) { return; }
		const settings = container.settings;
		const type = settings.get('type');
		const url = String(settings.get('youtube' === type ? 'youtube_url' : 'vimeo_url') || '').trim();
		if (!url) { toast('Introduce primero el enlace del vídeo.'); return; }
		const button = view.ui && view.ui.button && view.ui.button[0];
		if (button) { button.disabled = true; }
		fetch(config.rest, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
			body: JSON.stringify({ url }),
		})
			.then((response) => response.json().then((data) => ({ ok: response.ok, data })))
			.then(({ ok, data }) => {
				if (!ok) { toast((data && data.message) || 'No se han podido obtener los datos del vídeo.'); return; }
				const changes = {};
				if (data.title) { changes.title = data.title; }
				if (data.duration) { changes.duration = data.duration; }
				if (data.thumbnail) { changes.thumbnail = { url: data.thumbnail, id: '', size: '', alt: '', source: 'library' }; }
				if (!Object.keys(changes).length) { toast('El vídeo no tiene datos que importar.'); return; }
				$e.run('document/elements/settings', { container, settings: changes, options: { external: true } });
			})
			.catch(() => toast('No se han podido obtener los datos del vídeo.'))
			.finally(() => { if (button) { button.disabled = false; } });
	};

	window.addEventListener('elementor/init', () => {
		elementor.channels.editor.on('elementorPlaylistWidget:fetchVideoData', fetchData);
	});
})();
