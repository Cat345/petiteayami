/**
 * Elementor compatibility — re-init the bundle behavior when Elementor renders.
 *
 * Elementor re-renders a widget on editor edits, and Pro re-clones a popup's
 * content on every show, so nothing bound at boot survives. This file only
 * calls the behavior layer's re-init entry points.
 */
(function () {
	"use strict";

	/** `element_ready` hands a jQuery object, not an Element. */
	function el(scope) {
		return (scope && scope[0]) || scope || null;
	}

	function bind() {
		var F = window.wffnBundlesSkins;
		var hooks = window.elementorFrontend && window.elementorFrontend.hooks;
		if (!F || !hooks) return;

		// The two add-to-cart widgets are the only places a bundle can appear: one
		// renders it through the core hooks, the other through the adapter's prepend.
		["woocommerce-product-add-to-cart", "wc-add-to-cart"].forEach(function (widget) {
			hooks.addAction("frontend/element_ready/" + widget + ".default", function ($scope) {
				F.rescan(el($scope));
			});
		});

		// The price widget can be re-rendered on its own, leaving every live quote
		// holding a detached node.
		hooks.addAction("frontend/element_ready/woocommerce-product-price.default", function ($scope) {
			if (F.refreshPrice) F.refreshPrice(el($scope));
		});
	}

	// elementorFrontend.hooks does not exist before this event, so a top-level
	// addAction would throw.
	window.addEventListener("elementor/frontend/init", bind);

	/**
	 * A popup holding an add-to-cart widget. Pro re-clones popup content from a
	 * snapshot, so the clone carries `data-wffn-bundles-bound` while its listeners are gone.
	 * The event carries no node; the modal id is derived from the popup id.
	 */
	window.addEventListener("elementor/popup/show", function (e) {
		var F = window.wffnBundlesSkins;
		var id = e && e.detail && e.detail.id;
		if (!F || !F.rescan || !id) return;
		var root = document.getElementById("elementor-popup-modal-" + id);
		if (root) F.rescan(root);
	});
})();
