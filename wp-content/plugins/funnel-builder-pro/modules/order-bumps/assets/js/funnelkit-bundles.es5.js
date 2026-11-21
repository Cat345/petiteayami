"use strict";

/**
 * FunnelKit Bundles inside an order bump.
 *
 * The bundle widget stays hidden until the bump is accepted. Delegated from the
 * document because the checkout replaces the payment section on every refresh.
 */
(function () {
  "use strict";

  var WIDGET = ".wfob-bundle-widget";
  var BUMP = ".wfob_bump";
  var QV = "#wfob_qr_model_wrap";
  var MEMBER = ".wffn-bundles-fb-member, .wffn-bundles-fb-flat-row";
  var TIER = ".wffn-bundles-tier--selected";
  var UNIT = ".wffn-bundles-qb-unit";
  var AXIS = ".wffn-bundles-variant-axis"; // Every re-place refreshes the checkout, so anything faster would loop.

  var READD_GAP = 1200; // Set while this script writes to the widget; a primed change is not a shopper's pick.

  var priming = false;
  var attaching = false;
  var hooked = false;
  var opener = "";
  var placed = {};
  var placedPicks = {};
  var placedTicks = {};
  var lastReadd = {};
  var pending = {};
  var attachedWidgets = [];

  function skins() {
    return window.wffnBundlesSkins || null;
  }
  /**
   * No live quote for an inline bump's widget: every change re-places the bump, which redraws
   * it with the server's prices, so a quote would only add a request to each refresh.
   * The quick view adds only on its button, so it keeps the quote.
   */


  function quietQuotes() {
    var F = skins();

    if (!F || !F.quotePayload || F.quotePayload.wfobQuiet) {
      return;
    }

    var own = F.quotePayload;

    F.quotePayload = function (widget) {
      return widget && widget.closest && widget.closest(WIDGET) && !widget.closest(QV) ? null : own.apply(this, arguments);
    };

    F.quotePayload.wfobQuiet = true;
  }
  /** Dim a bump and hold its clicks while its lines are being re-placed. */


  function busy(row, on) {
    if (row) {
      row.classList.toggle("wfob-bundle-busy", on);
      row.setAttribute("aria-busy", on ? "true" : "false");
    }
  }
  /** Mirrored onto the skins, whose live quote skips a primed change instead of re-quoting it. */


  function setPriming(on) {
    priming = on;
    var F = skins();

    if (F) {
      F.priming = on;
    }
  }
  /** The bump row a widget belongs to; it is printed as the row's sibling. */


  function rowFor(widget) {
    var scope = widget.closest(".wfob_wrap_start") || widget.parentElement;
    return scope ? scope.querySelector(BUMP) : null;
  }

  function widgetOf(row) {
    var scope = row && (row.closest(".wfob_wrap_start") || row.parentElement);
    return scope ? scope.querySelector(WIDGET) : null;
  }

  function keyOf(widget) {
    return widget.getAttribute("data-wfob-bundle-key") || "";
  }
  /** Whether the shopper has said yes to this bump. */


  function accepted(widget) {
    if (widget.getAttribute("data-wfob-bundle-open")) {
      return true;
    }

    var row = rowFor(widget);
    var box = row ? row.querySelector("input.wfob_checkbox") : null;

    if (box) {
      return box.checked;
    }

    return !!(row && row.getAttribute("cart_key"));
  }
  /** Move the widget inside the bump's box; the server can only print it beside. */


  function nest(widget) {
    var row = rowFor(widget);

    if (row && !row.contains(widget)) {
      (row.querySelector(".wfob_sec_start") || row).appendChild(widget);
    }
  }

  function attach(widget) {
    var F = skins();

    if (!F || !F.rescan || widget.hasAttribute("data-wfob-bundle-attached") || widget.querySelector("[data-wffn-bundles-bound]")) {
      return;
    }

    widget.setAttribute("data-wfob-bundle-attached", "1");
    attaching = true;

    try {
      F.rescan(widget);
    } finally {
      attaching = false;
    }

    attachedWidgets.push(widget);
  }
  /** Abort the listeners of widgets the checkout has since removed. */


  function detachRemoved() {
    var F = skins();

    if (!F || !F.detachBehavior) {
      return;
    }

    attachedWidgets = attachedWidgets.filter(function (w) {
      if (document.contains(w)) {
        return true;
      }

      F.detachBehavior(w);
      return false;
    });
  }

  function setIn(widget) {
    return widget.matches(".wffn-bundles-fbt") ? widget : widget.querySelector(".wffn-bundles-fbt");
  }
  /** The Quantity Breaks tier the widget has selected, or null. */


  function tierIn(widget) {
    return widget.querySelector(TIER);
  }
  /** The offer the widget shows: `single`, `bundle`, the set or a tier id. */


  function offerFor(widget) {
    if (setIn(widget)) {
      return "fbt-set";
    }

    var tier = tierIn(widget);

    if (tier) {
      return tier.getAttribute("data-tier-id") || "";
    }

    var single = widget.querySelector(".wffn-bundles-fb-single");

    if (single) {
      return single.classList.contains("wffn-bundles-fb-single--selected") ? "single" : "bundle";
    }

    return widget.querySelector(".wffn-bundles-fb-card, .wffn-bundles-fb-flat") ? "bundle" : "";
  }

  function writeAxes(axes, values) {
    for (var j = 0; j < axes.length && j < values.length; j++) {
      if (values[j] && axes[j].value !== values[j]) {
        axes[j].value = values[j];
        axes[j].dispatchEvent(new Event("change", {
          bubbles: true
        }));
      }
    }
  }
  /** Show the variation each member is added at when its picker was never touched. */


  function primeDefaults(widget) {
    var F = skins();
    var raw = widget.getAttribute("data-wfob-bundle-defaults");

    if (!F || !F.axesIn || !raw || widget.getAttribute("data-wfob-bundle-primed")) {
      return;
    }

    widget.setAttribute("data-wfob-bundle-primed", "1");
    var rows;

    try {
      rows = JSON.parse(raw);
    } catch (e) {
      return;
    }

    var members = widget.querySelectorAll(MEMBER);
    setPriming(true);

    for (var i = 0; i < members.length; i++) {
      var attrs = rows[i] && rows[i].attributes;
      var axes = attrs ? F.axesIn(members[i]) : [];

      for (var j = 0; j < axes.length; j++) {
        if (axes[j].value) {
          continue;
        }

        for (var label in attrs) {
          var want = label + ":" + attrs[label];

          for (var o = 0; o < axes[j].options.length; o++) {
            if (axes[j].options[o].value === want) {
              axes[j].value = want;
              axes[j].dispatchEvent(new Event("change", {
                bubbles: true
              }));
              break;
            }
          }
        }
      }
    }

    setPriming(false);
  }
  /** Put the half or tier the shopper last chose back after a repaint. */


  function primeOffer(widget) {
    var want = placed[keyOf(widget)];

    if (!want || "fbt-set" === want || want === offerFor(widget)) {
      return;
    }

    var target;

    if (tierIn(widget)) {
      target = widget.querySelector('.wffn-bundles-tier[data-tier-id="' + want + '"]');
    } else if ("single" === want || "bundle" === want) {
      target = widget.querySelector("single" === want ? ".wffn-bundles-fb-single" : ".wffn-bundles-fb-card");
    }

    if (target) {
      setPriming(true);
      target.click();
      setPriming(false);
    }
  }

  function pickScope(widget) {
    var tier = tierIn(widget);

    if (tier) {
      return tier.querySelectorAll(UNIT);
    }

    var members = widget.querySelectorAll(MEMBER);

    if (members.length) {
      return members;
    }

    var single = widget.querySelector(".wffn-bundles-fb-single");
    return single ? [single] : [];
  }

  function rememberPicks(widget) {
    var units = pickScope(widget);
    var rows = [];

    for (var i = 0; i < units.length; i++) {
      rows.push(Array.prototype.map.call(units[i].querySelectorAll(AXIS), function (a) {
        return a.value;
      }));
    }

    if (rows.length) {
      placedPicks[keyOf(widget) + "|" + offerFor(widget)] = rows;
    }
  }
  /** Put the shopper's picks back; the repaint renders the stored defaults. */


  function primePicks(widget) {
    var kept = placedPicks[keyOf(widget) + "|" + offerFor(widget)];
    var units = pickScope(widget);

    if (!kept) {
      return;
    }

    setPriming(true);

    for (var i = 0; i < units.length && i < kept.length; i++) {
      writeAxes(units[i].querySelectorAll(AXIS), kept[i]);
    }

    setPriming(false);
    memberThumbs(widget);
  }
  /** A member's picture follows its picked variation; the skin's own swap rides the quiet quote. */


  function memberThumbs(widget) {
    var F = skins();

    if (!F || !F.swapThumb || !F.variantImage) {
      return;
    }

    widget.querySelectorAll(MEMBER + ", .wffn-bundles-fb-single").forEach(function (row) {
      F.swapThumb(row, ".wffn-bundles-fb-thumb img", F.variantImage(row.querySelector(".wffn-bundles-variant")));
    });
  }

  function companionBoxes(widget) {
    return widget.querySelectorAll('.wffn-bundles-fbt-item:not([data-wffn-bundles-key="host"]) .wffn-bundles-fbt-check');
  }

  function rememberTicks(widget) {
    var boxes = companionBoxes(widget);
    var ticks = {};

    for (var i = 0; i < boxes.length; i++) {
      ticks[boxes[i].closest(".wffn-bundles-fbt-item").getAttribute("data-wffn-bundles-key")] = boxes[i].checked;
    }

    placedTicks[keyOf(widget)] = ticks;
  }

  function primeTicks(widget) {
    var kept = placedTicks[keyOf(widget)];

    if (!kept) {
      return;
    }

    setPriming(true);
    Object.keys(kept).forEach(function (id) {
      var box = widget.querySelector('.wffn-bundles-fbt-item[data-wffn-bundles-key="' + id + '"] .wffn-bundles-fbt-check');

      if (box && !box.disabled && box.checked !== kept[id]) {
        box.checked = kept[id];
        box.dispatchEvent(new Event("change", {
          bubbles: true
        }));
      }
    });
    setPriming(false);
  }
  /**
   * Show the gifts the shopper picked from a list as picked again. The repaint draws them
   * unpicked, and a re-place posts only what the widget shows, so the next change dropped them.
   */


  function primeGifts(widget) {
    var raw = widget.getAttribute("data-wfob-bundle-gifts");

    if (!raw || widget.getAttribute("data-wfob-bundle-gifts-primed")) {
      return;
    }

    widget.setAttribute("data-wfob-bundle-gifts-primed", "1");
    var chosen;

    try {
      chosen = JSON.parse(raw);
    } catch (e) {
      return;
    }

    setPriming(true);

    try {
      widget.dispatchEvent(new CustomEvent("wffn-bundles:restore-gifts", {
        bubbles: true,
        detail: {
          chosen: chosen
        }
      }));
    } finally {
      setPriming(false);
    }
  }
  /**
   * Drop what the shopper chose in a bump they have since taken back. Its price shows the offer
   * the widget opens on, so ticking it again must add that offer, not the one picked before.
   */


  function forget(key) {
    delete placed[key];
    delete placedTicks[key];
    Object.keys(placedPicks).forEach(function (k) {
      if (0 === k.indexOf(key + "|")) {
        delete placedPicks[k];
      }
    });
  }
  /** Take an inline bundle off the bump's own "choose an option" route. */


  function claimInline(widget) {
    var row = rowFor(widget);

    if (!row) {
      return;
    }

    row.querySelectorAll(".wfob_choose_variation").forEach(function (el) {
      el.classList.remove("wfob_choose_variation");
    });
    var link = row.querySelector(".wfob_qv-button");

    if (link) {
      link.hidden = true;
    }
  }

  function sync() {
    detachRemoved();
    hook();
    quietQuotes(); // A refresh redraws the bumps; one still waiting to re-place stays busy.

    document.querySelectorAll(BUMP + ".wfob-bundle-busy").forEach(function (row) {
      busy(row, false);
    });
    Object.keys(pending).forEach(function (key) {
      var live = pending[key] && widgetFor(key);

      if (live) {
        busy(rowFor(live), true);
      }
    });
    document.querySelectorAll(WIDGET).forEach(function (widget) {
      if (widget.closest(QV)) {
        return;
      }

      claimInline(widget);
      widget.hidden = !accepted(widget);

      if (widget.hidden) {
        forget(keyOf(widget));
      }

      nest(widget);
      attach(widget);
      primeDefaults(widget);
      primeOffer(widget);
      primePicks(widget);
      primeTicks(widget);
      primeGifts(widget);
    });
  }
  /** The markup a bump's choices live in, inline or in the quick view. */


  function widgetFor(key) {
    var inline = document.querySelector(WIDGET + '[data-wfob-bundle-key="' + key + '"]');

    if (inline) {
      return inline;
    }

    var modal = document.querySelector(QV);
    return modal && modal.getAttribute("data-item-key") === key ? modal.querySelector(WIDGET) : null;
  }
  /** The variation the bump's own line is added at, matched against the printed map. */


  function anchorVariation(widget) {
    var F = skins();
    var raw = widget.getAttribute("data-wfob-bundle-variations");
    var offer = offerFor(widget);
    var unit = null;
    var tier = tierIn(widget);

    if (tier) {
      // A tier's first line is its first unit's variation.
      unit = tier.querySelector(UNIT);
    } else if ("single" === offer) {
      unit = widget.querySelector(".wffn-bundles-fb-single");
    } else if ("fbt-set" === offer) {
      unit = widget.querySelector('.wffn-bundles-fbt-item[data-wffn-bundles-key="host"]');
    }

    if (!raw || !unit || !F || !F.readAttributes) {
      return null;
    }

    var map;

    try {
      map = JSON.parse(raw);
    } catch (e) {
      return null;
    }

    var picked = F.readAttributes(unit);
    var found = Object.keys(map).filter(function (id) {
      return Object.keys(map[id]).every(function (label) {
        return !map[id][label] || map[id][label] === picked[label];
      });
    })[0];
    return found ? {
      id: found,
      attributes: picked
    } : null;
  }
  /** Whether the bump can be added on what the widget shows; a variable pick must be complete. */


  function ready(widget) {
    var F = skins();
    var tier = tierIn(widget);

    if (tier) {
      return !F || !F.axesComplete || F.axesComplete(tier);
    }

    return "bundle" === offerFor(widget) || !widget.getAttribute("data-wfob-bundle-variations") || !!anchorVariation(widget);
  }
  /** Add the bundle's choices to the bump's add request. */


  function hook() {
    if (hooked || !window.wfob_frontend || !window.wfob_frontend.hooks) {
      return;
    }

    hooked = true;
    window.wfob_frontend.hooks.addFilter("wfob_before_ajax_data_add_order_bump", function (data) {
      var F = skins();
      var key = data && data.product_key;
      var widget = key ? widgetFor(key) : null;

      if (!widget || !F) {
        return data;
      }

      var offer = offerFor(widget);
      data.wfob_bundle_offer = offer;
      var set = setIn(widget);
      var tier = tierIn(widget);

      if (set && F.fbtSelection) {
        data.wfob_bundle_items = JSON.stringify(F.fbtSelection(set, true) || {});
      } else if (tier && F.readAttributes) {
        // A simple tier has no units to pick; the server adds the product at the tier quantity.
        data.wfob_bundle_items = JSON.stringify(F.axesIn(tier).length ? Array.prototype.map.call(tier.querySelectorAll(UNIT), function (u) {
          return {
            attributes: F.readAttributes(u)
          };
        }) : []);
      } else if (F.readAttributes) {
        data.wfob_bundle_items = JSON.stringify(Array.prototype.map.call(widget.querySelectorAll(MEMBER), function (m) {
          return {
            attributes: F.readAttributes(m)
          };
        }));
      }

      if (F.selectionExtras) {
        data.wfob_bundle_addons = JSON.stringify(F.selectionExtras(widget));
      } // The bump cannot stock-check a variable parent, so the line needs its variation.


      var pick = anchorVariation(widget);

      if (pick) {
        data.variation_id = pick.id;
        data.attributes = pick.attributes;
      }

      placed[key] = offer;
      return data;
    });
  }

  function send(row, key, removeKey) {
    window.set_data({
      action: "add_order_bump",
      type: "post",
      remove_item_key: removeKey,
      product_key: key,
      wfob_id: row.getAttribute("data-wfob-id"),
      qty: 1
    });
  }
  /** Replace an accepted bump's lines under the shopper's new choice. */


  function readd(widget) {
    var row = rowFor(widget);
    var cartKey = row && row.getAttribute("cart_key");
    var key = keyOf(widget);

    if (priming || attaching || !cartKey || typeof window.set_data !== "function") {
      return;
    }

    if (!ready(widget)) {
      return;
    }

    var wait = lastReadd[key] ? READD_GAP - (Date.now() - lastReadd[key]) : 0;

    if (wait > 0) {
      busy(row, true);

      if (!pending[key]) {
        pending[key] = window.setTimeout(function () {
          pending[key] = null;
          var live = widgetFor(key);

          if (live) {
            readd(live);
          }
        }, wait);
      }

      return;
    }

    lastReadd[key] = Date.now();
    busy(row, true);
    send(row, key, cartKey);
  }
  /** Place a held-back variable bump once its pick completes, else re-place. */


  function commitPick(widget) {
    if (widget.getAttribute("data-wfob-bundle-await")) {
      if (ready(widget)) {
        widget.removeAttribute("data-wfob-bundle-await");
        var row = rowFor(widget);

        if (row && !row.getAttribute("cart_key") && typeof window.set_data === "function") {
          send(row, keyOf(widget), "");
        }
      }

      return;
    }

    readd(widget);
  } // Capture: a variable bump with no variation picked waits for the pick instead of adding.


  document.addEventListener("change", function (e) {
    var box = e.target && e.target.closest ? e.target.closest("input.wfob_checkbox") : null;

    if (!box || !box.checked) {
      return;
    }

    var row = box.closest(BUMP);
    var widget = widgetOf(row);

    if (!widget || row.getAttribute("cart_key") || ready(widget)) {
      return;
    }

    e.stopPropagation();
    widget.setAttribute("data-wfob-bundle-await", "1");
    sync();
  }, true);
  document.addEventListener("change", function (e) {
    if (!e.target || !e.target.closest) {
      return;
    }

    var widget = e.target.closest(WIDGET);

    if (widget && e.target.closest(AXIS)) {
      memberThumbs(widget);
    }

    if (widget && widget.closest(QV)) {
      return;
    }

    if (widget && !priming && e.target.closest(".wffn-bundles-fbt-check")) {
      rememberTicks(widget);
      readd(widget);
      return;
    }

    if (widget && e.target.closest(AXIS)) {
      if (priming) {
        return;
      }

      rememberPicks(widget); // A re-place repaints the checkout and would close an open options popup.

      var popup = e.target.closest(".wffn-bundles-variant--open");

      if (popup) {
        popup._wfobBundleKey = keyOf(widget);
        return;
      }

      commitPick(widget);
      return;
    }

    if (!widget && e.target.closest(BUMP)) {
      sync();
    }
  }); // Capture: the popup's own close handler stops the click bubbling.

  document.addEventListener("click", function (e) {
    var close = e.target && e.target.closest ? e.target.closest("[data-act=vclose]") : null;
    var popup = close && close.closest(".wffn-bundles-variant--open");

    if (!popup || !popup._wfobBundleKey) {
      return;
    }

    var key = popup._wfobBundleKey;
    popup._wfobBundleKey = null;
    window.setTimeout(function () {
      var live = widgetFor(key);

      if (live) {
        commitPick(live);
      }
    }, 0);
  }, true); // `sublium-bfw:change`: a plan picked in the subscription strip the widget carries.

  ["wffn-bundles:fb-change", "wffn-bundles:tier-change", "wffn-bundles:addon-change", "sublium-bfw:change"].forEach(function (name) {
    document.addEventListener(name, function (e) {
      var widget = priming || attaching ? null : e.target && e.target.closest ? e.target.closest(WIDGET) : null;

      if (!widget || widget.closest(QV)) {
        return;
      }

      var key = keyOf(widget);
      var now = offerFor(widget);

      if (("wffn-bundles:fb-change" === name || "wffn-bundles:tier-change" === name) && placed[key] === now) {
        return;
      }

      placed[key] = now; // The widget unlocks the gifts the new tier earns right after this event, so read its choices one tick later: read now, a Buy 6 bump posts no gifts and the order carries none.

      window.setTimeout(function () {
        var live = widgetFor(key) || widget;

        if (live && document.contains(live)) {
          readd(live);
        }
      }, 0);
    });
  }); // Capture: on a button skin the first click opens the widget rather than buying its opening offer.

  document.addEventListener("click", function (e) {
    var button = e.target && e.target.closest ? e.target.closest(".wfob_l3_f_btn.wfob_btn_add") : null;

    if (!button || button.classList.contains("wfob_btn_remove")) {
      return;
    }

    var widget = widgetOf(button.closest(BUMP));

    if (!widget || accepted(widget)) {
      return;
    }

    e.preventDefault();
    e.stopPropagation();
    widget.setAttribute("data-wfob-bundle-open", "1");
    sync();
  }, true);
  document.addEventListener("click", function (e) {
    var row = e.target && e.target.closest ? e.target.closest(BUMP) : null;

    if (row) {
      opener = row.getAttribute("data-wfob-id") || "";
    }
  }, true);
  /** Bind the widget the quick view injected over AJAX. */

  function attachQuickView() {
    var F = skins();
    var modal = document.querySelector(QV);

    if (!F || !modal || !modal.querySelector(WIDGET)) {
      return;
    }

    if (opener) {
      modal.classList.add("wfob-bundle-design-" + opener);
    }

    hook();
    F.rescan(modal);
    window.setTimeout(syncQuickViewVariation, 0);
  }
  /** The form select that owns one axis, matched on its attribute name. */


  function axisSelect(form, label, value) {
    var want = String(label).toLowerCase().replace(/[^a-z0-9]+/g, "");
    var fallback = null;
    var selects = form.querySelectorAll('select[name^="attribute_"]');

    for (var i = 0; i < selects.length; i++) {
      var name = selects[i].name.replace(/^attribute_/, "").replace(/^pa[_-]/, "");

      if (name.toLowerCase().replace(/[^a-z0-9]+/g, "") === want) {
        return selects[i];
      }

      for (var o = 0; o < selects[i].options.length; o++) {
        if (selects[i].options[o].value === value) {
          fallback = fallback || selects[i];
        }
      }
    }

    return fallback;
  }
  /** Carry the widget's pick into the quick view's own hidden variations form. */


  function syncQuickViewVariation() {
    var F = skins();
    var modal = document.querySelector(QV);
    var form = modal ? modal.querySelector("form.variations_form") : null;
    var widget = modal ? modal.querySelector(WIDGET) : null;

    if (!F || !F.readAttributes || !form || !widget) {
      return;
    }

    var scope = widget.querySelector(TIER + " " + UNIT) || widget.querySelector('.wffn-bundles-fbt-item[data-wffn-bundles-key="host"]') || widget.querySelector(".wffn-bundles-fb-single") || widget;
    var attrs = F.readAttributes(scope);
    var wrote = false;
    Object.keys(attrs).forEach(function (label) {
      var select = axisSelect(form, label, attrs[label]);

      if (select && select.value !== attrs[label]) {
        select.value = attrs[label];
        wrote = true;
      }
    });

    if (wrote && window.jQuery) {
      window.jQuery(form).find('select[name^="attribute_"]').trigger("change");
      window.jQuery(form).trigger("check_variations");
    }
  }
  /** Send the quick view's add when the panel has no variations form for the bump to send. */


  function quickViewAdd() {
    var panel = document.querySelector(QV);

    if (!panel || !panel.querySelector(WIDGET) || panel.querySelector(".variations_form")) {
      return;
    }

    var key = panel.getAttribute("data-item-key");
    var row = key ? document.querySelector(BUMP + '[data-product-key="' + key + '"]') : null;

    if (row && typeof window.set_data === "function") {
      send(row, key, panel.getAttribute("data-cart-key") || row.getAttribute("cart_key") || "");
    }
  }

  document.addEventListener("change", function (e) {
    if (e.target && e.target.closest && e.target.closest(QV)) {
      window.setTimeout(syncQuickViewVariation, 0);
    }
  });
  document.addEventListener("click", function (e) {
    if (e.target && e.target.closest && e.target.closest(QV + " .wfob_single_add_to_cart_button")) {
      quickViewAdd();
    }
  });

  if (window.jQuery) {
    window.jQuery(document.body).on("wfob_quick_view_open", function () {
      window.setTimeout(attachQuickView, 0);
    }); // Before sync: a refused add leaves the previous line in the cart, so the repaint keeps the offer drawn from it.

    window.jQuery(document.body).on("updated_checkout", function (e, data) {
      var res = data && data.fragments ? data.fragments.wfob_ajax_data : null;

      if (res && "add_order_bump" === res.action && !res.status && res.wfob_product_key) {
        delete placed[res.wfob_product_key];
      }
    });
    window.jQuery(document.body).on("updated_checkout wfob_product_added wfob_product_removed", sync);
    window.jQuery(document.body).on("checkout_error", function () {
      document.querySelectorAll(BUMP + ".wfob-bundle-busy").forEach(function (row) {
        busy(row, false);
      });
    });
  }

  quietQuotes();
  document.addEventListener("DOMContentLoaded", sync);
  sync();
})();
