/**
 * Skins behavior — Frequently Bought Together.
 *
 * The set has its own button and its own total, so the theme's add-to-cart
 * button and price are never touched. The host's quantity is read off the
 * theme's form; the host and each ticked companion carry their own picks.
 */
(function () {
	"use strict";

	var F = window.wffnBundlesSkins;
	if (!F) return;

	var OFFER = "fbt-set";

	// The set's ticked lines as an add posts them, for a host that owns the submit; `ready` drops unpicked ones.
	F.fbtSelection = function (widget, ready) {
		return widget._wffnBundlesFbtSelection ? widget._wffnBundlesFbtSelection(false, ready) : null;
	};

	function text(key, fallback) {
		var i18n = (window.wffnBundlesCart && window.wffnBundlesCart.i18n) || {};
		return i18n[key] || fallback;
	}

	function setup(widget) {
		var signal = F.bind(widget, "fbt");
		if (!signal) return;

		var bundle = widget.getAttribute("data-wffn-bundles-bundle") || "";
		var product = widget.getAttribute("data-wffn-bundles-product") || "";
		var button = widget.querySelector(".wffn-bundles-fbt-add");
		var summary = widget.querySelector(".wffn-bundles-fbt-summary");
		var form = F.cartForm(widget);

		function companions() {
			var out = [];
			var rows = widget.querySelectorAll(".wffn-bundles-fbt-item[data-wffn-bundles-key]");
			for (var i = 0; i < rows.length; i++) {
				if (rows[i].getAttribute("data-wffn-bundles-key") !== "host") out.push(rows[i]);
			}
			return out;
		}

		function isOn(row) {
			var box = row.querySelector(".wffn-bundles-fbt-check");
			return !!(box && box.checked);
		}

		function nameOf(row) {
			var spans = row.querySelectorAll(".wffn-bundles-fbt-name > span");
			return spans.length ? spans[spans.length - 1].textContent : "";
		}

		// The strip thumbnail follows its row's tick.
		function sync(row) {
			var on = isOn(row);
			row.classList.toggle("is-off", !on);
			var strip = widget.querySelector('.wffn-bundles-fbt-strip-item[data-wffn-bundles-key="' + row.getAttribute("data-wffn-bundles-key") + '"]');
			if (strip) strip.classList.toggle("is-off", !on);
		}

		// The row and strip thumbnails show the picked variation's image, or the product's own until one is picked.
		function showVariantImage(row) {
			var src = F.variantImage(row.querySelector(".wffn-bundles-variant"));
			F.swapThumb(row, ".wffn-bundles-fbt-thumb img", src);
			var strip = widget.querySelector('.wffn-bundles-fbt-strip-item[data-wffn-bundles-key="' + row.getAttribute("data-wffn-bundles-key") + '"]');
			F.swapThumb(strip, ".wffn-bundles-fbt-thumb img", src);
		}

		// The host's variation comes from its own row, never the theme's form; its quantity does.
		var hostRow = widget.querySelector('.wffn-bundles-fbt-item[data-wffn-bundles-key="host"]');
		var hostBox = hostRow ? hostRow.querySelector(".wffn-bundles-fbt-check") : null;
		// Untickable only where this box adds on its own.
		if (hostBox && button) hostBox.disabled = false;
		function hostOn() {
			return !hostBox || hostBox.checked;
		}
		function host() {
			var qty = form ? form.querySelector('input[name="quantity"]') : null;
			return {
				variation: "",
				attributes: hostRow ? F.readAttributes(hostRow) : {},
				quantity: qty ? parseInt(qty.value, 10) || 1 : 1,
				ready: !hostRow || !hostOn() || F.axesComplete(hostRow),
			};
		}

		// `all` keeps unticked rows too, flagged, so the quote prices every row.
		function selection(all, ready) {
			var h = host();
			var items = [];
			companions().forEach(function (row) {
				var on = isOn(row);
				if (!all && !on) return;
				if (ready && !F.axesComplete(row)) return;
				items.push({ id: row.getAttribute("data-wffn-bundles-key"), attributes: F.readAttributes(row), on: on });
			});
			return { product: product, variation: h.variation, hostAttributes: h.attributes, hostOn: hostOn(), quantity: h.quantity, items: items };
		}
		widget._wffnBundlesFbtSelection = selection;

		function blocker() {
			if (!host().ready) return hostRow ? text("fbtChooseItem", "Choose options for %s first.").replace("%s", nameOf(hostRow)) : text("fbtChooseHost", "Choose this product's options first.");
			var rows = companions();
			var any = hostOn();
			for (var i = 0; i < rows.length; i++) {
				if (isOn(rows[i]) && !F.axesComplete(rows[i])) {
					return text("fbtChooseItem", "Choose options for %s first.").replace("%s", nameOf(rows[i]));
				}
				any = any || isOn(rows[i]);
			}
			return any ? "" : text("fbtChooseAny", "Choose at least one item.");
		}

		// Said before the click, not after: the button dims and names what is missing.
		var hint = widget.querySelector(".wffn-bundles-fbt-hint");
		// Without a button of its own, the host form's add button carries the set's gate.
		var gate = !button && widget.closest("form.cart") ? F.addButton(form) : null;
		function refreshBlock() {
			var reason = blocker();
			if (gate) gate.disabled = !!reason;
			if (button) button.setAttribute("aria-disabled", reason ? "true" : "false");
			if (hint) {
				hint.textContent = reason;
				hint.hidden = !reason;
			}
		}

		function clearMessages() {
			F.clearError(widget);
			F.clearNotice(widget);
			F.clearSuccess(widget);
		}

		var seq = 0;
		function requote() {
			var mine = ++seq;
			if (summary) summary.classList.add("wffn-bundles-fbt-pending");
			F.quote({ bundle: bundle, type: F.TYPE.FREQUENTLY_BOUGHT_TOGETHER, offer: OFFER, selection: selection(true) })
				.then(function (data) {
					if (mine !== seq) return;
					if (summary) summary.classList.remove("wffn-bundles-fbt-pending");
					var q = data && data.fbt;
					if (!q) return;
					(q.rows || []).forEach(function (r) {
						var row = widget.querySelector('.wffn-bundles-fbt-item[data-wffn-bundles-key="' + r.key + '"]');
						var prices = row && row.querySelector(".wffn-bundles-fbt-prices");
						if (prices) prices.innerHTML = r.html;
					});
					var total = widget.querySelector(".wffn-bundles-fbt-sum-price");
					if (total && q.totalHtml) total.innerHTML = q.totalHtml;
					var note = widget.querySelector(".wffn-bundles-fbt-sum-note");
					if (note) note.innerHTML = q.noteHtml || "";
					if (button && q.buttonText) button.textContent = q.buttonText;
					refreshBlock();
					F.bus.dispatchEvent(new CustomEvent("wffn-bundles:price-quoted", { detail: { widget: widget, data: data } }));
				})
				.catch(function () {
					if (mine === seq && summary) summary.classList.remove("wffn-bundles-fbt-pending");
				});
		}

		widget.addEventListener("change", function (e) {
			var t = e.target;
			if (!t || !t.classList) return;
			if (t.classList.contains("wffn-bundles-fbt-check")) {
				var row = t.closest(".wffn-bundles-fbt-item");
				if (row) sync(row);
				clearMessages();
				requote();
			} else if (t.classList.contains("wffn-bundles-variant-axis")) {
				var picked = t.closest(".wffn-bundles-fbt-item");
				if (picked) showVariantImage(picked);
				F.clearNotice(widget);
				requote();
			}
		}, { signal: signal });

		if (form) {
			form.addEventListener("change", function (e) {
				if (e.target && "quantity" === e.target.name) requote();
			}, { signal: signal });
		}

		if (button) {
			button.addEventListener("click", function (e) {
				e.preventDefault();
				clearMessages();
				var reason = blocker();
				if (reason) {
					F.showNotice(widget, reason);
					return;
				}
				button.disabled = true;
				button.classList.add("loading");
				var done = function () {
					button.disabled = false;
					button.classList.remove("loading");
				};
				F.addBundle({ bundle: bundle, type: F.TYPE.FREQUENTLY_BOUGHT_TOGETHER, offer: OFFER, selection: selection(false) })
					.then(function (j) {
						done();
						if (j && j.success) {
							if (!F.showCartNotices(widget, j.data && j.data.notices)) F.showSuccess(widget, text("fbtAdded", "Added to your cart."));
							for (var a = 0; a < F.addedHandlers.length; a++) F.addedHandlers[a](j.data || {}, widget);
							return;
						}
						if (j && j.success === false) {
							var err = F.errorPayload(j.data);
							F.showError(widget, err.code, err.text);
						}
					})
					.catch(function () {
						done();
						F.showError(widget, "add_failed");
					});
			}, { signal: signal });
		}

		// An express button reads `form.cart` instead of clicking anything, so it is
		// handed the set this button would add. Not where a bundle widget owns that
		// form, nor with no companion ticked: the product is then bought on its own.
		if (form && button) {
			form.addEventListener("formdata", function (e) {
				if (form.querySelector('input[name="wffn_bundles_bundle_id"]')) return;
				var picked = selection(false);
				if (!picked.items.length || !picked.hostOn) return;
				if (blocker()) {
					e.formData.set("wffn_bundles_payload", JSON.stringify({ ready: false }));
					return;
				}
				var cfg = window.wffnBundlesCart || {};
				e.formData.set("wffn_bundles_payload", JSON.stringify({
					bundle: bundle,
					type: F.TYPE.FREQUENTLY_BOUGHT_TOGETHER,
					offer: OFFER,
					selection: picked,
					nonce: cfg.nonce || "",
					currency: cfg.currency || "",
				}));
			}, { signal: signal });
		}

		// The theme's variation picker and the host row's pick the same product:
		// each follows the other, so either one readies the set and the theme's buttons.
		function slugsOf(sel, prefixed) {
			var out = [];
			for (var i = 0; i < sel.options.length; i++) {
				var v = sel.options[i].value;
				if (v) out.push(prefixed ? v.slice(v.indexOf(":") + 1) : v);
			}
			return out.sort().join("|");
		}
		function setValue(sel, value) {
			if (sel.value === value) return;
			sel.value = value;
			sel.dispatchEvent(new Event("change", { bubbles: true }));
		}
		var pairs = [];
		if (form && hostRow) {
			var natives = form.querySelectorAll('select[name^="attribute_"]');
			F.axesIn(hostRow).forEach(function (axis) {
				var key = slugsOf(axis, true);
				for (var i = 0; i < natives.length; i++) {
					if (slugsOf(natives[i], false) === key) {
						pairs.push({ axis: axis, native: natives[i] });
						return;
					}
				}
			});
		}
		pairs.forEach(function (p) {
			var prefix = function (slug) {
				for (var i = 0; i < p.axis.options.length; i++) {
					var v = p.axis.options[i].value;
					if (v && v.slice(v.indexOf(":") + 1) === slug) return v;
				}
				return "";
			};
			p.axis.addEventListener("change", function () {
				setValue(p.native, p.axis.value ? p.axis.value.slice(p.axis.value.indexOf(":") + 1) : "");
			}, { signal: signal });
			p.native.addEventListener("change", function () {
				setValue(p.axis, prefix(p.native.value));
			}, { signal: signal });
			if (p.native.value && !p.axis.value) {
				setValue(p.axis, prefix(p.native.value));
			} else if (p.axis.value && !p.native.value) {
				setValue(p.native, p.axis.value.slice(p.axis.value.indexOf(":") + 1));
			}
		});

		companions().forEach(sync);
		refreshBlock();
		// A variable row is stamped as a range, though a companion's picker opens on a variation.
		var variable = false;
		widget.querySelectorAll(".wffn-bundles-fbt-item").forEach(function (row) {
			if (F.axesIn(row).length) variable = true;
		});
		if (variable) requote();
	}

	F.register(function (root) {
		F.each(root, ".wffn-bundles-fbt", setup);
	});
})();
