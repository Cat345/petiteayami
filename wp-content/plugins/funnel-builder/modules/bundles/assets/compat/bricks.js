/**
 * Bricks compatibility — deny Bricks' AJAX add-to-cart, then re-init on its
 * render events.
 *
 * Bricks re-renders through four AJAX surfaces and re-inserts markup nothing
 * bound at boot covers; this file only calls the behavior layer's re-init entry
 * points. The standalone-form unwrap lives in core.js.
 */
(function () {
	"use strict";

	/**
	 * Stop Bricks claiming our add-to-cart button.
	 *
	 * Bricks binds its AJAX handler to `.single_add_to_cart_button` on the same
	 * node we do, so capture phase cannot win; the gate is denied instead.
	 * `functionCanRun()` reads a falsy windowVariableCheck as absent, so setting
	 * this at parse time stops it binding and subscribing to its own re-init.
	 *
	 * Page-global, because the gate is; scoping is by enqueue.
	 */
	// Only Bricks defines this object; it is localized before this file runs.
	if (window.bricksWooCommerce) {
		window.bricksWooCommerce.ajaxAddToCartEnabled = false;
	}

	/**
	 * Re-init a scope. Always rescan, never attachBehavior: a node Bricks
	 * re-inserted still carries `data-wffn-bundles-bound` while its listeners are gone.
	 */
	function rescan(scope) {
		var F = window.wffnBundlesSkins;
		if (!F || !F.rescan) return;
		F.rescan(scope || document);
		if (F.refreshPrice) F.refreshPrice(scope || document);
	}

	// Bricks' own default re-init set (`assets/js/frontend.js:1289-1294`). The
	// query events carry only a queryId, so those fall back to the document.
	["bricks/ajax/pagination/completed", "bricks/ajax/load_page/completed", "bricks/ajax/query_result/displayed"].forEach(
		function (name) {
			document.addEventListener(name, function () {
				rescan(document);
			});
		}
	);

	// Both popup events carry the node, so the scope is exact; an AJAX popup fires
	// both and rescans twice, which is free. `open` is the only event a preloaded popup fires.
	["bricks/ajax/popup/loaded", "bricks/popup/open"].forEach(function (name) {
		document.addEventListener(name, function (e) {
			rescan((e && e.detail && e.detail.popupElement) || document);
		});
	});
})();
