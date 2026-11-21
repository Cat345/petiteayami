/**
 * Block Cart / Checkout / mini-cart — render a bundle as a grouped product.
 *
 * The Store API attaches grouping data (group, role, bundleTitle) to each cart
 * item under the `wffn-bundles` extension. These checkout filters add classes and
 * relabel the container line; the rows stay separate cart items.
 */
(function () {
	"use strict";

	var bc = window.wc && window.wc.blocksCheckout;
	if (!bc || typeof bc.registerCheckoutFilters !== "function") {
		return;
	}

	function bundle(extensions) {
		return (extensions && extensions["wffn-bundles"]) || {};
	}

	function isContainer(b) {
		return "container" === b.role || "parent" === b.role || "heading" === b.role;
	}

	function headingPrice(value, extensions) {
		var b = bundle(extensions);
		return "heading" === b.role && b.headingTotal ? b.headingTotal + "<price/>" : value;
	}

	bc.registerCheckoutFilters("wffn-bundles", {
		cartItemClass: function (value, extensions, args) {
			var b = bundle(extensions);
			if (b.saveTotal) value += " wffn-bundles-cart-save";
			if ("heading" === b.role) return value + " wffn-bundles-cart-parent wffn-bundles-cart-heading wffn-bundles-cart-fixedqty";
			// A bought-together set is not indented: its lines stay ordinary rows, companions keep their quantity.
			if (b.leads) return value;
			if (b.indent) return value + " wffn-bundles-cart-fixedqty";
			if (isContainer(b)) return value + " wffn-bundles-cart-parent";
			if ("child" === b.role) return value + " wffn-bundles-cart-child";
			if (b.nested) return value + " wffn-bundles-cart-child wffn-bundles-cart-nested" + (false === b.fixedQty ? "" : " wffn-bundles-cart-fixedqty");
			// Granted at a quantity the offer fixes, so the Store API marks the line
			// non-editable and it renders as a dead input.
			var limits = args && args.cartItem && args.cartItem.quantity_limits;
			if (b.role && limits && false === limits.editable) return value + " wffn-bundles-cart-fixedqty";
			return value;
		},
		// The container line fronts the bundle and shows its title.
		itemName: function (value, extensions, args) {
			var b = bundle(extensions);
			if (isContainer(b) && b.bundleTitle) return b.bundleTitle;
			// A member's count is a Qty row from the Store API (Cart::item_meta), not part of the name.
			return value;
		},
		// Members are read-only — only the container can be removed.
		showRemoveItemLink: function (value, extensions) {
			var b = bundle(extensions);
			return "child" === b.role || b.nested ? false : value;
		},
		// The real saving rides in as text; WooCommerce's per-unit figure is hidden in CSS.
		saleBadgePriceFormat: function (value, extensions) {
			var b = bundle(extensions);
			return b.saveTotal ? b.saveTotal + "<price/>" : value;
		},
		// A heading is priced 0; its total rides in as text and the real price is hidden in CSS.
		subtotalPriceFormat: headingPrice,
		cartItemPrice: function (value, extensions, args) {
			var b = bundle(extensions);
			// The interactive mini-cart (raw Store API cart) prints this as one text node, so the 0 price cannot be hidden.
			if ("heading" === b.role && b.headingTotal && args && args.cart && Array.isArray(args.cart.items)) {
				return b.headingTotal;
			}
			return headingPrice(value, extensions);
		},
	});

	// Members follow their lead line's quantity: the shopper changed the lead, which is already announced.
	var hooks = window.wp && window.wp.hooks;
	if (hooks && typeof hooks.addFilter === "function") {
		hooks.addFilter("woocommerce_show_cart_item_quantity_changed_notice", "wffn-bundles", function (show, item) {
			var b = bundle(item && item.extensions);
			return "child" === b.role || (b.nested && false !== b.fixedQty) || b.indent ? false : show;
		});
		// A pack's members leave with the pack the shopper removed.
		hooks.addFilter("woocommerce_show_cart_item_removed_notice", "wffn-bundles", function (show, item) {
			var b = bundle(item && item.extensions);
			return "child" === b.role || b.nested ? false : show;
		});
	}
})();
