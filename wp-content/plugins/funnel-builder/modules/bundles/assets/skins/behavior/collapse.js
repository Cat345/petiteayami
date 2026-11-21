/**
 * Skins behavior — "Show all N options" for long offer lists.
 *
 * The server renders every offer; the collapse and its button are applied here,
 * so with JavaScript off the shopper gets the full list and no button.
 */
(function () {
	"use strict";

	var F = window.wffnBundlesSkins;

	/**
	 * How many offers stay visible when collapsed.
	 *
	 * A count, not a pixel height: a tier measures 87px on Storefront and 121px
	 * on OceanWP. PHP owns the number (`Product_Display::OFFERS_LIMIT`); the
	 * literal is the fallback for a page rendered without the cart config.
	 */
	var LIMIT = ((window.wffnBundlesCart && window.wffnBundlesCart.offersLimit) | 0) || 6;

	/**
	 * The offer list of each bundle type. All gated on the same count.
	 *
	 * The item selector must match the template's actual child class, and
	 * `selected` names the class a picked item carries. Fixed Bundle is listed
	 * for completeness and does not fire today; a bundle's members are
	 * deliberately not collapsible. Another script adds its own lists to
	 * `F.collapseLists` before the widgets are set up.
	 */
	var LISTS = [
		{ list: ".wffn-bundles-fb-stack", item: ".wffn-bundles-fb-card, .wffn-bundles-fb-single", selected: "wffn-bundles-fb-card--selected" },
	];
	F.collapseLists = F.collapseLists || [];

	function setup(widget) {
		var lists = LISTS.concat(F.collapseLists);
		for (var i = 0; i < lists.length; i++) {
			var list = widget.querySelector(":scope > " + lists[i].list) || widget.querySelector(lists[i].list);
			if (!list) continue;
			collapse(widget, list, lists[i].item, lists[i].selected);
			// Release the pre-paint guard (Product_Display::emit_paint_guard) after
			// collapse(), so the two states swap in one frame.
			list.classList.add("wffn-bundles-collapse-ready");
		}
	}

	function collapse(widget, list, itemSel, selectedClass) {
		var signal = F.bind(list, "collapse");
		if (!signal) return;

		var items = Array.prototype.filter.call(
			list.children,
			function (el) { return el.matches && el.matches(itemSel); }
		);
		// The limit is overridable per widget so a store can retune it without a
		// build; absent (the normal case) it is the constant above.
		var limit = F.num(widget.getAttribute("data-wffn-bundles-offer-limit")) || LIMIT;
		if (items.length <= limit) return;

		var i18n = (window.wffnBundlesCart && window.wffnBundlesCart.i18n) || {};
		var hidden = items.slice(limit);

		function isSelected(el) {
			return !!el.querySelector("input:checked") || (!!selectedClass && el.classList.contains(selectedClass));
		}

		// Start expanded when the pre-selected offer is one of the hidden ones, so
		// the quoted price above the button belongs to a visible row.
		var selectedHidden = hidden.some(isSelected);

		var btn = document.createElement("button");
		btn.type = "button";
		btn.className = "wffn-bundles-offers-more";
		list.insertAdjacentElement("afterend", btn);

		function render(open) {
			for (var i = 0; i < hidden.length; i++) {
				// The picked offer stays in view: the price and quantity above still describe it.
				hidden[i].classList.toggle("wffn-bundles-offer--hidden", !open && !isSelected(hidden[i]));
			}
			btn.setAttribute("aria-expanded", open ? "true" : "false");
			btn.textContent = open
				? (i18n.showFewerOffers || "Show fewer options")
				: (i18n.showAllOffers || "Show all %d options").replace("%d", items.length);
		}

		btn.addEventListener("click", function () {
			var open = btn.getAttribute("aria-expanded") === "true";
			render(!open);
			// Collapsing from the bottom of a long list would otherwise leave the
			// viewport somewhere below the widget, looking like the page jumped.
			if (open) {
				var top = list.getBoundingClientRect().top;
				if (top < 0) list.scrollIntoView({ block: "start", behavior: "smooth" });
			}
		}, { signal: signal });

		render(selectedHidden);
	}

	F.register(function (root) {
		F.each(root, ".wffn-bundles", setup);
	});
})();
