<?php
if ( ! class_exists( 'WFOCU_Oxy_Offer_Price' ) ) {
	class WFOCU_Oxy_Offer_Price extends WFOCU_Oxy_HTML_BLOCK {
		public $slug = 'wfocu_offer_price';
		protected $id = 'wfocu_offer_price';

		public function __construct() {
			$this->name = __( "WF Offer Price" );

			parent::__construct();
		}

		public function setup_data() {
			$offer_id = WFOCU_Core()->template_loader->get_offer_id();

			$subscriptions   = $products = array();
			$product_options = array( '0' => __( '--No Product--', 'woofunnels-upstroke-one-click-upsell' ) );

			if ( ! empty( $offer_id ) ) {
				$products        = WFOCU_Core()->template_loader->product_data->products;
				$product_options = array();
			}

			foreach ( $products as $key => $product ) {
				$product_options[ $key ] = $product->data->get_name();
				if ( in_array( $product->type, array( 'subscription', 'variable-subscription', 'subscription_variation' ), true ) ) {
					array_push( $subscriptions, $key );
				}
			}

			$tab_id = $this->add_tab( __( 'Price', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_select( $tab_id, 'selected_product', __( 'Product', 'woofunnels-upstroke-one-click-upsell' ), $product_options, key( $product_options ) );
			$this->add_text_alignments( $tab_id, 'text_align', '.wfocu-price-wrapper' );
			$this->add_margin( $tab_id, 'sale_price_margin', '.wfocu-price-wrapper:not(.wfocu-price_block-yes)', __( 'Spacing', 'woofunnels-upstroke-one-click-upsell' ) );


			$this->style_field( $subscriptions );
		}

		public function style_field( $subscriptions ) {

			//Regular Price Start
			$r_tab_id = $this->add_tab( __( 'Regular Price', 'woofunnels-upstroke-one-click-upsell' ) );

			$this->add_switcher( $r_tab_id, 'show_reg_price', __( 'Show', 'woofunnels-upstroke-one-click-upsell' ), 'on' );
			$condition = [ 'show_reg_price' => 'on' ];
			$this->add_text( $r_tab_id, 'reg_label', __( 'Label', 'woofunnels-upstroke-one-click-upsell' ), '', $condition );
			$this->add_margin( $r_tab_id, 'reg_label_margin', '.reg_wrapper', __( 'Spacing ', 'woofunnels-upstroke-one-click-upsell' ) );


			$this->add_typography( $r_tab_id, 'reg_label_typography', '.wfocu-price-wrapper .wfocu-reg-label', __( 'Label Typography', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_typography( $r_tab_id, 'reg_price_typography', '.wfocu-price-wrapper .wfocu-regular-price *', __( 'Price Typography', 'woofunnels-upstroke-one-click-upsell' ) );

			//Regular Price End


			//Offer Price Start
			$r_tab_id = $this->add_tab( __( 'Offer Price', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_switcher( $r_tab_id, 'show_offer_price', __( 'Show', 'woofunnels-upstroke-one-click-upsell' ), 'on' );
			$this->add_switcher( $r_tab_id, 'offer_slider_enabled', __( 'Stacked', 'woofunnels-upstroke-one-click-upsell' ), 'on' );

			$condition = [ 'show_offer_price' => 'on', ];

			$this->add_text( $r_tab_id, 'offer_label', __( 'Label', 'woofunnels-upstroke-one-click-upsell' ), '', $condition );
			$this->add_margin( $r_tab_id, 'offer_label_margin', '.offer_wrapper', __( 'Spacing', 'woofunnels-upstroke-one-click-upsell' ) );

			$this->add_typography( $r_tab_id, $this->slug . '_offer_label_typography', '.wfocu-price-wrapper .wfocu-offer-label', __( 'Label Typography', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_typography( $r_tab_id, $this->slug . '_offer_price_typography', '.wfocu-price-wrapper .offer_wrapper .wfocu-sale-price span, .wfocu-price-wrapper .offer_wrapper .wfocu-sale-price span bdi', __( 'Price Typography', 'woofunnels-upstroke-one-click-upsell' ) );

			if ( count( $subscriptions ) > 0 ) {

				$r_tab_id = $this->add_tab( __( 'Signup Fee', 'woofunnels-upstroke-one-click-upsell' ) );
				$this->add_switcher( $r_tab_id, 'show_signup_fee', __( 'Show', 'woofunnels-upstroke-one-click-upsell' ), 'off', '' );
				$condition = [ 'show_signup_fee' => 'on' ];

				$this->add_text( $r_tab_id, 'signup_label', __( 'Label', 'woofunnels-upstroke-one-click-upsell' ), '', $condition );
				$this->add_margin( $r_tab_id, 'signup_label_margin', '.signup_details_wrap', __( 'Spacing', 'woofunnels-upstroke-one-click-upsell' ) );
				$this->add_typography( $r_tab_id, 'signup_label_typography', '.wfocu-price-wrapper .signup_details_wrap .signup_price_label', __( 'Label Typography', 'woofunnels-upstroke-one-click-upsell' ) );
				$this->add_typography( $r_tab_id, 'signup_price_typography', '.wfocu-price-wrapper .signup_details_wrap span.amount, .wfocu-price-wrapper .signup_details_wrap span.amount span', __( 'Price Typography', 'woofunnels-upstroke-one-click-upsell' ) );

				$r_tab_id = $this->add_tab( __( 'Recurring Price', 'woofunnels-upstroke-one-click-upsell' ) );
				$this->add_switcher( $r_tab_id, 'show_rec_price', __( 'Show', 'woofunnels-upstroke-one-click-upsell' ), 'on', '' );
				$condition = [ 'show_rec_price' => 'on' ];
				$this->add_text( $r_tab_id, 'recurring_label', __( 'Label', 'woofunnels-upstroke-one-click-upsell' ), '', $condition );
				$this->add_margin( $r_tab_id, 'rec_label_margin', '.recurring_details_wrap', __( 'Spacing', 'woofunnels-upstroke-one-click-upsell' ) );

				$this->add_typography( $r_tab_id, 'rec_label_typography', '.wfocu-price-wrapper .recurring_details_wrap .recurring_price_label', __( 'Label Typography', 'woofunnels-upstroke-one-click-upsell' ) );
				$this->add_typography( $r_tab_id, 'rec_price_typography', '.wfocu-price-wrapper .recurring_details_wrap .subscription-details, .wfocu-price-wrapper .recurring_details_wrap .amount, .wfocu-price-wrapper .recurring_details_wrap .amount span', __( 'Price Typography', 'woofunnels-upstroke-one-click-upsell' ) );
			}

			$key       = "wfocu_offer_price";
			$border_id = $this->add_tab( __( 'Border', 'woofunnels-upstroke-one-click-upsell' ) );

			$this->add_border( $border_id, $key . '_border', '.wfocu-price-wrapper ' );
			$this->add_box_shadow( $border_id, $key . '_box_shadow', '.wfocu-price-wrapper ' );

		}


		protected function html( $settings, $defaults, $content ) {//phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			$sel_product = isset( $settings['selected_product'] ) ? $settings['selected_product'] : '';
			$product     = WFOCU_Common::default_selected_product( $sel_product );
			$product_key = WFOCU_Common::default_selected_product_key( $sel_product );
			$product_key = ( $product_key !== false ) ? $product_key : '';

			echo '<style>';
			if ( isset( $settings['offer_slider_enabled'] ) && 'on' === $settings['offer_slider_enabled'] ) {
				echo 'body .wfocu-price-wrapper > span {display: block;}';
			} else {
				echo 'body .wfocu-price-wrapper > span {display: inline-block;}';
			}
			echo '</style>';

			?>
            <div class="wfocu_offer_price">
				<?php
				if ( '' !== $product_key ) { ?>

                    <div class="wfocu-element wfocu-element wfocu-widget wfocu-widget-wfocu_price">
                        <div class="wfocu-widget-container">
                            <div class="wfocu-price-wrapper">
								<?php

								if ( $product instanceof WC_Product ) {
									/** Price */
									$regular_price     = ( isset( $settings['show_reg_price'] ) && 'on' === $settings['show_reg_price'] ) ? WFOCU_Common::maybe_parse_merge_tags( '{{product_regular_price info="no" key="' . $product_key . '"}}' ) : 0;
									$sale_price        = ( isset( $settings['show_offer_price'] ) && 'on' === $settings['show_offer_price'] ) ? WFOCU_Common::maybe_parse_merge_tags( '{{product_offer_price info="no" key="' . $product_key . '"}}' ) : 0;
									$regular_price_raw = WFOCU_Common::maybe_parse_merge_tags( '{{product_regular_price_raw key="' . $product_key . '"}}' );
									$sale_price_raw    = WFOCU_Common::maybe_parse_merge_tags( '{{product_sale_price_raw key="' . $product_key . '"}}' );

									$reg_label   = ( isset( $settings['reg_label'] ) && ! empty( $settings['reg_label'] ) ) ? '<span class="wfocu-reg-label">' . $settings['reg_label'] . '</span>' : '';
									$offer_label = ( isset( $settings['offer_label'] ) && ! empty( $settings['offer_label'] ) ) ? '<span class="wfocu-offer-label">' . $settings['offer_label'] . '</span>' : '';

									$price_output = '';
									if ( round( $sale_price_raw, 2 ) !== round( $regular_price_raw, 2 ) ) {
										if ( isset( $settings['show_reg_price'] ) && 'on' === $settings['show_reg_price'] ) {

											$price_output .= '<span class="reg_wrapper">' . $reg_label . '<span class="wfocu-regular-price"><strike>' . $regular_price . '</strike></span></span>';
										}
										if ( isset( $settings['show_offer_price'] ) && 'on' === $settings['show_offer_price'] ) {
											$price_output .= '<span class="offer_wrapper">' . $offer_label . '<span class="wfocu-sale-price">' . $sale_price . '</span></span>';
										}
									} else {

										if ( 'variable' === $product->get_type() ) {
											$price_output .= sprintf( '<span class="wfocu-regular-price"><strike><span class="wfocu_variable_price_regular" style="display: none;" data-key="%s"></span></strike></span>', $product_key );
											$price_output .= $sale_price ? '<span class="offer_wrapper">' . $offer_label . '<span class="wfocu-sale-price">' . $sale_price . '</span></span>' : '';
										} else {

											$price_output .= $regular_price ? '<span class="reg_wrapper">' . $reg_label . '<span class="wfocu-regular-price">' . $regular_price . '</span></span>' : '';
											$price_output .= $sale_price ? '<span class="offer_wrapper">' . $offer_label . '<span class="wfocu-sale-price">' . $sale_price . '</span></span>' : '';
										}
									}

									echo $price_output;//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

									if ( isset( $settings['show_signup_fee'] ) && 'on' === $settings['show_signup_fee'] ) {
										$signup_label = isset( $settings['signup_label'] ) ? $settings['signup_label'] : '';
										echo WFOCU_Common::maybe_parse_merge_tags( '{{product_signup_fee key="' . $product_key . '" signup_label="' . $signup_label . '"}}' );
									}

									if ( isset( $settings['show_rec_price'] ) && 'on' === $settings['show_rec_price'] ) {
										$recurring_label = isset( $settings['recurring_label'] ) ? $settings['recurring_label'] : '';
										echo WFOCU_Common::maybe_parse_merge_tags( '{{product_recurring_total_string info="yes" key="' . $product_key . '" recurring_label="' . $recurring_label . '"}}' );
									}
								}

								?>

                            </div>

                            <div class="jdsh"></div>
                        </div>
                    </div>
					<?php
				}
				?>
            </div>
			<?php
		}

		public function defaultCSS() {

			$defaultCSS = "
			.wfocu-price-wrapper .recurring_details_wrap .subscription-details,
			.wfocu-price-wrapper .recurring_details_wrap .amount,
			.wfocu-price-wrapper .recurring_details_wrap .amount span,
			.wfocu-price-wrapper .wfocu-regular-price *,
			.wfocu-price-wrapper .offer_wrapper .wfocu-sale-price span,
			.wfocu-price-wrapper .signup_details_wrap .signup_price_label,
			.wfocu-price-wrapper .wfocu-reg-label {
				font-size: 16px;
				line-height: 1.5;
			}			
			.wfocu-price-wrapper .signup_details_wrap .signup_price_label,
			.wfocu-price-wrapper .wfocu-regular-price *,
			.wfocu-price-wrapper .wfocu-reg-label {
				color: #8d8e92;
			}		
			.wfocu-price-wrapper .recurring_details_wrap .recurring_price_label {
				font-size: 16px;
				line-height: 1.5;
				color: #414349;
			}			
			.wfocu-price-wrapper .wfocu-offer-label {
				font-size: 16px;
				line-height: 1.5;
				color: #414349;
			}			
			.wfocu-price-wrapper .signup_details_wrap span.amount,
			.wfocu-price-wrapper .signup_details_wrap span.amount span {
				font-size: 16px;
				line-height: 1.5;
				color: #414349;
			}			
			.wfocu-price-wrapper .wfocu-sale-price span.woocommerce-Price-currencySymbol,
			.wfocu-price-wrapper .wfocu-sale-price span *,
			.wfocu-price-wrapper .wfocu-sale-price span bdi {
				color: #414349;
			}			
			.wfocu-price-wrapper .recurring_details_wrap span,
			.wfocu-price-wrapper .recurring_details_wrap .subscription-details {
				color: #414349;
			}
			#wfocu_product_title .wfocu-product-title-wrapper .wfocu-product-title,
			#wfocu_product_image .wfocu-product-gallery a {
				padding-bottom: 0;
			}
			.wfocu-button-wrapper .wfocu_upsell {
				margin: 0;
			}
			.wfocu-button-wrapper a {
				display: inline-block;
				padding-bottom: 0;
			}		
			span.wfocu-button-icon.et-pb-icon {
				font-size: 18px;
				line-height: 18px;
				color: #fff;
			}			
			.wfocu-reject-button-wrap span.wfocu-button-icon {
				font-size: 16px;
				line-height: 16px;
				color: #fff;
			}			
			.wfocu-button-icon.et-pb-icon {
				display: inline-block;;
			}
			.wfocu-reject-button-wrap .wfocu-wfocu-reject {
				background-color: #d9534f;
				color: #fff;
				display: block;
				font-weight: bold;
			}			
			.wfocu_proqty_inline .wfocu-prod-qty-wrapper label {
				width: 250px;
				float: left;
				padding-right: 2px;
			}			
			.wfocu_proqty_inline .wfocu-prod-qty-wrapper label + span.wfocu-select-wrapper {
				padding-left: 2px;
			}			
			.oxy-wfocu-offer-price{
				width:100%;
			}
			.wfocu-price-wrapper .signup_details_wrap .signup_price_label, .wfocu-price-wrapper .wfocu-reg-label, .wfocu-price-wrapper .wfocu-offer-label, .wfocu-price-wrapper .recurring_details_wrap .recurring_price_label {
    			margin-right: 5px;
			}

		";

			return $defaultCSS;
		}


	}

	return new WFOCU_Oxy_Offer_Price;
}