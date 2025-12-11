<?php
/**
 * compatibility for all thing subscription plugin
 */
if ( ! class_exists( 'WFOCU_WC_ATTS_Compatibility' ) ) {
	class WFOCU_WC_ATTS_Compatibility {

		public function __construct() {
			add_action( 'wfocu_add_custom_html_above_accept_button', array( $this, 'schemes_template_html' ), 10, 2 );
			add_filter( 'wfocu_rule_type_product_args', array( $this, 'register_rule_type' ), 10, 1 );
			add_action( 'footer_after_print_scripts', array( $this, 'render_js' ) );
			add_filter( 'wfocu_force_subscription_product', array( $this, 'push_force_subscription_product' ), 10, 2 );
			add_filter( 'wfocu_params_localize_script_data', array( $this, 'register_subs_product_search_nonce' ) );
			add_action( 'wp_ajax_wfocu_subs_product_search', array( $this, 'subs_product_search' ) );
			add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hide_order_meta' ) );

			add_filter( 'wfocu_offer_data', array( $this, 'add_scheme_plan_data' ), 10, 2 );
			add_filter( 'wfocu_offer_product_added', array( $this, 'update_scheme_plan_data' ), 10, 4 );
			add_filter( 'wfocu_update_offer_save_setting', array( $this, 'save_scheme_plan_data' ), 10, 3 );
			add_filter( 'wfocu_add_custom_html_in_offer_admin_area', array( $this, 'list_schemes_admin_offer' ) );
			add_shortcode( 'wfocu_subscription_plans_list', array( $this, 'subscription_plans_list_shortcode' ) );
			add_filter( 'wfocu_assets_styles', array( $this, 'add_styles' ) );
			add_filter( 'wfocu_customize_recurring_price', array( $this, 'update_recurring_price' ), 10, 3 );

		}

		public function is_enable() {
			if ( class_exists( 'WCS_ATT' ) && class_exists( 'WCS_ATT_Product_Schemes' ) ) {
				return true;
			}

			return false;
		}

		/**
		 * @param $scheme_option
		 * @param $offer_option
		 * @param $product
		 * @param $sub_price_html
		 *
		 * Update subscription plan description for offer
		 *
		 * @return array
		 */
		public function get_product_price( $scheme_option, $offer_option, $product, $sub_price_html ) {

			$args = [];

			if ( is_array( $scheme_option ) && isset( $scheme_option['pricing_mode'] ) ) {
				if ( $scheme_option['pricing_mode'] === 'override' ) {
					$get_regular_price = ! empty( $product->get_regular_price() ) ? $product->get_regular_price() : 0;
					$price             = ( ! empty( $product->get_sale_price() ) ) ? $product->get_sale_price() : ( ! empty( $product->get_price() ) ? $product->get_price() : 0 );
					$org_sale_price    = ! empty( $scheme_option['sale_price'] ) ? $scheme_option['sale_price'] : $price;
					$org_regular_price = ! empty( $scheme_option['regular_price'] ) ? $scheme_option['regular_price'] : $get_regular_price;
					$sale_price        = ! empty( $scheme_option['sale_price'] ) ? $scheme_option['sale_price'] : $price;
					$regular_price     = ! empty( $scheme_option['regular_price'] ) ? $scheme_option['regular_price'] : $get_regular_price;

				} else {
					$get_regular_price       = ! empty( $product->get_regular_price() ) ? $product->get_regular_price() : 0;
					$object                  = new stdClass();
					$object->discount_type   = 'percentage_on_reg';
					$object->discount_amount = $scheme_option['discount'];
					$price                   = ( ! empty( $product->get_sale_price() ) ) ? $product->get_sale_price() : ( ! empty( $product->get_price() ) ? $product->get_price() : 0 );
					$org_sale_price          = WFOCU_Common::apply_discount( $price, $object );
					$org_regular_price       = $get_regular_price;
					$sale_price              = WFOCU_Common::apply_discount( $price, $object );
					$regular_price           = $get_regular_price;
				}

				if ( is_object( $offer_option ) && isset( $offer_option->discount_type ) ) {

					if ( $offer_option->discount_type !== '' ) {

						$quantity      = ( isset( $offer_option->quantity ) && $offer_option->quantity > 0 ) ? $offer_option->quantity : 1;
						$shipping_cost = ( isset( $offer_option->shipping_cost_flat ) && $offer_option->shipping_cost_flat > 0 ) ? $offer_option->shipping_cost_flat : 0;
						if ( true === in_array( $offer_option->discount_type, [ 'percentage_on_sale', 'fixed_on_sale' ], true ) ) {
							$sale_price = WFOCU_Common::apply_discount( $sale_price, $offer_option );
						}
						if ( true === in_array( $offer_option->discount_type, [ 'fixed_on_reg', 'percentage_on_reg' ], true ) ) {
							$regular_price = WFOCU_Common::apply_discount( $regular_price, $offer_option );
						}

						$regular_price = round( $regular_price, wc_get_price_decimals() );
						$regular_price = wc_get_price_excluding_tax( $product, array( 'price' => $regular_price ) ) * $quantity + $shipping_cost;
						$regular_price = number_format( (float) $regular_price, 2, '.', '' );
						$sale_price    = round( $sale_price, wc_get_price_decimals() );
						$sale_price    = wc_get_price_excluding_tax( $product, array( 'price' => $sale_price ) ) * $quantity + $shipping_cost;
						$sale_price    = number_format( (float) $sale_price, 2, '.', '' );

						if ( $sale_price <= 0 || $sale_price >= $regular_price ) {
							$price         = $regular_price;
							$regular_price = ( is_admin() ) ? '<span class="subs_reg">' . $regular_price . '</span>' : $regular_price;
							$price_html    = '<ins><span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">' . get_woocommerce_currency_symbol() . '</span>' . $regular_price . '</bdi></span></ins>';
						} else {
							$price         = $sale_price;
							$sale_price    = ( is_admin() ) ? '<span class="subs_sale">' . $sale_price . '</span>' : $sale_price;
							$regular_price = ( is_admin() ) ? '<span class="subs_reg">' . $regular_price . '</span>' : $regular_price;
							$price_html    = '<del aria-hidden="true"><span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">' . get_woocommerce_currency_symbol() . '</span>' . $regular_price . '</bdi></span></del> <ins><span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">' . get_woocommerce_currency_symbol() . '</span>' . $sale_price . '</bdi></span></ins>';
						}

						preg_match( '/<span class="subscription-details">(.*?)<\/span>/s', $sub_price_html, $match );

						$price_html .= ' <span class="subscription-details">' . $match[0] . '</span>';

						$args['price']             = $price;
						$args['price_html']        = $price_html;
						$args['org_regular_price'] = $org_regular_price;
						$args['org_sale_price']    = $org_sale_price;

					}

				}

			}

			return $args;

		}


		/**
		 * @param $product_id
		 *
		 * show scheme plan option on frontend
		 *
		 * @param string $product_key
		 */
		public function schemes_template_html( $product_id, $product_key = '' ) {
			if ( ! $this->is_enable() || false === apply_filters( 'wfocu_show_scheme_list', true, $product_id, $product_key ) ) {
				return;
			}

			$options = $this->get_subscription_products_options( $product_id, '', $product_key, true );

			if ( ! is_array( $options ) || count( $options ) === 0 ) {
				return;
			}


			$hidden = false;

			/**
			 * Check if we have only one plan i.e. one time, then do not print anything
			 * Else check if we have subsription option as single options
			 */
			if ( count( $options ) === 1 && isset( $options['one_time'] ) ) {
				return;
			} elseif ( count( $options ) === 1 ) {
				$hidden = true;
			}


			if ( empty( $product_key ) ) {
				$get_offer_id = ( isset( $_REQUEST['offer_id'] ) ) ? $_REQUEST['offer_id'] : WFOCU_Core()->data->get( 'current_offer' );//phpcs:ignore
				$offer_data   = WFOCU_Core()->offers->get_offer_meta( $get_offer_id );

				if ( ! empty( $offer_data ) ) {

					foreach ( $offer_data->products as $key => $value ) {
						if ( absint( $value ) === absint( $product_id ) ) {
							$product_key = $key;
							break;
						}
					}
				}
			}

			if ( empty( $product_key ) && false === WFOCU_Core()->public->is_preview ) {
				return;
			}

			$product = wc_get_product( $product_id );

			$text = WCS_ATT_Display_Product::get_subscription_options_prompt_text( $product );

			?>

            <div class="wfocu-subs-product-attr-wrapper">
                <form class="wfocu_subs_plan_selector_form" data-key="<?php echo $product_key; ?>">
                    <div class="wfocu_subs_plan_selector_wrap" data-key="<?php echo $product_key; ?>">
                        <div class="wcsatt-options-wrapper wcsatt-options-wrapper-flat wcsatt-options-wrapper-text open " data-sign_up_text="Sign up now">
							<?php if ( $hidden ) {
								foreach ( $options as $option ) { ?>
                                    <input type="hidden" name="wfocu_convert_to_sub" data-custom="<?php echo esc_attr( json_encode( $option['data'] ) ); ?>" data-price="<?php echo esc_attr( $option['price'] ); ?>" value="<?php echo esc_attr( $option['value'] ); ?>" class="wfocu_convert_sub_hidden">
                                    <div style="margin:12px 0;" class="<?php echo esc_attr( $option['class'] ) . '-details'; ?>"><?php echo $option['description']; ?></div>
									<?php
								}
							} else { ?>

								<?php if ( ! empty( $text ) ) { ?>
                                    <div class="wcsatt-options-product-prompt wcsatt-options-product-prompt-flat wcsatt-options-product-prompt-text wcsatt-options-product-prompt--visible" data-prompt_type="text">
                                        <div class="wcsatt-options-prompt-text"><?php echo $text; ?></div>
                                    </div>
								<?php } ?>
                                <div class="wcsatt-options-product-wrapper">
                                    <ul class="wcsatt-options-product wcsatt-options-product--">
										<?php foreach ( $options as $option ) { ?>
                                            <li class="<?php echo esc_attr( $option['class'] ); ?>">
                                                <label>
                                                    <input type="radio" name="wfocu_convert_to_sub" data-custom="<?php echo esc_attr( json_encode( $option['data'] ) ); ?>" data-price="<?php echo esc_attr( $option['price'] ); ?>" value="<?php echo esc_attr( $option['value'] ); ?>" <?php checked( $option['selected'], true, true ); ?>>
                                                    <span class="<?php echo esc_attr( $option['class'] ) . '-details'; ?>"><?php echo $option['description']; ?></span>
                                                </label>
                                            </li>
										<?php } ?>
                                    </ul>
                                </div>
							<?php } ?>
                        </div>
                    </div>
                </form>
            </div>
			<?php
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

			$product = wc_get_product( $product_id );
			$options = array();

			$subscription_schemes = WCS_ATT_Product_Schemes::get_subscription_schemes( $product );

			if ( ! is_array( $subscription_schemes ) || count( $subscription_schemes ) === 0 ) {
				return;
			}

			$force_subscription = WCS_ATT_Product_Schemes::has_forced_subscription_scheme( $product );
			$base_scheme        = WCS_ATT_Product_Schemes::get_base_subscription_scheme( $product );

			if ( $offer_data === '' ) {

				if ( isset( WFOCU_Core()->template_loader ) && isset( WFOCU_Core()->template_loader->product_data ) ) {

					$offer_data = WFOCU_Core()->template_loader->product_data;

				} else {
					$get_offer_id = ( isset( $_REQUEST['offer_id'] ) ) ? $_REQUEST['offer_id'] : WFOCU_Core()->data->get( 'current_offer' ); //phpcs:ignore

					$offer_data = WFOCU_Core()->offers->get_offer_meta( $get_offer_id );
				}


			}

			$default_scheme    = '';
			$org_regular_price = '';
			$org_sale_price    = '';

			// Filter default key.

			// Option selected by default.
			// Non-recurring (one-time) option.
			if ( false === $force_subscription ) {

				$none_string = _x( 'one time', 'product subscription selection - negative response', 'woocommerce-all-products-for-subscriptions' );
				$options[]   = array(
					'class'             => 'one-time-option',
					'description'       => apply_filters( 'wcsatt_single_product_one_time_option_description', $none_string, $product ),
					'value'             => 'one_time',
					'price'             => '',
					'selected'          => 'one_time' === $default_scheme,
					'org_regular_price' => $org_regular_price,
					'org_sale_price'    => $org_sale_price,
					'data'              => [],
				);
			}

			if ( ! empty( $offer_data ) ) {

				if ( $product_key === '' ) {
					foreach ( $offer_data->products as $key => $value ) {
						if ( absint( $value ) === absint( $product_id ) ) {
							$product_key    = $key;
							$default_scheme = isset( $offer_data->fields->{$product_key}->default_scheme ) ? $offer_data->fields->{$product_key}->default_scheme : '';
							if ( isset( $options[0]['selected'] ) ) {
								$options[0]['selected'] = 'one_time' === $default_scheme;
							}
							break;
						}
					}
				} else {
					$default_scheme = isset( $offer_data->fields->{$product_key}->default_scheme ) ? $offer_data->fields->{$product_key}->default_scheme : '';
					if ( isset( $options[0]['selected'] ) ) {
						$options[0]['selected'] = 'one_time' === $default_scheme;
					}

				}

				if ( true === $is_front ) {
					if ( ! isset( $offer_data->schemes ) || ! isset( $offer_data->schemes->{$product_key} ) ) {
						$subscription_schemes = [];
						$options              = [];
					} else {
						if ( ! array_key_exists( "one_time", $offer_data->schemes->{$product_key} ) ) {
							$options = [];
						}
						$subscription_schemes = array_intersect_key( $subscription_schemes, $offer_data->schemes->{$product_key} );

					}
				}

			}

			// Subscription options.

			if ( ! is_array( $subscription_schemes ) || count( $subscription_schemes ) === 0 ) {
				return;
			}

			foreach ( $subscription_schemes as $subscription_scheme ) {


				$option_price_html_args = array(
					'context'         => 'radio',
					'append_discount' => true
				);


				$scheme_key       = $subscription_scheme->get_key();
				$is_base_scheme   = $base_scheme->get_key() === $scheme_key;
				$price            = number_format( (float) WCS_ATT_Product_Prices::get_price( $product, $scheme_key ), 2, '.', '' );
				$has_price_filter = $subscription_scheme->has_price_filter();


				/**
				 * 'wcsatt_single_product_subscription_option_price_html_args' filter
				 *
				 * Use this filter to override subscription plan price strings.
				 *
				 * For example, add [ 'append_discount' => true ] to append discounts to plan prices.
				 *
				 * @param array $option_price_html_args
				 * @param WCS_ATT_Scheme $subscription_scheme
				 * @param WC_Product $product
				 * @param WC_Product|null $parent_product
				 */


				$is_nyp = class_exists( 'WCS_ATT_Integration_NYP' ) && WC_Name_Your_Price_Helpers::is_nyp( $product );

				if ( $is_nyp ) {
					WCS_ATT_Integration_NYP::before_subscription_option_get_price_html();
				}

				// Get price.
				$sub_price_html = WCS_ATT_Product_Prices::get_price_html( $product, $scheme_key, $option_price_html_args );
				if ( ! empty( $offer_data ) ) {
					$offer_option                  = $offer_data->fields->{$product_key};
					$offer_option->discount_amount = isset( $offer_data->schemes->{$product_key}[ $scheme_key ]->discount_amount ) ? $offer_data->schemes->{$product_key}[ $scheme_key ]->discount_amount : $offer_option->discount_amount;

					$price_data = $this->get_product_price( $subscription_scheme->get_data(), $offer_option, $product, $sub_price_html );

					if ( is_array( $price_data ) && count( $price_data ) > 0 ) {
						$sub_price_html    = $price_data['price_html'];
						$price             = $price_data['price'];
						$org_regular_price = $price_data['org_regular_price'];
						$org_sale_price    = $price_data['org_sale_price'];
					}
				}

				if ( $is_nyp ) {
					WCS_ATT_Integration_NYP::after_subscription_option_get_price_html();
				}

				$option_data = array(
					'discount_from_regular' => apply_filters( 'wcsatt_discount_from_regular', false ),
					'option_has_price'      => false,
					'subscription_scheme'   => array_merge( $subscription_scheme->get_data(), array(
						'is_prorated'      => WCS_ATT_Sync::is_first_payment_prorated( $product, $scheme_key ),
						'is_base'          => $is_base_scheme,
						'has_price_filter' => $has_price_filter
					) ),
				);

				$parent_product = null;

				$option_data = apply_filters( 'wcsatt_single_product_subscription_option_data', $option_data, $subscription_scheme, $product, $parent_product );

                $option_data = array_filter($option_data, function($value, $key) {
					return strpos($key, '_html') === false;
				}, ARRAY_FILTER_USE_BOTH);

				$option = array(
					'class'             => 'subscription-option',
					'value'             => $scheme_key,
					'description'       => html_entity_decode( $sub_price_html ),
					'data'              => $option_data,
					'price'             => $price,
					'selected'          => $scheme_key === $default_scheme,
					'org_regular_price' => $org_regular_price,
					'org_sale_price'    => $org_sale_price,
				);

				$options[] = $option;
			}

			return $options;
		}

		/**
		 *
		 * On change get and save subscription data in offer meta and check if product is subscription base on this data
		 */
		public function render_js() {

			if ( ! $this->is_enable() ) {
				return '';
			}

			?>
            <script>
                jQuery(document).ready(function () {
                    wfocuCommons.addFilter('wfocu_additem_data', set_custom_data);
                    wfocuCommons.addFilter('wfocu_additem_price', set_price);

                    function set_custom_data(extraData, key, getVariationID) {
                        if (jQuery('.wfocu_subs_plan_selector_form[data-key=' + key + ']').length > 0) {
                            let subs_val = '.wfocu_subs_plan_selector_form[data-key=' + key + '] input[name="wfocu_convert_to_sub"].wfocu_convert_sub_hidden';
                            if (jQuery(subs_val).length > 0) {
                                subs_val = jQuery(subs_val);
                            } else {
                                subs_val = jQuery('.wfocu_subs_plan_selector_form[data-key=' + key + '] input[name="wfocu_convert_to_sub"]:checked');
                            }
                            if (subs_val && typeof subs_val.val() !== 'undefined' && subs_val.val() !== '') {
                                extraData.push('_convert_sub_plan=' + subs_val.val());
                                extraData.push('_convert_sub_plan_data=' + subs_val.attr('data-custom'));
                            }
                        }
                        return extraData;
                    }

                    function set_price(getPrice, key, getVariationID) {
                        if (jQuery('.wfocu_subs_plan_selector_form[data-key=' + key + ']').length > 0) {
                            let subs_val = '.wfocu_subs_plan_selector_form[data-key=' + key + '] input[name="wfocu_convert_to_sub"].wfocu_convert_sub_hidden';
                            if (jQuery(subs_val).length > 0) {
                                subs_val = jQuery(subs_val);
                            } else {
                                subs_val = jQuery('.wfocu_subs_plan_selector_form[data-key=' + key + '] input[name="wfocu_convert_to_sub"]:checked');
                            }
                            if (subs_val && typeof subs_val.val() !== 'undefined' && subs_val.val() !== '' && subs_val.val() !== 'one_time') {
                                getPrice = parseFloat(subs_val.attr('data-price'));
                            }
                        }
                        return getPrice;
                    }
                });
            </script>
			<?php
		}


		/**
		 * @param $args
		 * Register rule
		 *
		 * @return array|mixed
		 */
		public function register_rule_type( $args ) {

			if ( $this->is_enable() && is_array( $args ) && isset( $args['Order'] ) ) {
				$args['Order']['order_subs'] = __( 'All Products For Subscription', 'woofunnels-upstroke-power-pack' );
			}

			return $args;

		}

		/**
		 * @param $is
		 * @param $product
		 *
		 * make simple product to subscription
		 *
		 * @return bool|mixed
		 */
		public function push_force_subscription_product( $is, $product ) {

			if ( ! $this->is_enable() ) {
				return $is;
			}

			if ( is_array( $product ) && isset( $product['args']['variation']['_convert_sub_plan'] ) && $product['args']['variation']['_convert_sub_plan'] !== '' ) {
				$data = json_decode( stripslashes( $product['args']['variation']['_convert_sub_plan_data'] ) );
				WFOCU_Core()->log->log( 'WFOCU offer convert in all thing subscription order' . print_r( $data, true ) );// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				return true;
			}

			if ( ! empty( $product ) && isset( $product->id ) > 0 ) {
				$scheme = get_post_meta( $product->id, '_wcsatt_schemes', true );
				if ( is_array( $scheme ) && count( $scheme ) > 0 ) {
					return true;
				}
			}

			return $is;

		}

		/**
		 * @param $data
		 *
		 * Register nonce for search subscription product plan
		 *
		 * @return array|mixed
		 */
		public function register_subs_product_search_nonce( $data ) {
			if ( ! $this->is_enable() ) {
				return $data;
			}

			if ( is_array( $data ) ) {
				$data['search_subs_products_nonce'] = wp_create_nonce( 'search_subs_products' );
			}

			return $data;
		}


		/**
		 * @param string $str
		 * Searchable all thing subscription plan on rule screen
		 *
		 * Return only which product have '_wcsatt_schemes'
		 *
		 * @return mixed|void
		 */
		public function subs_product_search( $str = '' ) {

			$term = empty( $str ) ? ( isset( $_REQUEST['term'] ) ) ? stripslashes( wc_clean( $_REQUEST['term'] ) ) : '' : $str;
			if ( $str === 'get_data' ) {
				$term = '';
			}
			$ids             = WFOCU_Common::search_products( $term, true );
			$product_objects = array_filter( array_map( 'wc_get_product', $ids ), 'wc_products_array_filter_editable' );

			$products = array();

			if ( ! empty( $product_objects ) ) {
				foreach ( $product_objects as $product_object ) {
					if ( 'publish' === $product_object->get_status() ) {
						$product_name = rawurldecode( WFOCU_Common::get_formatted_product_name( $product_object ) );
						$product_id   = $product_object->get_id();
						$scheme       = get_post_meta( $product_id, '_wcsatt_schemes', true );
						if ( is_array( $scheme ) && count( $scheme ) > 0 ) {
							$options = $this->get_subscription_products_options( $product_id );
							if ( is_array( $options ) && count( $options ) > 0 ) {
								foreach ( $options as $option ) {
									if ( ! empty( $option['value'] ) ) {
										$key              = $product_id . '-' . $option['value'];
										$products[ $key ] = $product_name . ' - (' . strip_tags( $option['description'] ) . ')';
									}
								}

							}
						}
					}
				}
			}

			$products = apply_filters( 'wfocu_json_search_found_subs_products', $products );

			if ( $str === 'get_data' ) {
				return $products;
			}

			wp_send_json( $products );

		}

		/**
		 * List all scheme plan in admin screen
		 *
		 */
		public function list_schemes_admin_offer() {

			?>
			<div v-if="product.vars_subs_count>0" class="have_scheme">
				<?php _e( "{{product.vars_subs_count}} Subscriptions plans <a class='have_scheme_expand'>(Expand</a> / <a class='have_scheme_close'>Close)</a> ", 'woofunnels-upstroke-power-pack' ) ?>
			</div>
			<table width="100%" class="scheme_products" id="scheme_product_id" v-bind:data-index="index" v-if="product.vars_subs_count>0" border="1">
				<thead>
				<tr>
					<th>
						<input type="checkbox" v-on:change="disable_enable_scheme(index,$event)" v-bind:name="'offers['+current_offer_id+'][products]['+index+'][schemes_enable]'" class="disable_enable_scheme">
					</th>
					<th><?php _e( 'Default', 'woofunnels-upstroke-power-pack' ); ?></th>
					<th><?php _e( 'Attributes', 'woofunnels-upstroke-power-pack' ); ?></th>
					<th><?php _e( 'Price', 'woofunnels-upstroke-power-pack' ); ?></th>
					<th><?php _e( 'Discount', 'woofunnels-upstroke-power-pack' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<tr v-for="(scheme, var_index) in product.schemes" v-bind:id="scheme.vid" class="product_scheme_row">
					<td>
						<input type="checkbox" v-model="scheme.is_enable" v-on:change="disable_enable_scheme_row(index,$event,var_index)" v-bind:name="'offers['+current_offer_id+'][products]['+index+'][schemes]['+var_index+'][is_enable]'" class="scheme_check" v-bind:data-scheme="var_index">
					</td>
					<td>
						<input type="radio" v-model="scheme.default_scheme" name="'offers['+current_offer_id+'][products]['+index+'][default_scheme]'" v-bind:name="'offers['+current_offer_id+'][products]['+index+'][default_scheme]'" v-bind:value="var_index" v-bind:data-scheme="var_index" class="default_scheme">
					</td>
					<td>
						<div class="variation_attributes">
							<p v-for="(attr_i ,attribute) in scheme.attributes"> {{attribute}} : {{attr_i}}</p>
						</div>
					</td>
					<td>
						<div class=" product_options">
							<p v-if="typeof scheme.value !== 'undefined' && scheme.value == 'one_time'"><span v-html="scheme.description"></span></p>
							<p v-else><span v-bind:class="'wfocu_of_price_subs_'+index+'_'+var_index" v-html="scheme.description"></span></p>
						</div>
					</td>
					<td>
						<input v-if="typeof scheme.value !== 'undefined' && scheme.value == 'one_time'" name="scheme_discount" v-model="products[index].schemes[var_index].discount_amount" type="number" step="0.01" v-bind:name="'offers['+current_offer_id+'][products]['+index+'][schemes]['+var_index+'][discount_amount]'" readonly class="scheme_discount" oninput="this.value = Math.abs(this.value)" v-on:keyup="update_offer_price($event,index)">
						<input v-else name="scheme_discount" v-model="products[index].schemes[var_index].discount_amount" type="number" step="0.01" v-bind:name="'offers['+current_offer_id+'][products]['+index+'][schemes]['+var_index+'][discount_amount]'" v-bind:data-scheme="var_index" :readonly="(!scheme.is_enable)" class="scheme_discount" oninput="this.value = Math.abs(this.value)" v-on:keyup="update_offer_price($event,index)">
						<!-- This Hidden input placed here just to make sure the vue instance update himself on change of the modal data -->
						<input style="display:none;" name="hidden_v" disabled v-model="hidden_v" type="number" step="0.01">
					</td>
				</tr>
				</tbody>
			</table>


			<?php

		}

		/**
		 * @param $output
		 * @param $offer_data
		 *
		 * add scheme plan data in offer meta
		 *
		 * @return mixed
		 */
		public function add_scheme_plan_data( $output, $offer_data ) {
			if ( ! $this->is_enable() ) {
				return $output;
			}
			$is_front = true;
			if ( did_action( 'admin_enqueue_scripts' ) ) {
				$is_front = false;
			}
			$schemes = new stdClass();
			foreach ( $offer_data->products as $hash_key => $pid ) {
				$pro = wc_get_product( $pid );
				if ( $pro instanceof WC_Product ) {

					$get_plans = $this->get_subscription_products_options( $pro->get_id(), $offer_data, '', $is_front );

					if ( ! is_array( $get_plans ) || count( $get_plans ) === 0 ) {
						return $output;
					}
					$first_scheme = null;
					foreach ( $get_plans as $plan ) {
						$scheme_options                    = new stdClass();
						$scheme_options->value             = $plan['value'];
						$scheme_options->description       = $plan['description'];
						$scheme_options->org_regular_price = $plan['org_regular_price'];
						$scheme_options->org_sale_price    = $plan['org_sale_price'];
						$scheme_options->is_enable         = false;
						$attributes                        = array();
						if ( isset( $plan['data'] ) && isset( $plan['data']['subscription_scheme'] ) ) {
							$attributes = array(
								'period'   => $plan['data']['subscription_scheme']['period'],
								'interval' => $plan['data']['subscription_scheme']['interval'],
								'length'   => $plan['data']['subscription_scheme']['length'],
							);
						}

						if ( $plan['value'] === 'one_time' ) {
							$attributes = array(
								'period' => __( 'one time', 'woofunnels-upstroke-power-pack' ),
							);
						}
						$scheme_options->attributes      = $attributes;
						$scheme_options->discount_amount = 0;

						if ( isset( $offer_data->schemes->{$hash_key} ) ) {
							if ( isset( $offer_data->schemes->{$hash_key}[ $scheme_options->value ] ) ) {
								$vars = $offer_data->schemes->{$hash_key}[ $scheme_options->value ];
								foreach ( $vars as $vkey => $vval ) {
									$scheme_options->is_enable = true;
									$scheme_options->{$vkey}   = $vval;
								}
							}
						}

						if ( isset( $output->fields->{$hash_key} ) ) {
							if ( isset( $output->fields->{$hash_key}->default_scheme ) ) {
								$scheme_options->default_scheme = $output->fields->{$hash_key}->default_scheme;
							}
						}

						if ( is_null( $first_scheme ) && ! empty( $scheme_options->default_scheme ) ) {
							$first_scheme                   = true;
							$scheme_options->default_scheme = $plan['value'];

						}

						$schemes->{$hash_key}[ $scheme_options->value ] = $scheme_options;
						unset( $scheme_options );
					}
				}
			}

			$output->schemes = $schemes;

			return $output;

		}

		/**
		 * @param $output
		 * @param $offer_data
		 *
		 * update scheme plan data in offer meta
		 *
		 * @return mixed
		 */
		public function update_scheme_plan_data( $output, $offer_data, $offer_id, $funnel_id ) {

			$schemes     = array();
			$scheme_save = array();

			foreach ( $offer_data->products as $hash_key => $pid ) {
				$pro = wc_get_product( $pid );
				if ( $pro instanceof WC_Product ) {

					$get_plans = $this->get_subscription_products_options( $pro->get_id(), $offer_data );

					if ( is_array( $get_plans ) && count( $get_plans ) > 0 ) {


						$first_scheme             = null;
						$scheme_save[ $hash_key ] = array();
						$schemes[ $hash_key ]     = array();

						foreach ( $get_plans as $plan ) {
							$scheme_id                         = $plan['value'];
							$scheme_options                    = new stdClass();
							$scheme_options->value             = $scheme_id;
							$scheme_options->description       = $plan['description'];
							$scheme_options->org_regular_price = $plan['org_regular_price'];
							$scheme_options->org_sale_price    = $plan['org_sale_price'];
							$scheme_options->is_enable         = true;
							$attributes                        = array();
							if ( isset( $plan['data'] ) && isset( $plan['data']['subscription_scheme'] ) ) {
								$attributes = array(
									'period'   => $plan['data']['subscription_scheme']['period'],
									'interval' => $plan['data']['subscription_scheme']['interval'],
									'length'   => $plan['data']['subscription_scheme']['length'],
								);
							}

							if ( $plan['value'] === 'one_time' ) {
								$attributes = array(
									'period' => __( 'one time', 'woofunnels-upstroke-power-pack' ),
								);
							}
							$scheme_options->attributes = $attributes;


							$scheme_options->discount_amount = 0;
							$scheme_options->discount_type   = 'percentage_on_reg';


							if ( is_null( $first_scheme ) ) {
								$first_scheme                                           = true;
								$scheme_options->default_scheme                         = $plan['value'];
								$offer_data->fields->{$hash_key}->default_scheme        = $scheme_id;
								$offer_data->fields->{$hash_key}->schemes_enable        = true;
								$scheme_save[ $hash_key ][ $scheme_id ]                 = new stdClass();
								$scheme_save[ $hash_key ][ $scheme_id ]->disount_amount = 0;
								$scheme_save[ $hash_key ][ $scheme_id ]->value          = $scheme_id;

							}

							$schemes[ $hash_key ][ $scheme_id ] = $scheme_options;
							unset( $scheme_options );

						}
					}

				}
			}

			$offer_meta_data     = WFOCU_Core()->offers->get_offer( $offer_id );
			$offer_data->schemes = ( isset( $offer_meta_data ) && isset( $offer_meta_data->schemes ) ) ? $offer_meta_data->schemes : new stdClass();


			if ( count( $schemes ) > 0 ) {
				$output->schemes = new stdClass();
				if ( count( $schemes ) > 0 ) {
					foreach ( $schemes as $hash => $scheme ) {
						if ( ! empty( $scheme ) ) {
							$output->schemes->{$hash}     = $scheme;
							$offer_data->schemes->{$hash} = $scheme_save[ $hash ];
						}
					}
				} else {
					$offer_data->schemes = new stdClass();
				}
			}

			/**
			 * Save the modified template
			 */

			WFOCU_Common::update_offer( $offer_id, $offer_data, $funnel_id );

			return $output;

		}

		/**
		 * @param $offer_data
		 * @param $offer_id
		 * @param $funnel_id
		 * get schema plan list for react call
		 *
		 * @return mixed
		 */
		public function get_scheme_plan_data( $offer_data, $offer_id, $funnel_id ) {

			$schemes     = array();
			$scheme_save = array();

			foreach ( $offer_data->products as $hash_key => $pid ) {
				$pro = wc_get_product( $pid );
				if ( $pro instanceof WC_Product ) {

					$get_plans = $this->get_subscription_products_options( $pro->get_id(), $offer_data );

					if ( is_array( $get_plans ) && count( $get_plans ) > 0 ) {

						$first_scheme             = null;
						$scheme_save[ $hash_key ] = array();
						foreach ( $get_plans as $plan ) {
							$description_text = preg_replace( '/<del.*?<\/del>/', '', $plan['description'] );
							$description_text = preg_replace( '/<ins>.*?<\/ins>/', '', $description_text );


							$scheme_id                           = $plan['value'];
							$scheme_options                      = array();
							$scheme_options['value']             = $scheme_id;
							$scheme_options['description']       = $plan['description'];
							$scheme_options['org_regular_price'] = $plan['org_regular_price'];
							$scheme_options['org_sale_price']    = $plan['org_sale_price'];
							$scheme_options['description_text']  = $description_text;


							$scheme_options['is_enable'] = true;
							$attributes                  = array();
							if ( isset( $plan['data'] ) && isset( $plan['data']['subscription_scheme'] ) ) {
								$attributes = array(
									'period'   => $plan['data']['subscription_scheme']['period'],
									'interval' => $plan['data']['subscription_scheme']['interval'],
									'length'   => $plan['data']['subscription_scheme']['length'],
								);
							}

							if ( $plan['value'] === 'one_time' ) {
								$attributes = array(
									'period' => __( 'one time', 'woofunnels-upstroke-power-pack' ),
								);
							}
							$scheme_options['attributes'] = $attributes;


							$scheme_options['discount_amount'] = 0;
							$scheme_options['discount_type']   = 'percentage_on_reg';


							if ( is_null( $first_scheme ) ) {
								$first_scheme                                             = true;
								$scheme_options['default_scheme']                         = $plan['value'];
								$scheme_save[ $hash_key ][ $scheme_id ]['disount_amount'] = 0;
								$scheme_save[ $hash_key ][ $scheme_id ]['value']          = $scheme_id;

							}

							$schemes[] = $scheme_options;
							unset( $scheme_options );

						}
					}

				}
			}


			return $schemes;

		}

		/**
		 * @param $offers_setting
		 * @param $offers
		 * @param $offer_id
		 * @param $funnel_id
		 *
		 * save offer setting for schemes
		 */
		public function save_scheme_plan_data( $offers_setting, $offers, $offer_id ) {

			if ( ! empty( $offers ) && count( $offers ) > 0 && isset( $offers[ $offer_id ] ) ) {

				$offer = $offers[ $offer_id ];
				if ( ! empty( $offer['products'] ) && count( $offer['products'] ) > 0 ) {
					$offers_setting->schemes = new stdClass();
					foreach ( $offer['products'] as $hash_key => $pro ) {
						if ( isset( $pro['schemes'] ) && count( $pro['schemes'] ) > 0 ) {
							$offers_setting->schemes->{$hash_key} = array();
							foreach ( $pro['schemes'] as $scheme_id => $settings ) {
								if ( isset( $settings['is_enable'] ) && 'on' === $settings['is_enable'] ) {
									$offers_setting->schemes->{$hash_key}[ $scheme_id ]                  = new stdClass();
									$offers_setting->schemes->{$hash_key}[ $scheme_id ]->value           = $scheme_id;
									$offers_setting->schemes->{$hash_key}[ $scheme_id ]->discount_amount = $settings['discount_amount'];
									$offers_setting->schemes->{$hash_key}[ $scheme_id ]                  = apply_filters( 'wfocu_schemes_offers_setting_data', $offers_setting->schemes->{$hash_key}[ $scheme_id ] );
								}
							}
							$offers_setting->fields->{$hash_key}->default_scheme = isset( $pro['default_scheme'] ) ? $pro['default_scheme'] : '';

						}
					}
				}
			}

			/**
			 * save settings in 3.0 U1
			 */
            if ( ! empty( $offers ) && ! empty( $offers['products'] ) && count( $offers['products'] ) > 0 ) {

	            foreach ( $offers['products'] as $pro ) {
		            $hash_key                = $pro['id'];
		            $offers_setting->schemes = new stdClass();
		            if ( isset( $pro['schemes'] ) && count( $pro['schemes'] ) > 0 ) {
			            $offers_setting->schemes->{$hash_key} = array();
			            foreach ( $pro['schemes'] as $scheme_id => $settings ) {
				            if ( isset( $pro['checkVarient'] ) && in_array( $settings['value'], $pro['checkVarient'] ) ) {
					            $offers_setting->schemes->{$hash_key}[ $settings['value'] ]                  = new stdClass();
					            $offers_setting->schemes->{$hash_key}[ $settings['value'] ]->value           = $settings['value'];
					            $offers_setting->schemes->{$hash_key}[ $settings['value'] ]->discount_amount = $settings['discount_amount'];
					            $offers_setting->schemes->{$hash_key}[ $settings['value'] ]                  = apply_filters( 'wfocu_schemes_offers_setting_data', $offers_setting->schemes->{$hash_key}[ $settings['value'] ] );
				            }
			            }

			            $offers_setting->fields->{$hash_key}->default_scheme = isset( $pro['radioVarient'] ) ? $pro['radioVarient'] : '';
		            }
	            }
            }

			return $offers_setting;

		}

		/**
		 * @param $styles
		 *
		 * Load wcsatt css for schemes HTML.
		 *
		 * @return mixed
		 */
		public function add_styles( $styles ) {

			if ( ! $this->is_enable() ) {
				return $styles;
			}

			$styles['wcsatt-css'] = array(
				'path'      => WCS_ATT()->plugin_url() . '/assets/css/frontend/woocommerce.css',
				'version'   => WCS_ATT::VERSION,
				'in_footer' => false,
				'supports'  => array(
					'customizer',
					'customizer-preview',
					'offer',
					'offer-page',
				),
			);

			return $styles;
		}

		public function subscription_plans_list_shortcode( $attr ) {

			$data = WFOCU_Core()->data->get( '_current_offer_data' );
			$attr = shortcode_atts( array(
				'key' => 1,
			), $attr );

			if ( ! isset( $data->products ) ) {
				return '';
			}

			if ( ! isset( $data->products->{$attr['key']} ) ) {
				$attr['key'] = WFOCU_Core()->offers->get_product_key_by_index( $attr['key'], $data->products );
			}
			if ( ! empty( $attr['key'] ) ) {
				if ( isset( $data->products ) && isset( $data->products->{$attr['key']} ) ) {
					$this->schemes_template_html( $data->products->{$attr['key']}->id, $attr['key'] );
				}
			}
		}

		/**
		 * @param $args
		 *
		 * hide meta in order inline items
		 *
		 * @return array|mixed
		 */
		public function hide_order_meta( $args ) {
			if ( is_array( $args ) ) {
				$args[] = '_convert_sub_plan_data';
			}

			return $args;
		}

		/**
		 * @param $amount
		 * @param $get_offer_data
		 * @param $products
		 *
		 * Update recurring with purchased schema plan price
		 *
		 * @return mixed
		 */
		public function update_recurring_price( $amount, $get_offer_data, $product ) {

			if ( $this->push_force_subscription_product( false, $product ) ) {
				if ( isset( $product['price'] ) ) {
					$amount = $product['price'];
				}
			}


			return $amount;
		}

	}

	WFOCU_Plugin_Compatibilities::register( new WFOCU_WC_ATTS_Compatibility(), 'wfocu_wc_atts' );
}
