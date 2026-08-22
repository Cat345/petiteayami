<?php
defined( 'ABSPATH' ) || exit;
/**
 * Compatibility class for FunnelKit Payments integration with UpStroke.
 */

use Sublium_WCS\Includes\Helpers\Template;
use Sublium_WCS\Includes\Main\Product;
use Sublium_WCS\Plugin;
use Sublium_WCS\Includes\Helpers\Utility;

if ( ! class_exists( 'WFOCU_Sublium_Compatibility' ) ) {
	#[\AllowDynamicProperties]
	class WFOCU_Sublium_Compatibility {
		/**
		 * Cache for plans to avoid repetitive DB queries
		 *
		 * @var array
		 */
		private $plans_cache        = array();
		private $one_time_supported = false;

		/**
		 * Per-request cache of the true, undiscounted "on Sale Price" basis (the plan's own price
		 * before our discount is applied), keyed by product_id + plan_id. See
		 * override_cart_price_for_regular_basis_discount() — WooCommerce's own cart calculation runs
		 * calculate_totals() multiple times per request and caches each pass's *filtered* price back
		 * onto the product object, so by the 2nd pass $modified_price is already our own previous
		 * output, not the plan's native price — discounting it again would compound. Caching the first
		 * (correct) value we see and reusing it thereafter avoids that regardless of how many passes run.
		 *
		 * @var array
		 */
		private $sale_price_basis_cache = array();

		/**
		 * Encoding multiplier for Sublium plan variant IDs in product search results.
		 * combined_id = product_id * PLAN_ID_MULTIPLIER + plan_id
		 * Must be pure integer so the React app template literal stays valid JSON.
		 */
		const PLAN_ID_MULTIPLIER = 1000000;

		/** Plan IDs extracted from encoded combined IDs during a REST product-add request. */
		private $pending_checkout_plan_ids    = array();
		private $intercepted_checkout_step_id = 0;
		private $bump_plan_selector_removed   = false;

		/**
		 * Constructor
		 */
		public function __construct() {

			// Frontend hooks
			add_action( 'wfocu_add_custom_html_above_accept_button', array( $this, 'schemes_template_html' ), 20, 2 );
			add_action( 'wfocu_footer_after_print_scripts', array( $this, 'render_js' ) );

			// Register shortcode for Sublium plans selector
			add_shortcode( 'wfocu_sublium_plans_selector', array( $this, 'wfocu_sublium_plans_selector_output' ) );

			// Add shortcode to backend list for visibility
			add_filter( 'wfocu_shortcode_list', array( $this, 'add_sublium_plans_selector_to_list' ), 10, 1 );

			// Admin hooks
			add_filter( 'wfocu_rule_type_product_args', array( $this, 'register_rule_type' ), 10, 1 );
			add_filter( 'wfocu_params_localize_script_data', array( $this, 'register_subs_product_search_nonce' ) );
			add_action( 'wp_ajax_wfocu_subs_product_search', array( $this, 'subs_product_search' ) );

			// Data processing hooks
			add_filter( 'wfocu_offer_data', array( $this, 'add_scheme_plan_data' ), 10, 2 );
			add_filter( 'sublium_wcs_plan_data', array( $this, 'attach_discounting_data' ), 10, 2 );
			add_filter( 'sublium_wcs_subscription_price', array( $this, 'set_subscription_prices_for_calculation' ), 10, 3 );
			add_filter( 'wfocu_offer_after_product_added', array( $this, 'add_scheme_product_level' ), 10, 4 );

			// Validation hook to skip the upsell when the original order's gateway isn't one Sublium
			// supports for recurring billing (e.g. COD, or any gateway outside Sublium's own
			// whitelist) and the offer's product resolves to a Sublium plan.
			add_filter( 'wfocu_offer_validation_result', array( $this, 'maybe_validate_sublium_gateway_support' ), 10, 2 );
			add_filter( 'wfocu_variations_attributes', array( $this, 'modify_variations_attributes' ), 10, 4 );
			add_action( 'wfocu_offer_setup_completed', array( $this, 'clear_elementor_cache' ), 5, 2 );

			// Add has_sublium_plans key to product data in API responses
			add_filter( 'wfocu_offer_product_details', array( $this, 'add_sublium_plans_data' ), 10, 5 );
			add_filter( 'wfocu_offer_product_details', array( $this, 'append_plan_name_on_offer_get' ), 15, 5 );
			add_filter( 'wfocu_offer_product_display_name', array( $this, 'append_plan_name_to_product_dropdown' ), 10, 4 );
			add_filter( 'wfocu_offer_product_schema', array( $this, 'add_sublium_plans_data_schema' ), 10, 3 );
			add_filter( 'wfocu_offer_product_schema', array( $this, 'append_plan_name_on_offer_schema' ), 15, 3 );

			// Pre-selected plan: inject _sublium_data into upsell package at charge time
			add_filter( 'wfocu_upsell_package', array( $this, 'inject_preselected_plan_into_package' ) );

			// FunnelKit app product search: append plan variants
			add_filter( 'wffn_woocommerce_json_search_found_products', array( $this, 'append_sublium_plans_to_funnelkit_search' ), 10, 1 );
			add_filter( 'wffn_rest_api_checkout_add_product', array( $this, 'append_plan_name' ), 10, 2 );
			add_filter( 'wffn_rest_api_checkout_get_product', array( $this, 'append_plan_name_on_get' ), 10, 2 );
			// Checkout frontend: price only (plan name NOT appended to title on frontend).
			add_filter( 'wfacp_product_switcher_price_data', array( $this, 'set_switcher_plan_price' ), 25, 4 );
			// Checkout frontend: subscription billing summary below product name.
			add_filter( 'wfacp_subscription_string', array( $this, 'set_switcher_sublium_subscription_string' ), 10, 4 );
			// Checkout frontend: hide plan selector when plan is pre-assigned in backend.
			// WFACP's own discount engine (calculate_totals(), priority 1 on woocommerce_before_calculate_totals
			// — runs before Sublium's price filter engages) computes its cached _wfacp_item_discount display
			// value from the product's raw WC price/regular_price, ignoring the Sublium plan price entirely.
			// The real cart charge still ends up plan-correct (Sublium's own price filter wins by the time
			// totals are finalized), but the cached value is what's shown in the mini-cart/order review —
			// correct it at the source via WFACP's own extension point.
			add_filter( 'wfacp_product_raw_data', array( $this, 'fix_switcher_discount_basis_for_sublium_plan' ), 10, 2 );
			// _wfacp_item_discount is cached per-currency on the cart item and only recomputed when
			// forced — an existing session already carrying a stale (pre-fix or plan-unaware) cached
			// value would otherwise never pick up the corrected basis above on a plain page reload.
			// Safe to force every pass now that get_checkout_bump_display_price() always derives from
			// the stable true regular price rather than $product->get_price(), so repeated recomputation
			// can't compound the discount.
			add_filter( 'wfacp_force_calculate_discount', array( $this, 'force_recalculate_discount_for_sublium_plan' ), 10, 3 );

			// Order bump plan hooks.
			add_filter( 'wffn_rest_api_bump_add_product', array( $this, 'append_plan_name' ), 10, 2 );
			add_filter( 'wffn_rest_api_bump_get_product', array( $this, 'append_plan_name_on_get' ), 10, 2 );
			// Bump frontend: price, plan selector (hidden when pre-assigned), and subscription summary merge tag (plan name NOT appended to title).
			add_filter( 'wfob_product_switcher_price_data', array( $this, 'set_bump_plan_price' ), 25, 4 );
			// Same _wfob_item_discount display-cache fix as the checkout switcher above, for bumps.
			add_filter( 'wfob_product_raw_data', array( $this, 'fix_bump_discount_basis_for_sublium_plan' ), 10, 3 );
			add_filter( 'wfob_merge_tag_subscription_summary', array( $this, 'sublium_subscription_summary_merge_tag' ), 10, 5 );
			add_action( 'wfob_after_product_description', array( $this, 'maybe_hide_bump_plan_selector' ), 9, 3 );
			add_action( 'wfob_after_product_description', array( $this, 'restore_bump_plan_selector' ), 11, 0 );
			// Cart: inject sublium_wcs_plan from WFACP product options, then lock it.
			add_filter( 'woocommerce_add_cart_item_data', array( $this, 'inject_plan_from_product_options' ), 9, 1 );
			// Upsell offer plan hooks (plan name NOT appended to title on frontend).
			add_filter( 'wffn_rest_api_offer_add_product', array( $this, 'append_plan_name_on_offer_add' ), 10, 3 );
			// Read the selected plan out of products_meta_data (sent by the "Add Product" search modal)
			// and set it on the offer product's fields so append_plan_name_on_offer_schema/_get can find it.
			add_filter( 'wfocu_offer_add_product_fields', array( $this, 'inject_plan_id_into_offer_product_fields' ), 10, 3 );
			add_filter( 'wfocu_offer_save_product_fields', array( $this, 'preserve_plan_fields_on_offer_save' ), 10, 3 );
			// Order Bumps' "Add Product" flow: wffn_rest_api_bump_add_product/_get_product now fire
			// correctly (fixed upstream) and append_plan_name()/append_plan_name_on_get() already handle
			// title/price/plan_id for that products_meta_data shape — no separate hook needed here.
			// Preserve plan_id when saving offer — the save handler rebuilds fields from scratch and drops plan_id.
			add_filter( 'wfocu_update_offer_save_setting', array( $this, 'preserve_plan_id_on_save' ), 10, 3 );
			// Upsell frontend: hide plan selector and override price when plan is pre-assigned.
			add_filter( 'wfocu_sublium_plans_list', array( $this, 'maybe_hide_upsell_plan_selector' ), 10, 3 );
			add_filter( 'wfocu_product_raw_price', array( $this, 'override_price_for_preassigned_plan' ), 10, 3 );
			add_filter( 'wfocu_product_raw_sale_price', array( $this, 'override_price_for_preassigned_plan' ), 10, 3 );
			add_filter( 'wfocu_offer_product_data', array( $this, 'override_regular_price_for_preassigned_plan' ), 20, 5 );
			// Checkout switcher / Order bump cart items: force "on Regular Price" discount types to charge
			// off the product's true regular price, bypassing Sublium's own billing-cycle/signup-fee math —
			// same semantics as override_price_for_preassigned_plan() above, but applied at Sublium's final
			// cart-price filter since WFACP/WFOB items route through the real WC cart, not a direct charge.
			add_filter( 'sublium_wcs_subscription_price', array( $this, 'override_cart_price_for_regular_basis_discount' ), 10, 4 );
			// Sublium's own FunnelKit-checkout sidebar summary (includes/funnelkit/checkout.php) still
			// describes the plan's native billing (free trial, signup fee) even when the "on Regular
			// Price" bypass above means none of that applies to what's actually charged. Override the
			// text via Sublium's own filter rather than touching its files.
			add_filter( 'sublium_wcs_item_price_string', array( $this, 'override_sublium_item_price_string' ), 10, 2 );
			// "on Regular Price" discounts every recurring/renewal charge too, not just today's — override
			// the forward-looking "Recurring: $X / period" total Sublium projects from its own separate
			// $recurring_carts WC_Cart instances (built independently of WC()->cart, so the fix above
			// doesn't reach it).
			add_filter( 'sublium_wcs_woocommerce_cart_item_total', array( $this, 'override_recurring_cart_total_for_regular_basis_discount' ), 10, 2 );
			// Same, for the separate "Recurring Total" modal (templates/checkout/recurring-cart-view-details.php),
			// which renders per-item via a different filter (sublium_wcs_woocommerce_cart_item_subtotal)
			// against a different variable ($recurring_cart_item, not the whole $recurring_cart).
			add_filter( 'sublium_wcs_woocommerce_cart_item_subtotal', array( $this, 'override_recurring_cart_item_subtotal_for_regular_basis_discount' ), 10, 3 );
			// FunnelKit app checkout product add: handle "productId-planId" format
			add_filter( 'rest_pre_dispatch', array( $this, 'intercept_checkout_add_product_request' ), 10, 3 );
			add_action( 'updated_post_meta', array( $this, 'inject_plan_id_after_checkout_product_save' ), 10, 4 );
			add_action( 'added_post_meta', array( $this, 'inject_plan_id_after_checkout_product_save' ), 10, 4 );
		}
		public function clear_elementor_cache_on_offer_setup_completed() {
			$this->clear_elementor_cache();
		}
		public function append_plan_name( $default, $product ) {
			if ( isset( $default['products_meta_data'] ) && ! empty( $default['products_meta_data'][1] ) && isset( $default['products_meta_data'][1]['sublium_plan'] ) ) {
				$plan_id = absint( $default['products_meta_data'][1]['sublium_plan'] );
				$plan    = \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( $plan_id, $product );
				if ( ! is_null( $plan ) ) {
					$default['title']   = $default['title'] . ' - ' . strip_tags( $plan->get_title( $product ) );
					$default['plan_id'] = $plan_id;

					list( $regular_price, $offer_price ) = $this->get_checkout_bump_display_price( $product, $plan, $default['discount_type'] ?? '' );
					// Raw numbers, not wc_price()-wrapped HTML: the React Products table's
					// formatAmount() calls parseFloat() on these fields (matching what
					// append_plan_name_on_get() already returns for the GET/reload path) — an
					// HTML string parses as NaN and renders as a blank price immediately after
					// "Add", even though the same values are correct once reloaded.
					$default['price']         = $offer_price;
					$default['regular_price'] = $regular_price;
					$default['sale_price']    = $offer_price;
					$default['is_on_sale']    = true;
				}
			} elseif ( isset( $default['products_meta_data'] ) && ! empty( $default['products_meta_data'][1]['sublium_one_time'] ) ) {
				// Admin explicitly picked the "- One Time Purchase" search row (see
				// append_sublium_plans_to_funnelkit_search()) rather than a specific plan — label it
				// the same way so the Products table shows why no plan is attached, and mark
				// force_one_time so inject_plan_from_product_options()/maybe_hide_bump_plan_selector()
				// keep it locked as a plain purchase instead of Sublium/the bump template still
				// offering an "upgrade to subscription" prompt for it.
				$default['title']         = $default['title'] . ' - ' . $this->get_one_time_purchase_label();
				$default['force_one_time'] = true;
			}
			return $default;
		}

		/**
		 * The admin Products table's "Regular Price"/"Offer Price" basis for a Sublium-plan checkout/bump
		 * product — shared by append_plan_name()/append_plan_name_on_get() (checkout AND bump both use
		 * these same two functions). "Regular Price" is always the product's true WooCommerce regular
		 * price (a plain informational reference), regardless of discount_type. The second value is the
		 * "Offer Price" BASIS the admin table's own discount_type-aware JS (getDiscountPrice() in
		 * funnel-builder's product-table component) applies the row's discount_amount/discount_type to:
		 * the true regular price for "on Regular Price" types, or the plan's own price for "on Sale
		 * Price" types — matching override_cart_price_for_regular_basis_discount()'s real-charge basis.
		 * Deliberately NOT pre-discounted here — that JS only treats this basis (returned as the row's
		 * "sale_price" field) as the discount basis when "is_on_sale" is also true, so the calling
		 * functions must set that flag; see append_plan_name()/append_plan_name_on_get().
		 *
		 * @return array{0: float, 1: float} [true_regular_price, offer_price_basis]
		 */
		private function get_checkout_bump_display_price( $product, $plan, $discount_type ) {
			$true_regular_price = $this->get_true_regular_price( $product );
			$is_regular_type     = in_array( $discount_type, array( 'percent_discount_reg', 'fixed_discount_reg' ), true );
			// $true_regular_price (stable postmeta), not $product->get_price() — the latter may
			// already reflect a prior pass's plan/discount mutation on a repeatedly-processed cart
			// item (WFACP/WFOB re-run their discount calc on every woocommerce_before_calculate_totals
			// pass), which would compound the plan discount further on each subsequent call.
			$basis                = $is_regular_type ? $true_regular_price : $plan->get_recurring_cart_price( $true_regular_price, $product );

			return array( $true_regular_price, $basis );
		}

		public function append_plan_name_on_get( $product_data, $product ) {
			if ( empty( $product_data['plan_id'] ) ) {
				return $product_data;
			}

			$plan_id = absint( $product_data['plan_id'] );
			$plan    = \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( $plan_id, $product );
			if ( is_null( $plan ) ) {
				return $product_data;
			}

			$product_data['title'] = $product_data['title'] . ' - ' . strip_tags( $plan->get_title( $product ) );
			list( $product_data['regular_price'], $product_data['sale_price'] ) = $this->get_checkout_bump_display_price( $product, $plan, $product_data['discount_type'] ?? '' );
			$product_data['is_on_sale'] = true;

			return $product_data;
		}

		public function update_switcher_item_title( $item_name, $cart_item, $cart_item_key, $pro, $switcher_settings, $product_data ) {
			if ( empty( $product_data['plan_id'] ) || ! $pro instanceof \WC_Product ) {
				return $item_name;
			}
			$plan_id = absint( $product_data['plan_id'] );
			$plan    = \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( $plan_id, $pro );
			if ( is_null( $plan ) ) {
				return $item_name;
			}
			return $item_name . ' - ' . strip_tags( $plan->get_title( $pro ) );
		}

		public function set_switcher_plan_price( $price_data, $pro, $cart_item_key, $product_data = array() ) {
			if ( ! $pro instanceof \WC_Product ) {
				return $price_data;
			}
			// Skip for cart items — Sublium already handles their pricing via cart hooks.
			if ( is_string( $cart_item_key ) && '' !== $cart_item_key ) {
				return $price_data;
			}
			$plan_id = ! empty( $product_data['plan_id'] ) ? absint( $product_data['plan_id'] ) : 0;
			if ( $plan_id > 0 ) {
				$plan = \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( $plan_id, $pro );
			} else {
				// No pre-selected plan: show first plan price (Sublium auto-assigns the first plan at checkout).
				$plans = \Sublium_WCS\Includes\Main\Product::get_instance()->get_cached_plans_for_product( $pro );
				$plan  = ! empty( $plans ) ? reset( $plans ) : null;
			}
			if ( is_null( $plan ) ) {
				return $price_data;
			}
			// "on Regular Price" discount types show (and, via override_cart_price_for_regular_basis_discount,
			// actually charge) the true regular price discounted directly, bypassing the plan's own price —
			// keep this display value consistent with the real cart charge.
			if ( ! empty( $product_data['discount_type'] ) && in_array( $product_data['discount_type'], array( 'percent_discount_reg', 'fixed_discount_reg' ), true ) ) {
				// get_true_regular_price(), not get_regular_price(): for quantity > 1, WFACP_Common::
				// set_product_price() may already have mutated $pro's regular_price prop to
				// regular_price * quantity by the time this runs, which would double-count quantity
				// once price_data['price'] is itself multiplied by $org_quantity below.
				$true_regular_price = $this->get_true_regular_price( $pro );
				$plan_price         = WFACP_Common::calculate_discount(
					array(
						'wfacp_product_rp'      => $true_regular_price,
						'wfacp_product_p'       => (float) $pro->get_price(),
						'wfacp_discount_amount' => ! empty( $product_data['discount_amount'] ) ? floatval( $product_data['discount_amount'] ) : 0,
						'wfacp_discount_type'   => $product_data['discount_type'],
					)
				);
				$plan_price = is_null( $plan_price ) ? $true_regular_price : $plan_price;
				$price_data['wfacp_regular_basis_discount'] = true;
			} else {
				$plan_price = $plan->get_recurring_cart_price( $pro->get_price(), $pro );
				// "on Sale Price" discounts Sublium's own plan price on top — keep this display value
				// consistent with the real cart charge (override_cart_price_for_regular_basis_discount()).
				if ( ! empty( $product_data['discount_type'] ) && ! empty( $product_data['discount_amount'] ) && in_array( $product_data['discount_type'], array( 'percent_discount_sale', 'fixed_discount_sale' ), true ) ) {
					$discounted = WFACP_Common::calculate_discount(
						array(
							'wfacp_product_rp'      => $this->get_true_regular_price( $pro ),
							'wfacp_product_p'       => $plan_price,
							'wfacp_discount_amount' => floatval( $product_data['discount_amount'] ),
							'wfacp_discount_type'   => $product_data['discount_type'],
						)
					);
					$plan_price = is_null( $discounted ) ? $plan_price : $discounted;
				}
			}
			$org_quantity = ! empty( $product_data['org_quantity'] ) ? absint( $product_data['org_quantity'] ) : 1;
			$rg_price     = (float) $pro->get_regular_price();
			$rg_price     = (float) apply_filters( 'sublium_wcs_plan_regular_price', $rg_price, $pro, $plan );
			// Multiply by org_quantity: WFACP shows price_data['price'] directly without any qty multiplication.
			$price_data['regular_org']      = ( $rg_price > 0 ? $rg_price : $plan_price ) * $org_quantity;
			$price_data['price']            = $plan_price * $org_quantity;
			$price_data['sublium_plan_id']  = $plan->get_id();
			$price_data['sublium_plan_obj'] = $plan;
			return $price_data;
		}

		public function set_switcher_sublium_subscription_string( $string, $pro, $price_data, $cart_item_key ) {
			if ( '' !== $string || ! $pro instanceof \WC_Product ) {
				return $string;
			}
			//var_dump($price_data);

			// For cart items, resolve plan from cart item data.
			if ( is_string( $cart_item_key ) && '' !== $cart_item_key ) {
				$cart = WC()->cart ? WC()->cart->get_cart() : array();
				if ( empty( $cart[ $cart_item_key ]['sublium_wcs_plan'] ) ) {
					return $string;
				}

				// "on Regular Price" bypasses the plan's own recurring/free-trial/signup-fee billing —
				// Sublium's own stored sublium_wcs_plan_summary still describes that native billing,
				// which no longer matches what's actually charged. Override with the real amount.
				$options = isset( $cart[ $cart_item_key ]['_wfacp_options'] ) ? $cart[ $cart_item_key ]['_wfacp_options'] : ( isset( $cart[ $cart_item_key ]['_wfob_options'] ) ? $cart[ $cart_item_key ]['_wfob_options'] : null );
				if ( ! empty( $options['discount_type'] ) && in_array( $options['discount_type'], array( 'percent_discount_reg', 'fixed_discount_reg' ), true ) ) {
					// This template renders after calculate_totals() has fully settled — safe to read
					// the final filtered price here (unlike inside override_cart_price_for_regular_basis_discount(),
					// which is itself part of that same filter chain and must avoid re-triggering it).
					// get_price() is per-unit — multiply by the cart item's own quantity to match the
					// real line total (WooCommerce multiplies quantity separately, this string doesn't).
					$qty = ! empty( $cart[ $cart_item_key ]['quantity'] ) ? absint( $cart[ $cart_item_key ]['quantity'] ) : 1;
					return sprintf(
						/* translators: %s: price charged today */
						__( 'Billed %s today.', 'woofunnels-upstroke-power-pack' ),
						wc_price( $pro->get_price() * $qty )
					);
				}

				return $cart[ $cart_item_key ]['sublium_wcs_plan_summary']??"";

			} else {
				// For non-cart switcher items, plan was resolved in set_switcher_plan_price.
				if ( empty( $price_data['sublium_plan_obj'] ) ) {
					return $string;
				}
				$plan = $price_data['sublium_plan_obj'];

				if ( ! empty( $price_data['wfacp_regular_basis_discount'] ) ) {
					return sprintf(
						/* translators: %s: price charged today */
						__( 'Billed %s today.', 'woofunnels-upstroke-power-pack' ),
						wc_price( $price_data['price'] )
					);
				}
			}

			if ( ! $plan ) {
				return $string;
			}

			return $plan->display_summary( $pro ,absint($price_data['quantity']));
		}

		public function append_plan_name_to_bump_product_name( $product_name, $pro, $cart_item_key, $product_data = array() ) {
			if ( empty( $product_data['plan_id'] ) || ! $pro instanceof \WC_Product ) {
				return $product_name;
			}
			$plan_id = absint( $product_data['plan_id'] );
			$plan    = \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( $plan_id, $pro );
			if ( is_null( $plan ) ) {
				return $product_name;
			}
			return $product_name . ' - ' . strip_tags( $plan->get_title( $pro ) );
		}

		public function set_bump_plan_price( $price_data, $pro, $qty, $product_data = array() ) {
			if ( ! $pro instanceof \WC_Product ) {
				return $price_data;
			}
			$plan_id = ! empty( $product_data['plan_id'] ) ? absint( $product_data['plan_id'] ) : 0;
			if ( $plan_id > 0 ) {
				$plan = \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( $plan_id, $pro );
			} else {
				// No pre-selected plan: show first plan price (Sublium auto-assigns the first plan at checkout).
				$plans = \Sublium_WCS\Includes\Main\Product::get_instance()->get_cached_plans_for_product( $pro );
				$plan  = ! empty( $plans ) ? reset( $plans ) : null;
			}
			if ( is_null( $plan ) ) {
				return $price_data;
			}

			// "on Regular Price" discount types show (and, via override_cart_price_for_regular_basis_discount,
			// actually charge) the true regular price discounted directly, bypassing the plan's own price —
			// keep this display value consistent with the real cart charge.
			if ( ! empty( $product_data['discount_type'] ) && in_array( $product_data['discount_type'], array( 'percent_discount_reg', 'fixed_discount_reg' ), true ) ) {
				$true_regular_price = $this->get_true_regular_price( $pro );
				$plan_price         = WFOB_Common::calculate_discount(
					array(
						'wfob_product_rp'      => $true_regular_price,
						'wfob_product_p'       => (float) $pro->get_price(),
						'wfob_discount_amount' => ! empty( $product_data['discount_amount'] ) ? floatval( $product_data['discount_amount'] ) : 0,
						'wfob_discount_type'   => $product_data['discount_type'],
					)
				);
				$plan_price = is_null( $plan_price ) ? $true_regular_price : $plan_price;
			} else {
				// For per-product plans (object_type=1), product_plan_map_data holds the relation pricing.
				// get_recurring_cart_price() uses it directly for type 2 (Subscription), ignoring input price.
				// For global/taxonomy plans (no relation pricing), fall back to the WC product base price.
				$map_data = $plan->get_product_plan_map_data( $pro );
				if ( ! empty( $map_data ) && isset( $map_data['sale_price'] ) ) {
					$plan_price = ! empty( $map_data['sale_price'] )
						? floatval( $map_data['sale_price'] )
						: floatval( $map_data['regular_price'] );
					$plan_price = apply_filters( 'sublium_wcs_subscription_price', $plan_price, $pro, $plan );
				} else {
					// Use get_base_price() (reads raw WC meta) to avoid double-discount: Sublium hooks
					// woocommerce_product_get_price to return the plan price, so $pro->get_price() already
					// reflects the plan discount. Passing that into get_recurring_cart_price() would apply
					// the plan discount a second time.
					$base_price = \Sublium_WCS\Includes\Abstracts\Plan::get_base_price( $pro );
					$plan_price = $plan->get_recurring_cart_price( $base_price, $pro );
				}

				// "on Sale Price" discounts Sublium's own plan price on top — keep this display value
				// consistent with the real cart charge (override_cart_price_for_regular_basis_discount()).
				if ( ! empty( $product_data['discount_type'] ) && ! empty( $product_data['discount_amount'] ) && in_array( $product_data['discount_type'], array( 'percent_discount_sale', 'fixed_discount_sale' ), true ) ) {
					$discounted = WFOB_Common::calculate_discount(
						array(
							'wfob_product_rp'      => $this->get_true_regular_price( $pro ),
							'wfob_product_p'       => $plan_price,
							'wfob_discount_amount' => floatval( $product_data['discount_amount'] ),
							'wfob_discount_type'   => $product_data['discount_type'],
						)
					);
					$plan_price = is_null( $discounted ) ? $plan_price : $discounted;
				}
			}

			$price_data['regular_org'] = $plan_price;
			$price_data['price']       = $plan_price;
			return $price_data;
		}

		/**
		 * Correct the basis WFACP_Public::modify_calculate_price_per_session() uses to compute
		 * _wfacp_item_discount (shown in the mini-cart/order review) for a checkout switcher product on
		 * a Sublium plan. That method reads $product->get_data()['regular_price']/['price'] — the
		 * product's raw WooCommerce price — before Sublium's own price filter has engaged (WFACP's
		 * calculate_totals() runs at priority 1 on woocommerce_before_calculate_totals, earlier than
		 * Sublium's), so without this it discounts off the undiscounted $49.99 instead of the plan's
		 * $37.49, e.g. showing "$47.99" instead of "$35.49" for a $2 fixed discount off sale price.
		 *
		 * @param array       $raw_data $product->get_data().
		 * @param \WC_Product $product  The cart item's product object.
		 *
		 * @return array
		 */
		/**
		 * Force WFACP to recompute _wfacp_item_discount for Sublium-plan checkout switcher items
		 * instead of trusting the per-currency cache, so a session already carrying a stale value
		 * (from before this fix, or from a discount_amount change) self-corrects on the next page
		 * load rather than needing the cart to be manually cleared.
		 *
		 * @param bool   $force
		 * @param string $key
		 * @param array  $item
		 *
		 * @return bool
		 */
		public function force_recalculate_discount_for_sublium_plan( $force, $key, $item ) {
			if ( $force ) {
				return $force;
			}
			return ! empty( $item['_wfacp_options']['plan_id'] );
		}

		public function fix_switcher_discount_basis_for_sublium_plan( $raw_data, $product ) {
			if ( ! $this->is_enable() || ! $product instanceof \WC_Product || ! function_exists( 'WC' ) || ! WC()->cart ) {
				return $raw_data;
			}
			// get_cart_contents() (not get_cart(), which can rebuild fresh product clones from session
			// mid-request) returns the exact array WFACP's own calculate_totals() is iterating and
			// passed us $product from, so identity match is reliable here.
			foreach ( WC()->cart->get_cart_contents() as $cart_item ) {
				if ( ! isset( $cart_item['data'] ) || $cart_item['data'] !== $product ) {
					continue;
				}
				$options = isset( $cart_item['_wfacp_options'] ) ? $cart_item['_wfacp_options'] : null;
				if ( empty( $options['plan_id'] ) ) {
					return $raw_data;
				}
				$plan = \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( absint( $options['plan_id'] ), $product );
				if ( is_null( $plan ) ) {
					return $raw_data;
				}
				list( $raw_data['regular_price'], $raw_data['price'] ) = $this->get_checkout_bump_display_price( $product, $plan, $options['discount_type'] ?? '' );
				return $raw_data;
			}
			return $raw_data;
		}

		/**
		 * Same as fix_switcher_discount_basis_for_sublium_plan() above, for order bump's equivalent
		 * _wfob_item_discount cache (WFOB_Public::modify_calculate_price_per_session()). wfob_product_raw_data
		 * conveniently passes the cart item key directly, so no identity-matching loop is needed here.
		 *
		 * @param array       $raw_data $product->get_data().
		 * @param \WC_Product $product  The cart item's product object.
		 * @param string      $key      Cart item key.
		 *
		 * @return array
		 */
		public function fix_bump_discount_basis_for_sublium_plan( $raw_data, $product, $key ) {
			if ( ! $this->is_enable() || ! $product instanceof \WC_Product || ! function_exists( 'WC' ) || ! WC()->cart ) {
				return $raw_data;
			}
			$cart_item = WC()->cart->get_cart_item( $key );
			$options   = isset( $cart_item['_wfob_options'] ) ? $cart_item['_wfob_options'] : null;
			if ( empty( $options['plan_id'] ) ) {
				return $raw_data;
			}
			$plan = \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( absint( $options['plan_id'] ), $product );
			if ( is_null( $plan ) ) {
				return $raw_data;
			}
			list( $raw_data['regular_price'], $raw_data['price'] ) = $this->get_checkout_bump_display_price( $product, $plan, $options['discount_type'] ?? '' );
			return $raw_data;
		}

		public function maybe_hide_bump_plan_selector( $pro, $cart_item_key, $data = array() ) {
			if ( ! empty( $data['plan_id'] ) || ! empty( $data['force_one_time'] ) ) {
				remove_action( 'wfob_after_product_description', array( 'Sublium_WCS\\Includes\\Funnelkit\\Checkout', 'show_plan_selector_inside_bump' ), 10 );
				$this->bump_plan_selector_removed = true;
			}
		}

		public function restore_bump_plan_selector() {
			if ( $this->bump_plan_selector_removed ) {
				add_action( 'wfob_after_product_description', array( 'Sublium_WCS\\Includes\\Funnelkit\\Checkout', 'show_plan_selector_inside_bump' ), 10, 2 );
				$this->bump_plan_selector_removed = false;
			}
		}



		public function sublium_subscription_summary_merge_tag( $result, $product, $cart_item, $cart_item_key, $product_data ) {
			if ( '' !== $result || ! $product instanceof \WC_Product ) {
				return $result;
			}

			// Always compute per-item billing via display_summary() which uses the product's own base price.
			// Do NOT use sublium_wcs_plan_summary from the cart item — that holds the total recurring cart
			// amount across all subscription items, not the per-item price.
			$plan_id = ! empty( $product_data['plan_id'] ) ? absint( $product_data['plan_id'] ) : 0;
			if ( $plan_id > 0 ) {
				$plan = \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( $plan_id, $product );
			} else {
				$plans = \Sublium_WCS\Includes\Main\Plans::get_sorted_plans_by_product( $product );
				$plan  = ! empty( $plans ) ? reset( $plans ) : null;
			}

			if ( ! $plan ) {
				return $result;
			}

			// WFOB bump data uses 'quantity'; WFACP checkout data uses 'org_quantity'.
			$qty = ! empty( $product_data['org_quantity'] ) ? absint( $product_data['org_quantity'] ) : ( ! empty( $product_data['quantity'] ) ? absint( $product_data['quantity'] ) : 1 );

			// "on Regular Price" discount types charge the discounted true regular price today,
			// bypassing the plan's own recurring billing/free-trial/signup-fee — display_summary()'s
			// native wording ("after N days free trial", "one-time signup fee") would be actively
			// misleading here, since none of that applies to what's actually charged.
			if ( ! empty( $product_data['discount_type'] ) && in_array( $product_data['discount_type'], array( 'percent_discount_reg', 'fixed_discount_reg' ), true ) ) {
				$true_regular_price = $this->get_true_regular_price( $product );
				$new_price          = WFOB_Common::calculate_discount(
					array(
						'wfob_product_rp'      => $true_regular_price,
						'wfob_product_p'       => (float) $product->get_price( 'edit' ),
						'wfob_discount_amount' => ! empty( $product_data['discount_amount'] ) ? floatval( $product_data['discount_amount'] ) : 0,
						'wfob_discount_type'   => $product_data['discount_type'],
					)
				);
				$new_price = is_null( $new_price ) ? $true_regular_price : $new_price;

				return sprintf(
					/* translators: %s: price charged today */
					__( 'Billed %s today.', 'woofunnels-upstroke-power-pack' ),
					wc_price( $new_price * $qty )
				);
			}

			$summary = $plan->display_summary( $product );

			// display_summary() computes its own per-unit recurring price internally and is not aware
			// of the bump's discount_type — so its wording is right (free trial/period phrasing) but the
			// dollar amount inside it needs correcting for qty > 1 and/or "on Sale Price" discounts.
			// "on Sale Price" discounts the plan's own recurring price, unlike "on Regular Price" above —
			// this legitimately applies to every billing cycle, so just correct the amount inside
			// Sublium's own native wording rather than replacing the whole string.
			$base_price    = \Sublium_WCS\Includes\Abstracts\Plan::get_base_price( $product );
			$unit_price    = $plan->get_recurring_cart_price( $base_price, $product );
			$final_price   = $unit_price;
			$needs_replace = ( $qty > 1 );

			if ( ! empty( $product_data['discount_type'] ) && ! empty( $product_data['discount_amount'] ) && in_array( $product_data['discount_type'], array( 'percent_discount_sale', 'fixed_discount_sale' ), true ) ) {
				$discounted = WFOB_Common::calculate_discount(
					array(
						'wfob_product_rp'      => $this->get_true_regular_price( $product ),
						'wfob_product_p'       => $unit_price,
						'wfob_discount_amount' => floatval( $product_data['discount_amount'] ),
						'wfob_discount_type'   => $product_data['discount_type'],
					)
				);
				if ( ! is_null( $discounted ) ) {
					$final_price   = $discounted;
					$needs_replace = true;
				}
			}

			if ( $unit_price > 0 && $needs_replace ) {
				$summary = str_replace( wc_price( $unit_price ), wc_price( $final_price * $qty ), $summary );
			}

			return $summary;
		}

		public function inject_plan_from_product_options( $cart_item_data ) {
				$options = isset( $cart_item_data['_wfacp_options'] ) ? $cart_item_data['_wfacp_options'] :(isset($cart_item_data['_wfob_options'])?$cart_item_data['_wfob_options']:[]);



			if ( empty( $options ) ) {
				return $cart_item_data;
			}
			// Checkout/offer product configs carry the selected plan nested under products_meta_data
			// (raw payload from the "Add Product" search modal). Order Bumps have no products_meta_data
			// at all — plan_id is a flat top-level key on the saved product config instead. Without this
			// fallback, a bump's cart item never gets sublium_wcs_plan set, so Sublium auto-picks the
			// product's first plan by ID instead of the one configured in the admin.
			$plan_id = 0;
			if ( isset( $options['products_meta_data'] ) && isset( $options['products_meta_data'][1] ) && ! empty( $options['products_meta_data'][1]['sublium_plan'] ) ) {
				$plan_id = absint( $options['products_meta_data'][1]['sublium_plan'] );
			} elseif ( ! empty( $options['plan_id'] ) ) {
				$plan_id = absint( $options['plan_id'] );
			}
			if ( $plan_id > 0 ) {
				$cart_item_data['sublium_wcs_plan'] = $plan_id;
				$cart_item_data['sublium_wcs_plan_added_by_funnelkit'] = 1;
				$cart_item_data['sublium_wcs_plan_locked'] = 1;

			} elseif ( ! empty( $options['force_one_time'] ) || ! empty( $options['products_meta_data'][1]['sublium_one_time'] ) ) {
				// Admin explicitly configured this as a plain one-time purchase (no plan_id at all) —
				// lock it so Sublium's render_upgrade_button() doesn't still offer an "upgrade to
				// subscription" prompt for it, which would defeat the point of choosing one-time.
				$cart_item_data['sublium_wcs_plan_locked'] = 1;
			}
			return $cart_item_data;
		}



		/**
		 * Extract the Sublium plan selected in the "Add Product" search modal from the raw
		 * products_meta_data sent in the request body, and set it as plan_id on the offer
		 * product's fields object.
		 *
		 * @param stdClass $product_fields Product fields object being built for this product.
		 * @param int      $pid            Product ID being added.
		 * @param array    $options        Full decoded request body (contains products_meta_data).
		 *
		 * @return stdClass
		 */
		public function inject_plan_id_into_offer_product_fields( $product_fields, $pid, $options ) {
			if ( empty( $options['products_meta_data'] ) || ! is_array( $options['products_meta_data'] ) ) {
				return $product_fields;
			}
			foreach ( $options['products_meta_data'] as $meta_entry ) {
				if ( ! is_array( $meta_entry ) || ! isset( $meta_entry[0], $meta_entry[1] ) ) {
					continue;
				}
				if ( absint( $meta_entry[0] ) !== absint( $pid ) ) {
					continue;
				}
				if ( ! empty( $meta_entry[1]['sublium_plan'] ) ) {
					$product_fields->plan_id = absint( $meta_entry[1]['sublium_plan'] );
					// wfocu_add_product() hardcodes discount_type to 'percentage_on_reg' for every new
					// product. For a Sublium plan, "on Regular Price" now discounts off the product's
					// true regular price (bypassing the plan's own recurring price) — a safe-by-default
					// admin has to opt into explicitly, not land on by accident. Default new plan
					// products to 'percentage_on_sale' instead; the admin can still switch it later.
					if ( isset( $product_fields->discount_type ) && 'percentage_on_reg' === $product_fields->discount_type ) {
						$product_fields->discount_type = 'percentage_on_sale';
					}
				} elseif ( ! empty( $meta_entry[1]['sublium_one_time'] ) ) {
					// Admin explicitly picked the "- One Time Purchase" search row: this offer field
					// should render as a plain product (no Sublium plan widget), not just "no plan
					// pre-assigned" (which would still show the full plan/one-time selector because the
					// underlying product has plans). See schemes_template_html() short-circuit.
					$product_fields->force_one_time = true;
				}
				break;
			}
			return $product_fields;
		}

		/**
		 * The "Save" request rebuilds every product's fields object from scratch using only the
		 * React table's known columns (discount_amount/discount_type/quantity/shipping_cost_flat),
		 * so plan_id/force_one_time (set once, at add-time, by inject_plan_id_into_offer_product_fields())
		 * would otherwise be silently dropped on every subsequent save. Carry them forward from the
		 * offer's existing saved meta.
		 *
		 * @param stdClass $fields              Field object being built for this product.
		 * @param string   $hash_key            Product key in the offer.
		 * @param mixed    $existing_offer_meta The offer's _wfocu_setting meta before this save.
		 *
		 * @return stdClass
		 */
		public function preserve_plan_fields_on_offer_save( $fields, $hash_key, $existing_offer_meta ) {
			if ( ! is_object( $existing_offer_meta ) || ! isset( $existing_offer_meta->fields->{$hash_key} ) ) {
				return $fields;
			}
			$existing = $existing_offer_meta->fields->{$hash_key};
			if ( ! empty( $existing->plan_id ) ) {
				$fields->plan_id = absint( $existing->plan_id );
			} elseif ( ! empty( $existing->force_one_time ) ) {
				$fields->force_one_time = true;
			}
			return $fields;
		}

		/**
		 * Text label for the "- One Time Purchase" name suffix, shared by the search row,
		 * offer-add response, schema conversion, and offer-get reload paths so it's identical
		 * everywhere it's appended.
		 */
		private function get_one_time_purchase_label() {
			return html_entity_decode( strip_tags( \Sublium_WCS\Includes\Helpers\Language::get_translation( 'one-time-purchase-label' ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}

		public function append_plan_name_on_offer_add( $product_details, $pro, $product_fields ) {
			if ( empty( $product_fields->plan_id ) && empty( $product_fields->products_meta_data[1]['sublium_plan'] )
				&& ( ! empty( $product_fields->force_one_time ) || ! empty( $product_fields->products_meta_data[1]['sublium_one_time'] ) ) ) {
				$product_fields->force_one_time = true;
				$product_details->name          = $product_details->name . ' - ' . $this->get_one_time_purchase_label();
				return $product_details;
			}
			$plan_id = 0;
			if ( ! empty( $product_fields->plan_id ) ) {
				$plan_id = absint( $product_fields->plan_id );
			} elseif ( isset( $product_fields->products_meta_data[1]['sublium_plan'] ) ) {
				$plan_id = absint( $product_fields->products_meta_data[1]['sublium_plan'] );
			}
			if ( ! $plan_id || ! $pro instanceof \WC_Product ) {
				return $product_details;
			}
			$plan = \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( $plan_id, $pro );
			if ( is_null( $plan ) ) {
				return $product_details;
			}
			$product_details->name              = $product_details->name . ' - ' . strip_tags( $plan->get_title( $pro ) );
			$plan_price                         = $plan->get_recurring_cart_price( $pro->get_price(), $pro );
			$product_details->regular_price     = wc_price( $plan_price );
			$product_details->regular_price_raw = $plan_price;
			$product_details->price             = wc_price( $plan_price );
			$product_details->price_raw         = $plan_price;
			$product_fields->plan_id            = $plan_id;
			return $product_details;
		}

		public function append_plan_name_on_offer_schema( $product_details, $product, $posted_product ) {
			if ( empty( $posted_product['plan_id'] ) && ! empty( $posted_product['force_one_time'] ) ) {
				$product_details['title'] = $product_details['title'] . ' - ' . $this->get_one_time_purchase_label();
				return $product_details;
			}
			$plan_id = ! empty( $posted_product['plan_id'] ) ? absint( $posted_product['plan_id'] ) : 0;
			if ( ! $plan_id || ! $product instanceof \WC_Product ) {
				return $product_details;
			}
			$plan = \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( $plan_id, $product );
			if ( is_null( $plan ) ) {
				return $product_details;
			}
			$product_details['title'] = $product_details['title'] . ' - ' . strip_tags( $plan->get_title( $product ) );
			list( $product_details['regular_price'], $product_details['sale_price'] ) = $this->get_offer_display_prices_for_plan( $product, $plan, $posted_product['discount_type'] ?? '' );
			$product_details['is_on_sale'] = true;
			return $product_details;
		}

		public function append_plan_name_on_offer_get( $product_details, $product, $offer_id, $funnel_id, $key ) {
			$offer_meta = WFOCU_Core()->offers->get_offer_meta( $offer_id );
			if ( ! $offer_meta ) {
				return $product_details;
			}
			if ( empty( $offer_meta->fields->{$key}->plan_id ) && ! empty( $offer_meta->fields->{$key}->force_one_time ) ) {
				$product_details['title'] = $product_details['title'] . ' - ' . $this->get_one_time_purchase_label();
				return $product_details;
			}
			if ( ! isset( $offer_meta->fields->{$key}->plan_id ) || empty( $offer_meta->fields->{$key}->plan_id ) ) {
				return $product_details;
			}
			$plan_id = absint( $offer_meta->fields->{$key}->plan_id );
			if ( ! $product instanceof \WC_Product ) {
				return $product_details;
			}
			$plan = \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( $plan_id, $product );
			if ( is_null( $plan ) ) {
				return $product_details;
			}
			$product_details['title'] = $product_details['title'] . ' - ' . strip_tags( $plan->get_title( $product ) );
			$fields                   = $offer_meta->fields->{$key};
			list( $product_details['regular_price'], $product_details['sale_price'] ) = $this->get_offer_display_prices_for_plan( $product, $plan, $fields->discount_type ?? '' );
			$product_details['is_on_sale'] = true;
			return $product_details;
		}

		/**
		 * Append the plan/one-time suffix to the product name used in page-builder widget/tag
		 * "Product" dropdowns (Accept Button, Variation Selector, Offer Price, Product Title/Images/
		 * Short Description, Qty Selector, etc.) — including $is_front contexts like the Elementor
		 * editor's live preview. Without this, two dropdown entries for the same underlying product
		 * (e.g. one plain, one on a Sublium "Every Month" plan) are indistinguishable, even though the
		 * admin Products table (via append_plan_name_on_offer_get()) tells them apart.
		 *
		 * @param string     $name     Product display name for this offer field.
		 * @param \WC_Product $product The underlying product.
		 * @param int        $offer_id
		 * @param string     $hash_key Product key in the offer.
		 *
		 * @return string
		 */
		public function append_plan_name_to_product_dropdown( $name, $product, $offer_id, $hash_key ) {
			$offer_meta = WFOCU_Core()->offers->get_offer_meta( $offer_id );
			if ( ! $offer_meta || ! isset( $offer_meta->fields->{$hash_key} ) ) {
				return $name;
			}
			$fields = $offer_meta->fields->{$hash_key};
			if ( ! empty( $fields->plan_id ) && $product instanceof \WC_Product ) {
				$plan = \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( absint( $fields->plan_id ), $product );
				if ( ! is_null( $plan ) ) {
					return $name . ' - ' . strip_tags( $plan->get_title( $product ) );
				}
			} elseif ( ! empty( $fields->force_one_time ) ) {
				return $name . ' - ' . $this->get_one_time_purchase_label();
			}
			return $name;
		}

		/**
		 * The admin Products table's "Regular Price"/"Offer Price" basis (and the offer schema fed to
		 * the React app) for a Sublium-plan product. "Regular Price" is always the product's true
		 * WooCommerce regular price (a plain informational reference), regardless of discount_type. The
		 * second value is the "Offer Price" BASIS the admin table's own discount_type-aware JS
		 * (getDiscountPrice() in funnel-builder's product-table component) applies the row's
		 * discount_amount/discount_type to: the true regular price for "on Regular Price" types, or the
		 * plan's own price for "on Sale Price" types — matching
		 * override_price_for_preassigned_plan()'s real-charge basis. Deliberately NOT pre-discounted
		 * here — that JS only treats this basis (returned as the row's "sale_price" field) as the
		 * discount basis when "is_on_sale" is also true, so the calling functions must set that flag;
		 * see append_plan_name_on_offer_schema()/append_plan_name_on_offer_get().
		 *
		 * @return array{0: float, 1: float} [true_regular_price, offer_price_basis]
		 */
		private function get_offer_display_prices_for_plan( $product, $plan, $discount_type ) {
			$true_regular_price = $this->get_true_regular_price( $product );
			$is_regular_type     = in_array( $discount_type, array( 'percentage_on_reg', 'fixed_on_reg' ), true );
			$basis                = $is_regular_type ? $true_regular_price : $plan->get_recurring_cart_price( $product->get_price(), $product );

			return array( $true_regular_price, $basis );
		}

		public function preserve_plan_id_on_save( $offers_setting, $get_options, $offer_id ) {
			$existing_meta = WFOCU_Core()->offers->get_offer_meta( $offer_id );
			if ( ! $existing_meta || empty( $existing_meta->fields ) ) {
				return $offers_setting;
			}
			foreach ( $offers_setting->fields as $hash_key => $field ) {
				if ( isset( $existing_meta->fields->{$hash_key}->plan_id ) && ! empty( $existing_meta->fields->{$hash_key}->plan_id ) ) {
					$offers_setting->fields->{$hash_key}->plan_id = absint( $existing_meta->fields->{$hash_key}->plan_id );
				}
			}
			return $offers_setting;
		}

		public function maybe_hide_upsell_plan_selector( $show, $product_id, $product_key ) {
			if ( ! $this->is_enable() || empty( $product_key ) ) {
				return $show;
			}
			$offer_data = WFOCU_Core()->data->get( '_current_offer_data' );
			if ( ! $offer_data || ! isset( $offer_data->fields->{$product_key}->plan_id ) || empty( $offer_data->fields->{$product_key}->plan_id ) ) {
				return $show;
			}
			return false;
		}

		public function override_price_for_preassigned_plan( $price, $product, $options ) {
			if ( ! $this->is_enable() || ! $product instanceof \WC_Product ) {
				return $price;
			}
			$plan_id = ! empty( $options->plan_id ) ? absint( $options->plan_id ) : 0;
			if ( ! $plan_id ) {
				return $price;
			}
			$plan = \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( $plan_id, $product );
			if ( is_null( $plan ) ) {
				return $price;
			}
			$qty = ! empty( $options->quantity ) ? absint( $options->quantity ) : 1;

			// During a free trial the first charge is the plan's signup fee (0 when the plan
			// has none), NOT the recurring or discounted price. This must take precedence over
			// the discount-type branches below: an "on Regular Price" discount only changes what
			// the recurring charge is once the trial ends, it does not turn the trial's initial
			// charge into the discounted regular price. Previously this returned 0.0
			// unconditionally, which dropped a configured signup fee from the initial charge.
			if ( $plan->get_free_trial() > 0 ) {
				return (float) $plan->get_signup_fee( $product ) * $qty;
			}

			// "On Regular Price" discount types discount off the product's true regular price
			// (e.g. $3000 -> $2700 for 10% off), which then becomes the actual subscription charge —
			// bypassing the plan's own recurring price entirely. Everything else (including no
			// discount_type chosen yet) keeps discounting off the plan's recurring price as before.
			if ( is_object( $options ) && isset( $options->discount_type ) && in_array( $options->discount_type, array( 'percentage_on_reg', 'fixed_on_reg' ), true ) ) {
				$regular_price = ! empty( $product->get_regular_price() ) ? floatval( $product->get_regular_price() ) : 0;
				return $regular_price * $qty;
			}

			$recurring_price = $plan->get_recurring_cart_price( $product->get_price(), $product );
			return $recurring_price * $qty;
		}

		public function override_regular_price_for_preassigned_plan( $product_details, $output, $offer_data, $is_front, $hash_key ) {
			if ( ! $this->is_enable() || ! $is_front ) {
				return $product_details;
			}
			if ( ! isset( $offer_data->fields->{$hash_key}->plan_id ) || empty( $offer_data->fields->{$hash_key}->plan_id ) ) {
				return $product_details;
			}
			$plan_id = absint( $offer_data->fields->{$hash_key}->plan_id );
			$product = isset( $product_details->data ) ? $product_details->data : null;
			if ( ! $product instanceof \WC_Product ) {
				return $product_details;
			}
			$plan = \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( $plan_id, $product );
			if ( is_null( $plan ) ) {
				return $product_details;
			}
			$qty            = ! empty( $offer_data->fields->{$hash_key}->quantity ) ? absint( $offer_data->fields->{$hash_key}->quantity ) : 1;
			$discount_type  = isset( $offer_data->fields->{$hash_key}->discount_type ) ? $offer_data->fields->{$hash_key}->discount_type : '';
			$is_regular_type = in_array( $discount_type, array( 'percentage_on_reg', 'fixed_on_reg' ), true );

			// During a free trial the "today" charge is the signup fee (see
			// override_price_for_preassigned_plan()), not the discounted regular or recurring
			// price. Zero out the strikethrough reference for BOTH discount types so no stale,
			// unrelated regular price is shown next to the trial's initial charge.
			if ( $plan->get_free_trial() > 0 ) {
				$plan_price = 0.0;
			} elseif ( $is_regular_type ) {
				// "on Regular Price" discounts (and, via override_price_for_preassigned_plan(),
				// actually charges off) the product's true regular price, not the plan's own price —
				// the "struck through" reference shown here needs to match, or it shows a stale,
				// unrelated number next to the real (correctly computed elsewhere) price.
				$plan_price = $this->get_true_regular_price( $product );
			} else {
				$plan_price = $plan->get_recurring_cart_price( $product->get_price(), $product );
			}
			$incl_price = wc_get_price_including_tax( $product, array( 'price' => $plan_price ) ) * $qty;
			$excl_price = wc_get_price_excluding_tax( $product, array( 'price' => $plan_price ) ) * $qty;

			$product_details->regular_price_incl_tax = $incl_price;
			$product_details->regular_price_excl_tax = $excl_price;
			$product_details->regular_price          = WFOCU_Core()->offers->show_price_including_tax() ? $incl_price : $excl_price;

			return $product_details;
		}

		/**
		 * Force the real cart charge for Checkout/Order Bump Sublium-plan items to reflect their
		 * configured discount_type. "on Regular Price" discounts the product's true regular price
		 * directly and that becomes the final charge, bypassing Sublium's own recurring-billing math
		 * (billing-cycle division, signup fee, free trial) entirely. "on Sale Price" discounts
		 * Sublium's own already-computed plan price ($modified_price — recurring/initial price, with
		 * free trial/signup fee already applied), so that math still runs, just with the admin's
		 * discount layered on top.
		 *
		 * Hooked on Sublium's own final cart-price filter (fires from both Plan::get_recurring_cart_price()
		 * and Cart::set_subscription_prices_for_calculation()) because WFACP_Common::calculate_discount()/
		 * WFOB_Common::calculate_discount() already run earlier in the same woocommerce_before_calculate_totals
		 * pass, but Sublium's own price filter is registered at priority 10000 and runs last, so without this,
		 * it silently overrides whatever WFACP/WFOB computed — for EITHER discount basis, not just "on Regular
		 * Price". Confirmed live: a Sublium plan priced at $10 with a 10% "on Sale Price" discount configured
		 * in WFACP/WFOB still charged the full $10, because Sublium's filter ignored the admin's discount config.
		 *
		 * @param float|null       $modified_price The price Sublium computed.
		 * @param \WC_Product      $product        The cart item's product object.
		 * @param object           $plan           The resolved Sublium plan.
		 * @param string|null      $calculation_type
		 *
		 * @return float|null
		 */
		public function override_cart_price_for_regular_basis_discount( $modified_price, $product, $plan = null, $calculation_type = null ) {
			if ( ! $this->is_enable() || ! $product instanceof \WC_Product || ! function_exists( 'WC' ) || ! WC()->cart ) {
				return $modified_price;
			}
			// sublium_wcs_subscription_price fires twice per price resolution: once inside
			// Plan::get_recurring_cart_price() (3 args — $calculation_type absent), and again in the
			// outer Cart::set_subscription_prices_for_calculation() (4 args) which itself calls
			// get_recurring_cart_price(). Only act on the outer call — for "on Regular Price" the
			// fixed recompute is idempotent either way, but "on Sale Price" discounts $modified_price
			// itself, so acting on both would apply the discount twice (e.g. 10% off $10 -> $9 -> $8.10).
			if ( null === $calculation_type ) {
				return $modified_price;
			}

			// Identity match first (precise — correct for the live cart's own calculate_totals() pass,
			// which is what actually charges the customer today). Sublium's forward-looking recurring-cart
			// projections (used for the "Recurring"/"Subscribe and Save" totals and the "Recurring Total"
			// modal) run their OWN calculate_totals() on a separate \WC_Cart-like object with different
			// product clones, so identity never matches there — fall back to plan-id matching in that case
			// so those projections get discounted too, not just the display strings layered on top of them.
			$plan_id_fallback = absint( $product->get_meta( 'sublium_wcs_plan' ) );
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$is_identity_match = isset( $cart_item['data'] ) && $cart_item['data'] === $product;
				$is_plan_match     = ! $is_identity_match && $plan_id_fallback > 0 && ! empty( $cart_item['sublium_wcs_plan'] ) && absint( $cart_item['sublium_wcs_plan'] ) === $plan_id_fallback;
				if ( ! $is_identity_match && ! $is_plan_match ) {
					continue;
				}

				$options = isset( $cart_item['_wfacp_options'] ) ? $cart_item['_wfacp_options'] : ( isset( $cart_item['_wfob_options'] ) ? $cart_item['_wfob_options'] : null );
				if ( empty( $options ) || empty( $options['discount_type'] ) ) {
					return $modified_price;
				}
				$discount_type   = $options['discount_type'];
				$is_regular_type = in_array( $discount_type, array( 'percent_discount_reg', 'fixed_discount_reg' ), true );
				$is_sale_type    = in_array( $discount_type, array( 'percent_discount_sale', 'fixed_discount_sale' ), true );
				if ( ! $is_regular_type && ! $is_sale_type ) {
					return $modified_price;
				}
				// A zero discount_amount is a no-op — skip so calculate_discount()'s own
				// "0 == $value" fallback (which differs slightly between WFACP/WFOB) doesn't
				// substitute a different basis than what Sublium already computed.
				if ( empty( $options['discount_amount'] ) ) {
					return $modified_price;
				}

				// get_true_regular_price() reads raw postmeta rather than calling get_regular_price():
				// (a) any context still re-triggers the woocommerce_product_get_price filter chain we're
				// already inside (recursion risk), and (b) for quantity > 1 checkout items,
				// WFACP_Common::set_product_price() mutates the product object's regular_price prop to
				// regular_price * quantity, which get_regular_price() would return regardless of context —
				// discounting off that would double-count quantity once WooCommerce applies its own qty
				// multiplication on top.
				$regular_price = $this->get_true_regular_price( $product );
				// "on Regular Price": discount off the product's true regular price, bypassing the plan's
				// own price entirely (becomes the final charge). "on Sale Price": discount off the plan's
				// own price — cached on first use per request (see $sale_price_basis_cache docblock) rather
				// than trusting the live $modified_price, which on any calculate_totals() pass after the
				// first already reflects our own previous discount, not the plan's native price.
				if ( $is_regular_type ) {
					$price = ! empty( $product->get_price( 'edit' ) ) ? floatval( $product->get_price( 'edit' ) ) : $regular_price;
				} else {
					$sale_basis_key = $product->get_id() . '_' . ( is_object( $plan ) && method_exists( $plan, 'get_id' ) ? $plan->get_id() : 0 );
					if ( ! isset( $this->sale_price_basis_cache[ $sale_basis_key ] ) ) {
						$this->sale_price_basis_cache[ $sale_basis_key ] = floatval( $modified_price );
					}
					$price = $this->sale_price_basis_cache[ $sale_basis_key ];
				}
				$discount_data = array(
					'discount_amount' => floatval( $options['discount_amount'] ),
					'discount_type'   => $discount_type,
				);

				if ( isset( $cart_item['_wfacp_options'] ) ) {
					$discount_data['wfacp_product_rp']      = $regular_price;
					$discount_data['wfacp_product_p']       = $price;
					$discount_data['wfacp_discount_amount'] = $discount_data['discount_amount'];
					$discount_data['wfacp_discount_type']   = $discount_data['discount_type'];
					$new_price                              = WFACP_Common::calculate_discount( $discount_data );
				} else {
					$discount_data['wfob_product_rp']      = $regular_price;
					$discount_data['wfob_product_p']       = $price;
					$discount_data['wfob_discount_amount'] = $discount_data['discount_amount'];
					$discount_data['wfob_discount_type']   = $discount_data['discount_type'];
					$new_price                             = WFOB_Common::calculate_discount( $discount_data );
				}

				return is_null( $new_price ) ? $modified_price : $new_price;
			}

			return $modified_price;
		}

		/**
		 * Override Sublium's own FunnelKit-checkout sidebar summary text
		 * (Sublium_WCS\Includes\Helpers\Plan::get_plan_price_string()) for cart items whose
		 * discount_type is "on Regular Price" — that native text describes the plan's free
		 * trial/signup fee/recurring billing, none of which applies once
		 * override_cart_price_for_regular_basis_discount() has bypassed it.
		 *
		 * @param string $summary Sublium's own generated summary text.
		 * @param object $plan    The resolved Sublium plan (not bound to a specific product).
		 *
		 * @return string
		 */
		public function override_sublium_item_price_string( $summary, $plan ) {
			if ( ! $this->is_enable() || ! is_object( $plan ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
				return $summary;
			}

			foreach ( WC()->cart->get_cart() as $cart_item ) {
				if ( empty( $cart_item['sublium_wcs_plan'] ) || absint( $cart_item['sublium_wcs_plan'] ) !== absint( $plan->get_id() ) ) {
					continue;
				}

				$options = isset( $cart_item['_wfacp_options'] ) ? $cart_item['_wfacp_options'] : ( isset( $cart_item['_wfob_options'] ) ? $cart_item['_wfob_options'] : null );
				if ( empty( $options['discount_type'] ) ) {
					continue;
				}
				$qty = ! empty( $cart_item['quantity'] ) ? absint( $cart_item['quantity'] ) : 1;

				if ( in_array( $options['discount_type'], array( 'percent_discount_reg', 'fixed_discount_reg' ), true ) ) {
					return sprintf(
						/* translators: %s: price charged today */
						__( 'Billed %s today.', 'woofunnels-upstroke-power-pack' ),
						wc_price( $cart_item['data']->get_price() * $qty )
					);
				}

				// "on Sale Price" discounts the plan's own recurring price — legitimately applies to
				// every billing cycle, so correct the amount inside Sublium's own native $summary
				// (preserving its period/free-trial wording) rather than replacing the whole string.
				if ( ! empty( $options['discount_amount'] ) && in_array( $options['discount_type'], array( 'percent_discount_sale', 'fixed_discount_sale' ), true ) && '' !== $summary ) {
					$plan_id_for_item = absint( $cart_item['sublium_wcs_plan'] );
					if ( $plan_id_for_item === absint( $plan->get_id() ) ) {
						$product           = $cart_item['data'];
						$base_price        = \Sublium_WCS\Includes\Abstracts\Plan::get_base_price( $product );
						$native_unit_price = $plan->get_recurring_cart_price( $base_price, $product );
						if ( $native_unit_price > 0 ) {
							// get_price() is per-unit and already correctly discounted — this filter
							// fires after calculate_totals() has fully settled the cart, so it's safe
							// to read here (see override_cart_price_for_regular_basis_discount() for
							// why it's NOT safe to read there).
							return str_replace( wc_price( $native_unit_price ), wc_price( $product->get_price() * $qty ), $summary );
						}
					}
				}

				return $summary;
			}

			return $summary;
		}

		/**
		 * Override Sublium's forward-looking "Recurring: $X / period" total (checkout/templates/checkout/
		 * parts/recurring.php) for items whose discount_type is "on Regular Price", so every future
		 * renewal is discounted the same way as today's charge — not just the first one.
		 *
		 * $recurring_carts (templates/checkout/recurring-totals.php) are separate \WC_Cart instances
		 * Sublium builds internally to project future billing; they are NOT WC()->cart, so
		 * override_cart_price_for_regular_basis_discount()'s WC()->cart-based item matching never reaches
		 * them. Match by plan_id instead of object identity — the same product can appear in the live
		 * cart multiple times with different plans (e.g. main product + a bump of the same product), so
		 * identity/product-ID-only matching is unreliable (see the bump plan-attachment fix above).
		 *
		 * @param string $price_html Sublium's own wc_price()-formatted total for this recurring cart.
		 * @param object $sublium_recurring_cart A \WC_Cart instance representing one future billing cycle.
		 *
		 * @return string
		 */
		public function override_recurring_cart_total_for_regular_basis_discount( $price_html, $sublium_recurring_cart ) {
			if ( ! $this->is_enable() || ! is_object( $sublium_recurring_cart ) || ! method_exists( $sublium_recurring_cart, 'get_cart' ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
				return $price_html;
			}
			$recurring_plan_id = absint( $sublium_recurring_cart->sublium_wcs_plan ?? 0 );
			if ( ! $recurring_plan_id ) {
				return $price_html;
			}

			// Find the live cart item this recurring cart is projected from, matched by plan.
			$options    = null;
			$is_wfacp   = true;
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				if ( empty( $cart_item['sublium_wcs_plan'] ) || absint( $cart_item['sublium_wcs_plan'] ) !== $recurring_plan_id ) {
					continue;
				}
				if ( isset( $cart_item['_wfacp_options'] ) ) {
					$options  = $cart_item['_wfacp_options'];
					$is_wfacp = true;
				} elseif ( isset( $cart_item['_wfob_options'] ) ) {
					$options  = $cart_item['_wfob_options'];
					$is_wfacp = false;
				}
				break;
			}
			$is_regular_type = in_array( $options['discount_type'] ?? '', array( 'percent_discount_reg', 'fixed_discount_reg' ), true );
			$is_sale_type    = in_array( $options['discount_type'] ?? '', array( 'percent_discount_sale', 'fixed_discount_sale' ), true );
			if ( empty( $options['discount_amount'] ) || ( ! $is_regular_type && ! $is_sale_type ) ) {
				return $price_html;
			}

			$total = 0.0;
			foreach ( $sublium_recurring_cart->get_cart() as $recurring_item ) {
				if ( empty( $recurring_item['data'] ) || ! $recurring_item['data'] instanceof \WC_Product ) {
					continue;
				}
				$product       = $recurring_item['data'];
				// See get_true_regular_price() docblock — avoids double-counting quantity for
				// checkout items where WFACP_Common::set_product_price() has mutated the product's
				// regular_price prop to regular_price * quantity.
				$regular_price = $this->get_true_regular_price( $product );
				$qty           = ! empty( $recurring_item['quantity'] ) ? absint( $recurring_item['quantity'] ) : 1;
				// "on Sale Price": discount off the plan's own recurring price (computed from the
				// stable true regular price, not $product->get_price() — matching
				// get_checkout_bump_display_price(), keeps this idempotent across repeated calls).
				$plan       = $is_sale_type ? \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( $recurring_plan_id, $product ) : null;
				$sale_basis = ( $is_sale_type && $plan ) ? $plan->get_recurring_cart_price( $regular_price, $product ) : $regular_price;
				$discount_data = array(
					'discount_amount' => floatval( $options['discount_amount'] ),
					'discount_type'   => $options['discount_type'],
				);
				if ( $is_wfacp ) {
					$discount_data['wfacp_product_rp']      = $regular_price;
					$discount_data['wfacp_product_p']       = $sale_basis;
					$discount_data['wfacp_discount_amount'] = $discount_data['discount_amount'];
					$discount_data['wfacp_discount_type']   = $discount_data['discount_type'];
					$line_price                             = WFACP_Common::calculate_discount( $discount_data );
				} else {
					$discount_data['wfob_product_rp']      = $regular_price;
					$discount_data['wfob_product_p']       = $sale_basis;
					$discount_data['wfob_discount_amount'] = $discount_data['discount_amount'];
					$discount_data['wfob_discount_type']   = $discount_data['discount_type'];
					$line_price                            = WFOB_Common::calculate_discount( $discount_data );
				}
				$total += ( is_null( $line_price ) ? $sale_basis : $line_price ) * $qty;
			}

			return wc_price( $total );
		}

		/**
		 * Same as override_recurring_cart_total_for_regular_basis_discount(), but for the separate
		 * "Recurring Total" modal (templates/checkout/recurring-cart-view-details.php), which renders
		 * per-item via sublium_wcs_woocommerce_cart_item_subtotal against a recurring cart ITEM array
		 * (not the whole recurring cart object) — a different template, different filter, same gap.
		 *
		 * @param string $price_string        Sublium's own formatted price string for this item.
		 * @param array  $recurring_cart_item The recurring cart's item array (has 'data', 'quantity').
		 * @param string $cart_item_key       The recurring cart item's key.
		 *
		 * @return string
		 */
		public function override_recurring_cart_item_subtotal_for_regular_basis_discount( $price_string, $recurring_cart_item, $cart_item_key ) {
			if ( ! $this->is_enable() || empty( $recurring_cart_item['data'] ) || ! $recurring_cart_item['data'] instanceof \WC_Product || ! function_exists( 'WC' ) || ! WC()->cart ) {
				return $price_string;
			}
			$product = $recurring_cart_item['data'];
			$plan_id = absint( $product->get_meta( 'sublium_wcs_plan' ) );
			if ( ! $plan_id ) {
				return $price_string;
			}

			// Match the live cart item by plan (not identity/key — this is a separately-built
			// projection cart, see override_cart_price_for_regular_basis_discount()).
			$options  = null;
			$is_wfacp = true;
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				if ( empty( $cart_item['sublium_wcs_plan'] ) || absint( $cart_item['sublium_wcs_plan'] ) !== $plan_id ) {
					continue;
				}
				if ( isset( $cart_item['_wfacp_options'] ) ) {
					$options  = $cart_item['_wfacp_options'];
					$is_wfacp = true;
				} elseif ( isset( $cart_item['_wfob_options'] ) ) {
					$options  = $cart_item['_wfob_options'];
					$is_wfacp = false;
				}
				break;
			}
			$is_regular_type = in_array( $options['discount_type'] ?? '', array( 'percent_discount_reg', 'fixed_discount_reg' ), true );
			$is_sale_type    = in_array( $options['discount_type'] ?? '', array( 'percent_discount_sale', 'fixed_discount_sale' ), true );
			if ( empty( $options['discount_amount'] ) || ( ! $is_regular_type && ! $is_sale_type ) ) {
				return $price_string;
			}

			$regular_price = $this->get_true_regular_price( $product );
			$qty           = ! empty( $recurring_cart_item['quantity'] ) ? absint( $recurring_cart_item['quantity'] ) : 1;
			// "on Sale Price": discount off the plan's own recurring price (computed from the stable
			// true regular price, not $product->get_price() — matching get_checkout_bump_display_price(),
			// keeps this idempotent across repeated calls).
			$plan          = $is_sale_type ? \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( $plan_id, $product ) : null;
			$sale_basis    = ( $is_sale_type && $plan ) ? $plan->get_recurring_cart_price( $regular_price, $product ) : $regular_price;
			$discount_data = array(
				'discount_amount' => floatval( $options['discount_amount'] ),
				'discount_type'   => $options['discount_type'],
			);
			if ( $is_wfacp ) {
				$discount_data['wfacp_product_rp']      = $regular_price;
				$discount_data['wfacp_product_p']       = $sale_basis;
				$discount_data['wfacp_discount_amount'] = $discount_data['discount_amount'];
				$discount_data['wfacp_discount_type']   = $discount_data['discount_type'];
				$line_price                             = WFACP_Common::calculate_discount( $discount_data );
			} else {
				$discount_data['wfob_product_rp']      = $regular_price;
				$discount_data['wfob_product_p']       = $sale_basis;
				$discount_data['wfob_discount_amount'] = $discount_data['discount_amount'];
				$discount_data['wfob_discount_type']   = $discount_data['discount_type'];
				$line_price                            = WFOB_Common::calculate_discount( $discount_data );
			}
			$line_price = is_null( $line_price ) ? $sale_basis : $line_price;

			return wc_price( $line_price * $qty );
		}

		public function modify_variations_attributes( $attributes, $variation, $product, $discount_data ) {

			if ( isset( $variation['sublium_plans'] ) ) {

				$sublium_plans     = array();
				$variation_product = wc_get_product( $attributes['id'] );
				$plans             = $this->get_cached_plans( $attributes['id'] );
				foreach ( $plans as $plan ) {
					$plan->update_meta_data( 'upsell_discount_data', $discount_data );
					$sublium_plans[] = $plan->get_plan_product_data( $variation_product );
				}

				$attributes['sublium_plans'] = $sublium_plans;

				// Check if allow_one_time_purchase setting is enabled AND product supports it
				$offer_allows_one_time = $this->is_one_time_purchase_enabled();
				$parent_product_id     = 0;
				// Get parent product ID if this is a variation
				if ( $variation_product instanceof WC_Product_Variation ) {
					$parent_product_id = $variation_product->get_parent_id();
				} elseif ( $product instanceof WC_Product ) {
					$parent_product_id = $product->get_id();
				}

				// Check if parent product supports one-time purchase
				$product_supports_one_time = false;
				if ( $parent_product_id > 0 ) {
					$parent_product = wc_get_product( $parent_product_id );
					if ( $parent_product instanceof WC_Product ) {
						$product_supports_one_time = \Sublium_WCS\Includes\Main\Product::get_instance()->if_product_supports_one_time( $parent_product );
					}
				}

				// Only enable one-time purchase if both conditions are met
				if ( $offer_allows_one_time && $product_supports_one_time && isset( $variation['one_time_purchase_available'] ) && $variation['one_time_purchase_available'] ) {
					$attributes['one_time_purchase_available']  = true;
					$attributes['one_time_purchase_price_html'] = isset( $variation['one_time_purchase_price_html'] ) ? $variation['one_time_purchase_price_html'] : '';
					$attributes['one_time_purchase_label']      = isset( $variation['one_time_purchase_label'] ) ? $variation['one_time_purchase_label'] : '';
				} else {
					$attributes['one_time_purchase_available']  = false;
					$attributes['one_time_purchase_price_html'] = '';
					$attributes['one_time_purchase_label']      = '';
				}
			}
			return $attributes;
		}


		/**
		 * Add inline CSS for Sublium primary color variables
		 * Hooked to wfocu_print_all_styles action
		 */
		public function add_sublium_inline_css() {
			$settings      = \Sublium_WCS\Includes\Helpers\Data::get_settings();
			$primary_color = ! empty( $settings['theme_color_p'] ) ? esc_attr( $settings['theme_color_p'] ) : '#0073aa';
			$primary_rgb   = Utility::hex_to_rgb( $primary_color );
			$custom_css    = ":root { --sublium-theme-color-p: {$primary_color}; --sublium-theme-color-p-rgb: {$primary_rgb} }";
			printf( '<style type="text/css">%s</style>', esc_html( $custom_css ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}


		/**
		 * @param $data
		 * @param $plan \Sublium\Includes\Abstracts\Plan
		 *
		 * @return void
		 */
		public function attach_discounting_data( $data, $plan ) {
			$upsell_discount_data = $plan->get_meta( 'upsell_discount_data' );

			if ( ! empty( $upsell_discount_data ) ) {

				$data['_sublium_data'] = array( 'upsell_discount_data' => $upsell_discount_data );
			}

			$order = WFOCU_Data::get_instance()->get_parent_order();
			if ( $order instanceof WC_Order ) {
				$recurring_price = $data['recurring_price'];

				$address      = $order->get_address();
				$address_data = array(
					'country'   => $address['country'],
					'state'     => $address['state'],
					'postcode'  => $address['postcode'],
					'city'      => $address['city'],
					'tax_class' => '',
				);
				if ( wc_tax_enabled() ) {
					$including_tax = ( get_option( 'woocommerce_prices_include_tax' ) === 'yes' );
					// Price already includes tax → Woo needs net + tax
					$tax_rates               = WC_Tax::find_rates( $address_data );
					$taxes                   = WC_Tax::calc_tax( $recurring_price, $tax_rates, $including_tax ); // extract tax
					$tax_total               = array_sum( $taxes );
					$data['recurring_price'] = $recurring_price;
					if ( $including_tax ) {
						$data['recurring_price'] = $recurring_price - $tax_total;
					}
					$data['recurring_price_tax'] = $tax_total;

					$tax_data = array();
					foreach ( $taxes as $rate_id => $tax ) {
						$tax_data[] = array(
							'item_type' => 3,
							'item_data' => array(
								'rate_id'          => $rate_id,
								'amount'           => wc_round_tax_total( $tax ),
								'is_compound'      => WC_Tax::is_compound( $rate_id ),
								'label'            => WC_Tax::get_rate_label( $rate_id ),
								'formatted_amount' => '',
							),
						);
					}

					// For trial plans with $0 initial charge, sublium_tax_data must reflect
					// the initial (first) payment taxes — not the recurring price taxes.
					// Passing recurring-price taxes here causes the subscription to show
					// Tax = recurring_tax even though Sub Total = $0 during the trial.
					$initial_price = isset( $data['initial_price'] ) ? (float) $data['initial_price'] : 0.0;
					if ( ! empty( $data['free_trial'] ) ) {
						if ( $initial_price > 0 ) {
							// Signup fee present — compute tax on the signup fee only.
							$initial_taxes             = WC_Tax::calc_tax( $initial_price, $tax_rates, $including_tax );
							$initial_tax_total         = array_sum( $initial_taxes );
							$data['initial_price_tax'] = $initial_tax_total;
							if ( $including_tax ) {
								$data['initial_price'] = $initial_price - $initial_tax_total;
							}
							// Replace sublium_tax_data with signup-fee taxes so the subscription
							// line items reflect the actual first charge, not the recurring charge.
							$initial_tax_data = array();
							foreach ( $initial_taxes as $rate_id => $tax ) {
								$initial_tax_data[] = array(
									'item_type' => 3,
									'item_data' => array(
										'rate_id'          => $rate_id,
										'amount'           => wc_round_tax_total( $tax ),
										'is_compound'      => WC_Tax::is_compound( $rate_id ),
										'label'            => WC_Tax::get_rate_label( $rate_id ),
										'formatted_amount' => '',
									),
								);
							}
							$data['_sublium_data']['sublium_tax_data'] = $initial_tax_data;
						} else {
							// Free trial with no signup fee → first charge is $0, tax is $0.
							$data['initial_price_tax']                 = 0;
							$data['_sublium_data']['sublium_tax_data'] = array();
						}
					} else {
						$data['_sublium_data']['sublium_tax_data'] = $tax_data;
						$data['initial_price_tax']                 = 0;
					}
				}
			}

			return $data;
		}


		/**
		 * Check if FunnelKit Payments is active
		 *
		 * @return bool
		 */
		public function is_enable() {
			return class_exists( '\Sublium_WCS\Plugin', false );
		}

		/**
		 * The product's true, single-unit regular price, read directly from postmeta.
		 *
		 * $product->get_regular_price() (any context) is not reliable here: for a switcher/bump
		 * product with quantity > 1, WFACP_Common::set_product_price() mutates the product object's
		 * regular_price prop to regular_price * quantity (org_quantity) — and that mutation survives
		 * regardless of get_regular_price()'s context argument, since it changes the underlying prop,
		 * not just a filter output. Reading raw postmeta sidesteps that entirely, so the same regular
		 * price is used no matter when in the request this runs relative to that mutation.
		 *
		 * @param \WC_Product $product
		 *
		 * @return float
		 */
		private function get_true_regular_price( $product ) {
			if ( ! $product instanceof \WC_Product ) {
				return 0.0;
			}
			$regular_price = get_post_meta( $product->get_id(), '_regular_price', true );

			return '' !== $regular_price && is_numeric( $regular_price ) ? floatval( $regular_price ) : 0.0;
		}

		/**
		 * Get plans for a product with caching
		 *
		 * @param int|\WC_Product $product_id Product ID or object
		 *
		 * @return array Array of plan objects
		 */
		private function get_cached_plans( $product_id ) {

			return Product::get_instance()->get_cached_plans_for_product( wc_get_product( $product_id ) );
		}



		/**
		 * Build WCS-style recurring_details_wrap and signup_details_wrap HTML for a Sublium plan.
		 * Mirrors the structure rendered by the WCS powerpack subscription widget.
		 *
		 * The "Recurring: $X / period" line must reflect what the customer is actually billed at
		 * every renewal — matching override_cart_price_for_regular_basis_discount()'s real-charge
		 * basis: the discounted true regular price for "on Regular Price" types, or the discounted
		 * plan price for "on Sale Price" types (both types apply to every renewal, unlike a one-time
		 * today-only discount). Previously this always showed the plan's own undiscounted native
		 * price regardless of discount_type, so e.g. a 10% "on Sale Price" offer showing $1,170.00
		 * today still advertised the full, undiscounted $1,300.00 as the recurring amount.
		 */
		private function get_sublium_billing_summary_html( $plan, $product, $product_key, $qty = 1, $discount_type = '', $discount_amount = 0 ) {
			$html            = '';
			$base_price      = \Sublium_WCS\Includes\Abstracts\Plan::get_base_price( $product );
			$signup_fee      = $plan->get_signup_fee( $product );
			$is_regular_type = in_array( $discount_type, array( 'percentage_on_reg', 'fixed_on_reg' ), true );
			$basis           = $is_regular_type ? $this->get_true_regular_price( $product ) : $plan->get_recurring_cart_price( $base_price, $product );
			if ( ! empty( $discount_amount ) ) {
				$options = (object) array(
					'discount_type'   => $discount_type,
					'discount_amount' => $discount_amount,
					'quantity'        => 1,
				);
				$discounted = WFOCU_Common::apply_discount( $basis, $options, $product );
				if ( ! is_null( $discounted ) ) {
					$basis = $discounted;
				}
			}
			$recurring_price = $basis * $qty;
			// Match get_product_price()'s tax handling for the bold/strikethrough price above: gate on
			// WFOCU's own "Show Prices with Taxes" funnel setting and call wc_get_price_including_tax()
			// directly. get_price_based_on_tax() is the wrong helper here — it keys off WooCommerce's
			// generic woocommerce_tax_display_cart option, which is independent of this funnel setting
			// and is a no-op when set to "excl" (since $recurring_price is already tax-exclusive).
			if ( WFOCU_Core()->funnels->show_prices_including_tax() ) {
				$recurring_price = wc_get_price_including_tax( $product, array( 'price' => $recurring_price ) );
			}
			$period_string   = $plan->get_subscription_period_strings();
			$free_trial      = $plan->get_free_trial();

$subscrition_label='';
if(1==$plan->get_type()){
	$subscrition_label=\Sublium_WCS\Includes\Helpers\Data::get('subscribe_and_save_label');
}elseif(2==$plan->get_type()){
	$subscrition_label=\Sublium_WCS\Includes\Helpers\Data::get('recurring_label');
}elseif(3==$plan->get_type()){
	$subscrition_label=\Sublium_WCS\Includes\Helpers\Data::get('installments_label');
}



if(empty($subscrition_label)){
$subscrition_label=__( 'Recurring Total: ', 'woocommerce-subscription' );
}else{
	$subscrition_label.=": ";
}
// Build period label: " / month" or " / 3 months" + optional free trial suffix.e
			$subscription_details = ' / ' . $period_string;
			if ( $free_trial > 0 ) {
				$subscription_details .= ' ' . sprintf(
					/* translators: %d number of free trial days */
					_n( 'with a %d-day free trial', 'with a %d-day free trial', $free_trial, 'sublium-subscriptions-for-woocommerce' ),
					$free_trial
				);
			}

			// Signup fee row.
			if ( $signup_fee > 0 ) {
				$signup_fee_price = $signup_fee * $qty;
				if ( WFOCU_Core()->funnels->show_prices_including_tax() ) {
					$signup_fee_price = wc_get_price_including_tax( $product, array( 'price' => $signup_fee_price ) );
				}
				$html            .= '<div class="signup_details_wrap" data-key="' . esc_attr( $product_key ) . '">'
					. '<span class="signup_price_label">' . esc_html__( 'Signup Fee: ', 'woocommerce-subscription' ) . '</span>'
					. wp_kses_post( wc_price( $signup_fee_price ) )
					. '</div>';
			}

			// Recurring total row.
			$html .= '<div class="recurring_details_wrap" data-key="' . esc_attr( $product_key ) . '">'
				. '<span class="recurring_price_label">' . esc_html($subscrition_label) . '</span>'
				. wp_kses_post( wc_price( $recurring_price ) )
				. '<span class="subscription-details">' . esc_html( $subscription_details ) . '</span>'
				. '</div>';

			return $html;
		}

		/**
		 * Output the inline script that clones billing divs into every .wfocu_price_wrapper on page.
		 * Called once per product_key (pre-assigned plan or single-plan).
		 */
		private function print_billing_move_script( $product_key ) {
			$key = esc_js( $product_key );
			echo '<script>jQuery(document).ready(function(){';
			echo 'jQuery(".signup_details_wrap[data-key=\"' . $key . '\"], .recurring_details_wrap[data-key=\"' . $key . '\"]").each(function(){'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo 'var $el=jQuery(this);if($el.closest(".wfocu_price_wrapper").length)return;';
			echo 'jQuery(".wfocu_price_wrapper[data-key=\"' . $key . '\"]").each(function(){var $w=jQuery(this);if($w.children(".signup_details_wrap, .recurring_details_wrap").length)return;$w.append($el.clone().removeAttr("style"));});'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '$el.hide();});});</script>';
		}

		/**
		 * Inject Signup Fee + Recurring Total divs inside .wfocu_price_wrapper — identical to WCS.
		 * Runs on wfocu_template_price_html (same hook used by WCS powerpack).
		 */
		public function inject_billing_details_in_price_html( $html, $regular_price_raw, $regular_price, $sale_price_raw, $sale_price, $data ) {
			if ( ! $this->is_enable() ) {
				return $html;
			}
			$product_key = $data['key'] ?? '';
			$product     = $data['product']->data ?? null;
			if ( ! $product instanceof \WC_Product || ! $product_key ) {
				return $html;
			}
			$offer_data = WFOCU_Core()->data->get( '_current_offer_data' );
			$plan       = null;

			// Admin explicitly forced one-time purchase for this offer field — never show plan billing
			// details, not even the single-plan auto-select fallback below.
			if ( $offer_data && ! empty( $offer_data->fields->{$product_key}->force_one_time ) ) {
				return $html;
			}

			// Pre-assigned plan.
			if ( $offer_data && isset( $offer_data->fields->{$product_key}->plan_id ) && ! empty( $offer_data->fields->{$product_key}->plan_id ) ) {
				$plan = \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( absint( $offer_data->fields->{$product_key}->plan_id ), $product );
			} else {
				// Single-plan product (no one-time): auto-select first plan.
				$plans = \Sublium_WCS\Includes\Main\Product::get_instance()->get_cached_plans_for_product( $product );
				if ( count( $plans ) === 1 && ! $this->one_time_supported ) {
					$plan = reset( $plans );
				}
			}

			if ( is_null( $plan ) ) {
				return $html;
			}

			$discount_type   = isset( $offer_data->fields->{$product_key}->discount_type ) ? $offer_data->fields->{$product_key}->discount_type : '';
			$discount_amount = isset( $offer_data->fields->{$product_key}->discount_amount ) ? $offer_data->fields->{$product_key}->discount_amount : 0;

			return $html . $this->get_sublium_billing_summary_html( $plan, $product, $product_key, 1, $discount_type, $discount_amount );
		}

		/**
		 * Render subscription plan options above the accept button
		 *
		 * @param int    $product_id Product ID
		 * @param string $product_key Product key in the offer
		 */
		public function schemes_template_html( $product_id, $product_key = '' ) {

			if ( ! $this->is_enable() ) {
				return;
			}

			// When a plan is pre-assigned, show billing summary (once) then move into all price wrappers via JS.
			if ( ! empty( $product_key ) ) {
				$offer_data = WFOCU_Core()->data->get( '_current_offer_data' );

				// Admin explicitly picked the "- One Time Purchase" search row for this offer field:
				// render nothing here so WFOCU's own default (plain) price/button shows, instead of
				// Sublium's full plan widget (which would otherwise still appear — including a
				// single-option "One Time payment" radio — just because the underlying product has
				// plans configured elsewhere).
				if ( $offer_data && ! empty( $offer_data->fields->{$product_key}->force_one_time ) ) {
					return;
				}

				if ( $offer_data && isset( $offer_data->fields->{$product_key}->plan_id ) && ! empty( $offer_data->fields->{$product_key}->plan_id ) ) {
					static $sublium_preassigned_rendered = array();
					if ( ! isset( $sublium_preassigned_rendered[ $product_key ] ) ) {
						$sublium_preassigned_rendered[ $product_key ] = true;
						$plan_id = absint( $offer_data->fields->{$product_key}->plan_id );
						$product = wc_get_product( absint( $product_id ) );
						if ( $product instanceof \WC_Product ) {
							$plan = \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( $plan_id, $product );
							if ( ! is_null( $plan ) ) {
								$discount_type   = isset( $offer_data->fields->{$product_key}->discount_type ) ? $offer_data->fields->{$product_key}->discount_type : '';
								$discount_amount = isset( $offer_data->fields->{$product_key}->discount_amount ) ? $offer_data->fields->{$product_key}->discount_amount : 0;
								echo $this->get_sublium_billing_summary_html( $plan, $product, $product_key, 1, $discount_type, $discount_amount ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								$this->print_billing_move_script( $product_key );
							}
						}
					}
					return;
				}
			}

			if ( false === apply_filters( 'wfocu_sublium_plans_list', true, $product_id, $product_key ) ) {
				return;
			}

			/**
			 * Enqueue scripts and styles for the single product page.
				*/
			Template::get( 'product/plans-wp-template' );
			?>
			<div class="wfocu-subs-product-attr-wrapper">
				<form class="wfocu_subs_plan_selector_form" data-key="<?php echo esc_attr( $product_key ); ?>">
					<div class="wfocu_subs_plan_selector_wrap" data-key="<?php echo esc_attr( $product_key ); ?>">
						<?php
						// Get and display plans here
						$this->render_plan_options( $product_id, $product_key );
						?>
					</div>
				</form>
			</div>
			<?php
		}

		/**
		 * Render plan options for a product
		 *
		 * @param int    $product_id Product ID
		 * @param string $product_key Product key in the offer
		 */
		private function render_plan_options( $product_id, $product_key ) {
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				return;
			}

			$plans = $this->get_cached_plans( absint( $product_id ) );

			// Check if allow_one_time_purchase setting is enabled for this offer
			// AND if the product supports one-time purchase
			$offer_allows_one_time     = $this->is_one_time_purchase_enabled();
			$product_supports_one_time = \Sublium_WCS\Includes\Main\Product::get_instance()->if_product_supports_one_time( $product );

			$this->one_time_supported = $offer_allows_one_time && $product_supports_one_time;

			$default_plan = apply_filters( 'wfocu_default_sublium_plan', '', $plans, $product_key, $product_id );

			// Get settings dynamically
			$settings                    = \Sublium_WCS\Includes\Helpers\Data::get_settings();
			$one_time_purchase_label     = ! empty( $settings['one_time_purchase_label'] ) ? $settings['one_time_purchase_label'] : \Sublium_WCS\Includes\Helpers\Language::get_translation( 'one-time-purchase' );
			$btn_txt                     = ! empty( $settings['add_to_cart_label'] ) ? $settings['add_to_cart_label'] : '';
			$btn_txt_installment         = ! empty( $settings['add_to_cart_label_installment'] ) ? $settings['add_to_cart_label_installment'] : '';
			$one_time_purchase_available = $this->one_time_supported;
			$sublium_pro                 = \Sublium_WCS\Includes\Helpers\AccessPermission::has_plugin_support( 1 ) ? 'yes' : 'no';

			// Get offer price HTML (with discounts applied) instead of regular product price
			$one_time_price_html = $product->get_price_html();
			if ( ! empty( $product_key ) && class_exists( 'WFOCU_Core' ) ) {
				$offer_data = WFOCU_Core()->data->get( '_current_offer_data' );
				if ( ! empty( $offer_data ) && is_object( $offer_data ) && isset( $offer_data->fields->{$product_key} ) ) {
					$product_options  = $offer_data->fields->{$product_key};
					$is_show_tax      = WFOCU_Core()->funnels->show_prices_including_tax( $offer_data, $product_key );
					$offer_price_html = WFOCU_Core()->offers->get_product_price_display( $product, $product_options, $is_show_tax, $offer_data );
					if ( ! empty( $offer_price_html ) ) {
						$one_time_price_html = $offer_price_html;
					}
				}
			}

			// Get script handle dynamically
			$script_handle = 'sublium-frontend-product';
			if ( ! wp_script_is( $script_handle, 'enqueued' ) && ! wp_script_is( $script_handle, 'registered' ) ) {
				// Fallback: register and enqueue a minimal script for inline content
				wp_register_script( 'sublium-skin-data', '', array( 'jquery' ), defined( 'SUBLIUM_WCS_VERSION' ) ? SUBLIUM_WCS_VERSION : '1.0.0', true );
				wp_enqueue_script( 'sublium-skin-data' );
				$script_handle = 'sublium-skin-data';
			}
			$plans_json = array_map(
				function ( $plan ) use ( $product ) {
					return $plan->get_plan_product_data( $product );
				},
				$plans
			);

			static $one_time_price_html_static;
			if ( ! $one_time_price_html_static ) {
			// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
				$inline_script = '
		window.sublium_plans = ' . wp_json_encode( $plans_json ) . ';
		window.sublium_onetime = ' . wp_json_encode( $one_time_purchase_available ? 'yes' : 'no' ) . ';
		window.sublium_onetime_label = ' . wp_json_encode( $one_time_purchase_label ) . ';
		window.sublium_onetime_price_html = ' . wp_json_encode( wp_kses_post( $one_time_price_html ) ) . ';
		window.sublium_add_to_cart_btn_text = ' . wp_json_encode( wp_kses_post( $btn_txt ) ) . ';
		window.sublium_add_to_cart_btn_text_installment = ' . wp_json_encode( wp_kses_post( $btn_txt_installment ) ) . ';
		if (typeof window.sublium_pro === "undefined") { window.sublium_pro = ' . wp_json_encode( $sublium_pro ) . '; }';
			// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

				$listener_script = '
/* Hide Sublium\'s separate price div — price is shown in the WFOCU widget directly. */
jQuery("<style>.sublium-plan-price-wrapper{display:none!important}</style>").appendTo("head");

/* When "One Time Purchase" is selected, hide the subscription plan group rows (each has
   data-type and no template-sublium-one-time class) so only the one-time option remains visible.
   Toggled via a class on the stable .wfocu-subscription-options wrapper (not re-rendered by
   Sublium\'s own JS), so it survives Sublium\'s render() re-creating everything inside it. */
jQuery("<style>.wfocu-subscription-options.wfocu-one-time-active .sublium-front-purchase-group:not(.template-sublium-one-time){display:none!important}</style>").appendTo("head");

/* Clone signup_details_wrap and recurring_details_wrap into every .wfocu_price_wrapper (WCS structure).
   One set is rendered by PHP; we clone into each wrapper then hide the originals. */
jQuery(document).ready(function(){
	jQuery(".signup_details_wrap[data-key], .recurring_details_wrap[data-key]").each(function(){
		var $el=jQuery(this);
		if($el.closest(".wfocu_price_wrapper").length)return; /* already inside */
		var dataKey=$el.data("key");
		jQuery(".wfocu_price_wrapper[data-key=\""+dataKey+"\"]").each(function(){
			var $w=jQuery(this);
			if($w.children(".signup_details_wrap, .recurring_details_wrap").length)return;
			$w.append($el.clone().removeAttr("style"));
		});
		$el.hide();
	});
});
/* Per-dataKey saved original WFOCU sale price inner HTML, used to restore on one-time select. */
var _wfocuOrigSalePrice={};

/* Update WFOCU offer price widget in-place.
   Sublium\'s own frontend.min.js hides .wfocu_price_wrapper and creates .sublium-plan-price-wrapper
   during sublim_plan_selected. Our handler runs after Sublim\'s, so we remove the created div,
   update the original wrapper content, and call .show() to counteract the hide. */
function wfocuSublimSyncPrice(dataKey,priceHtml,summaryHtml){
	jQuery("div.sublium-plan-price-wrapper[data-key=\""+dataKey+"\"]").remove();
	var $wrapper=jQuery(".wfocu_price_wrapper[data-key=\""+dataKey+"\"]");
	if($wrapper.length&&priceHtml){
		var $parsed=jQuery("<div>").html(priceHtml);
		var $saleEl=$parsed.find(".wfocu-sale-price");
		var $regEl=$parsed.find(".wfocu-regular-price");
		if($saleEl.length){
			$wrapper.find(".wfocu-sale-price").html($saleEl.html());
			var $rw=$wrapper.find(".reg_wrapper");
			if($regEl.length&&$rw.length){$rw.find(".wfocu-regular-price").html($regEl.html());$rw.show();}
			else if($rw.length){$rw.hide();}
		}else if($regEl.length){
			$wrapper.find(".wfocu-sale-price").html($regEl.html());
			$wrapper.find(".reg_wrapper").hide();
		}
		$wrapper.show();
	}
	var $bill=jQuery(".wfocu-sublium-billing-summary");
	if($bill.length){
		if(summaryHtml){$bill.html(summaryHtml).show();}
		else{$bill.hide().empty();}
	}
}

jQuery(document.body).on("sublium_one_time_plan_selected",function(){
	var dataKey=jQuery(".wfocu-subscription-options").first().data("key");
	jQuery(".wfocu-subscription-options[data-key=\""+dataKey+"\"]").addClass("wfocu-one-time-active");
	jQuery(".wfocu-sublium-billing-summary").hide().empty();
	jQuery("div.sublium-plan-price-wrapper[data-key=\""+dataKey+"\"]").remove();
	var $wrapper=jQuery(".wfocu_price_wrapper[data-key=\""+dataKey+"\"]");
	if(_wfocuOrigSalePrice[dataKey]){
		$wrapper.find(".wfocu-sale-price").html(_wfocuOrigSalePrice[dataKey]);
	}
	$wrapper.find(".reg_wrapper").hide();
	$wrapper.show();
});

jQuery(document.body).on("sublium_plan_selected",function(e,data){
	if(!data||!data.plan_id||!data.selector)return;
	var elem=jQuery(data.selector).parents(".sublium-front-purchase-group[data-type="+data.data_single.type+"]");
	var upsell=elem.parents("div.wfocu_subs_plan_selector_wrap");
	if(upsell.length===0)return;
	upsell.find(".wfocu-subscription-options").removeClass("wfocu-one-time-active");
	upsell.find(".sublium-option-plan").val(data.plan_id);
	upsell.find(".sublium-option-plan").addClass("wfocu_convert_sub_hidden");
	upsell.find(".sublium-option-plan").data("sublium-data",data.data_single);
	try{
		var dataKey=elem.parents("div.wfocu-subscription-options").data("key");
		if(data.data_single.type===3){return;}
		var priceHtml=data.data_single.discounted_upsell_price_html||data.data_single.discounted_price_html;
		wfocuSublimSyncPrice(dataKey,priceHtml,data.data_single.display_summary||"");
	}catch(err){}
});

/* On page load: save original WFOCU sale price for one-time restoration;
   init price if a single plan is pre-selected via hidden input. */
jQuery(document).ready(function(){
	var dataKey=jQuery(".wfocu-subscription-options").first().data("key");
	if(!dataKey)return;
	var hasPlans=window.sublium_plans&&window.sublium_plans.length>0;
	var hasOnetime=window.sublium_onetime==="yes";
	if(!hasPlans&&!hasOnetime)return;
	/* Single plan: billing summary rendered by PHP (WCS-style divs). Only big widget needs JS update. */
	var isSinglePlan=(window.sublium_plans&&window.sublium_plans.length===1&&!hasOnetime);
	setTimeout(function(){
		var $wrapper=jQuery(".wfocu_price_wrapper[data-key=\""+dataKey+"\"]");
		_wfocuOrigSalePrice[dataKey]=$wrapper.first().find(".wfocu-sale-price").html();
		var $planInput=jQuery(".wfocu_subs_plan_selector_wrap[data-key=\""+dataKey+"\"] .sublium-option-plan").first();
		var selectedPlanId=parseInt($planInput.val())||0;
		if(selectedPlanId>0){
			var plans=window.sublium_plans||[];
			for(var i=0;i<plans.length;i++){
				if(plans[i].id===selectedPlanId&&plans[i].single_data){
					var freeTrial=plans[i].free_trial||0;
					if(isSinglePlan&&freeTrial>0){
						/* Free trial: show €0 in big widget; billing divs already PHP-rendered. */
						jQuery("div.sublium-plan-price-wrapper[data-key=\""+dataKey+"\"]").remove();
						$wrapper.find(".wfocu-sale-price").html(jQuery(".wfocu-sale-price").first().clone().text("").html()||"<span class=\"woocommerce-Price-amount amount\"><bdi><span class=\"woocommerce-Price-currencySymbol\">€</span>0.00</bdi></span>");
						$wrapper.find(".reg_wrapper").hide();
						$wrapper.show();
					}else{
						/* No free trial or multi-plan: update big widget; billing summary only for multi-plan. */
						var summaryHtml=isSinglePlan?"":( plans[i].single_data.display_summary||"");
						wfocuSublimSyncPrice(dataKey,plans[i].single_data.discounted_upsell_price_html||plans[i].single_data.discounted_price_html,summaryHtml);
					}
					break;
				}
			}
		}
	},200);
});

jQuery(document).on("wfocu_variation_selected",function(e,key,variationID,variationData){
	if(typeof window.subliumGetInstance!=="function")return;
	var instance=window.subliumGetInstance();
	if(!instance)return;
	setTimeout(function(){
		if(variationData.sublium_plans){
			instance.setVariationData(variationData);
			var currentPlan=parseInt(jQuery(".sublium-front-widget-container").data("sublium-plan-id"))||0;
			var oneTimePriceHtml=variationData.one_time_purchase_price_html||"";
			if(key&&variationID){
				var WFOCUVariationSelect=typeof window.WFOCU_Variation_Select!=="undefined"?window.WFOCU_Variation_Select:typeof WFOCU_Variation_Select!=="undefined"?WFOCU_Variation_Select:null;
				if(WFOCUVariationSelect&&typeof WFOCUVariationSelect.getOneTimePriceHtml==="function"){
					oneTimePriceHtml=WFOCUVariationSelect.getOneTimePriceHtml(key,variationID);
				}
			}
			instance.updatePlans(variationData.sublium_plans,variationData.one_time_purchase_available,oneTimePriceHtml,variationData.one_time_purchase_label,currentPlan);
		}else{
			instance.updatePlans([],"","");
		}
		instance.render();
		setTimeout(function(){jQuery(document.body).trigger("sublium_wfocu_after_variation_render");},200);
	},600);
});';

				add_action(
					'wp_footer',
					function () use ( $inline_script, $listener_script, $script_handle ) {
						wp_add_inline_script( $script_handle, $inline_script, 'before' );
						wp_add_inline_script( $script_handle, $listener_script, 'after' );
					},
					1
				);

				$one_time_price_html_static = $one_time_price_html;
			}
			echo '<div class="wfocu-subscription-options" data-key="' . esc_attr( $product_key ) . '">';
			\Sublium_WCS\Includes\Main\Plans::render_plans_group( $plans, $product, 'upsell', $default_plan, $this->one_time_supported );

			// When only one plan exists, render_plans_group outputs the plan-single template
			// (hidden inputs only) and sublium_plan_selected never fires — so wfocu_convert_sub_hidden
			// class and sublium-data are never set by JS. Emit a dedicated hidden input here so
			// the wfocu_additem_* JS filters can find and read plan data on Accept.
			if ( count( $plans ) === 1 ) {
				$single_plan = reset( $plans );
				echo '<input type="hidden" name="sublium-option-plan" class="sublium-option-plan wfocu_convert_sub_hidden" value="' . esc_attr( $single_plan->get_id() ) . '" />';

				// Only echo the billing summary + move script once per product_key per request —
				// the move script clones into the single shared .wfocu_price_wrapper on the page,
				// so repeat calls (once per accept-button widget instance) would stack duplicates.
				static $sublium_single_plan_rendered = array();
				if ( ! isset( $sublium_single_plan_rendered[ $product_key ] ) ) {
					$sublium_single_plan_rendered[ $product_key ] = true;
					$discount_type   = isset( $offer_data ) && isset( $offer_data->fields->{$product_key}->discount_type ) ? $offer_data->fields->{$product_key}->discount_type : '';
					$discount_amount = isset( $offer_data ) && isset( $offer_data->fields->{$product_key}->discount_amount ) ? $offer_data->fields->{$product_key}->discount_amount : 0;
					echo $this->get_sublium_billing_summary_html( $single_plan, $product, $product_key, 1, $discount_type, $discount_amount ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					$this->print_billing_move_script( $product_key );
				}
			}

			echo '</div>';
			$this->one_time_supported = false;
		}

		/**
		 * Shortcode output for Sublium plans selector
		 * Similar to wfocu_variation_selector_form shortcode
		 *
		 * @param array $atts Shortcode attributes
		 * @return string Shortcode output
		 */
		public function wfocu_sublium_plans_selector_output( $atts ) {
			$atts = shortcode_atts(
				array(
					'key'     => 1,
					'display' => 'yes',
				),
				$atts
			);

			$data = WFOCU_Core()->data->get( '_current_offer_data' );

			if ( false === $data ) {
				return '';
			}

			// Get product key from shortcode attribute or find it by index
			if ( ! isset( $data->products->{$atts['key']} ) ) {
				$atts['key'] = WFOCU_Core()->offers->get_product_key_by_index( $atts['key'], $data->products );
			}

			if ( ! isset( $data->products->{$atts['key']} ) ) {
				return '';
			}

			// Get product ID from offer data
			$product_id = isset( $data->products->{$atts['key']}->id ) ? absint( $data->products->{$atts['key']}->id ) : 0;
			if ( empty( $product_id ) ) {
				return '';
			}

			$product_key = $atts['key'];

			// Suppress selector when admin has pre-selected a plan for this product
			if ( isset( $data->fields->{$product_key}->plan_id ) && ! empty( $data->fields->{$product_key}->plan_id ) ) {
				return '';
			}

			// Start output buffering to capture schemes_template_html output
			ob_start();

			// Call schemes_template_html function with product_id and product_key
			$this->schemes_template_html( $product_id, $product_key );

			$output = ob_get_clean();

			// Handle display attribute
			if ( isset( $atts['display'] ) && 'no' === $atts['display'] ) {
				$output = str_replace( '<div class="wfocu-subs-product-attr-wrapper">', '<div class="wfocu-subs-product-attr-wrapper" style="display:none;">', $output );
			}

			return $output;
		}

		/**
		 * Add Sublium plans selector shortcode to backend shortcode list
		 *
		 * @param array $shortcode_list Existing shortcode list
		 * @return array Modified shortcode list with Sublium plans selector
		 */
		public function add_sublium_plans_selector_to_list( $shortcode_list ) {
			// Find the position after "All Products Subscription Plan List" or at the end
			$insert_position = false;
			$target_label    = __( 'All Products Subscription Plan List', 'woofunnels-upstroke-power-pack' );

			foreach ( $shortcode_list as $index => $item ) {
				if ( isset( $item['label'] ) && $target_label === $item['label'] ) {
					$insert_position = $index + 1;
					break;
				}
			}

			$sublium_shortcode = array(
				'label' => __( 'Sublium Plans Selector', 'woofunnels-upstroke-power-pack' ),
				'code'  => array(
					'single' => '[wfocu_sublium_plans_selector]',
					'multi'  => '[wfocu_sublium_plans_selector key="%s"]',
				),
			);

			if ( false !== $insert_position ) {
				// Insert after "All Products Subscription Plan List"
				array_splice( $shortcode_list, $insert_position, 0, array( $sublium_shortcode ) );
			} else {
				// Append to the end if target not found
				$shortcode_list[] = $sublium_shortcode;
			}

			return $shortcode_list;
		}

		/**
		 * Check if allow_one_time_purchase setting is enabled for the current offer
		 *
		 * @return bool True if one-time purchase is enabled in offer settings
		 */
		private function is_one_time_purchase_enabled() {
			// Get current offer ID
			$offer_id = WFOCU_Core()->data->get( 'current_offer' );
			if ( empty( $offer_id ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a public endpoint
				$offer_id = isset( $_REQUEST['offer_id'] ) ? absint( wp_unslash( $_REQUEST['offer_id'] ) ) : 0;
			}

			if ( empty( $offer_id ) ) {
				$offer_id = get_the_ID();

			}
			if ( empty( $offer_id ) ) {
				return false;
			}

			// Get offer meta data
			$offer_meta = WFOCU_Core()->offers->get_offer_meta( $offer_id );

			if ( empty( $offer_meta ) || ! is_object( $offer_meta ) ) {
				return false;
			}

			// Check if allow_one_time_purchase setting is enabled
			if ( ! empty( $offer_meta->settings ) && is_object( $offer_meta->settings ) ) {
				$allow_one_time = isset( $offer_meta->settings->allow_one_time_purchase ) ? $offer_meta->settings->allow_one_time_purchase : false;
				return wc_string_to_bool( $allow_one_time );
			}

			return false;
		}


		/**
		 * @param $product_id
		 * @param string $offer_data
		 *
		 * Get product all subscription data
		 *
		 * @return array|void
		 */
		public function get_subscription_products_options( $product_id, $offer_data = '', $product_key = '', $is_front = false ) {
			if ( ! $this->is_enable() ) {
				return;
			}

			if ( $offer_data === '' ) {
				if ( isset( WFOCU_Core()->template_loader ) && isset( WFOCU_Core()->template_loader->product_data ) ) {
					$offer_data = WFOCU_Core()->template_loader->product_data;

				} else {
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a public endpoint
					$get_offer_id = isset( $_REQUEST['offer_id'] ) ? absint( wp_unslash( $_REQUEST['offer_id'] ) ) : WFOCU_Core()->data->get( 'current_offer' );
					$offer_data   = WFOCU_Core()->offers->get_offer_meta( $get_offer_id );
				}
			}

			$discount_data = array();
			if ( ! empty( $offer_data ) ) {
				if ( $product_key === '' ) {
					foreach ( $offer_data->products as $key => $value ) {
						if ( absint( $value ) === absint( $product_id ) ) {
							$product_key   = $key;
							$discount_data = $offer_data->fields->{$product_key};
							break;
						}
					}
				} else {
					$discount_data = $offer_data->fields->{$product_key};

				}
			}

			return array(
				'plans'         => $this->get_cached_plans( absint( $product_id ) ),
				'discount_data' => $discount_data,
			);
		}


		/**
		 * Client-side JavaScript for handling subscription selection
		 */
		public function render_js() {
			if ( ! $this->is_enable() ) {
				return '';
			}
			?>
			<script>
				jQuery(document).ready(function () {

					/**
					 * Initialise sublium-data on pre-selected plan inputs (single-plan case).
					 * When only one plan exists, sublium_plan_selected never fires, so
					 * wfocu_convert_sub_hidden and sublium-data are never set by the main
					 * Sublium frontend JS. This reads window.sublium_plans and populates
					 * the hidden input added by PHP above.
					 */
					function wfocu_init_sublium_preselected_plans() {
						jQuery('.wfocu_subs_plan_selector_wrap').each(function () {
							var $wrap  = jQuery(this);
							var $input = $wrap.find('input.sublium-option-plan');
							if ($input.length === 0) {
								return;
							}
							if ($input.hasClass('wfocu_convert_sub_hidden') && $input.data('sublium-data')) {
								return;
							}
							var planId = $input.val();
							if (!planId || planId === '0') {
								return;
							}
							var plans       = window.sublium_plans || [];
							var matchedPlan = null;
							for (var i = 0; i < plans.length; i++) {
								if (String(plans[i].id) === String(planId)) {
									matchedPlan = plans[i].single_data || plans[i];
									break;
								}
							}
							if (matchedPlan) {
								$input.addClass('wfocu_convert_sub_hidden');
								$input.data('sublium-data', matchedPlan);
							}
						});
					}

					setTimeout(wfocu_init_sublium_preselected_plans, 500);
					jQuery('body').on('sublium_default_first_plan_selected', function () {
						setTimeout(wfocu_init_sublium_preselected_plans, 200);
					});

					wfocuCommons.addFilter('wfocu_additem_data', function (extraData, key, getVariationID) {
						if (jQuery('.wfocu_subs_plan_selector_form[data-key=' + key + ']').length > 0) {
							let subs_val = '.wfocu_subs_plan_selector_form[data-key=' + key + '] input[name="sublium-option-plan"].wfocu_convert_sub_hidden';
							if (jQuery(subs_val).length > 0) {
								subs_val = jQuery(subs_val);
							} else {
								subs_val = jQuery('.wfocu_subs_plan_selector_form[data-key=' + key + '] input[name="wfocu_convert_to_sub"]:checked');
							}
							if (subs_val && typeof subs_val.val() !== 'undefined' && subs_val.val() !== '') {
								let data = subs_val.data('sublium-data');
								extraData.push('_sublium_data=' + JSON.stringify({"plan_id":subs_val.val(),'data':data._sublium_data}));
							}
						}
						return extraData;
					});

					wfocuCommons.addFilter('wfocu_additem_price', function (getPrice, key, getVariationID) {
						try {
							if (jQuery('.wfocu_subs_plan_selector_form[data-key=' + key + ']').length > 0) {
								let subs_val = jQuery('.wfocu_subs_plan_selector_form[data-key=' + key + '] input[name="sublium-option-plan"].wfocu_convert_sub_hidden');
								let data = subs_val.data('sublium-data');
								if (undefined !== data && Object.keys(data).length > 0) {
									// Use initial_price when plan has a free trial (first charge = signup fee or $0).
									getPrice = (data.free_trial > 0) ? (data.initial_price || 0) : data.recurring_price;
								}
							}
						} catch (e) {

						}
						return getPrice;
					});
					wfocuCommons.addFilter('wfocu_additem_taxes', function (getPrice, key, getVariationID) {
						try {
							if (jQuery('.wfocu_subs_plan_selector_form[data-key=' + key + ']').length > 0) {
								let subs_val = jQuery('.wfocu_subs_plan_selector_form[data-key=' + key + '] input[name="sublium-option-plan"].wfocu_convert_sub_hidden');
								let data = subs_val.data('sublium-data');
								if (undefined !== data && Object.keys(data).length > 0) {
									if (data.free_trial > 0) {
										// Trial plan: use initial_price_tax (signup fee tax) or 0 if no signup fee.
										getPrice = data.initial_price_tax || 0;
									} else if (data.hasOwnProperty('recurring_price_tax')) {
										getPrice = data.recurring_price_tax;
									}
								}
							}
						} catch (e) {

						}
						return getPrice;
					});
				});
			</script>
			<?php
			$this->clear_elementor_cache();
		}

		/**
		 * Register FunnelKit Payments rule type for offers
		 *
		 * @param array $args Rule type arguments
		 *
		 * @return array Modified rule type arguments
		 */
		public function register_rule_type( $args ) {

			if ( $this->is_enable() && is_array( $args ) ) {
				$args[ __( 'Sublium Subscription', 'sublium-subscriptions-for-woocommerce' ) ] = array(
					'order_contain_sublium_subscription' => __( 'Order Contain Subscriptions', 'sublium-subscriptions-for-woocommerce' ),
					'order_sublium_recurring_plan'       => __( 'Recurring Products', 'sublium-subscriptions-for-woocommerce' ),
					'order_sublium_subscribe_and_save'   => __( 'Subscribe & Save Products', 'sublium-subscriptions-for-woocommerce' ),
					'order_sublium_installment_plan'     => __( 'Installment Products', 'sublium-subscriptions-for-woocommerce' ),
				);
			}

			return $args;
		}

		/**
		 * Register nonce for subscription product search
		 *
		 * @param array $data Script data
		 *
		 * @return array Modified script data
		 */
		public function register_subs_product_search_nonce( $data ) {
			if ( ! $this->is_enable() || ! is_array( $data ) ) {
				return $data;
			}

			$data['search_sublium_products_nonce'] = wp_create_nonce( 'search_sublium_products_products' );

			// The JS always reads search_subs_products_nonce for the wfocu_subs_product_search action.
			// Register it here so the nonce is available when only Sublium is active (WC-ATT not present).
			if ( ! isset( $data['search_subs_products_nonce'] ) ) {
				$data['search_subs_products_nonce'] = wp_create_nonce( 'search_subs_products' );
			}

			return $data;
		}

		/**
		 * Search for products with subscription plans
		 *
		 * @param string $str Search term
		 *
		 * @return array|void List of products with plans or JSON response
		 */
		public function subs_product_search( $str = '' ) {
			if ( ! $this->is_enable() ) {
				return array();
			}

			if ( $str !== 'get_data' ) {
				check_ajax_referer( 'search_subs_products', 'security' );
				if ( ! current_user_can( 'edit_shop_orders' ) ) {
					wp_send_json( array() );
				}
			}

			// Get search term
			$term          = empty( $str ) ? ( isset( $_REQUEST['term'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['term'] ) ) : '' ) : $str; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$get_data_mode = ( $str === 'get_data' );
			if ( $get_data_mode ) {
				$term = '';
			}

			// Search for products
			$ids             = WFOCU_Common::search_products( $term, true );
			$product_objects = array_filter( array_map( 'wc_get_product', $ids ), 'wc_products_array_filter_editable' );
			$products        = array();

			foreach ( $product_objects as $product_object ) {
				if ( 'publish' !== $product_object->get_status() ) {
					continue;
				}

				$product_name = rawurldecode( WFOCU_Common::get_formatted_product_name( $product_object ) );
				$product_id   = $product_object->get_id();

				// Get subscription plans
				$available_plans = $this->get_cached_plans( $product_object );
				if ( empty( $available_plans ) ) {
					continue;
				}

				// Check for one-time purchase option
				$one_time_purchase = \Sublium_WCS\Includes\Main\Product::get_instance()->if_product_supports_one_time( $product_object );
				if ( $one_time_purchase ) {
					$products[ $product_id . '-one_time' ] = $product_name . ' - (one time)';
				}

				// Add subscription plans
				foreach ( $available_plans as $plan ) {

					$plan_id       = $plan->get_id();
					$regular_price = $product_object->get_regular_price() ?: $product_object->get_price();
					$price         = $plan->get_recurring_cart_price( $regular_price, $product_object );

					// Apply currency conversion for multi-currency compatibility
					if ( class_exists( '\Sublium_WCS\Compatibilities\Compatibility' ) ) {
						$price = \Sublium_WCS\Compatibilities\Compatibility::get_fixed_currency_price( $price );
					}

					// Format description based on billing frequency
					if ( $plan->get_billing_frequency() === 1 ) {
						$description = '/ ' . $plan->get_billing_interval_string();
					} else {
						$description = 'every ' . $plan->get_billing_frequency() . ' ' . $plan->get_billing_interval_string() . ( $plan->get_billing_frequency() > 1 ? 's' : '' );
					}

					$products[ $product_id . '-' . $plan_id ] = $product_name . ' - (' . get_woocommerce_currency_symbol() . number_format( $price, 2 ) . ' ' . $description . ')';
				}
			}

			$products = apply_filters( 'wfocu_json_search_found_subs_products', $products );

			if ( $get_data_mode ) {
				return $products;
			}

			wp_send_json( $products );
		}


		/**
		 * Add subscription plan data to offer data
		 *
		 * @param object $output Output object
		 * @param object $offer_data Offer data
		 *
		 * @return object Modified output object
		 */
		/**
		 * Inject pre-selected plan_id into the upsell package at charge time.
		 * When admin pre-selects a plan during offer configuration, this ensures the plan
		 * is applied without requiring the buyer to see or interact with the plan selector.
		 */
		public function inject_preselected_plan_into_package( $package ) {
			if ( ! $this->is_enable() || ! isset( $package['products'] ) ) {
				return $package;
			}

			$offer_data = WFOCU_Core()->data->get( '_current_offer_data' );
			if ( ! $offer_data || ! isset( $offer_data->fields ) ) {
				return $package;
			}

			foreach ( $package['products'] as $i => $product ) {
				$hash = isset( $product['hash'] ) ? $product['hash'] : '';
				if ( empty( $hash ) ) {
					continue;
				}

				// Skip if buyer already sent a plan selection
				if ( ! empty( $product['args']['variation']['_sublium_data'] ) ) {
					continue;
				}

				if ( ! isset( $offer_data->fields->{$hash}->plan_id ) || empty( $offer_data->fields->{$hash}->plan_id ) ) {
					continue;
				}

				$plan_id    = absint( $offer_data->fields->{$hash}->plan_id );
				$wc_product = isset( $product['data'] ) ? $product['data'] : wc_get_product( $product['id'] );

				if ( ! $wc_product instanceof \WC_Product ) {
					continue;
				}

				$plan_obj = \Sublium_WCS\Includes\Main\Plans::get_plan_by_id( $plan_id, $wc_product );
				if ( $plan_obj ) {
					$package['products'][ $i ]['args']['variation']['_sublium_data'] = wp_json_encode(
						array(
							'plan_id' => $plan_id,
							'data'    => $plan_obj,
						)
					);
				}
			}

			return $package;
		}

		public function add_scheme_plan_data( $output, $offer_data ) {
			if ( ! $this->is_enable() ) {
				return $output;
			}

			$is_front = true;
			if ( did_action( 'admin_enqueue_scripts' ) ) {
				$is_front = false;
			}

			foreach ( $offer_data->products as $hash_key => $pid ) {
				$product = wc_get_product( $pid );
				if ( ! $product instanceof \WC_Product ) {
					continue;
				}
				$get_plans = $this->get_subscription_products_options( $product->get_id(), $offer_data, '', $is_front );

				$available_plans = $get_plans['plans'];
				if ( empty( $available_plans ) ) {
					continue;
				}

				// Process subscription plans
				foreach ( $available_plans as $plan ) {
					$plan->update_meta_data( 'upsell_discount_data', $get_plans['discount_data'] );
				}
			}

			return $output;
		}




		/**
		 * Set subscription prices for calculation.
		 *
		 * @param float       $price The product price.
		 * @param \WC_Product $product The product object.
		 * @param $plan \Sublium\Includes\Abstracts\Plan
		 *
		 * @return float|int|mixed|null The modified price.
		 */
		public function set_subscription_prices_for_calculation( $price, $product, $plan ) {

			try {
				$upsell_discount_data = $plan->get_meta( 'upsell_discount_data' );
				if ( ! empty( $upsell_discount_data ) && $upsell_discount_data->discount_amount > 0 ) {
					$price = WFOCU_Common::apply_discount( $price, $upsell_discount_data );
				}
			} catch ( Error | Exception $error ) {

			}

			return $price;
		}

		public function add_scheme_product_level( $output ) {
			try {
				if ( ! isset( $output['products'], $output['schemes'] ) ) {
					return $output;
				}

				foreach ( $output['products'] as $index => $product ) {
					if ( ! isset( $product['id'] ) || ! $output['schemes'][ $product['id'] ] ) {
						continue;
					}
					$output['products'][ $index ]['schemes'] = array_values( $output['schemes'][ $product['id'] ] );
				}
			} catch ( Error | Exception $e ) {

			}

			return $output;
		}

		/**
		 * Validate if upsell should be shown when COD payment method is selected with Sublium subscription products
		 *
		 * @param bool   $validation_result Current validation result
		 * @param object $offer_build Offer build data
		 *
		 * @return bool False if COD is selected with Sublium subscription products, otherwise returns original result
		 */
		/**
		 * Skip the post-purchase offer when the original order was paid via a gateway WFOCU
		 * considers upsell-capable (so the offer would otherwise render and attempt a token
		 * charge) but which Sublium doesn't support for recurring/subscription billing — e.g.
		 * COD, or any gateway outside Sublium's own whitelist (Gateways::get_gateway()).
		 * Charging such a gateway for a Sublium-plan product would create a broken, non-recurring
		 * "subscription". Scoped to offers that actually resolve to a Sublium plan for at least
		 * one product — a plain one-time-purchase offer on the same gateway is unaffected.
		 *
		 * @param bool   $validation_result
		 * @param object $offer_build The current offer's product/field data (->products, ->fields).
		 *
		 * @return bool
		 */
		public function maybe_validate_sublium_gateway_support( $validation_result, $offer_build ) {
			// If validation already failed, return false
			if ( false === $validation_result ) {
				return $validation_result;
			}

			// Check if offer_build is valid
			if ( ! is_object( $offer_build ) || empty( $offer_build->products ) ) {
				return $validation_result;
			}

			// Check if Sublium is enabled
			if ( ! $this->is_enable() ) {
				return $validation_result;
			}

			// Get the current order
			$order = WFOCU_Core()->data->get_current_order();
			if ( ! $order instanceof WC_Order ) {
				return $validation_result;
			}

			$payment_method = $order->get_payment_method();
			if ( empty( $payment_method ) ) {
				return $validation_result;
			}

			// Sublium already supports this gateway for recurring billing — nothing to block.
			if ( ! is_null( \Sublium_WCS\Includes\Main\Gateways::get_instance()->get_gateway( $payment_method ) ) ) {
				return $validation_result;
			}

			if ( ! $this->offer_has_sublium_plan_product( $offer_build ) ) {
				return $validation_result;
			}

			WFOCU_Core()->log->log( sprintf( 'Offer Validation failed: Upsell product resolves to a Sublium subscription plan and payment method "%s" is not supported by Sublium for recurring billing. Upsell will only show with supported payment gateways.', $payment_method ) );

			return false;
		}

		/**
		 * Whether at least one product in the offer resolves to a Sublium subscription plan —
		 * either explicitly pre-assigned (fields->{$hash}->plan_id) or, absent that, because the
		 * product has any plans at all (Sublium auto-assigns the first one at checkout, same as
		 * set_switcher_plan_price()/set_bump_plan_price() elsewhere in this class). Products
		 * explicitly configured as force_one_time are excluded even if the underlying product has
		 * plans, since the offer treats them as a plain purchase.
		 *
		 * @param object $offer_build
		 *
		 * @return bool
		 */
		private function offer_has_sublium_plan_product( $offer_build ) {
			foreach ( $offer_build->products as $hash_key => $product_data ) {
				$product_id = isset( $product_data->id ) ? absint( $product_data->id ) : 0;
				if ( ! $product_id ) {
					continue;
				}

				$fields = isset( $offer_build->fields->{$hash_key} ) ? $offer_build->fields->{$hash_key} : null;
				if ( $fields && ! empty( $fields->force_one_time ) ) {
					continue;
				}

				if ( $fields && ! empty( $fields->plan_id ) ) {
					return true;
				}

				if ( ! empty( $this->get_cached_plans( $product_id ) ) ) {
					return true;
				}
			}

			// Set the invalidation reason so the FunnelKit timeline shows a meaningful skip reason instead of "NA".
			if ( isset( WFOCU_Core()->template_loader ) && class_exists( 'WFOCU_Offers' ) ) {
				WFOCU_Core()->template_loader->invalidation_reason = WFOCU_Offers::INVALIDATION_NOT_SUPPORT_SUBSCRIPTION;
			}

			// Set the skip id so the customer order gets the matching "subscription gateway not supported" note (id 10).
			if ( isset( WFOCU_Core()->session_db ) ) {
				WFOCU_Core()->session_db->set_skip_id( 10 );
			}

			return false;
		}
		public function clear_elementor_cache() { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Parameters required by hook signature
			try {
				$offer_id = WFOCU_Core()->data->get( 'current_offer' );

				if ( empty( $offer_id ) ) {
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a public endpoint
					$offer_id = isset( $_REQUEST['offer_id'] ) ? absint( wp_unslash( $_REQUEST['offer_id'] ) ) : 0;
				}

				if ( empty( $offer_id ) ) {
					$offer_id = get_the_ID();
				}

				$offer_id = absint( $offer_id );
				if ( $offer_id > 0 && class_exists( '\Elementor\Core\Base\Document' ) ) {
					$cache_meta_key = \Elementor\Core\Base\Document::CACHE_META_KEY;
					if ( ! empty( $cache_meta_key ) ) {
						delete_post_meta( $offer_id, $cache_meta_key );

					}
				}
			} catch ( \Throwable $e ) {
				// Log error silently to avoid breaking the upsell process.
				// Using \Throwable catches both Exception and Error (PHP 7.0+).
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'WFOCU Sublium Elementor cache deletion error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			}
		}

		/**
		 * Add Sublium plans data to product details
		 *
		 * @param array       $product_details Product details array
		 * @param \WC_Product $product         Product object
		 * @param int         $offer_id        Offer ID
		 * @param int         $funnel_id       Funnel ID
		 * @param string      $key            Product hash key
		 *
		 * @return array Modified product details
		 */
		public function add_sublium_plans_data( $product_details, $product, $offer_id, $funnel_id, $key ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Parameters required by filter hook signature
			if ( ! $this->is_enable() || ! $product instanceof \WC_Product ) {
				return $product_details;
			}

			$product_details['has_sublium_plans'] = $this->check_product_has_sublium_plans( $product );

			return $product_details;
		}

		/**
		 * Add Sublium plans data to product schema
		 *
		 * @param array       $product_details Product details array
		 * @param \WC_Product $product         Product object
		 * @param array       $posted_product  Posted product data
		 *
		 * @return array Modified product details
		 */
		public function add_sublium_plans_data_schema( $product_details, $product, $posted_product ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Parameters required by filter hook signature
			if ( ! $this->is_enable() || ! $product instanceof \WC_Product ) {
				return $product_details;
			}

			$product_details['has_sublium_plans'] = $this->check_product_has_sublium_plans( $product );

			return $product_details;
		}

		/**
		 * Check if product has Sublium plans
		 *
		 * @param \WC_Product $product Product object
		 *
		 * @return bool True if product has Sublium plans
		 */
		private function check_product_has_sublium_plans( $product ) {
			if ( ! $this->is_enable() ) {
				return false;
			}

			$plans             = $this->get_cached_plans( $product->get_id() );
			$has_sublium_plans = ! empty( $plans ) && is_array( $plans ) && count( $plans ) > 0;

			// For variable products, also check variations
			if ( ! $has_sublium_plans && $product->is_type( 'variable' ) ) {
				$variation_ids = $product->get_children();
				foreach ( $variation_ids as $variation_id ) {
					$variation = wc_get_product( absint( $variation_id ) );
					if ( $variation instanceof \WC_Product_Variation ) {
						$variation_plans = $this->get_cached_plans( $variation_id );
						if ( ! empty( $variation_plans ) && is_array( $variation_plans ) && count( $variation_plans ) > 0 ) {
							$has_sublium_plans = true;
							break;
						}
					}
				}
			}

			return $has_sublium_plans;
		}
		/**
		 * Append Sublium subscription plan variants to FunnelKit app product search results.
		 * Fires on the wffn_woocommerce_json_search_found_products filter used by the FunnelKit React SPA.
		 *
		 * @param array $products Existing product search results.
		 * @return array Products with plan variants appended.
		 */
		public function append_sublium_plans_to_funnelkit_search( $products ) {
			if ( ! $this->is_enable() || empty( $products ) ) {
				return $products;
			}

			$plan_items = array();
			foreach ( $products as $product_data ) {
				$product_id = isset( $product_data['id'] ) ? absint( $product_data['id'] ) : 0;
				if ( ! $product_id ) {
					continue;
				}

				$product = wc_get_product( $product_id );
				if ( ! $product instanceof \WC_Product ) {
					continue;
				}

				$plans = \Sublium_WCS\Includes\Main\Product::get_instance()->get_cached_plans_for_product( $product );
				if ( empty( $plans ) ) {
					continue;
				}

				$product_name    = rawurldecode( $product->get_title() );
				$currency_symbol = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' );

				// One-time purchase isn't a Sublium plan entity — it's a per-product toggle — so it
				// never appears in $plans above. Add its own labeled row here, same as each plan below,
				// so it's just as discoverable/pickable in search instead of only the unlabeled base row.
				if ( \Sublium_WCS\Includes\Main\Product::get_instance()->if_product_supports_one_time( $product ) ) {
					$one_time_label = $this->get_one_time_purchase_label();
					$plan_items[]   = array(
						'id'                   => $product_id,
						'product'              => $product_name . ' - ' . $one_time_label,
						'product_attribute'    => '',
						'product_price'        => $currency_symbol . number_format( (float) $product->get_price(), 2 ),
						'product_image'        => isset( $product_data['product_image'] ) ? $product_data['product_image'] : '',
						'product_stock'        => isset( $product_data['product_stock'] ) ? $product_data['product_stock'] : 0,
						'product_stock_status' => isset( $product_data['product_stock_status'] ) ? $product_data['product_stock_status'] : true,
						'product_type'         => isset( $product_data['product_type'] ) ? $product_data['product_type'] : 'simple',
						'currency_symbol'      => $currency_symbol,
						'product_meta_data'    => array( 'sublium_one_time' => 1 ),
					);
				}

				foreach ( $plans as $plan ) {
					$plan_id  = $plan->get_id();
					$price    = $plan->get_recurring_cart_price( $product->get_price(), $product );

					$plan_title = html_entity_decode( strip_tags( $plan->get_title( $product ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
					$plan_items[] = array(
						'id'                   => $product_id,
						'product'              => $product_name . ' - ' . $plan_title,
						// product_attribute shows in the dropdown alongside the name; clarify this is a plan variant.
						'product_attribute'    => '',
						'product_price'        => $currency_symbol . number_format( (float) $price, 2 ),
						'product_image'        => isset( $product_data['product_image'] ) ? $product_data['product_image'] : '',
						'product_stock'        => isset( $product_data['product_stock'] ) ? $product_data['product_stock'] : 0,
						'product_stock_status' => isset( $product_data['product_stock_status'] ) ? $product_data['product_stock_status'] : true,
						'product_type'         => isset( $product_data['product_type'] ) ? $product_data['product_type'] : 'simple',
						'currency_symbol'      => $currency_symbol,
						'product_meta_data'=>['sublium_plan'=>$plan_id]
					);
				}
			}

			return array_merge( $products, $plan_items );
		}

		/**
		 * Intercept the FunnelKit app checkout product-add REST request.
		 * Decodes encoded plan variant IDs (product_id * PLAN_ID_MULTIPLIER + plan_id)
		 * back to plain product IDs so wc_get_product() can resolve them,
		 * and stores the plan_id context for the post-save hook.
		 *
		 * @param mixed            $result  Short-circuit response (null = proceed normally).
		 * @param WP_REST_Server   $server  REST server instance.
		 * @param WP_REST_Request  $request Incoming request.
		 * @return mixed Unchanged $result (null).
		 */
		public function intercept_checkout_add_product_request( $result, $server, $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- $server required by filter signature
			if ( ! $this->is_enable() ) {
				return $result;
			}

			if ( ! preg_match( '#^/funnelkit-app/funnel-checkout/(\d+)/products$#', $request->get_route(), $matches ) ) {
				return $result;
			}

			if ( ! in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
				return $result;
			}

			$body = json_decode( $request->get_body(), true );
			if ( ! isset( $body['products'] ) || ! is_array( $body['products'] ) ) {
				return $result;
			}

			$has_plan_ids = false;
			$new_products = array();
			$plan_id_map  = array();

			foreach ( $body['products'] as $pid ) {
				$pid     = absint( $pid );
				$real_pid = $pid;
				$plan_id  = 0;
				// Detect encoded Sublium plan variant: combined_id = product_id * PLAN_ID_MULTIPLIER + plan_id
				if ( $pid > self::PLAN_ID_MULTIPLIER && ! wc_get_product( $pid ) ) {
					$candidate_plan_id    = $pid % self::PLAN_ID_MULTIPLIER;
					$candidate_product_id = intdiv( $pid, self::PLAN_ID_MULTIPLIER );
					if ( $candidate_product_id > 0 && wc_get_product( $candidate_product_id ) ) {
						$real_pid = $candidate_product_id;
						$plan_id  = $candidate_plan_id;
					}
				}
				$new_products[] = (string) $real_pid;
				if ( $plan_id > 0 ) {
					$plan_id_map[ $real_pid ] = $plan_id;
					$has_plan_ids             = true;
				}
			}

			if ( $has_plan_ids ) {
				$this->intercepted_checkout_step_id = absint( $matches[1] );
				$this->pending_checkout_plan_ids    = $plan_id_map;
				$body['products']                   = $new_products;
				$request->set_body( wp_json_encode( $body ) );
			}

			return $result;
		}

		/**
		 * After _wfacp_selected_products meta is saved, inject plan_id into any newly added
		 * product whose ID was submitted in encoded plan variant format.
		 *
		 * @param int    $meta_id   Meta row ID.
		 * @param int    $object_id Post ID.
		 * @param string $meta_key  Meta key.
		 * @param mixed  $meta_value Value that was saved.
		 */
		public function inject_plan_id_after_checkout_product_save( $meta_id, $object_id, $meta_key, $meta_value ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- $meta_id, $meta_value required by hook signature
			if ( '_wfacp_selected_products' !== $meta_key || empty( $this->pending_checkout_plan_ids ) ) {
				return;
			}

			if ( absint( $object_id ) !== $this->intercepted_checkout_step_id ) {
				return;
			}

			// Read directly from DB to bypass WFACP_Common's post-meta cache.
			$products = get_post_meta( absint( $object_id ), '_wfacp_selected_products', true );
			if ( empty( $products ) || ! is_array( $products ) ) {
				return;
			}

			$modified = false;
			foreach ( $products as $key => &$product ) {
				$pid = isset( $product['id'] ) ? absint( $product['id'] ) : 0;
				if ( isset( $this->pending_checkout_plan_ids[ $pid ] ) && empty( $product['plan_id'] ) ) {
					$product['plan_id'] = $this->pending_checkout_plan_ids[ $pid ];
					$modified           = true;
				}
			}
			unset( $product );

			if ( ! $modified ) {
				return;
			}

			// Remove hooks to prevent recursion, re-save, then restore.
			remove_action( 'updated_post_meta', array( $this, 'inject_plan_id_after_checkout_product_save' ), 10 );
			remove_action( 'added_post_meta', array( $this, 'inject_plan_id_after_checkout_product_save' ), 10 );
			WFACP_Common::update_page_product( absint( $object_id ), $products );
			add_action( 'updated_post_meta', array( $this, 'inject_plan_id_after_checkout_product_save' ), 10, 4 );
			add_action( 'added_post_meta', array( $this, 'inject_plan_id_after_checkout_product_save' ), 10, 4 );

			// Clear context.
			$this->pending_checkout_plan_ids    = array();
			$this->intercepted_checkout_step_id = 0;
		}
	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Sublium_Compatibility(), 'wfocu_sublium' );
}
