/**
 * OldBear Expansion: when a Theme Builder template is published for the first time from the editor,
 * ask where to show it (the display conditions screen of the Theme Builder, in Elementor's app modal).
 */
(function () {
	'use strict';
	var cfg = window.obxEditorConditions;
	if (!cfg || !window.$e || !window.$e.commands) { return; }
	var pending = {};

	function target(args) {
		var doc = (args && args.document) || (window.elementor && elementor.documents && elementor.documents.getCurrent());
		return doc && doc.config && cfg.types.indexOf(doc.config.type) !== -1 ? doc : null;
	}

	$e.commands.on('run:before', function (component, command, args) {
		if (command !== 'document/save/publish') { return; }
		var doc = target(args);
		if (doc) { pending[doc.id] = !doc.config.status || doc.config.status.value !== 'publish'; }
	});

	$e.commands.on('run:after', function (component, command, args, result) {
		if (command !== 'document/save/publish') { return; }
		var doc = target(args);
		if (!doc || !pending[doc.id]) { return; }
		var id = doc.id;
		pending[id] = false;
		Promise.resolve(result).then(function () {
			var base = (window.elementorAppConfig && window.elementorAppConfig.base_url) || cfg.app;
			$e.run('app/open', { url: base + '#/site-editor/conditions/' + id });
		}, function () { /* Not published: nothing to ask. */ });
	});
})();
