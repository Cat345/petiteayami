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
		 * Constructor
		 */
		public function __construct() {

			// Frontend hooks
			add_action( 'wfocu_add_custom_html_above_accept_button', array( $this, 'schemes_template_html' ), 20, 2 );
			add_action( 'footer_after_print_scripts', array( $this, 'render_js' ) );

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

			// Validation hook to prevent upsell when COD is selected with Sublium subscription products
			add_filter( 'wfocu_offer_validation_result', array( $this, 'maybe_validate_sublium_cod' ), 10, 2 );
			add_filter( 'wfocu_variations_attributes', array( $this, 'modify_variations_attributes' ), 10, 4 );
			add_action( 'wfocu_offer_setup_completed', array( $this, 'clear_elementor_cache' ), 5, 2 );

			// Add has_sublium_plans key to product data in API responses
			add_filter( 'wfocu_offer_product_details', array( $this, 'add_sublium_plans_data' ), 10, 5 );
			add_filter( 'wfocu_offer_product_schema', array( $this, 'add_sublium_plans_data_schema' ), 10, 3 );
		}
		public function clear_elementor_cache_on_offer_setup_completed() {
			$this->clear_elementor_cache();
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

					$data['_sublium_data']['sublium_tax_data'] = $tax_data;
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
		 * Render subscription plan options above the accept button
		 *
		 * @param int    $product_id Product ID
		 * @param string $product_key Product key in the offer
		 */
		public function schemes_template_html( $product_id, $product_key = '' ) {

			if ( ! $this->is_enable() || false === apply_filters( 'wfocu_sublium_plans_list', true, $product_id, $product_key ) ) {

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

				add_action(
					'wp_footer',
					function () use ( $inline_script, $script_handle ) {
						wp_add_inline_script( $script_handle, $inline_script, 'before' );
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
									getPrice = data.recurring_price;
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
								if (undefined !== data && Object.keys(data).length > 0 && data.hasOwnProperty('recurring_price_tax')) {
									getPrice = data.recurring_price_tax;
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
		public function maybe_validate_sublium_cod( $validation_result, $offer_build ) {
			// If validation already failed, return false
			if ( false === $validation_result ) {
				return $validation_result;
			}

			// Check if offer_build is valid
			if ( ! is_object( $offer_build ) ) {
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

			// Check if payment method is COD (cash on delivery)
			$payment_method = $order->get_payment_method();
			if ( empty( $payment_method ) || 'cod' !== $payment_method ) {
				return $validation_result;
			}

			// COD is selected and offer contains Sublium subscription plans - prevent upsell
			WFOCU_Core()->log->log( 'Offer Validation failed: Upsell product contains Sublium subscription plan and Cash on Delivery payment method is selected. Upsell will only show with supported payment gateways.' );

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

			$has_sublium_plans                    = $this->check_product_has_sublium_plans( $product );
			$product_details['has_sublium_plans'] = $has_sublium_plans;

			// Set default discount_type to sale price option for sublium products
			// since regular price discounts are not available for sublium plans
			if ( $has_sublium_plans && isset( $product_details['discount_type'] ) ) {
				if ( 'percentage_on_reg' === $product_details['discount_type'] ) {
					$product_details['discount_type'] = 'percentage_on_sale';
				} elseif ( 'fixed_on_reg' === $product_details['discount_type'] ) {
					$product_details['discount_type'] = 'fixed_on_sale';
				}
			}

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

			$has_sublium_plans                    = $this->check_product_has_sublium_plans( $product );
			$product_details['has_sublium_plans'] = $has_sublium_plans;

			// Set default discount_type to sale price option for sublium products
			// since regular price discounts are not available for sublium plans
			if ( $has_sublium_plans && isset( $product_details['discount_type'] ) ) {
				if ( 'percentage_on_reg' === $product_details['discount_type'] ) {
					$product_details['discount_type'] = 'percentage_on_sale';
				} elseif ( 'fixed_on_reg' === $product_details['discount_type'] ) {
					$product_details['discount_type'] = 'fixed_on_sale';
				}
			}

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
	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_Sublium_Compatibility(), 'wfocu_sublium' );
}
