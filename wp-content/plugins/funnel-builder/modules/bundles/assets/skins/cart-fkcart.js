/**
 * FunnelKit Cart slide-out — `:has()` fallback for the bundle grouping.
 *
 * cart-fkcart.css carries the grouping on `:has()`, which needs no script and so
 * never flashes ungrouped after a fragment swap. This promotes the same marker
 * to a class on the row wrapper for browsers without it, and is inert elsewhere.
 */
(function () {
	"use strict";

	var supported =
		window.CSS && typeof window.CSS.supports === "function" && window.CSS.supports("selector(:has(*))");

	if (supported) {
		return;
	}

	function promote() {
		var marks = document.querySelectorAll(".fkcart-item-wrap .wffn-bundles-fkcart-mark");
		for (var i = 0; i < marks.length; i++) {
			var row = marks[i].parentNode;
			if (!row || !row.classList) continue;
			var role = marks[i].getAttribute("data-role");
			if ("container" === role) {
				row.classList.add("wffn-bundles-fkcart-container");
			} else if ("child" === role) {
				row.classList.add("wffn-bundles-fkcart-child");
			}
			if ("1" === marks[i].getAttribute("data-nested")) {
				row.classList.add("wffn-bundles-fkcart-nested");
			}
			if ("1" === marks[i].getAttribute("data-qty")) {
				row.classList.add("wffn-bundles-fkcart-qty");
			}
		}
	}

	// The drawer is replaced wholesale on every refresh, so the classes have to be
	// rewritten each time rather than set once on load.
	var events = [
		"fkcart_fragments_loaded",
		"fkcart_update_side_cart",
		"fkcart_quantity_updated",
		"fkcart_cart_open",
		"added_to_cart",
	];

	if (window.jQuery) {
		window.jQuery(document.body).on(events.join(" "), function () {
			window.setTimeout(promote, 0);
		});
	}

	if ("loading" === document.readyState) {
		document.addEventListener("DOMContentLoaded", promote);
	} else {
		promote();
	}
})();
