/**
 * Skins behavior core: the `window.wffnBundlesSkins` namespace, DOM helpers, the module
 * registry, and a standalone pub/sub bus. Loaded before the skin modules.
 */
(function () {
	"use strict";

	var F = (window.wffnBundlesSkins = window.wffnBundlesSkins || {});
	F._mods = F._mods || [];

	/** Standalone pub/sub bus — decouples widgets across shadow-DOM boundaries. */
	F.bus = F.bus || new EventTarget();

	/** Bundle type ids posted with every quote/add. Mirrors Bundle_Store::TYPE_*. */
	F.TYPE = {
		FIXED_BUNDLES: 2,
		FREQUENTLY_BOUGHT_TOGETHER: 4,
	};

	/** The FunnelKit Cart drawer's quick view, where a widget renders for an upsell, not the page's product. */
	var DRAWER_SELECTOR = ".fkcart-drawer-content";

	/** Register a per-skin initializer: fn(root) wires its widgets under root. */
	F.register = function (fn) {
		F._mods.push(fn);
	};

	/** Run every registered initializer over a root (document or shadow root). */
	F.attachBehavior = function (root) {
		var scope = root || document;
		// Must run before any module binds: interceptAddToCart caches its form list.
		resolveStandalone(scope);
		applyBodyFlags(scope);
		for (var i = 0; i < F._mods.length; i++) {
			F._mods[i](scope);
		}
	};

	/**
	 * Mirror the widget's server-stamped flags onto <body>.
	 *
	 * Product_Display::body_class() only runs on a single-product page, so a
	 * builder-rendered bundle leaves every `body.wffn-bundles-has-bundle` rule inert.
	 *
	 * @param {Document|ShadowRoot|Element} scope Root being wired.
	 */
	function applyBodyFlags(scope) {
		var root = scope || document;
		// The admin preview wires a shadow root; its flags must not restyle wp-admin.
		if (root.host || (root.ownerDocument && root.ownerDocument !== document)) {
			return;
		}
		var host = document.querySelector("[data-wffn-bundles-flags]");
		if (!host || !document.body) {
			return;
		}
		// A widget in the cart drawer's quick view must not restyle the page behind it.
		if (host.closest && host.closest(DRAWER_SELECTOR)) {
			return;
		}
		var flags = String(host.getAttribute("data-wffn-bundles-flags") || "").split(/\s+/);
		for (var i = 0; i < flags.length; i++) {
			if (flags[i]) {
				document.body.classList.add(flags[i]);
			}
		}
	}

	/**
	 * Tear down every widget bound under `root`: abort its AbortController and
	 * clear the bound markers so a later re-attach rebinds cleanly.
	 */
	F.detachBehavior = function (root) {
		var scope = root || document;
		var els = scope.querySelectorAll("[data-wffn-bundles-bound]");
		for (var i = 0; i < els.length; i++) {
			var el = els[i];
			if (el._wffnBundlesAC) {
				el._wffnBundlesAC.abort();
				el._wffnBundlesAC = null;
			}
			el.removeAttribute("data-wffn-bundles-bound");
			el._wffnBundlesBound = null;
		}
	};

	// Back-compat re-scan hook for AJAX-loaded product pages / page builders.
	F.init = F.attachBehavior;

	/**
	 * The re-init contract for page builders: detach, then attach.
	 *
	 * Not `attachBehavior` alone: a reused node still carries `data-wffn-bundles-bound`
	 * and would be skipped while its AbortController is already aborted.
	 */
	F.rescan = function (root) {
		var scope = root && root.nodeType ? root : root && root[0] && root[0].nodeType ? root[0] : null;
		if (!scope) return;
		F.detachBehavior(scope);
		F.attachBehavior(scope);
	};

	/**
	 * The widget's AbortController, created on first bind. Listeners pass
	 * `{ signal: F.controller(el).signal }` so `detachBehavior` can remove them
	 * all with a single `abort()`. Returns null if it was already torn down.
	 */
	F.controller = function (el) {
		if (!el._wffnBundlesAC) {
			el._wffnBundlesAC = new AbortController();
			el.setAttribute("data-wffn-bundles-bound", "1");
		}
		return el._wffnBundlesAC;
	};

	/**
	 * Claim a widget for a concern, returning its AbortSignal to scope listeners
	 * to — or null if it was already bound for that `key`. `key` namespaces the
	 * concern so several modules can wire the same element.
	 */
	F.bind = function (el, key) {
		var bound = el._wffnBundlesBound || (el._wffnBundlesBound = {});
		if (bound[key]) return null;
		bound[key] = 1;
		return F.controller(el).signal;
	};

	/** Parse an integer attribute, defaulting to 0. */
	F.num = function (v) {
		var n = parseInt(String(v == null ? "" : v), 10);
		return isFinite(n) ? n : 0;
	};

	/** Run fn on each element matching selector under root. */
	F.each = function (root, selector, fn) {
		var els = (root || document).querySelectorAll(selector);
		for (var i = 0; i < els.length; i++) {
			fn(els[i]);
		}
	};

	/**
	 * Publish the active-offer change on the bus: `detail.activeId` is the picked
	 * offer, `detail.lockAll` (bool) says no offer is being bought. The last
	 * detail is cached so a late subscriber can seed from it (see `onOffer`).
	 */
	F._offerSync = F._offerSync || null;
	F.publishOffer = function (detail) {
		F._offerSync = detail || {};
		F.bus.dispatchEvent(new CustomEvent("wffn-bundles:offer-sync", { detail: F._offerSync }));
	};

	/**
	 * Subscribe to active-offer broadcasts, scoped to `signal` so it is removed on
	 * teardown. Replays the last broadcast so a late mount still seeds its state.
	 */
	F.onOffer = function (signal, fn) {
		F.bus.addEventListener("wffn-bundles:offer-sync", function (e) { fn(e.detail); }, { signal: signal });
		if (F._offerSync) fn(F._offerSync);
	};

	// Clicks on these inside an offer row belong to the control, not the row.
	F.passSelectors = F.passSelectors || [".wffn-bundles-variant", ".wffn-bundles-portal"];
	F.passClick = function (target) {
		return !!(target && target.closest && target.closest(F.passSelectors.join(", ")));
	};

	/* Multi-product bundles cannot ride the single-product native form, so their
	   selection posts to `wffn_bundles_add_bundle`, which adds and prices the whole group. */

	/**
	 * The variation axes that belong to `el` itself.
	 *
	 * Skips axes inside a modal nested under `el` (a modal host can sit inside
	 * an offer row), which would otherwise read as the row's own.
	 */
	F.axesIn = function (el) {
		var out = [];
		if (!el) return out;
		var nodes = el.querySelectorAll(".wffn-bundles-variant-axis");
		for (var i = 0; i < nodes.length; i++) {
			var modal = nodes[i].closest(".wffn-bundles-portal");
			if (modal && modal !== el && el.contains(modal)) continue;
			out.push(nodes[i]);
		}
		return out;
	};

	/** Whether every axis belonging to `el` has a real (non-placeholder) pick. */
	F.axesComplete = function (el) {
		var axes = F.axesIn(el);
		for (var i = 0; i < axes.length; i++) {
			if (!axes[i].value) return false;
		}
		return true;
	};

	/** The attribute a dropdown chooses; option values are authored "Attribute:Option". */
	F.axisName = function (sel) {
		for (var i = 0; i < sel.options.length; i++) {
			var cut = sel.options[i].value.indexOf(":");
			if (cut > 0) return sel.options[i].value.slice(0, cut);
		}
		return "";
	};

	/** Read a pool/unit row's variant dropdowns into a { Name: Value } map. */
	F.readAttributes = function (el) {
		var out = {};
		var sels = F.axesIn(el);
		for (var i = 0; i < sels.length; i++) {
			var v = sels[i].value || "";
			var k = v.indexOf(":");
			if (k > 0) out[v.slice(0, k)] = v.slice(k + 1);
		}
		return out;
	};

	/* Never `document.querySelector("form.cart")`: a theme with a sticky or floating
	   add-to-cart bar has a second cart form, and the first match is the wrong one. */

	/** The product id a cart form posts, "" when it declares none. */
	function formProductId(form) {
		var v = form.getAttribute("data-product_id"); // variations_form
		if (v) return String(v);
		var f = form.querySelector('[name="add-to-cart"], [name="product_id"]');
		return f ? String(f.value || "") : "";
	}

	/**
	 * Resolve the standalone `form.cart` a builder widget emits when the template
	 * has no add-to-cart widget of its own: unwrap it when another form already
	 * posts the same product (matched on `data-wffn-bundles-standalone`), else mark it
	 * resolved. Unwrapping moves out every child not marked
	 * `data-wffn-bundles-standalone-field`, since the wrapper holds three sibling roots.
	 */
	function resolveStandalone(root) {
		var scope = root && root.querySelectorAll ? root : document;
		var forms = scope.querySelectorAll("form[data-wffn-bundles-standalone]");
		for (var i = 0; i < forms.length; i++) {
			var form = forms[i];
			var pid = String(form.getAttribute("data-wffn-bundles-standalone") || "");
			var host = null;
			var all = document.querySelectorAll("form.cart");
			for (var j = 0; j < all.length && !host; j++) {
				// Another standalone wrapper is not a host: both would unwrap and the
				// product would end up with no form at all.
				if (all[j] === form || all[j].hasAttribute("data-wffn-bundles-standalone")) continue;
				if (pid && formProductId(all[j]) === pid) host = all[j];
			}
			if (!host) {
				// Required: the submit stays hidden until this attribute lands.
				form.setAttribute("data-wffn-bundles-standalone-resolved", "1");
				continue;
			}
			var parent = form.parentNode;
			if (!parent) continue;
			var kids = [];
			for (var k = 0; k < form.childNodes.length; k++) kids.push(form.childNodes[k]);
			for (var m = 0; m < kids.length; m++) {
				var node = kids[m];
				if (node.nodeType === 1 && node.hasAttribute("data-wffn-bundles-standalone-field")) continue;
				parent.insertBefore(node, form);
			}
			parent.removeChild(form);
		}
	}

	/**
	 * The product's own add-to-cart form.
	 *
	 * `Cart::form_fields()` prints `input[name="wffn_bundles_bundle_id"]` only inside the
	 * real form, so that field identifies it; falls back to the old behaviour.
	 */
	F.cartForm = function (widget) {
		var own = widget && widget.closest ? widget.closest("form.cart") : null;
		if (own) return own;
		// An embed whose host owns the add; any page form found below would be another product's.
		if (widget && widget.closest && widget.closest("[data-wffn-bundles-formless]")) return null;
		// A widget in the drawer's quick view belongs to the drawer's form, not to a form of the page behind
		// it: the page may carry its own widget, or sell the same product.
		var drawer = widget && widget.closest ? widget.closest(DRAWER_SELECTOR) : null;
		if (drawer) return drawer.querySelector("form.cart");
		// A product-switcher row is a hazard for the fallbacks below: Aero owns the row's
		// add/switch path, not a real form.cart, so the page-wide fallback below
		// would hand one row another row's form and cross-wire their prices and
		// hidden fields. Scoped to the row first so a form genuinely inside it (e.g.
		// the quick-view modal's variations_form) is still found on its own merits.
		var row = widget && widget.closest ? widget.closest(".wfacp_product_row") : null;
		if (row) return row.querySelector("form.cart");
		var marker = document.querySelector('form.cart input[name="wffn_bundles_bundle_id"]');
		if (marker) {
			var marked = marker.closest("form.cart");
			if (marked) return marked;
		}
		return document.querySelector("form.cart");
	};

	/**
	 * Every cart form on the page that posts this product.
	 *
	 * A theme's sticky bar has its own handler and endpoint, so gating only the
	 * real button leaves it as an ungated route. Always contains cartForm() first.
	 */
	F.cartForms = function (widget) {
		var own = F.cartForm(widget);
		if (!own) return [];
		var pid = formProductId(own);
		var out = [own];
		var all = document.querySelectorAll("form.cart");
		var inDrawer = !!(widget && widget.closest && widget.closest(DRAWER_SELECTOR));
		for (var i = 0; i < all.length; i++) {
			if (all[i] === own || !pid || formProductId(all[i]) !== pid) continue;
			// The drawer's forms and the page's are separate purchases, whichever widget asks.
			if (!!(all[i].closest && all[i].closest(DRAWER_SELECTOR)) !== inDrawer) continue;
			// A form carrying a bundle widget of its own is that widget's, e.g. a repeated offer block.
			if (all[i].querySelector(".wffn-bundles") && !all[i].contains(widget)) continue;
			out.push(all[i]);
		}
		return out;
	};

	/** The add-to-cart control inside a cart form, or null. Tried in order: a standalone form
	 *  carries a hidden `add-to-cart` input ahead of its button. */
	F.addButton = function (form) {
		if (!form) return null;
		// A <button> with no type submits too; the widget's own buttons inside the form are not the add.
		var bare = Array.prototype.find.call(form.querySelectorAll("button:not([type])"), function (b) {
			return !b.closest(".wffn-bundles");
		});
		return form.querySelector(".single_add_to_cart_button")
			|| form.querySelector("button[type=submit]")
			|| bare
			|| form.querySelector("[name=add-to-cart]");
	};

	/**
	 * Keep a widget's add-button gate asserted behind WooCommerce's own.
	 *
	 * On a variable product the theme's variations_form re-stamps `disabled
	 * wc-variation-selection-needed` from its own events, which never see a
	 * native variation because the bundle resolves each member itself.
	 *
	 * Deferred: jQuery runs handlers in binding order and ours binds first, so
	 * re-asserting inline would be overwritten by WooCommerce's own handler.
	 */
	F.holdGate = function (widget, signal, resync) {
		if (!window.jQuery || typeof resync !== "function") return;
		var events = "check_variation_data reset_data show_variation hide_variation found_variation";
		F.cartForms(widget).forEach(function (form) {
			if (!form.classList.contains("variations_form")) return;
			var $form = window.jQuery(form);
			var reassert = function () { window.setTimeout(resync, 0); };
			$form.on(events, reassert);
			if (signal) signal.addEventListener("abort", function () { $form.off(events, reassert); });
		});
	};

	/* A variable product's axes render as independent selects, so constrain each to
	   the buyable combos in `data-wffn-bundles-variations` (built in includes/skins/variants.php). */

	/**
	 * Disable every option in `wrap`'s axes that can't lead to a real variation
	 * given the other axes' current picks, and reset any now-invalid selection
	 * back to its placeholder. No-op when the wrapper carries no matrix.
	 *
	 * `changed` (optional) is the axis the shopper just edited — it is protected
	 * from reset, so changing Color only clears a now-invalid Logo, not itself.
	 */
	F.constrainVariants = function (wrap, changed) {
		if (!wrap) return;
		var matrix = wrap._wffnBundlesMatrix;
		if (matrix === undefined) {
			matrix = null;
			var raw = wrap.getAttribute("data-wffn-bundles-variations");
			if (raw) { try { matrix = JSON.parse(raw); } catch (e) { matrix = null; } }
			matrix = matrix && matrix.length ? matrix : null;
			wrap._wffnBundlesMatrix = matrix;
		}
		if (!matrix) return;

		var selects = [];
		var nodes = wrap.querySelectorAll(".wffn-bundles-variant-axis");
		for (var i = 0; i < nodes.length; i++) selects.push(nodes[i]);
		if (!selects.length) return;
		var protectedIdx = changed ? selects.indexOf(changed) : -1;

		// The axis label a select drives, read off its first real "Label:value" option.
		function axisLabel(sel) {
			for (var o = 0; o < sel.options.length; o++) {
				var v = sel.options[o].value || "";
				var k = v.indexOf(":");
				if (k > 0) return v.slice(0, k);
			}
			return "";
		}
		var labels = selects.map(axisLabel);

		// Is there a buyable combo with `label`=`val` matching every other picked
		// axis? An empty combo value means "any"; attributes live under `a`.
		function comboExists(chosen, label, val) {
			for (var i = 0; i < matrix.length; i++) {
				var combo = matrix[i], attrs = (combo && combo.a) ? combo.a : combo, ok = true, k;
				for (k in chosen) {
					if (k === label) continue;
					var cv = attrs[k];
					if (cv !== undefined && cv !== "" && cv !== chosen[k]) { ok = false; break; }
				}
				if (!ok) continue;
				var mine = attrs[label];
				if (mine === undefined || mine === "" || mine === val) return true;
			}
			return false;
		}

		// Re-run until stable: resetting an invalid pick can free up its siblings.
		var guard = 0, changedAny = true;
		while (changedAny && guard++ <= selects.length) {
			changedAny = false;
			var chosen = {};
			for (var a = 0; a < selects.length; a++) {
				var sv = selects[a].value || "", sk = sv.indexOf(":");
				if (sk > 0) chosen[sv.slice(0, sk)] = sv.slice(sk + 1);
			}
			for (var b = 0; b < selects.length; b++) {
				var sel = selects[b], label = labels[b], opts = sel.options;
				for (var o = 0; o < opts.length; o++) {
					var opt = opts[o], v = opt.value || "", kk = v.indexOf(":");
					if (kk <= 0) continue; // the "Choose an option" placeholder stays open
					var enabled = comboExists(chosen, label, v.slice(kk + 1));
					opt.disabled = !enabled;
					opt.hidden = !enabled;
				}
				// Drop a now-invalid pick to the placeholder, except the axis the shopper
				// just changed; a conflicting sibling resets first and frees it next pass.
				if (b !== protectedIdx && sel.selectedIndex >= 0 && sel.options[sel.selectedIndex].disabled) {
					sel.value = "";
					changedAny = true;
				}
			}
		}
	};

	/**
	 * Point a thumbnail at `src`, remembering what the server first rendered.
	 *
	 * An empty `src` restores that original, so a pick with no image of its own
	 * never leaves the previous variation's picture behind.
	 */
	F.swapThumb = function (scope, selector, src) {
		if (!scope) return;
		var img = scope.querySelector(selector);
		if (!img) return;
		if (img._wffnBundlesBaseSrc == null) img._wffnBundlesBaseSrc = img.getAttribute("src") || "";
		var next = src || img._wffnBundlesBaseSrc;
		if (next && img.getAttribute("src") !== next) img.setAttribute("src", next);
	};

	/** Variation image URLs already requested, so each is fetched once per page. */
	var preloaded = {};

	/**
	 * Warm the browser cache with every variation image a widget can show, once
	 * the page is idle, so a later pick swaps its thumbnail without a download.
	 */
	F.preloadVariantImages = function (widget) {
		var run = function () {
			var wraps = widget.querySelectorAll("[data-wffn-bundles-variations]");
			for (var i = 0; i < wraps.length; i++) {
				var matrix;
				try { matrix = JSON.parse(wraps[i].getAttribute("data-wffn-bundles-variations") || "[]"); } catch (e) { continue; }
				for (var j = 0; j < (matrix || []).length; j++) {
					var src = matrix[j] && matrix[j].img;
					if (!src || preloaded[src]) continue;
					preloaded[src] = new Image();
					preloaded[src].src = src;
				}
			}
		};
		if (window.requestIdleCallback) window.requestIdleCallback(run, { timeout: 2000 });
		else window.setTimeout(run, 300);
	};

	/**
	 * The image of the variation matching the current pick, or "".
	 *
	 * A fully-specified combo beats a wildcard one, mirroring variant-modal.js.
	 */
	F.variantImage = function (wrap) {
		if (!wrap || !F.axesComplete(wrap)) return "";
		var raw = wrap.getAttribute("data-wffn-bundles-variations");
		if (!raw) return "";
		var matrix;
		try { matrix = JSON.parse(raw); } catch (e) { return ""; }
		if (!matrix || !matrix.length) return "";

		var chosen = F.readAttributes(wrap), best = "", exact = false;
		for (var i = 0; i < matrix.length; i++) {
			var entry = matrix[i], attrs = (entry && entry.a) ? entry.a : entry, ok = true, wild = false, k;
			for (k in chosen) {
				var cv = attrs[k];
				if (cv === undefined || cv === "") { wild = true; continue; }
				if (cv !== chosen[k]) { ok = false; break; }
			}
			if (!ok || (exact && wild)) continue;
			best = (entry && entry.img) || "";
			exact = !wild;
			if (exact) break;
		}
		return best;
	};

	/* The add-bundle endpoint rejects a bad selection with a machine code (see
	   includes/frontend/cart.php); the copy is localized in `window.wffnBundlesCart.errors`. */

	/** Human message for a server error code (localized, with a safe fallback). */
	F.errorMessage = function (code) {
		var map = (window.wffnBundlesCart && window.wffnBundlesCart.errors) || {};
		return map[code] || map._default || "Sorry, this bundle couldn’t be added to your cart. Please try again.";
	};

	/**
	 * Render (or replace) the inline error notice inside a widget root. The
	 * notice is a direct child so `:scope > .wffn-bundles-error` finds exactly one.
	 */
	F.showError = function (widget, code, text) {
		if (!widget) return;
		code = code || "add_failed";
		var el = widget.querySelector(":scope > .wffn-bundles-error");
		if (!el) {
			el = document.createElement("div");
			el.className = "wffn-bundles-error";
			el.setAttribute("role", "alert");
			el.setAttribute("aria-live", "assertive");
			widget.appendChild(el);
		}
		el.setAttribute("data-wffn-bundles-error", code);
		el.textContent = text || F.errorMessage(code);
	};

	/**
	 * Read an error payload. The endpoint answers with a bare code string, or with
	 * {code, text} when the reason is specific to this selection.
	 */
	F.errorPayload = function (data) {
		if (data && typeof data === "object") {
			return { code: data.code || "add_failed", text: data.text || "" };
		}
		return { code: data || "add_failed", text: "" };
	};

	/** Remove any inline error notice from a widget root. */
	F.clearError = function (widget) {
		if (!widget) return;
		var el = widget.querySelector(":scope > .wffn-bundles-error");
		if (el && el.parentNode) el.parentNode.removeChild(el);
	};

	/**
	 * Render (or replace) an informational notice inside a widget root.
	 */
	F.showNotice = function (widget, message) {
		if (!widget || !message) return;
		var el = widget.querySelector(":scope > .wffn-bundles-notice");
		if (!el) {
			el = document.createElement("div");
			el.className = "wffn-bundles-notice";
			el.setAttribute("role", "status");
			el.setAttribute("aria-live", "polite");
			widget.appendChild(el);
		}
		el.textContent = message;
	};

	/** Remove any inline notice from a widget root. */
	F.clearNotice = function (widget) {
		if (!widget) return;
		var el = widget.querySelector(":scope > .wffn-bundles-notice");
		if (el && el.parentNode) el.parentNode.removeChild(el);
	};

	/**
	 * Render (or replace) the in-page success confirmation inside a widget root.
	 *
	 * The AJAX add suppresses WooCommerce's own "added to cart" confirmation, so
	 * this restores a visible one. Copy comes from window.wffnBundlesCart.i18n.
	 */
	F.showSuccess = function (widget, message) {
		if (!widget) return;
		var cfg = window.wffnBundlesCart || {};
		var i18n = cfg.i18n || {};
		message = message || i18n.addedToCart || "Added to your cart.";
		F.clearError(widget);
		F.clearNotice(widget);
		var el = widget.querySelector(":scope > .wffn-bundles-success");
		if (!el) {
			el = document.createElement("div");
			el.className = "wffn-bundles-success";
			el.setAttribute("role", "status");
			el.setAttribute("aria-live", "polite");
			widget.appendChild(el);
		}
		el.textContent = "";
		var text = document.createElement("span");
		text.className = "wffn-bundles-success-text";
		text.textContent = message;
		el.appendChild(text);
		if (cfg.cartUrl) {
			var link = document.createElement("a");
			link.className = "wffn-bundles-success-link wc-forward";
			link.href = cfg.cartUrl;
			link.textContent = i18n.viewCart || "View cart";
			el.appendChild(link);
		}
	};

	/**
	 * Show WooCommerce's own add-to-cart notice, as any other add would.
	 *
	 * The server queues it with wc_add_to_cart_message and hands back the rendered
	 * markup; this places it where the theme already prints notices. Returns
	 * false when the page has nowhere to put it.
	 */
	F.showCartNotices = function (widget, html) {
		if (!html) return false;
		var host = document.querySelector(".woocommerce-notices-wrapper");
		if (!host && widget && widget.parentNode) {
			host = document.createElement("div");
			host.className = "woocommerce-notices-wrapper";
			widget.parentNode.insertBefore(host, widget);
		}
		if (!host) return false;
		host.innerHTML = html;
		if (host.scrollIntoView) host.scrollIntoView({ block: "nearest", behavior: "smooth" });
		return true;
	};

	/** Remove any inline success confirmation from a widget root. */
	F.clearSuccess = function (widget) {
		if (!widget) return;
		var el = widget.querySelector(":scope > .wffn-bundles-success");
		if (el && el.parentNode) el.parentNode.removeChild(el);
	};

	/** Replace each WooCommerce cart fragment (selector → html) in place. */
	function applyFragments(fragments) {
		if (!fragments) return;
		Object.keys(fragments).forEach(function (sel) {
			// A fragment key is a selector only by convention; an invalid one throws from
			// querySelectorAll and would abort the loop before notifyCartUpdated() runs.
			var nodes;
			try {
				nodes = document.querySelectorAll(sel);
			} catch (e) {
				return;
			}
			for (var i = 0; i < nodes.length; i++) {
				var wrap = document.createElement("div");
				wrap.innerHTML = fragments[sel];
				if (wrap.firstElementChild) nodes[i].replaceWith(wrap.firstElementChild);
			}
		});
	}

	/** Blocks that render from the block cart store: Cart, Checkout, the non-interactive mini-cart and All Products. */
	var BLOCK_STORE_READERS = '.wp-block-woocommerce-cart, .wp-block-woocommerce-checkout, [data-block-name="woocommerce/mini-cart"]:not([data-wp-interactive]), .wp-block-woocommerce-all-products';

	/**
	 * Push the authoritative Store API cart into the block cart store.
	 *
	 * The add went through classic admin-ajax, so the block store is unaware;
	 * `invalidateResolutionForStore()` alone only refetches a mounted drawer.
	 */
	function refreshBlockCart() {
		// No block reads the store here; the interactive mini-cart keeps its own and refetches itself.
		if (!document.querySelector(BLOCK_STORE_READERS)) return;
		try {
			var d = window.wp && window.wp.data;
			var dispatch = d && typeof d.dispatch === "function" ? d.dispatch("wc/store/cart") : null;
			if (!dispatch) return;
			// Marking the store's data stale makes it refetch the cart once, itself. Fetching here as well
			// and handing the result over made the store fetch a second time, for the same cart.
			if (typeof dispatch.invalidateResolutionForStore === "function") {
				dispatch.invalidateResolutionForStore();
				return;
			}
			// A store without it: fetch the cart and hand it over.
			var got = window.wp && window.wp.apiFetch
				? window.wp.apiFetch({ path: "/wc/store/v1/cart" })
				: fetch("/wp-json/wc/store/v1/cart", { credentials: "same-origin" }).then(function (r) { return r.json(); });
			got.then(function (cart) {
				if (cart && typeof dispatch.receiveCart === "function") dispatch.receiveCart(cart);
				else if (typeof dispatch.invalidateResolutionForStore === "function") dispatch.invalidateResolutionForStore();
			}).catch(function () {
				if (typeof dispatch.invalidateResolutionForStore === "function") dispatch.invalidateResolutionForStore();
			});
		} catch (e) {}
	}

	/**
	 * Every cart-fragment cache declared on the page.
	 *
	 * WooCommerce and any theme shipping a second cart UI both localize a
	 * `…_cart_fragments_params` object; matching that convention rather than a
	 * theme name is what keeps this general.
	 */
	function fragmentCaches() {
		var out = [];
		var names;
		try {
			names = Object.keys(window);
		} catch (e) {
			return out;
		}
		for (var i = 0; i < names.length; i++) {
			if (!/cart_fragments_params$/.test(names[i])) continue;
			var p;
			try {
				p = window[names[i]];
			} catch (e) {
				continue; // A throwing getter is not a cache.
			}
			if (p && p.fragment_name && p.cart_hash_key) out.push(p);
		}
		return out;
	}

	/**
	 * Write the add's own fragments into those caches, as WooCommerce does after
	 * its own cart AJAX.
	 *
	 * The mini-cart key is required first: these caches are shared, so writing a
	 * partial set evicts the good one and the drawer renders empty.
	 * `wc_cart_created` is required too; Woodmart's reader throws without it.
	 *
	 * @return {boolean} whether any cache was written.
	 */
	function cacheFragments(fragments, hash) {
		if (!fragments || !fragments["div.widget_shopping_cart_content"]) return false;
		var caches = fragmentCaches();
		var wrote = false;
		for (var i = 0; i < caches.length; i++) {
			try {
				window.sessionStorage.setItem(caches[i].fragment_name, JSON.stringify(fragments));
				if (hash != null) {
					window.sessionStorage.setItem(caches[i].cart_hash_key, hash);
					window.localStorage.setItem(caches[i].cart_hash_key, hash);
					window.sessionStorage.setItem("wc_cart_created", String(new Date().getTime()));
				}
				wrote = true;
			} catch (e) {
				// Private mode / quota / disabled storage — fall through to the refetch.
			}
		}
		return wrote;
	}

	/* A throwing listener ends only that event's dispatch: later events still fire, and a done add is not reported as failed. */
	function triggerBody(name, args) {
		try {
			window.jQuery(document.body).trigger(name, args);
		} catch (e) {
			if (window.console) window.console.error(e);
		}
	}

	/* Reflect the new cart count in whichever cart UI the theme uses: classic
	   fragments + `added_to_cart`, the block mini-cart badge, and the block store. */
	function notifyCartUpdated(fragments, hash, count) {
		if (window.jQuery) {
			// Listeners call methods on the third argument, the clicked button; an empty set makes them no-ops.
			triggerBody("added_to_cart", [fragments || {}, hash, window.jQuery()]);
			triggerBody("wffn_bundles_cart_updated", [fragments || {}]);
			// A theme that repaints its cart from a cached copy never sees replaced
			// fragment nodes, so write the cache directly; refetch only when we cannot.
			if (cacheFragments(fragments || {}, hash)) {
				triggerBody("wc_fragments_refreshed");
			} else {
				triggerBody("wc_fragment_refresh");
			}
		}
		if (count != null) {
			var badges = document.querySelectorAll(".wc-block-mini-cart__badge, .cart-contents-count");
			for (var i = 0; i < badges.length; i++) badges[i].textContent = String(count);
		}
		// The classic cart page repaints its table on this event, as FunnelKit Cart does after its own adds.
		if (window.jQuery && document.querySelector(".woocommerce-cart-form")) {
			triggerBody("wc_update_cart");
		}
		refreshBlockCart();
	}

	/**
	 * Fields another script asks to ride with an add (`F.requestExtras()` returns a name => value map).
	 * The cart drawer uses this to say which bundle line an add replaces.
	 */
	function addExtras(body) {
		var extras = "function" === typeof F.requestExtras ? F.requestExtras() : null;
		if (!extras) return;
		Object.keys(extras).forEach(function (name) { body.set(name, extras[name]); });
	}

	/**
	 * POST a validated selection to the cart. On success, redirect to the cart if
	 * the store opts into it; otherwise update the mini-cart fragments in place and
	 * fire WooCommerce's `added_to_cart` event so the theme reacts — no page change.
	 */
	F.addBundle = function (payload) {
		var cfg = window.wffnBundlesCart || {};
		if (!cfg.url) return Promise.resolve(null);
		var body = new URLSearchParams();
		body.set("action", "wffn_bundles_add_bundle");
		body.set("nonce", cfg.nonce || "");
		// The currency this page was rendered in. Read, not computed — the server
		// prices in its own and only logs a disagreement (Cart::assert_currency).
		body.set("currency", cfg.currency || "");
		body.set("bundle", payload.bundle || "");
		body.set("type", payload.type || "");
		body.set("offer", payload.offer || "");
		body.set("selection", JSON.stringify(payload.selection || {}));
		addExtras(body);
		return fetch(cfg.addUrl || cfg.url, {
			method: "POST",
			credentials: "same-origin",
			headers: { "Content-Type": "application/x-www-form-urlencoded" },
			body: body.toString(),
		})
			.then(function (r) { return r.json(); })
			.then(function (j) {
				// WordPress answers an expired nonce with a bare `-1`, which is valid
				// JSON, so without this the click does nothing at all and says nothing.
				if (!j || typeof j !== "object") return { success: false, data: "session_expired" };
				if (!j || !j.success || !j.data) return j;
				if (j.data.redirect) {
					window.location.href = j.data.redirect;
					return j;
				}
				applyFragments(j.data.fragments);
				notifyCartUpdated(j.data.fragments, j.data.cart_hash, j.data.cart_count);
				return j;
			});
	};

	/**
	 * Add a single product (Fixed Bundle's "Buy 1" = the anchor) via AJAX so the
	 * cart updates in place. Selection extras ride with it in the same request.
	 */
	F.addSingle = function (payload) {
		var cfg = window.wffnBundlesCart || {};
		if (!cfg.url) return Promise.resolve(null);
		var body = new URLSearchParams();
		body.set("action", "wffn_bundles_add_single");
		body.set("nonce", cfg.nonce || "");
		body.set("product", payload.product || "");
		body.set("quantity", payload.quantity || 1);
		body.set("attributes", JSON.stringify(payload.attributes || {}));
		body.set("bundle", payload.bundle || "");
		body.set("selection", JSON.stringify(payload.selection || {}));
		addExtras(body);
		return fetch(cfg.addSingleUrl || cfg.url, {
			method: "POST",
			credentials: "same-origin",
			headers: { "Content-Type": "application/x-www-form-urlencoded" },
			body: body.toString(),
		})
			.then(function (r) { return r.json(); })
			.then(function (j) {
				// WordPress answers an expired nonce with a bare `-1`, which is valid
				// JSON, so without this the click does nothing at all and says nothing.
				if (!j || typeof j !== "object") return { success: false, data: "session_expired" };
				if (!j || !j.success || !j.data) return j;
				if (j.data.redirect) {
					window.location.href = j.data.redirect;
					return j;
				}
				applyFragments(j.data.fragments);
				notifyCartUpdated(j.data.fragments, j.data.cart_hash, j.data.cart_count);
				return j;
			});
	};

	/** Register `fn(root)` → object of extra selection keys posted with every add and quote. */
	F.selectionParts = F.selectionParts || [];
	F.addSelectionPart = function (fn) {
		F.selectionParts.push(fn);
	};

	/**
	 * Every registered part's keys under `root`, merged.
	 *
	 * Scoped because a checkout can carry several widgets (one per switcher row).
	 */
	F.selectionExtras = function (root, bundle) {
		var out = {};
		for (var i = 0; i < F.selectionParts.length; i++) {
			var part = F.selectionParts[i](root, bundle) || {};
			for (var k in part) {
				if (Object.prototype.hasOwnProperty.call(part, k)) out[k] = part[k];
			}
		}
		return out;
	};

	/**
	 * Where `widget`'s add-ons (gifts, upsells) are read from: undefined for the whole page, unless another
	 * script's `F.partsRoot( widget )` names a container that holds its own copy of the bundle.
	 */
	F.partsScope = function (widget) {
		var root = "function" === typeof F.partsRoot && widget ? F.partsRoot(widget) : null;
		return root || undefined;
	};

	/** Register `fn(next, bundle, root)` to run before an add of `bundle`; it returns true when it took over and will call next(). */
	F.addGates = F.addGates || [];
	F.addGate = function (fn) {
		F.addGates.push(fn);
	};

	/**
	 * The `wffn_bundles_payload` an express button would read from `form`, without opening
	 * any add gate. '' when the form carries none.
	 */
	F.expressPayload = function (form) {
		F._expressProbe = true;
		try {
			return new FormData(form).get("wffn_bundles_payload") || "";
		} finally {
			F._expressProbe = false;
		}
	};

	/** Register `fn(data, widget)` to run after a successful add. */
	F.addedHandlers = F.addedHandlers || [];
	F.onAdded = function (fn) {
		F.addedHandlers.push(fn);
	};

	/* The selection interceptAddToCart would POST is also quoted server-side on
	   every change, so the price shown equals the price charged. */

	/** The first candidate that is neither ours nor inside a product grid. */
	function pickPriceEl(els) {
		for (var i = 0; i < els.length; i++) {
			var e = els[i];
			if (e.closest(".wffn-bundles")) continue;
			if (e.closest(".wc-block-grid, .related, .up-sells, .cross-sells, ul.products, .wc-block-product-template, .wp-block-post-template")) continue;
			return e;
		}
		return null;
	}

	/** The theme's main product-price element near the cart form (block or classic),
	 *  skipping our own widget and any related/cross-sell product grids.
	 *
	 * `widget` scopes the opt-in `[data-wffn-bundles-price]` override, which the form cannot:
	 * a builder can put the price in a sibling popup or sticky container. */
	function findPriceEl(form, widget) {
		// Server-owned, behind the `wffn_bundles_price_selectors` filter; the literal is
		// the fallback for a page that never received the config.
		var list = (window.wffnBundlesCart || {}).priceSelectors;
		var sel = (list && list.length ? [].join.call(list, ", ") : "") ||
			"p.price, .wp-block-woocommerce-product-price, .woocommerce-variation-price";
		// In the cart drawer's quick view the page's own price is not this product's: stay inside the drawer.
		var drawer = form.closest ? form.closest(DRAWER_SELECTOR) : null;
		if (drawer) {
			return pickPriceEl(drawer.querySelectorAll(sel));
		}
		var pinned = (widget && widget.querySelector && widget.querySelector("[data-wffn-bundles-price]")) || document.querySelector("[data-wffn-bundles-price]");
		if (pinned) return pinned;
		var scope = form.closest(".product, .type-product, .single-product, main") || document;
		return pickPriceEl(scope.querySelectorAll(sel)) || pickPriceEl(document.querySelectorAll(sel));
	}

	// A checkout replaces its whole payment section on every repaint (a switcher
	// row's own add included), which re-seeds every visible widget's price from
	// scratch even when its selection never changed — the same payload quoted
	// again seconds apart. Short-lived so a genuine change is never served stale.
	var quoteCache = {};
	var QUOTE_CACHE_TTL = 4000;
	// Several widgets (or one widget re-attached across repaint waves) can ask for
	// the same unchanged selection within milliseconds of each other, all before
	// the first request has resolved — quoteCache alone can't catch that since it
	// only remembers a *finished* response. Tracking the in-flight promise too
	// means those callers share one network round trip instead of one each.
	var quotePending = {};
	// Every response this page has received, for the whole visit. Never served in
	// place of a request - quoteCache above decides that - only painted first, so
	// going back to a selection shows its last known price at once while the fresh
	// quote confirms it (F.liveQuote).
	var quoteSeen = {};

	function quoteKey(payload) {
		var cfg = window.wffnBundlesCart || {};
		return (cfg.currency || "") + "|" + (payload.bundle || "") + "|" + (payload.type || "") +
			"|" + (payload.offer || "") + "|" + JSON.stringify(payload.selection || {});
	}

	/** The last response this page received for exactly this payload, or null. */
	F.quotePeek = function (payload) {
		return (payload && quoteSeen[quoteKey(payload)]) || null;
	};

	/** POST a selection to the quote endpoint → { hasItems, isRange, hasDiscount, html } or null. */
	F.quote = function (payload) {
		var cfg = window.wffnBundlesCart || {};
		if (!cfg.url) return Promise.resolve(null);
		var key = quoteKey(payload);
		var cached = quoteCache[key];
		if (cached && Date.now() - cached.ts < QUOTE_CACHE_TTL) {
			return Promise.resolve(cached.data);
		}
		if (quotePending[key]) return quotePending[key];
		var body = new URLSearchParams();
		body.set("action", "wffn_bundles_quote");
		body.set("nonce", cfg.nonce || "");
		// The currency this page was rendered in. Read, not computed — the server
		// prices in its own and only logs a disagreement (Cart::assert_currency).
		body.set("currency", cfg.currency || "");
		body.set("bundle", payload.bundle || "");
		body.set("type", payload.type || "");
		body.set("offer", payload.offer || "");
		body.set("selection", JSON.stringify(payload.selection || {}));
		var request = fetch(cfg.quoteUrl || cfg.url, {
			method: "POST",
			credentials: "same-origin",
			headers: { "Content-Type": "application/x-www-form-urlencoded" },
			body: body.toString(),
		})
			.then(function (r) { return r.json(); })
			.then(function (j) {
				var data = j && j.success ? j.data : null;
				if (data) {
					quoteCache[key] = { data: data, ts: Date.now() };
					quoteSeen[key] = data;
				}
				return data;
			});
		quotePending[key] = request.then(
			function (data) { delete quotePending[key]; return data; },
			function (err) { delete quotePending[key]; throw err; }
		);
		return quotePending[key];
	};

	/**
	 * A widget's collect() result as the quote endpoint receives it, or null for an
	 * empty or blocked selection. The one place that shape is built, so a quote
	 * fetched ahead of a switch keys exactly as liveQuote's.
	 *
	 * @param {Element}     widget  The bundle widget root.
	 * @param {Object|null} payload What the skin's collect() returned.
	 * @return {Object|null}
	 */
	F.quotePayload = function (widget, payload) {
		if (!payload || payload.block) return null;
		var form = F.cartForm(widget);
		// The single pick is the anchor at its regular price, but a variable anchor
		// still resolves a variation; re-key collect()'s shape for the quote endpoint.
		if (payload.single) {
			payload = {
				type: payload.type,
				offer: "single",
				selection: { anchor: payload.product, attributes: payload.attributes || {} },
			};
		}
		if (!payload.bundle) {
			var f = form && form.querySelector('input[name="wffn_bundles_bundle_id"]');
			// No form.cart to carry the hidden field (a widget with no cart form
			// of its own): the widget's own root carries the bundle id instead.
			var bundleAttr = f ? null : widget.closest("[data-wffn-bundles-bundle]");
			payload.bundle = f ? f.value : (bundleAttr ? bundleAttr.getAttribute("data-wffn-bundles-bundle") : "");
		}
		payload.selection = payload.selection || {};
		// A type that quotes by its picks alone still names the page product, for any
		// consumer that must resolve against it.
		if (!payload.selection.product && !payload.selection.anchor) {
			var pid = form ? formProductId(form) : "";
			if (!pid) {
				var productAttr = widget.closest("[data-wffn-bundles-product]");
				pid = productAttr ? productAttr.getAttribute("data-wffn-bundles-product") : "";
			}
			if (pid) payload.selection.product = pid;
		}
		var extras = F.selectionExtras(F.partsScope(widget), payload.bundle);
		for (var ek in extras) {
			if (Object.prototype.hasOwnProperty.call(extras, ek)) payload.selection[ek] = extras[ek];
		}
		return payload;
	};

	/**
	 * Keep the product price in sync with the live selection. `collect()` is the
	 * same payload builder `interceptAddToCart` uses; null / { block:true }
	 * restores the original price. Stale responses are dropped.
	 */
	F.liveQuote = function (widget, signal, collect, opts) {
		opts = opts || {};
		// The product's own form: a sticky bar's form would scope findPriceEl() to
		// the bar and write the live bundle price into the wrong element. A widget
		// with no form at all (a formless embed) has no theme price
		// to touch either, so priceEl just stays null for it below.
		var form = F.cartForm(widget);
		var priceEl = form ? findPriceEl(form, widget) : null;
		// A builder layout makes this null far more often than a classic theme does
		// (no price element on the template at all), so it stays a silent no-op —
		// but only when a form exists and still came up empty; a formless widget
		// still needs to run for opts.onData (e.g. a per-tier price stamp).
		if (form && !priceEl && !form.closest(DRAWER_SELECTOR)) return;
		if (priceEl && priceEl._wffnBundlesOrig == null) priceEl._wffnBundlesOrig = priceEl.innerHTML;

		// Clears the "recalculating" state once a fresh price (or hint) is shown.
		function settle() { if (priceEl) priceEl.classList.remove("wffn-bundles-price-pending"); }

		// Empty selection: the skin's hint if it supplied one, nothing when it gives null,
		// else the product's own price. <small> deliberately: themes style the price by tag (Divi's !important).
		function rest() {
			if (priceEl) {
				var hint = opts.emptyText;
				priceEl.innerHTML = hint
					? '<small class="wffn-bundles-live-price wffn-bundles-live-price--hint">' + hint + "</small>"
					: (null === hint ? "" : priceEl._wffnBundlesOrig);
			}
			settle();
		}

		// Paint one quote: the theme price, the skin's own cards (onData), and the
		// event an integration re-prices its options from.
		function apply(data) {
			if (data && data.hasItems) { if (priceEl) priceEl.innerHTML = data.html; }
			else rest();
			settle();
			if (opts.onData) opts.onData(data);
			// Published so an integration can re-price its own options off the live
			// selection; carries the quote verbatim.
			F.bus.dispatchEvent(
				new CustomEvent("wffn-bundles:price-quoted", { detail: { widget: widget, data: data || null } })
			);
		}

		var seq = 0;
		function run() {
			var payload = F.quotePayload(widget, collect());
			// null/block restore the product's own price.
			if (!payload) { rest(); return; }
			var mine = ++seq;
			inFlight = true;
			// A selection already quoted this visit is painted at once, so a switch
			// never shows the previous offer's figures while its fresh quote is out.
			var seen = F.quotePeek(payload);
			if (seen) apply(seen);
			F.quote(payload).then(function (data) {
				inFlight = false;
				if (mine !== seq) return; // a newer change already fired
				// Identical to what was just painted: repainting would only churn the DOM.
				if (seen && data && JSON.stringify(data) === JSON.stringify(seen)) return;
				apply(data);
			});
		}
		var timer = null;
		var inFlight = false;
		// Dim the price the instant the selection changes so the stale value reads
		// as "recalculating" rather than as a result.
		function schedule() {
			// A widget replaying its own saved picks after a checkout repaint fires
			// the same change events a shopper's pick does; re-quoting on those is
			// pure waste and, left unbounded, piles up requests behind each other.
			if (F.priming) return;
			clearTimeout(timer);
			if (priceEl) priceEl.classList.add("wffn-bundles-price-pending");
			// Leading edge: a radio, tier card or checkbox cannot stream, so waiting
			// only ever spent latency. Changes arriving while a quote is in flight
			// still coalesce on the trailing edge, so a stepper burst is at most two
			// requests rather than one per click.
			if (!inFlight) { run(); return; }
			timer = setTimeout(run, 200);
		}

		// Any bundle/add-on state change re-prices (events bubble + are composed).
		["wffn-bundles:mm-change", "wffn-bundles:tier-change", "wffn-bundles:fb-change", "wffn-bundles:addon-change"].forEach(function (ev) {
			document.addEventListener(ev, schedule, { signal: signal });
		});
		// Our per-member axes live in the widget (outside form.cart on a simple host
		// product); the theme's native variation selects are caught on the form.
		widget.addEventListener("change", function (e) {
			var t = e.target;
			if (t && t.classList && t.classList.contains("wffn-bundles-variant-axis")) schedule();
		}, { signal: signal });
		if (form) {
			form.addEventListener("change", function (e) {
				var t = e.target;
				if (t && t.name && t.name.indexOf("attribute_") === 0) schedule();
			}, { signal: signal });
		}

		// A page builder can re-render the price widget on its own, which detaches
		// the node this closure holds; F.refreshPrice() re-resolves and re-seeds it.
		var binding = {
			widget: widget,
			// The element the quote writes into (F.priceEl).
			el: function () { return priceEl; },
			rebind: function () {
				if (!form) return;
				var next = findPriceEl(form, widget);
				if (!next || next === priceEl) return;
				priceEl = next;
				if (priceEl._wffnBundlesOrig == null) priceEl._wffnBundlesOrig = priceEl.innerHTML;
				run();
			},
		};
		F._priceBindings.push(binding);
		if (signal) {
			signal.addEventListener("abort", function () {
				var at = F._priceBindings.indexOf(binding);
				if (at > -1) F._priceBindings.splice(at, 1);
			});
		}

		run(); // seed from the initial selection
	};

	F._priceBindings = F._priceBindings || [];

	/**
	 * The theme price element the live quote drives for a widget, or null.
	 *
	 * @param {Element} widget The bundle widget root.
	 * @return {Element|null}
	 */
	F.priceEl = function (widget) {
		for (var i = 0; i < F._priceBindings.length; i++) {
			if (F._priceBindings[i].widget === widget && F._priceBindings[i].el) {
				return F._priceBindings[i].el();
			}
		}
		return null;
	};
	/**
	 * Re-resolve the price element every live quote writes into, and re-seed it.
	 * `root` is accepted for the builder contract; every binding is refreshed.
	 */
	F.refreshPrice = function (root) {
		void root;
		for (var i = 0; i < F._priceBindings.length; i++) {
			var b = F._priceBindings[i];
			if (b.widget && b.widget.isConnected === false) continue;
			b.rebind();
		}
	};

	/**
	 * Intercept the product's native add-to-cart so a multi-product bundle adds its
	 * whole selection. `collect()` returns the payload to POST, `{block:true}` to
	 * swallow the click, or null to let the native add proceed. Wired on the button
	 * in capture phase (beats theme AJAX handlers) and on submit.
	 */
	F.interceptAddToCart = function (widget, signal, collect) {
		// Every cart form posting this product: a theme's sticky bar submits through
		// its own form and handler, so intercepting one only moves the bypass.
		var forms = F.cartForms(widget);
		if (!forms.length) return;
		var form = forms[0]; // the product's own form — where the hidden fields live

		/** The bundle id, read once from the form's hidden field. */
		function bundleId(payload) {
			if (payload && payload.bundle) return payload.bundle;
			var f = form.querySelector('input[name="wffn_bundles_bundle_id"]');
			return f ? f.value : "";
		}
		/** Handle an addBundle/addSingle response uniformly (success confirms, failure surfaces). */
		function afterAdd(j) {
			// A redirect-after-add store has already navigated away in addBundle/addSingle;
			// the confirmation is moot there, so only show it on the in-place (fragment) path.
			if (j && j.success) {
				F.clearError(widget);
				// WooCommerce's own message when the server sent one; the in-widget
				// confirmation is the fallback for a page with no notice area.
				if (!F.showCartNotices(widget, j.data && j.data.notices)) F.showSuccess(widget);
				for (var a = 0; a < F.addedHandlers.length; a++) F.addedHandlers[a](j.data || {}, widget);
				return;
			}
			if (j && j.success === false) {
				var err = F.errorPayload(j.data);
				F.showError(widget, err.code, err.text);
			}
		}

		// An add in flight: a second click would post the same bundle again.
		var adding = false;

		function handle(e) {
			if (adding) {
				e.preventDefault();
				e.stopImmediatePropagation();
				return;
			}
			var payload = collect();
			if (!payload) return; // null → nothing for us to intercept; native add proceeds
			// Whether the bundle part can actually be added: an empty box (block) or a
			// skin-reported unfinished state (ready:false) means no.
			var bundleReady = !payload.block && payload.ready !== false;

			// Nothing qualifying: no ready bundle and not the single pick. Add-ons are
			// bundle-dependent, so swallow the click (this guards a stray submit).
			if (!bundleReady && !payload.single) {
				e.preventDefault();
				e.stopImmediatePropagation();
				return;
			}

			e.preventDefault();
			e.stopImmediatePropagation();
			F.clearError(widget);
			F.clearNotice(widget);
			F.clearSuccess(widget);

			runGates(payload, 0);
		}

		/** Run each add gate in turn, then add. A gate always resolves, so the add runs either way. */
		function runGates(payload, i) {
			var gate = F.addGates[i];
			if (!gate) {
				performAdd(payload);
				return;
			}
			// If a gate fails, buy anyway: the click is already preventDefault()ed, so a
			// throw in here would leave a dead Add to Cart button.
			try {
				if (gate(function () { runGates(payload, i + 1); }, bundleId(payload), F.partsScope(widget))) return;
			} catch (err) {
				/* fall through to the next gate */
			}
			runGates(payload, i + 1);
		}

		/** The request an add posts for `payload`; the extras are read from the live DOM. */
		function addRequest(payload) {
			var bundle = bundleId(payload);

			// Fixed Bundle "Buy 1": the anchor at its own price plus any selection
			// extras, grouped server-side for removal.
			if (payload.single) {
				payload.bundle = bundle;
				payload.selection = F.selectionExtras(F.partsScope(widget), bundle);
				return payload;
			}

			// A ready bundle posts its full selection plus its bundle-dependent add-ons.
			var selection = payload.selection || {};
			var extras = F.selectionExtras(F.partsScope(widget), bundle);
			for (var ek in extras) {
				if (Object.prototype.hasOwnProperty.call(extras, ek)) selection[ek] = extras[ek];
			}
			return {
				bundle: bundle,
				type: payload.type,
				offer: payload.offer || "",
				selection: selection,
			};
		}

		/** True when a gate took over. Its next() is a no-op: an express add is retried by the shopper, not resumed. */
		function holdForGates(bundle) {
			var held = false;
			for (var i = 0; i < F.addGates.length; i++) {
				try {
					if (F.addGates[i](function () {}, bundle, F.partsScope(widget))) held = true;
				} catch (err) {
					/* a failing gate never blocks the add */
				}
			}
			return held;
		}

		/** Post the selection. Split out of `handle` so a gate can defer it and then
		 *  run it unchanged. */
		function performAdd(payload) {
			var request = addRequest(payload);
			adding = true;
			(request.single ? F.addSingle(request) : F.addBundle(request))
				.then(function (j) {
					adding = false;
					afterAdd(j);
				})
				.catch(function () {
					adding = false;
					// Fail safe: a network/parse error never adds — show a generic notice.
					F.showError(widget, "add_failed");
				});
		}

		/**
		 * The add this widget would post, for a reader of the form that never clicks
		 * its button: an express checkout button serializes `form.cart` into an add
		 * of its own, which compat answers with this. '' when nothing is to be
		 * intercepted, so that add stays native; `ready:false` for an unfinished
		 * bundle, which must not be bought as its bare page product.
		 */
		function formPayload() {
			var payload = collect();
			if (!payload) return "";
			if ((payload.block || payload.ready === false) && !payload.single) return JSON.stringify({ ready: false });
			// That add skips the click the add gates run on, so a gate that needs the
			// shopper first (a free gift still to pick) is opened here and holds it; the
			// shopper answers it and pays again. A probe only reads the payload.
			if (!F._expressProbe && holdForGates(bundleId(payload))) return JSON.stringify({ ready: false });
			var request = addRequest(payload);
			var cfg = window.wffnBundlesCart || {};
			request.nonce = cfg.nonce || "";
			request.currency = cfg.currency || "";
			return JSON.stringify(request);
		}
		// Capture on the button so we run at AT_TARGET, before a theme's delegated
		// bubble-phase handler on document/body that stopImmediatePropagation() misses.
		forms.forEach(function (f) {
			var btn = F.addButton(f);
			if (btn) btn.addEventListener("click", handle, { capture: true, signal: signal });
			f.addEventListener("submit", handle, { signal: signal });
			// `new FormData(form)` fires this, so the payload is read at that moment.
			f.addEventListener("formdata", function (e) {
				var value = formPayload();
				if (value) e.formData.set("wffn_bundles_payload", value);
			}, { signal: signal });
		});

		// A corrected selection in this widget dismisses a stale error/notice.
		["wffn-bundles:mm-change", "wffn-bundles:tier-change", "wffn-bundles:fb-change", "wffn-bundles:addon-change"].forEach(function (ev) {
			document.addEventListener(ev, function (e) {
				if (widget.contains(e.target) || (e.detail && e.detail.widget === widget)) {
					F.clearError(widget);
					F.clearNotice(widget);
					// A changed selection makes the last add's confirmation stale.
					F.clearSuccess(widget);
				}
			}, { signal: signal });
		});
	};

	/** Containers whose skin captions their pickers itself; another script adds to it. */
	F.captionSkip = F.captionSkip || [];

	/* Caption every inline picker with its attribute names, in dropdown order. */
	F.register(function (root) {
		F.each(root, ".wffn-bundles-variant", function (wrap) {
			var skip = F.captionSkip.some(function (sel) { return wrap.closest(sel); });
			if (skip) return;
			var axes = wrap.querySelectorAll(":scope > .wffn-bundles-variant-axis");
			var names = [];
			for (var i = 0; i < axes.length; i++) {
				var name = F.axisName(axes[i]);
				if (!name) return;
				names.push(name);
				if (!axes[i].hasAttribute("aria-label")) axes[i].setAttribute("aria-label", name);
			}
			// The server prints the caption; only the dropdowns' labels are added then.
			if (!names.length || wrap.querySelector(":scope > .wffn-bundles-variant-head")) return;
			var head = document.createElement("span");
			head.className = "wffn-bundles-variant-head";
			head.setAttribute("aria-hidden", "true");
			head.textContent = names.join(" · ");
			wrap.insertBefore(head, wrap.firstChild);
		});
	});

	/* Dependent variation dropdowns: wire every variant picker that ships a combo
	   matrix. Shared by all skins via `.wffn-bundles-variant` / `.wffn-bundles-variant-axis`. */
	F.register(function (root) {
		F.each(root, ".wffn-bundles-variant[data-wffn-bundles-variations]", function (wrap) {
			var signal = F.bind(wrap, "variant-constrain");
			if (!signal) return; // already wired on this node
			F.constrainVariants(wrap); // constrain any pre-selected defaults up front
			wrap.addEventListener("change", function (e) {
				var t = e.target;
				if (t && t.classList && t.classList.contains("wffn-bundles-variant-axis")) {
					F.constrainVariants(e.currentTarget, t);
				}
			}, { signal: signal });
		});
	});
})();
