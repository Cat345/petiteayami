<?php

namespace FKCart\Pro;
if ( ! class_exists( '\FKCart\Pro\Special_Add_On' ) ) {
	#[\AllowDynamicProperties]
	class Special_Add_On {

		private static $instance = null;

		private function __construct() {
			/**
			 * Handle Special Product add on
			 */
			add_action( 'woocommerce_add_to_cart', [ $this, 'handle_special_addon_product' ], 9999, 2 );
			add_action( 'woocommerce_cart_emptied', [ $this, 'unset_special_addon_product' ] );
			/**
			 * Styling of special Add on
			 */
			add_action( 'wp_footer', [ $this, 'internal_style' ] );
			add_action( 'admin_footer', [ $this, 'internal_style' ] );
			add_filter( 'fkcart_css_var_style', [ $this, 'add_special_addon_css_variables' ] );
			add_action( 'fkcart_after_coupon_section', [ $this, 'special_addon_html' ] );
		}


		public static function get_settings() {
			return \FKCart\Includes\Data::get_settings();
		}

		/**
		 * @return Special_Add_On
		 */
		public static function getInstance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public function handle_special_addon_product( $cart_id, $product_id ) {

			try {
				$settings                 = \FKCart\Includes\Data::get_settings();
				$enable_special_addon     = ! empty( $settings['enable_special_addon'] ) ? $settings['enable_special_addon'] : false;
				$preselect_special_addon  = ! empty( $settings['preselect_special_addon'] ) ? $settings['preselect_special_addon'] : false;
				$is_remove_addons         = WC()->session->get( '_fkcart_remove_addons' );
				$special_addon_product_id = ! empty( $settings['special_addon_product']['key'] ) ? $settings['special_addon_product']['key'] : '';
				$special_addon_product_id = self::get_map_product( $special_addon_product_id );
				$same_product             = self::check_special_addon_exist_in_cart( $special_addon_product_id );
				/**
				 * check id addon remove by toggle
				 */
				if ( ! empty( $is_remove_addons ) ) {
					return;
				}

				$spl_add_on_product_id = WC()->session->get( '_fkcart_spl_addon_product_id' );
				$cart_item             = WC()->cart->get_cart_item( $cart_id );
				if ( isset( $_POST['fkcart_spl_product_cart_key'] ) && ! empty( $_POST['fkcart_spl_product_cart_key'] ) && isset( WC()->cart->removed_cart_contents[ $_POST['fkcart_spl_product_cart_key'] ] ) ) {
					return;
				}
				if ( false === $enable_special_addon || false === $preselect_special_addon || isset( $cart_item['_fkcart_spl_addon'] ) ) {
					return;
				}
				if ( ! empty( $spl_add_on_product_id ) || empty( $special_addon_product_id ) ) {
					return;
				}

				if ( $same_product === true ) {
					return;
				}
				remove_action( 'woocommerce_add_to_cart', [ $this, 'handle_special_addon_product' ], 99 );
				$product                                     = \wc_get_product( $special_addon_product_id );
				$custom_data['_fkcart_spl_addon']            = true;
				$custom_data['_fkcart_spl_addon_product_id'] = $special_addon_product_id;
				if ( method_exists( '\FKCart\Includes\Data', 'get_variation_product_type' ) && in_array( $product->get_type(), \FKCart\Includes\Data::get_variation_product_type() ) ) {
					$success = $this->handle_variable_product( $product );
				} else {
					$success = WC()->cart->add_to_cart( $special_addon_product_id, 1, 0, [], $custom_data );
				}
				if ( ! empty( $success ) ) {
					WC()->session->set( '_fkcart_spl_addon_product_id', $special_addon_product_id );
					WC()->session->set( '_fkcart_spl_addon_product_cart_key', $success );
				}
			} catch ( \Exception|\Error $ex ) {

			}
		}

		/**
		 * @var $product \WC_Product_Variable
		 */
		public static function handle_variable_product( $product ) {
			try {
				if ( fkcart_is_variation_product_type( $product->get_type() ) ) {
					$product_id           = $product->get_parent_id();
					$variation_id         = $product->get_id();
					$variation_attributes = $product->get_attributes();
					// Find Blank Attribute Any Any Case
					$blank_attribute = array_filter( $variation_attributes, function ( $v ) {
						return is_null( $v ) || empty( $v );
					} );
					// If Any-Any case found them map Remaining Attribute
					if ( ! empty( $blank_attribute ) ) {
						$parent_product       = wc_get_product( $product_id );
						$variation_attributes = fkcart_map_variation_attributes( $variation_attributes, $parent_product->get_variation_attributes() );
					}

					$cart_item_data['_fkcart_spl_addon']            = true;
					$cart_item_data['_fkcart_spl_addon_product_id'] = $product_id;

				} else if ( fkcart_is_variable_product_type( $product->get_type() ) ) {
					$product_id = $product->get_id();
					if ( isset( $temp_gift_variation_add[ $product->get_id() ] ) ) {
						$variation_attributes = $temp_gift_variation_add[ $product->get_id() ]['variation'];
						$variation_id         = $temp_gift_variation_add[ $product->get_id() ]['variation_id'];
					} else {
						/**
						 * @var $product \WC_Product_Variable
						 */
						$product_attributes = $product->get_variation_attributes();
						$variations         = $product->get_visible_children();
						if ( ! empty( $product_attributes ) && ! empty( $variations ) ) {
							$variation_id         = $variations[0];
							$variation            = wc_get_product( $variation_id );
							$variation_attributes = $variation->get_attributes();
							//Handle Any Any Case
							$variation_attributes = fkcart_map_variation_attributes( $variation_attributes, $product_attributes );
						}
					}
					$cart_item_data['_fkcart_spl_addon']            = true;
					$cart_item_data['_fkcart_spl_addon_product_id'] = $product_id;
				} else {
					$product_id   = $product->get_id();
					$variation_id = 0;
				}

				return WC()->cart->add_to_cart( $product_id, 1, $variation_id, $variation_attributes, $cart_item_data );
			} catch ( \Exception|\Error $ex ) {

			}
		}

		/**
		 * Special Product Add on
		 */
		public static function special_product_addon( $data = [] ) {
			try {
				if ( ! empty( $data ) ) {
					$_POST = array_merge( $_POST, $data );
				}

				if ( ! isset( $_POST['fkcart_spl_product_id'] ) || empty( $_POST['fkcart_spl_product_id'] ) ) {
					throw new \Exception( 'Special Product ID is required.' );
				}
				if ( ! isset( $_POST['fkcart_spl_product_action'] ) || empty( $_POST['fkcart_spl_product_action'] ) ) {
					throw new \Exception( 'Action required' );
				}
				$product_id   = absint( $_POST['fkcart_spl_product_id'] );
				$product_id   = self::get_map_product( $product_id );
				$quantity     = 1;
				$variation_id = 0;
				$attributes   = [];
				do_action( 'fkcart_spl_addon_before_add_to_cart', $_POST );
				if ( isset( $_POST['fkcart_spl_product_qty'] ) ) {
					$quantity = absint( $_POST['fkcart_spl_product_qty'] );
				}

				if ( $_POST['fkcart_spl_product_action'] == 'fkcart_add_spl_addon' ) {
					$custom_data['_fkcart_spl_addon']            = true;
					$custom_data['_fkcart_spl_addon_product_id'] = $product_id;
					$success                                     = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $attributes, $custom_data );
					/**
					 * When add to cart not done
					 */
					if ( '' == $success || false == $success ) {
						$messages = __( 'Sorry, we do not have enough stock to fulfill your order. Please change quantity and try again. We apologize for any inconvenience caused.', 'woocommerce' );
						throw new \Exception( $messages );
					}
					WC()->session->set( '_fkcart_spl_addon_product_id', $product_id );
					WC()->session->set( '_fkcart_spl_addon_product_cart_key', $success );
					WC()->session->set( '_fkcart_remove_addons', '' );

					return __( 'Product add to cart successfully.', 'funnel-builder-pro' );

				} elseif ( isset( $_POST['fkcart_spl_product_cart_key'] ) && ! empty( $_POST['fkcart_spl_product_cart_key'] ) ) {

					WC()->session->set( '_fkcart_spl_addon_product_id', '' );
					WC()->session->set( '_fkcart_spl_addon_product_cart_key', '' );
					WC()->session->set( '_fkcart_remove_addons', 'yes' );
					WC()->cart->remove_cart_item( $_POST['fkcart_spl_product_cart_key'] );

					return __( 'Product removed from the cart', 'funnel-builder-pro' );
				}
			} catch ( \Exception|\Error $exception ) {

			}

		}

		public function unset_special_addon_product() {
			if ( is_null( WC()->session ) ) {
				return;
			}
			WC()->session->__unset( '_fkcart_spl_addon_product_id' );
			WC()->session->__unset( '_fkcart_spl_addon_product_cart_key' );
			WC()->session->__unset( '_fkcart_remove_addons' );
		}

		public function internal_style() {
			?>
            <style>


                /*------------------------------Special Product Add on Styles-------------------------------------*/

                #fkcart-spl-addon {
                    padding: 8px 16px;
                }

                #fkcart-modal #fkcart-spl-addon + .fkcart-order-summary {
                    padding-top: 0;
                }

                #fkcart-spl-addon .fkcart-d-flex {
                    display: flex;
                    width: 100%;
                }

                #fkcart-spl-addon.fkcart-image-position-right .fkcart-d-flex {
                    flex-direction: row-reverse;
                }

                #fkcart-spl-addon .fkcart-gap-12 {
                    gap: 12px;
                }


                #fkcart-spl-addon .fkcart-d-col-flex {
                    -js-display: inline-flex;
                    display: -webkit-inline-box;
                    display: -webkit-inline-flex;
                    display: -moz-inline-box;
                    display: -ms-inline-flexbox;
                    display: inline-flex;
                }

                #fkcart-spl-addon .fkcart-spl-addon-image-wrap {
                    width: 100%;
                    max-width: var(--fkcart-spl-addon-special-addon-image-width);
                    height: var(--fkcart-spl-addon-special-addon-image-height);
                }

                #fkcart-spl-addon .fkcart-spl-addon-image-wrap .fkcart-product-image img {
                    border: 1px solid #DEDFEA;
                    border-radius: 4px;
                }


                #fkcart-spl-addon .fkcart-product-image img {
                    max-width: 100%;
                    height: 100%;
                }

                #fkcart-spl-addon .fkcart-d-col-flex:last-child {
                    flex: 1;
                    width: 100%;
                    align-self: center;
                    display: flex;
                    flex-direction: column;
                    align-items: flex-end;
                    text-align: right;
                }

                #fkcart-spl-addon .fkcart-item-title {
                    text-decoration: none;
                    font-size: 14px;
                    line-height: 1.5;
                    font-weight: 500;
                }

                #fkcart-spl-addon .fkcart-item-meta-content {
                    font-size: 12px;
                    line-height: 1.5;
                    font-weight: normal;
                }

                #fkcart-spl-addon .fkcart-item-meta-content p {
                    margin: 0;
                }

                #fkcart-spl-addon .fkcart-d-col-flex.fkcart-item-meta-wrap {
                    display: block;
                    width: calc(100% - 175px);
                }

                #fkcart-spl-addon.fkcart-image-disabled .fkcart-d-col-flex.fkcart-item-meta-wrap {
                    width: calc(100% - 110px);
                }


                #fkcart-spl-addon.fkcart-image-disabled .fkcart-spl-addon-image-wrap {
                    display: none;
                }


                /*----Cart Toggle style------- */

                #fkcart-spl-addon .fkcart-toggle-switcher label {
                    display: block;
                }

                #fkcart-spl-addon .fkcart-toggle-switcher label .sw {
                    display: block;
                    width: 36px;
                    height: 20px;
                    background-color: #82838E;
                    cursor: pointer;
                    position: relative;
                    border-radius: 20px;
                }

                #fkcart-spl-addon .fkcart-toggle-switcher label .sw:before {
                    content: '';
                    position: absolute;
                    background-color: #f1f2f9;
                    margin-top: 0;
                    height: 16px;
                    width: 16px;
                    border-radius: 50%;
                    transition: all ease .3s;
                    left: 2px;
                    top: 2px;
                }


                #fkcart-spl-addon .fkcart-toggle-switcher .fkcart-spl-checkbox:checked + label span:before {
                    left: 18px;
                    background-color: #fff;
                }

                #fkcart-spl-addon .fkcart-toggle-switcher .fkcart-switch {
                    display: none;
                }


                /**
				Shimmer Added
				 */
                .fkcart_spl_addon_active .fkcart-subtotal-wrap .fkcart-summary-amount,
                .fkcart_spl_addon_active .fkcart-checkout-wrap .fkcart-checkout--price {
                    position: relative;
                }

                .fkcart_spl_addon_active .fkcart-subtotal-wrap .fkcart-summary-amount:after {
                    animation: shimmer 2s linear infinite;
                    background: linear-gradient(to right, #eff1f3 4%, #e2e2e2 25%, #eff1f3 36%);
                    background-size: 1000px 100%;
                    content: " ";
                    display: block;
                    margin: 0;
                    position: absolute;
                    right: 0;
                    top: 0;
                    bottom: 0;
                    left: 0;
                    z-index: 999;
                }


                .fkcart_spl_addon_active .fkcart-checkout-wrap #fkcart-checkout-button,
                .fkcart-checkout-wrap #fkcart-checkout-button.fkcart-loading-active {
                    font-size: 0 !important;
                    transition: none !important;
                }

                .fkcart_spl_addon_active .fkcart-checkout-wrap #fkcart-checkout-button .fkcart-checkout--icon,
                .fkcart-checkout-wrap #fkcart-checkout-button.fkcart-loading-active .fkcart-checkout--icon {
                    opacity: 0;
                }

                .fkcart_spl_addon_active .fkcart-checkout-wrap #fkcart-checkout-button:after,
                .fkcart-checkout-wrap #fkcart-checkout-button.fkcart-loading-active:after {
                    position: absolute;
                    left: 0;
                    right: 0;
                    top: 50%;
                    content: '';
                    width: 16px;
                    margin: -8px auto auto;
                    height: 16px;
                    border: 2px solid #fff;
                    border-bottom-color: transparent;
                    border-radius: 50%;
                    display: inline-block;
                    box-sizing: border-box;
                    animation: rotation 1s linear infinite;
                }


                #fkcart-spl-addon a.fkcart-select-product {
                    font-size: 12px;
                    line-height: 16px;
                    color: #0073AA;
                    font-weight: normal;
                    text-decoration: none;
                }

                #fkcart-spl-addon a.fkcart-select-product:empty {
                    display: none;
                }

                /* Checkbox */
                #fkcart-spl-addon.fkcart-checkbox-selected .fkcart-toggle-switcher input[type="checkbox"] + label {
                    display: none;
                }

                #fkcart-spl-addon.fkcart-checkbox-selected .fkcart-toggle-switcher input[type="checkbox"] {
                    display: block;
                    width: 18px;
                    height: 18px;
                    -webkit-appearance: none;
                    -moz-appearance: none;
                    -webkit-appearance: none;
                    appearance: none;
                    background: 0 0;
                    border: 2px solid #bfbfbf;
                    box-shadow: none;
                    position: relative;
                    border-radius: 3px;
                    cursor: pointer;
                    padding: 0;
                }

                #fkcart-spl-addon.fkcart-checkbox-selected .fkcart-toggle-switcher input[type="checkbox"]:checked {
                    background: var(--fkcart-spl-addon-toggle-color);
                    border-color: transparent;
                }

                #fkcart-spl-addon.fkcart-checkbox-selected .fkcart-toggle-switcher input[type="checkbox"]:checked:before {
                    content: '';
                    height: 14px;
                    width: 14px;
                    position: absolute;
                    margin: auto;
                    top: 50%;
                    margin-top: -7px;
                    transform: none;
                    border: none;
                    background: url('<?php echo FKCART_PLUGIN_URL."/assets/img/tick.svg" ?>') no-repeat center center;
                    left: 0;
                    right: 0;
                }

                /*----Dynamic Css of special add on product ------- */
                #fkcart-spl-addon {
                    background-color: var(--fkcart-spl-addon-bg-color);
                }

                #fkcart-spl-addon .fkcart-item-title {
                    color: var(--fkcart-spl-addon-heading-color);
                }

                #fkcart-spl-addon .fkcart-item-meta-content {

                    color: var(--fkcart-spl-addon-description-color);
                }

                #fkcart-spl-addon .fkcart-toggle-switcher .fkcart-spl-checkbox:checked + label span {
                    background-color: var(--fkcart-spl-addon-toggle-color);
                }

                #fkcart-spl-addon .fkcart-price-wrap {
                    margin-top: 8px;
                }

                #fkcart-spl-addon .fkcart-d-col-flex .fkcart-price-wrap del,
                #fkcart-spl-addon .fkcart-d-col-flex .fkcart-price-wrap del * {
                    font-size: 12px;
                    line-height: 1;
                    color: var(--fkcart-strike-through-price-text-color);
                }

                #fkcart-spl-addon .fkcart-d-col-flex .fkcart-price-wrap del {
                    margin-right: 4px;
                }

                #fkcart-spl-addon .fkcart-d-col-flex .fkcart-price-wrap ins,
                #fkcart-spl-addon .fkcart-price-wrap span.woocommerce-Price-amount.amount {
                    color: var(--fkcart-spl-addon-description-color);
                    font-size: 14px;
                    line-height: 1;
                    font-weight: 400;
                    text-decoration: none;
                }
            </style>
			<?php
		}

		public function add_special_addon_css_variables( $var_style ) {
			// Get the special addon image size

			$width = \FKCart\Includes\Data::get_value( 'special_addon_image_size' );

			// Define the special addon CSS variables
			$special_addon_styles = "
            :root {
                --fkcart-spl-addon-special-addon-image-width: " . $width . "px;
                --fkcart-spl-addon-special-addon-image-height: " . $width . "px;
                --fkcart-spl-addon-toggle-color: " . \FKCart\Includes\Data::get_value( 'special_addon_toggle_color' ) . ";
                --fkcart-spl-addon-bg-color: " . \FKCart\Includes\Data::get_value( 'special_addon_bg_color' ) . ";
                --fkcart-spl-addon-heading-color: " . \FKCart\Includes\Data::get_value( 'special_addon_heading_color' ) . ";
                --fkcart-spl-addon-description-color: " . \FKCart\Includes\Data::get_value( 'special_addon_heading_color' ) . ";
            }";

			// Append the special addon styles to the existing styles
			return $var_style . $special_addon_styles;
		}

		public function special_addon_html( $cart_settings ) {
			if ( Plugin::valid_l() === false ) {
				return;
			}
			if ( fkcart_is_preview() || ( isset( $cart_settings['enable_special_addon'] ) && wc_string_to_bool( $cart_settings['enable_special_addon'] ) === true ) ) {
				fkcart_get_template_part( 'cart/special-addon-html', '', [], true, FKCART_PRO_PATH );
			}

		}

		public static function check_special_addon_exist_in_cart( $special_addon_product_id ) {
			$same_product = false;
			if ( ! is_null( WC()->session ) && ! is_null( WC()->cart ) && WC()->cart->get_cart() ) {
				foreach ( WC()->cart->get_cart() as $cart_item ) {
					if ( $cart_item['product_id'] == $special_addon_product_id && ! isset( $cart_item['_fkcart_spl_addon'] ) ) {
						$same_product = true;
					}
				}
			}

			return $same_product;
		}


		public static function get_map_product( $product_id ) {
			try {
				if ( class_exists( '\SitePress' ) ) {
					$product_id = \FKCart\Compatibilities\Compatibility::get_compatibility_class( 'wpml' )->wpml_map_product( $product_id );
				} else if ( defined( 'PLLWC_VERSION' ) ) {
					$product_id = \FKCart\Compatibilities\Compatibility::get_compatibility_class( 'poly_lang' )->polylang_map_product( $product_id );
				}
			} catch ( \Exception|\Error $e ) {

			}

			return absint( $product_id );
		}
	}
}