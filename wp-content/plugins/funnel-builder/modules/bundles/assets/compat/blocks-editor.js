/**
 * Block editor compatibility — show the bundle above WooCommerce's add-to-cart
 * blocks in the canvas.
 *
 * Neither woocommerce/add-to-cart-with-options nor add-to-cart-form
 * server-renders in the editor, so the storefront hook never fires there. Both
 * declare usesContext.postId, so the product comes from the block's context.
 */
(function (wp) {
	"use strict";

	if (
		!wp ||
		!wp.hooks ||
		!wp.blocks ||
		!wp.element ||
		!wp.compose ||
		!wp.serverSideRender
	) {
		return;
	}

	var el = wp.element.createElement;
	var PREVIEW = "wffn-bundles/bundle-preview";

	// ServerSideRender resolves the block against the client registry before it
	// calls the REST route, so the PHP-side register_block_type is not enough.
	if (!wp.blocks.getBlockType(PREVIEW)) {
		wp.blocks.registerBlockType(PREVIEW, {
			apiVersion: 3,
			title: "FunnelKit Bundle",
			category: "woocommerce",
			supports: { inserter: false, html: false },
			attributes: { productId: { type: "integer", default: 0 } },
			edit: function () {
				return null;
			},
			save: function () {
				return null;
			},
		});
	}

	/** The blocks a bundle can appear above. */
	var TARGETS = [
		"woocommerce/add-to-cart-with-options",
		"woocommerce/add-to-cart-form",
		"woocommerce/product-button",
	];

	/**
	 * Is this block a card in a products grid rather than one product page?
	 *
	 * `postType` is the marker available here: product-button does not declare
	 * `singleProduct` in usesContext, so the editor never receives it.
	 */
	function inLoop(context) {
		return !!(
			context.queryId ||
			context.query ||
			context.collection ||
			context.postType
		);
	}

	/**
	 * product-button is also the submit button inside add-to-cart-with-options,
	 * so without this the preview renders twice for the same product.
	 */
	function nestedButton(name, context) {
		return (
			name === "woocommerce/product-button" &&
			!!context["woocommerce/isDescendantOfAddToCartWithOptions"]
		);
	}

	var withBundlePreview = wp.compose.createHigherOrderComponent(function (
		BlockEdit
	) {
		return function (props) {
			var context = props.context || {};
			if (
				TARGETS.indexOf(props.name) === -1 ||
				!context.postId ||
				inLoop(context) ||
				nestedButton(props.name, context)
			) {
				return el(BlockEdit, props);
			}
			return el(
				wp.element.Fragment,
				null,
				el(wp.serverSideRender, {
					block: PREVIEW,
					attributes: { productId: context.postId },
				}),
				el(BlockEdit, props)
			);
		};
	}, "wffnBundlesWithBundlePreview");

	wp.hooks.addFilter(
		"editor.BlockEdit",
		"wffn-bundles/bundle-preview",
		withBundlePreview
	);
})(window.wp);
