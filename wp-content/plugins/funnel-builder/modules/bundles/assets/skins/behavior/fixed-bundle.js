/**
 * Skins behavior — Fixed Bundle option toggle.
 *
 * Switches between the single-product option and the bundle: moves the selected
 * modifiers, syncs the radios, and publishes the pick.
 */
(function () {
	"use strict";

	var F = window.wffnBundlesSkins;

	function setup(widget) {
		var signal = F.bind(widget, "fb");
		if (!signal) return;

		// Out-of-stock pack: a member cannot supply its quantity, so the all-or-nothing
		// bundle cannot be added; the single-product option stays buyable.
		var oos = widget.classList.contains("wffn-bundles-fb--oos");
		function bundleActive() {
			var s = widget.querySelector(".wffn-bundles-fb-single");
			return !(s && s.classList.contains("wffn-bundles-fb-single--selected"));
		}
		// Every axis in the active option must be chosen: WooCommerce only matches a
		// variation when all axes are set. F.axesComplete scopes each row to its own.
		function axesComplete() {
			var single = widget.querySelector(".wffn-bundles-fb-single");
			var rows = (single && single.classList.contains("wffn-bundles-fb-single--selected"))
				? [single]
				: widget.querySelectorAll(".wffn-bundles-fb-member, .wffn-bundles-fb-flat-row");
			for (var i = 0; i < rows.length; i++) {
				if (!F.axesComplete(rows[i])) return false;
			}
			return true;
		}
		// Gate the native Add button on the out-of-stock state and a complete variation
		// selection. Only undo the disable we applied, tracked by `_wffnBundlesFbDisabled`.
		function syncAdd() {
			var needsVariation = !axesComplete();
			var block = ( oos && bundleActive() ) || needsVariation;
			// The server prints the out-of-stock notice; it only applies while the pack is the pick.
			var oosNotice = widget.querySelector(".wffn-bundles-fb-oos");
			if (oosNotice) oosNotice.hidden = !( oos && bundleActive() );
			// Every button that posts this product, so a theme's sticky add-to-cart bar
			// can't stay live as an ungated route past the gate (OceanWP ships one).
			F.cartForms(widget).forEach(function (form) {
				var btn = F.addButton(form);
				if (!btn) return;
				// Releases the pre-paint fade (Product_Display::emit_paint_guard). Set
				// before the branch: from here the button's real state is the one shown.
				btn.classList.add("wffn-bundles-gate-ready");
				if (block) {
					btn.disabled = true;
					btn.classList.add("wffn-bundles-add-disabled");
					// `disabled` in lockstep with the property: themes style the class, not [disabled].
					btn.classList.add("disabled");
					btn.classList.toggle("wc-variation-selection-needed", needsVariation);
					btn.setAttribute("aria-disabled", "true");
					btn._wffnBundlesFbDisabled = true;
				} else if (btn._wffnBundlesFbDisabled || btn.classList.contains("wc-variation-selection-needed")) {
					// The marker test also clears WooCommerce's own variation gate, which the
					// bundle never satisfies; a theme's own disable carries neither marker.
					btn.disabled = false;
					btn.classList.remove("wffn-bundles-add-disabled");
					btn.classList.remove("wc-variation-selection-needed");
					btn.classList.remove("disabled");
					btn.removeAttribute("aria-disabled");
					btn._wffnBundlesFbDisabled = false;
				}
			});
			// The hint answers a failed attempt (see attemptHint), so it is only cleared
			// here, once the missing picks are complete.
			if (!needsVariation) F.clearNotice(widget);
		}

		/**
		 * Explain the dead button, but only once the shopper has actually tried to
		 * use it.
		 *
		 * A disabled button dispatches no click, so `pointer-events: none` lets the
		 * pointer fall through to form.cart. What it falls through to varies by
		 * theme, so confirm by geometry rather than by target.
		 */
		function attemptHint(e) {
			// `this` is the form the listener was attached to, so a sticky bar's dead
			// button explains itself with the same notice as the main one.
			var form = this;
			if (!form || widget.contains(e.target)) return;
			var btn = F.addButton(form);
			if (!btn || axesComplete()) return;
			var r = btn.getBoundingClientRect();
			if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) return;
			var i18n = (window.wffnBundlesCart && window.wffnBundlesCart.i18n) || {};
			F.showNotice(widget, i18n.selectOptionsFirst || "Select options first");
		}
		/** Show each row's picked variation image now; the live quote confirms it later. */
		function syncImages() {
			var rows = widget.querySelectorAll(".wffn-bundles-fb-member, .wffn-bundles-fb-flat-row, .wffn-bundles-fb-single");
			for (var i = 0; i < rows.length; i++) {
				var wrap = rows[i].querySelector(".wffn-bundles-variant");
				if (wrap) F.swapThumb(rows[i], ".wffn-bundles-fb-thumb img", F.variantImage(wrap));
			}
		}
		// Any member/anchor variation pick re-evaluates the gate.
		widget.addEventListener("change", function (e) {
			if (e.target && e.target.classList && e.target.classList.contains("wffn-bundles-variant-axis")) {
				syncAdd();
				syncImages();
			}
		}, { signal: signal });

		// The single↔bundle toggle only exists when a single-product option is
		// offered; flat-mode bundles have no choice (always the whole bundle).
		var hasToggle = !!widget.querySelector(".wffn-bundles-fb-single");
		if (hasToggle) {
			widget.addEventListener("click", function (e) {
				if (F.passClick(e.target)) return; // let the control work
				var option = e.target.closest(".wffn-bundles-fb-single, .wffn-bundles-fb-card");
				if (!option || !widget.contains(option)) return;
				var isSingle = option.classList.contains("wffn-bundles-fb-single");
				// The option is a <label> around a radio, so the browser forwards a second
				// click to the input; re-picking the current option is a no-op for that.
				var singleEl = widget.querySelector(".wffn-bundles-fb-single");
				if (singleEl.classList.contains("wffn-bundles-fb-single--selected") === isSingle) return;

				singleEl.classList.toggle("wffn-bundles-fb-single--selected", isSingle);
				var card = widget.querySelector(".wffn-bundles-fb-card");
				if (card) card.classList.toggle("wffn-bundles-fb-card--selected", !isSingle);

				var inputs = widget.querySelectorAll(".wffn-bundles-fb-input");
				for (var i = 0; i < inputs.length; i++) {
					inputs[i].checked = inputs[i].value === (isSingle ? "single" : "bundle");
				}

				widget.dispatchEvent(new CustomEvent("wffn-bundles:fb-change", {
					bubbles: true,
					composed: true,
					detail: { selected: isSingle ? "single" : "bundle", widget: widget },
				}));
				F.publishOffer({ lockAll: isSingle, widget: widget });
				// The bundle pick is un-addable when out of stock; the single pick is not.
				syncAdd();
			}, { signal: signal });
		}

		// Seed subscribers from the initial pick (flat mode = bundle).
		var single = widget.querySelector(".wffn-bundles-fb-single");
		F.publishOffer({ lockAll: !!(single && single.classList.contains("wffn-bundles-fb-single--selected")), widget: widget });
		syncAdd(); // Seed the Add button state from the initial pick.
		syncImages();
		F.preloadVariantImages(widget);
		// …and re-seed it behind WooCommerce's own variation gate, which fires after
		// this on a variable product and would leave the button painted as dead.
		F.holdGate(widget, signal, syncAdd);

		// The bundle adds its members via AJAX; the single pick adds just the anchor
		// (F.addSingle), so both update the cart in place from the same selection.
		function collect() {
			var s = widget.querySelector(".wffn-bundles-fb-single");
			if (s && s.classList.contains("wffn-bundles-fb-single--selected")) {
				// Read the anchor straight off the native form to cover simple (button
				// value) and variable (hidden input) products.
				var form = F.cartForm(widget);
				var pidEl = form && form.querySelector('[name="add-to-cart"], [name="product_id"]');
				var qtyEl = form && form.querySelector('[name="quantity"]');
				var pid = pidEl ? parseInt(pidEl.value, 10) : 0;
				if (!pid) return null; // Can't resolve the product → fall back to native add.
				return {
					single: true,
					type: F.TYPE.FIXED_BUNDLES,
					product: pid,
					quantity: qtyEl ? (parseInt(qtyEl.value, 10) || 1) : 1,
					attributes: F.readAttributes(s),
				};
			}
			// Each member's variation pick, in the bundle's configured order.
			var members = widget.querySelectorAll(".wffn-bundles-fb-member, .wffn-bundles-fb-flat-row");
			var items = [];
			for (var i = 0; i < members.length; i++) {
				items.push({ attributes: F.readAttributes(members[i]) });
			}
			// The page (anchor) product id lets the server key the container's cart
			// thumbnail to this page's lead image. Display-only, never trusted for pricing.
			var form = F.cartForm(widget);
			var addBtn = form && form.querySelector('[name="add-to-cart"]');
			var anchor = (form && form.getAttribute("data-product_id")) || (addBtn && addBtn.value) || "";
			// ready = every member's variation is chosen and the pack is in stock; an
			// incomplete/out-of-stock bundle isn't addable.
			var ready = axesComplete() && !oos;
			return { type: F.TYPE.FIXED_BUNDLES, offer: "", selection: { items: items, product: anchor }, ready: ready };
		}
		/**
		 * The card's own total follows the picks: the page rendered it as a min-max range while a
		 * variation was open, and it collapses to the exact price once every one is chosen (and
		 * returns to the range if a pick is cleared). The theme price already does; only the
		 * member lines used to.
		 */
		function stampTotal(data) {
			var boxes = widget.querySelectorAll(".wffn-bundles-fb-lead-top > .wffn-bundles-fb-prices, .wffn-bundles-fb-flat-head > .wffn-bundles-fb-prices");
			// The card prices the bundle alone; a ticked upsell is charged as its own line.
			var html = data.bundleHtml != null ? data.bundleHtml : data.html;
			var isRange = data.bundleHtml != null ? html.indexOf("wffn-bundles-price-dash") !== -1 : data.isRange;
			for (var b = 0; b < boxes.length; b++) {
				var box = boxes[b];
				if (box._wffnBundlesFbInit == null) box._wffnBundlesFbInit = box.innerHTML;
				if (isRange || !html) {
					box.innerHTML = box._wffnBundlesFbInit;
					continue;
				}
				var tmp = document.createElement("div");
				tmp.innerHTML = html;
				var ins = tmp.querySelector("ins");
				var del = tmp.querySelector("del");
				var live = tmp.querySelector(".wffn-bundles-live-price") || tmp;
				var fin = box.querySelector(".wffn-bundles-fb-price-final");
				var orig = box.querySelector(".wffn-bundles-fb-price-original");
				if (fin) fin.innerHTML = ins ? ins.innerHTML : live.innerHTML;
				if (del) {
					if (!orig && fin) {
						orig = document.createElement("span");
						orig.className = "wffn-bundles-fb-price-original";
						box.insertBefore(orig, fin);
					}
					if (orig) orig.innerHTML = del.innerHTML;
				} else if (orig) {
					orig.parentNode.removeChild(orig);
				}
			}
		}

		F.cartForms(widget).forEach(function (cartForm) {
			cartForm.addEventListener("click", attemptHint, { signal: signal });
		});
		F.interceptAddToCart(widget, signal, collect);
		// Re-stamp each member's price line from the quote so picking a variation
		// collapses that member's range to its exact price (and the total follows).
		F.liveQuote(widget, signal, collect, {
			onData: function (data) {
				if (!data || !data.members) return;
				// The single pick quotes one row — its own card — so it re-prices from
				// members[0] rather than the bundle's member lines.
				var single = widget.querySelector(".wffn-bundles-fb-single");
				if (single && single.classList.contains("wffn-bundles-fb-single--selected")) {
					var one = single.querySelector(".wffn-bundles-fb-price-final");
					if (one && data.members[0] && data.members[0].final != null) {
						one.innerHTML = data.members[0].final;
					}
					if (data.members[0]) F.swapThumb(single, ".wffn-bundles-fb-thumb img", data.members[0].image);
					return;
				}
				var rows = widget.querySelectorAll(".wffn-bundles-fb-member, .wffn-bundles-fb-flat-row");
				for (var i = 0; i < rows.length && i < data.members.length; i++) {
					var m = data.members[i];
					var fin = rows[i].querySelector(".wffn-bundles-fb-price-final");
					if (fin && m.final != null) fin.innerHTML = m.final;
					var orig = rows[i].querySelector(".wffn-bundles-fb-price-original");
					if (orig && m.original != null) orig.innerHTML = m.original;
					F.swapThumb(rows[i], ".wffn-bundles-fb-thumb img", m.image);
				}
				stampTotal(data);
			},
		});
	}

	F.register(function (root) {
		F.each(root, ".wffn-bundles-fb", setup);
	});
})();
