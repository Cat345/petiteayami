/**
 * Skins behavior — variant options quick-view.
 *
 * Re-presents an inline `.wffn-bundles-variant` picker as a "Select options" button and
 * popup. The <select>s move into the popup but stay inside their wrapper, so
 * add-to-cart, the dependent-dropdown constraint and pricing read them unchanged.
 */
(function () {
	"use strict";

	var F = window.wffnBundlesSkins;

	function cfg() {
		return window.wffnBundlesCart || {};
	}
	function label(key, fallback) {
		var i18n = cfg().i18n || {};
		return i18n[key] || fallback;
	}
	function mode() {
		return cfg().optionsDisplay || "auto";
	}

	function axesOf(wrap) {
		return wrap.querySelectorAll(".wffn-bundles-variant-axis");
	}

	/** Whether this picker should open in a popup rather than render inline. */
	function wantsModal(wrap) {
		var n = axesOf(wrap).length;
		if (!n) return false;
		// A dialog that already is the options prompt needs no trigger inside it.
		if (wrap.closest("[data-wffn-bundles-prompt]")) return false;
		var m = mode();
		if (m === "inline") return false;
		if (m === "modal") return true;
		// A skin that lays out every attribute itself keeps its pickers inline.
		if (wrap.closest("[data-wffn-bundles-inline]")) return false;
		return n > 2; // auto — simple products (≤ 2 attributes) stay inline
	}

	/** Every axis has a real (non-placeholder) value picked. */
	function allChosen(wrap) {
		var a = axesOf(wrap);
		for (var i = 0; i < a.length; i++) {
			if (!a[i].value) return false;
		}
		return a.length > 0;
	}

	/** The picked option labels joined ("Red / L"). */
	function summaryText(wrap) {
		var a = axesOf(wrap),
			parts = [];
		for (var i = 0; i < a.length; i++) {
			var opt = a[i].options[a[i].selectedIndex];
			if (opt && opt.value && opt.text) parts.push(opt.text);
		}
		return parts.join(" / ");
	}

	/** The base product identity ({ name, image }) carried on the wrapper. */
	function info(wrap) {
		if (wrap._wffnBundlesVInfo !== undefined) return wrap._wffnBundlesVInfo;
		var raw = wrap.getAttribute("data-wffn-bundles-vinfo"),
			o = null;
		if (raw) {
			try {
				o = JSON.parse(raw);
			} catch (e) {
				o = null;
			}
		}
		wrap._wffnBundlesVInfo = o || {};
		return wrap._wffnBundlesVInfo;
	}

	/** The buyable-combos matrix (`[{ a:{combo}, img }]`) carried on the wrapper. */
	function matrixOf(wrap) {
		if (wrap._wffnBundlesVMatrix !== undefined) return wrap._wffnBundlesVMatrix;
		var raw = wrap.getAttribute("data-wffn-bundles-variations"),
			m = null;
		if (raw) {
			try {
				m = JSON.parse(raw);
			} catch (e) {
				m = null;
			}
		}
		wrap._wffnBundlesVMatrix = m && m.length ? m : null;
		return wrap._wffnBundlesVMatrix;
	}

	/**
	 * The image of the variation matching the current full selection, or "".
	 * Empty axis values in a combo mean "any"; the most specific match wins so a
	 * concrete variation's image beats a wildcard one.
	 */
	function resolvedImage(wrap) {
		if (!allChosen(wrap)) return "";
		var m = matrixOf(wrap);
		if (!m) return "";
		var a = axesOf(wrap),
			chosen = {};
		for (var i = 0; i < a.length; i++) {
			var v = a[i].value || "",
				k = v.indexOf(":");
			if (k > 0) chosen[v.slice(0, k)] = v.slice(k + 1);
		}
		var best = "",
			bestScore = -1;
		for (var j = 0; j < m.length; j++) {
			var combo = m[j],
				attrs = combo && combo.a ? combo.a : combo,
				ok = true,
				score = 0,
				key;
			for (key in chosen) {
				var cv = attrs[key];
				if (cv === undefined || cv === "") continue; // "any" matches anything
				if (cv !== chosen[key]) {
					ok = false;
					break;
				}
				score++;
			}
			if (ok && score > bestScore) {
				bestScore = score;
				best = (combo && combo.img) || "";
			}
		}
		return best;
	}

	/** Point the popup thumbnail at the selected variation, or the base image. */
	function updateMedia(wrap) {
		var img = wrap.querySelector(".wffn-bundles-variant-modal-thumb");
		if (!img) return;
		var src = resolvedImage(wrap) || wrap._wffnBundlesBaseImg || "";
		if (src && img.getAttribute("src") !== src) img.setAttribute("src", src);
	}

	/** Sync the trigger button and popup thumbnail to the current selection. */
	function refresh(wrap) {
		updateMedia(wrap);
		var btn = wrap.querySelector(".wffn-bundles-variant-open");
		if (!btn) return;
		var chosen = allChosen(wrap);
		btn.textContent = chosen ? summaryText(wrap) : label("selectOptions", "Select options");
		wrap.classList.toggle("wffn-bundles-variant--chosen", chosen);
	}

	function esc(s) {
		return String(s).replace(/[&<>"']/g, function (c) {
			return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
		});
	}

	/** Restructure an inline picker into a button + popup (once per wrapper). */
	function transform(wrap) {
		if (wrap._wffnBundlesVModal) return;
		wrap._wffnBundlesVModal = 1;
		wrap.classList.add("wffn-bundles-variant--modal");

		// Product identity for the header: a thumbnail (starts on the product image,
		// swaps to the selected variation) plus the name, only if the wrapper has either.
		var inf = info(wrap);
		wrap._wffnBundlesBaseImg = (inf && inf.image) || "";
		var media = "";
		if (inf && (inf.image || inf.name)) {
			media =
				'<span class="wffn-bundles-variant-modal-media">' +
				(inf.image
					? '<img class="wffn-bundles-variant-modal-thumb" src="' + esc(inf.image) + '" alt="' + esc(inf.name || "") + '" />'
					: "") +
				(inf.name ? '<span class="wffn-bundles-variant-modal-name">' + esc(inf.name) + "</span>" : "") +
				"</span>";
		}

		var overlay = document.createElement("span");
		overlay.className = "wffn-bundles-variant-modal";
		overlay.innerHTML =
			'<span class="wffn-bundles-variant-modal-backdrop" data-act="vclose"></span>' +
			'<span class="wffn-bundles-variant-modal-box">' +
			'<span class="wffn-bundles-variant-modal-head">' +
			'<span class="wffn-bundles-variant-modal-title">' +
			esc(label("selectOptions", "Select options")) +
			"</span>" +
			'<button type="button" class="wffn-bundles-variant-modal-x" data-act="vclose" aria-label="' +
			esc(label("close", "Close")) +
			'"></button>' +
			"</span>" +
			media +
			'<span class="wffn-bundles-variant-modal-body"></span>' +
			'<button type="button" class="wffn-bundles-variant-modal-done" data-act="vclose">' +
			esc(label("done", "Done")) +
			"</button>" +
			"</span>";

		// Move the existing <select>s into the popup body (kept inside .wffn-bundles-variant).
		var body = overlay.querySelector(".wffn-bundles-variant-modal-body");
		var a = axesOf(wrap);
		for (var i = 0; i < a.length; i++) {
			var name = F.axisName(a[i]);
			if (name) {
				var tag = document.createElement("span");
				tag.className = "wffn-bundles-variant-modal-label";
				tag.setAttribute("aria-hidden", "true");
				tag.textContent = name;
				body.appendChild(tag);
				if (!a[i].hasAttribute("aria-label")) a[i].setAttribute("aria-label", name);
			}
			body.appendChild(a[i]);
		}

		var open = document.createElement("button");
		open.type = "button";
		open.className = "wffn-bundles-variant-open";
		open.setAttribute("aria-haspopup", "dialog");
		open.textContent = label("selectOptions", "Select options");

		wrap.appendChild(open);
		wrap.appendChild(overlay);
		refresh(wrap);
	}

	function setup(widget) {
		var signal = F.bind(widget, "variant-modal");
		if (!signal) return;

		F.each(widget, ".wffn-bundles-variant", function (wrap) {
			if (wantsModal(wrap)) transform(wrap);
		});

		widget.addEventListener(
			"click",
			function (e) {
				var overlay = e.target.closest(".wffn-bundles-variant-modal");
				if (overlay) {
					// A click anywhere in the popup (except on a dropdown) must not
					// toggle the offer's wrapping radio/label.
					if (!e.target.closest(".wffn-bundles-variant-axis")) e.preventDefault();
					if (e.target.closest("[data-act=vclose]")) {
						e.stopPropagation();
						var wc = overlay.closest(".wffn-bundles-variant");
						if (wc) wc.classList.remove("wffn-bundles-variant--open");
					}
					return;
				}
				var open = e.target.closest(".wffn-bundles-variant-open");
				if (open) {
					var w = open.closest(".wffn-bundles-variant");
					if (w) w.classList.add("wffn-bundles-variant--open");
				}
			},
			{ signal: signal }
		);

		widget.addEventListener(
			"change",
			function (e) {
				var t = e.target;
				if (t && t.classList && t.classList.contains("wffn-bundles-variant-axis")) {
					var w = t.closest(".wffn-bundles-variant--modal");
					if (w) refresh(w);
				}
			},
			{ signal: signal }
		);
	}

	F.register(function (root) {
		// Needs the global config (options mode + labels): present on the storefront
		// and in the editor preview, absent elsewhere where the dropdowns stay inline.
		if (!window.wffnBundlesCart) return;
		F.each(root, ".wffn-bundles", setup);
	});
})();
