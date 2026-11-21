/**
 * Skins behavior — storefront bootstrap.
 *
 * Runs the registered skin initializers over the document once the DOM is
 * ready. Loaded last, after core + every skin module. The admin preview does
 * not rely on this file; it calls wffnBundlesSkins.attachBehavior(shadowRoot) directly.
 */
(function () {
	"use strict";

	var F = window.wffnBundlesSkins;
	if (!F || !F.attachBehavior) return;

	function boot() {
		F.attachBehavior(document);
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", boot);
	} else {
		boot();
	}

	// Re-init entry point for code that has no reference to `wffnBundlesSkins` — a page
	// builder, a quick-view, an AJAX product page. `detail.root` scopes it.
	document.addEventListener("wffn-bundles:rescan", function (e) {
		var root = (e && e.detail && e.detail.root) || document;
		if (F.rescan) F.rescan(root);
	});
})();
