/**
 * FunnelKit Cart quick view — hands the drawer's "Add to cart" to the bundle widget.
 *
 * A bundle is added through the widget's own add (its selection, offer and add-ons are
 * validated server-side from that request), not through the drawer's variation add. The
 * quick view carries a hidden proxy button for the widget to intercept; this forwards the
 * drawer's button to it (or to a bought-together set's own button). Any add from a bundle widget in the quick view (a bought-together set
 * has its own button) closes the quick view once it lands, as the drawer's own add does.
 */
(function () {
	"use strict";

	/** The button of a bought-together set that can be pressed now. */
	function setButton(content) {
		var set = content.querySelector(".wffn-bundles-fbt-add");
		if (!set || set.disabled || "true" === set.getAttribute("aria-disabled") || set.hidden) return null;
		return set;
	}

	// Capture phase: ahead of the drawer's own delegated add handler.
	document.addEventListener(
		"click",
		function (e) {
			var button = e.target && e.target.closest ? e.target.closest(".fkcart-drawer-content .fkcart-add-variant-product") : null;
			if (!button) return;
			var content = button.closest(".fkcart-drawer-content");
			// A bundle is added by its widget; a bought-together set by its own button, so the drawer's button
			// buys what the shopper has ticked rather than the host alone.
			var target = content.querySelector(".wffn-bundles-fkcart-submit") || setButton(content);
			// No bundle in this quick view (a variable product, or a set that cannot be added yet): the drawer adds natively.
			if (!target) return;
			e.preventDefault();
			e.stopImmediatePropagation();
			target.click();
		},
		true
	);

	// A bundle reopened from its cart line: its add says which line it replaces, and the server swaps them.
	var skins = (window.wffnBundlesSkins = window.wffnBundlesSkins || {});
	skins.requestExtras = function () {
		// Only while the quick view is open: a closed one keeps its line key, and a page add would replace that line.
		var key = document.querySelector("#fkcart-modal .fkcart-quick-view-drawer.fkcart-show .fkcart-drawer-content input[name='fkcart-cart-key']");
		return key && key.value ? { replace: key.value } : null;
	};

	// The quick view draws its own copy of a bundle the page also shows; its gifts and upsells are the drawer's.
	skins.partsRoot = function (widget) {
		return widget.closest("#fkcart-modal .fkcart-drawer-content");
	};

	// A bundle reopened from its cart line opens on what the cart holds: the server prints the line's
	// choices (Adapter::edit_state), and this presses the same controls the shopper did.
	function slug(text) {
		return String(text).toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "");
	}

	function fire(el) {
		el.dispatchEvent(new Event("change", { bubbles: true }));
		if (window.jQuery) window.jQuery(el).trigger("change");
	}

	/** Pick the option of a variant dropdown that reads "Name:Value" for this value; true when it did. */
	function pickAxis(select, wanted) {
		for (var i = 0; i < select.options.length; i++) {
			var option = select.options[i];
			var cut = option.value.indexOf(":");
			if (cut > 0 && slug(option.value.slice(cut + 1)) === slug(wanted)) {
				if (select.value !== option.value) {
					select.value = option.value;
					fire(select);
				}
				return true;
			}
		}
		return false;
	}

	/** Whether a dropdown sits in the offer now chosen; every offer draws its own rows. */
	function inChosenOffer(select) {
		var offer = select.closest(".wffn-bundles-tier, .wffn-bundles-fb-card, .wffn-bundles-fb-single");
		var radio = offer && offer.querySelector(".wffn-bundles-tier-input, .wffn-bundles-fb-input");
		return !radio || radio.checked;
	}

	function restore(root, state) {
		var offer = String(state.offer || "");
		var inputs = root.querySelectorAll(".wffn-bundles-fb-input");
		var want = "fb-single" === offer ? "single" : "bundle";
		for (var i = 0; i < inputs.length; i++) {
			if (inputs[i].value === want && !inputs[i].checked) inputs[i].click();
		}
		var tier = offer ? root.querySelector(".wffn-bundles-tier-input[value='" + offer.replace(/'/g, "") + "']") : null;
		if (tier && !tier.checked) tier.click();
		// Mix & Match: press each product's add, then its stepper up to the quantity the cart holds.
		Object.keys(state.picks || {}).forEach(function (id) {
			var row = root.querySelector(".wffn-bundles-mm-item[data-product-id='" + id + "']");
			if (!row) return;
			var add = row.querySelector(".wffn-bundles-mm-add");
			if (add) add.click();
			var up = row.querySelector(".wffn-bundles-mm-step[data-act='add']");
			for (var n = 1; up && n < state.picks[id]; n++) up.click();
		});
		var checks = root.querySelectorAll(".wffn-bundles-upsell-check");
		for (var c = 0; c < checks.length; c++) {
			var item = checks[c].closest("[data-product-id]");
			// Only the add-ons the line holds; a state printed without the list ticks all or none.
			var want = state.addonIds && item ? state.addonIds.indexOf(parseInt(item.getAttribute("data-product-id"), 10)) !== -1 : !!state.addons;
			if (want !== checks[c].checked) checks[c].click();
		}
		// A switched offer redraws the widget's rows, so the dropdowns are read after it settles.
		window.setTimeout(function () {
			var selects = Array.prototype.filter.call(root.querySelectorAll("select.wffn-bundles-variant-axis"), inChosenOffer);
			var at = 0;
			(state.members || []).concat(state.gifts || []).forEach(function (variation) {
				Object.keys(variation).forEach(function (name) {
					for (var k = at; k < selects.length; k++) {
						if (pickAxis(selects[k], variation[name])) {
							at = k + 1;
							break;
						}
					}
				});
			});
			// The gifts the cart holds, for a gift the shopper chose from a list to show picked again.
			root.dispatchEvent(new CustomEvent("wffn-bundles:restore-gifts", { bubbles: true, detail: { chosen: state.chosen || [] } }));
		}, 250);
	}

	function applyEditState() {
		var drawer = document.querySelector("#fkcart-modal .fkcart-drawer-content");
		var holder = document.querySelector("#fkcart-modal script.wffn-bundles-edit-state");
		if (!drawer || !holder || holder.getAttribute("data-restored")) return;
		var root = drawer.querySelector(".wffn-bundles");
		// The widget wires itself up after it lands; wait until its controls exist.
		if (!root || !root.querySelector("input, select, .wffn-bundles-mm-add")) return;
		holder.setAttribute("data-restored", "1");
		var state;
		try {
			state = JSON.parse(holder.textContent || "{}");
		} catch (err) {
			return;
		}
		window.setTimeout(function () {
			restore(drawer, state);
		}, 150);
	}

	/**
	 * Wire a widget that arrived in the quick view after the page's behavior scripts had run.
	 *
	 * On a page with no bundle the fragment carries the scripts and they bind the widget as they run. On a bundle
	 * page (or any page that already loaded them) the scripts are not sent again and nothing else binds the new
	 * widget, so it would sell the bare product. Rescanning the drawer binds it, and any module the fragment added.
	 */
	function wireQuickView() {
		var drawer = document.querySelector("#fkcart-modal .fkcart-drawer-content");
		var skins = window.wffnBundlesSkins;
		if (!drawer || !skins || "function" !== typeof skins.rescan) return;
		var widgets = drawer.querySelectorAll(".wffn-bundles:not([data-wffn-qv-wired])");
		if (!widgets.length) return;
		for (var i = 0; i < widgets.length; i++) widgets[i].setAttribute("data-wffn-qv-wired", "1");
		// After the fragment's own scripts have run.
		window.setTimeout(function () {
			skins.rescan(drawer);
		}, 0);
	}

	new MutationObserver(function () {
		wireQuickView();
		applyEditState();
	}).observe(document.body, { childList: true, subtree: true });

	// A closed quick view keeps its bundle copy, whose gifts and upsells a page add would read as its own.
	// It loads afresh on every open, so the copy is dropped once the drawer has slid out.
	function dropClosedCopy() {
		var drawer = document.querySelector("#fkcart-modal .fkcart-quick-view-drawer");
		if (!drawer || drawer.classList.contains("fkcart-show")) return;
		Array.prototype.forEach.call(drawer.querySelectorAll(".fkcart-drawer-content .wffn-bundles"), function (widget) {
			widget.parentNode.removeChild(widget);
		});
	}
	new MutationObserver(function (records) {
		for (var i = 0; i < records.length; i++) {
			var el = records[i].target;
			if (el.classList && el.classList.contains("fkcart-quick-view-drawer") && !el.classList.contains("fkcart-show")) {
				window.setTimeout(dropClosedCopy, 600);
				return;
			}
		}
	}).observe(document.body, { attributes: true, attributeFilter: ["class"], subtree: true });
	// Loaded by a quick view that is already open, so its widget arrived before the observer.
	wireQuickView();
	applyEditState();

	if (window.jQuery) {
		// The drawer keeps a saved copy it repaints from on every page load; a bundle's own add never updates it.
		window.jQuery(document.body).on("wffn_bundles_cart_updated", function (e, fragments) {
			var cfg = window.fkcart_app_data;
			// The add already carries the drawer's fragments, so save those rather than fetch them again.
			if (fragments && fragments[".fkcart-modal-container"] && cfg && cfg.fragment_name) {
				try {
					window.sessionStorage.setItem(cfg.fragment_name, JSON.stringify(fragments));
					return;
				} catch (err) {
					// Storage blocked: fall back to the drawer's own refresh.
				}
			}
			window.jQuery(document.body).trigger("fkcart_update_side_cart", [false]);
		});
		window.jQuery(document.body).on("added_to_cart", function () {
			if (!document.querySelector("#fkcart-modal .fkcart-drawer-content .wffn-bundles")) return;
			var close = document.querySelector("#fkcart-modal .fkcart-quick-view-close");
			if (close) close.click();
		});
	}
})();
