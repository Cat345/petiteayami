<?php
if ( ! class_exists( 'WFOCU_Offer_Price' ) ) {
	class WFOCU_Offer_Price extends WFOCU_Divi_HTML_BLOCK {

		public function __construct() {
			$this->ajax = true;
			parent::__construct();
		}

		public function setup_data() {
			$offer_id        = WFOCU_Core()->template_loader->get_offer_id();
			$products        = array();
			$subscriptions   = 'off';
			$product_options = array( '0' => __( '--No Product--', 'woofunnels-upstroke-one-click-upsell' ) );

			if ( ! empty( $offer_id ) ) {
				$products        = WFOCU_Core()->template_loader->product_data->products;
				$product_options = array();
			}

			foreach ( $products as $key => $product ) {
				$product_options[ $key ] = $product->data->get_name();
				if ( in_array( $product->type, array( 'subscription', 'variable-subscription', 'subscription_variation' ), true ) ) {
					$subscriptions = 'on';
				}
			}

			$tab_id = $this->add_tab( __( 'Price', 'woofunnels-upstroke-one-click-upsell' ), 5 );
			$this->add_select( $tab_id, 'selected_product', __( 'Product', 'woofunnels-upstroke-one-click-upsell' ), $product_options, key( $product_options ) );
			$this->add_text_alignments( $tab_id, 'text_align', '%%order_class%% .wfocu-price-wrapper' );

			$default = '0px|10px|0|0|false|false';
			$this->add_margin( $tab_id, 'sale_price_margin', '%%order_class%%:not(.wfocu-price_block-yes) .reg_wrapper', $default, __( 'Spacing', 'woofunnels-upstroke-one-click-upsell' ) );


			$this->style_field( $subscriptions );
		}

		public function style_field( $subscriptions ) {

			//Regular Price Start
			$r_tab_id = $this->add_tab( __( 'Regular Price', 'woofunnels-upstroke-one-click-upsell' ), 2 );

			$this->add_switcher( $r_tab_id, 'show_reg_price', __( 'Show ', 'woofunnels-upstroke-one-click-upsell' ), 'on' );
			$condition = [ 'show_reg_price' => 'on', ];
			$this->add_subheading( $r_tab_id, 'Price Typography', '', $condition );
			$this->add_typography( $r_tab_id, 'reg_price_typography', '%%order_class%% .wfocu-price-wrapper .wfocu-regular-price *', __( 'Label Typography', 'woofunnels-upstroke-one-click-upsell' ), '', $condition );
			$this->add_color( $r_tab_id, 'reg_price_color', '%%order_class%% .wfocu-price-wrapper  .wfocu-regular-price *', __( 'Price Color', 'woofunnels-upstroke-one-click-upsell' ), '#8d8e92', $condition );
			$this->add_text( $r_tab_id, 'reg_label', __( 'Label', 'woofunnels-upstroke-one-click-upsell' ), '', $condition );
			$this->add_subheading( $r_tab_id, 'Label Typography', '', $condition );
			$this->add_typography( $r_tab_id, 'reg_label_typography', '%%order_class%% .wfocu-price-wrapper .wfocu-reg-label', '', '', $condition );
			$this->add_color( $r_tab_id, 'reg_label_color', '%%order_class%% .wfocu-price-wrapper .wfocu-reg-label', __( 'Label Color', 'woofunnels-upstroke-one-click-upsell' ), '#8d8e92', $condition );


			$this->add_margin( $r_tab_id, 'reg_label_margin', '%%order_class%% .wfocu-reg-label', '', __( 'Spacing ', 'woofunnels-upstroke-one-click-upsell' ), $condition );


			//Regular Price End


			//Offer Price Start
			$r_tab_id = $this->add_tab( __( 'Offer Price', 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$this->add_switcher( $r_tab_id, 'show_offer_price', __( 'Show', 'woofunnels-upstroke-one-click-upsell' ), 'on' );
			$this->add_switcher( $r_tab_id, 'offer_slider_enabled', __( 'Stacked', 'woofunnels-upstroke-one-click-upsell' ), 'on' );

			$condition = [ 'show_offer_price' => 'on', ];

			$this->add_text( $r_tab_id, 'offer_label', __( 'Label', 'woofunnels-upstroke-one-click-upsell' ), __( 'Offer Price: ', 'woofunnels-upstroke-one-click-upsell' ), $condition );
			$this->add_subheading( $r_tab_id, __( 'Label Typography', 'woofunnels-upstroke-one-click-upsell' ), '', $condition );
			$this->add_typography( $r_tab_id, 'offer_label_typography', '%%order_class%% .wfocu-price-wrapper .wfocu-offer-label', __( 'Label', 'woofunnels-upstroke-one-click-upsell' ), '', $condition );
			$this->add_color( $r_tab_id, 'offer_label_color', '%%order_class%% .wfocu-price-wrapper .wfocu-offer-label', __( 'Label Color', 'woofunnels-upstroke-one-click-upsell' ), '#414349', $condition );


			$this->add_subheading( $r_tab_id, 'Offer Price Typography', '', $condition );
			$this->add_typography( $r_tab_id, 'offer_price_typography', '%%order_class%% .wfocu-price-wrapper .offer_wrapper .wfocu-sale-price span', __( 'Label Typography', 'woofunnels-upstroke-one-click-upsell' ), '', $condition );
			$this->add_color( $r_tab_id, 'offer_price_color', '%%order_class%% .wfocu-price-wrapper  .wfocu-sale-price span.woocommerce-Price-currencySymbol,%%order_class%% .wfocu-price-wrapper  .wfocu-sale-price span *, %%order_class%% .wfocu-price-wrapper  .wfocu-sale-price span bdi', __( 'Price Color', 'woofunnels-upstroke-one-click-upsell' ), '#414349', $condition );
			$this->add_margin( $r_tab_id, 'offer_label_margin', '%%order_class%% .wfocu-offer-label', '', __( 'Spacing', 'woofunnels-upstroke-one-click-upsell' ), $condition );
			//Offer Price End

			//SignUp Start
			$condition = [ 'selected_product' => $subscriptions ];
			$r_tab_id  = $this->add_tab( __( 'Signup Fee', 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$this->add_switcher( $r_tab_id, 'show_signup_fee', __( 'Show', 'woofunnels-upstroke-one-click-upsell' ), 'off', $condition );
			$condition['show_signup_fee'] = 'on';
			$this->add_subheading( $r_tab_id, 'Label Typography', '', $condition );
			$this->add_text( $r_tab_id, 'signup_label', __( 'Label', 'woofunnels-upstroke-one-click-upsell' ), __( 'Signup Fee: ', 'woofunnels-upstroke-one-click-upsell' ), $condition );
			$this->add_subheading( $r_tab_id, 'Typography', '', $condition );
			$this->add_typography( $r_tab_id, 'signup_label_typography', '%%order_class%% .wfocu-price-wrapper .signup_details_wrap .signup_price_label', __( 'Label Typography', 'woofunnels-upstroke-one-click-upsell' ), '', $condition );
			$this->add_color( $r_tab_id, 'signup_label_color', '%%order_class%% .wfocu-price-wrapper .signup_details_wrap .signup_price_label', __( 'Label Color', 'woofunnels-upstroke-one-click-upsell' ), '#8d8e92', $condition );

			$this->add_subheading( $r_tab_id, 'Price Typography', '', $condition );
			$this->add_typography( $r_tab_id, 'signup_price_typography', '%%order_class%% .wfocu-price-wrapper .signup_details_wrap span.amount, %%order_class%% .wfocu-price-wrapper .signup_details_wrap span.amount span', __( 'Price Typography', 'woofunnels-upstroke-one-click-upsell' ), '', $condition );
			$this->add_color( $r_tab_id, 'signup_price_color', '%%order_class%% .wfocu-price-wrapper .signup_details_wrap span.amount, %%order_class%% .wfocu-price-wrapper .signup_details_wrap span.amount span', __( 'Price Color', 'woofunnels-upstroke-one-click-upsell' ), '#414349', $condition );
			$this->add_margin( $r_tab_id, 'signup_label_margin', '%%order_class%% .signup_details_wrap .signup_price_label, %%order_class%% .signup_details_wrap .signup_price_label', '', __( 'Spacing', 'woofunnels-upstroke-one-click-upsell' ), $condition );
			//SignUp End

			//Recuring
			$condition = [ 'selected_product' => $subscriptions ];
			$r_tab_id  = $this->add_tab( __( 'Recurring Price', 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$this->add_switcher( $r_tab_id, 'show_rec_price', __( 'Show', 'woofunnels-upstroke-one-click-upsell' ), 'off', $condition );
			$condition['show_rec_price'] = 'on';
			$this->add_text( $r_tab_id, 'recurring_label', __( 'Label', 'woofunnels-upstroke-one-click-upsell' ), __( 'Recurring Total: ', 'woofunnels-upstroke-one-click-upsell' ), $condition );
			$this->add_subheading( $r_tab_id, 'Label Typography', '', $condition );
			$this->add_typography( $r_tab_id, 'rec_label_typography', '%%order_class%% .wfocu-price-wrapper .recurring_details_wrap .recurring_price_label', __( 'Label Typography', 'woofunnels-upstroke-one-click-upsell' ), '', $condition );
			$this->add_color( $r_tab_id, 'rec_label_color', '%%order_class%% .wfocu-price-wrapper .recurring_details_wrap .recurring_price_label', __( 'Label Color', 'woofunnels-upstroke-one-click-upsell' ), '#414349', $condition );

			$this->add_subheading( $r_tab_id, 'Price Typography', '', $condition );
			$this->add_typography( $r_tab_id, 'rec_price_typography', '%%order_class%% .wfocu-price-wrapper .recurring_details_wrap .subscription-details, %%order_class%% .wfocu-price-wrapper .recurring_details_wrap .amount, %%order_class%% .wfocu-price-wrapper .recurring_details_wrap .amount span', __( 'Price Typography', 'woofunnels-upstroke-one-click-upsell' ), '', $condition );
			$this->add_color( $r_tab_id, 'rec_price_color', '%%order_class%% .wfocu-price-wrapper .recurring_details_wrap span, %%order_class%% .wfocu-price-wrapper .recurring_details_wrap .subscription-details', __( 'Price Color', 'woofunnels-upstroke-one-click-upsell' ), '#414349', $condition );
			$this->add_margin( $r_tab_id, 'rec_label_margin', '%%order_class%% .recurring_details_wrap .recurring_price_label, %%order_class%% .recurring_details_wrap .recurring_price_label', '', __( 'Spacing', 'woofunnels-upstroke-one-click-upsell' ), $condition );


			$key       = "wfocu_offer_price";
			$border_id = $this->add_tab( __( 'Border', 'woofunnels-upstroke-one-click-upsell' ), 2 );

			$default_args = [
				'border_type'          => 'none',
				'border_width_top'     => '0',
				'border_width_bottom'  => '0',
				'border_width_left'    => '0',
				'border_width_right'   => '0',
				'border_radius_top'    => '0',
				'border_radius_bottom' => '0',
				'border_radius_left'   => '0',
				'border_radius_right'  => '0',
				'border_color'         => '#fff',
			];


			$this->add_border( $border_id, $key . '_border', '%%order_class%%', [], $default_args );

			$this->add_box_shadow( $border_id, $key . '_box_shadow', '%%order_class%% ' );

			$spacing_id = $this->add_tab( __( 'Spacing', 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$this->add_margin( $spacing_id, $key . '_margin', '%%order_class%% ' );
			$this->add_padding( $spacing_id, $key . '_padding', '%%order_class%% ' );


		}


		protected function html( $attrs, $content = null, $render_slug = '' ) {


			$settings = $this->props;

			$sel_product = isset( $this->props['selected_product'] ) ? $this->props['selected_product'] : '';
			$product_key = WFOCU_Core()->template_loader->default_product_key( $sel_product );

			if ( isset( $this->props['offer_slider_enabled'] ) && 'on' === $this->props['offer_slider_enabled'] ) {

				ET_Builder_Element::set_style( $render_slug, array(
					'selector'    => '%%order_class%% .wfocu-price-wrapper .reg_wrapper',
					'declaration' => "display: block;",
				) );

			}

			ob_start();
			?>
            <div class="wfocu_offer_price">
				<?php
				if ( isset( $product_key ) && ! empty( $product_key ) ) { ?>

                    <div class="wfocu-element wfocu-element wfocu-widget wfocu-widget-wfocu_price">
                        <div class="wfocu-widget-container">
                            <div class="wfocu-price-wrapper">
								<?php
								if ( isset( WFOCU_Core()->template_loader->product_data->products ) ) {

									$product_data = WFOCU_Core()->template_loader->product_data->products;
									$product      = '';
									if ( isset( $product_data->{$product_key} ) ) {
										$product = $product_data->{$product_key}->data;
									}
									if ( $product instanceof WC_Product ) {


										/** Price */
										$regular_price     = ( isset( $settings['show_reg_price'] ) && 'on' === $settings['show_reg_price'] ) ? WFOCU_Common::maybe_parse_merge_tags( '{{product_regular_price info="no" key="' . $product_key . '"}}' ) : 0;
										$sale_price        = ( isset( $settings['show_offer_price'] ) && 'on' === $settings['show_offer_price'] ) ? WFOCU_Common::maybe_parse_merge_tags( '{{product_offer_price info="no" key="' . $product_key . '"}}' ) : 0;
										$regular_price_raw = WFOCU_Common::maybe_parse_merge_tags( '{{product_regular_price_raw key="' . $product_key . '"}}' );
										$sale_price_raw    = WFOCU_Common::maybe_parse_merge_tags( '{{product_sale_price_raw key="' . $product_key . '"}}' );

										$reg_label   = isset( $settings['reg_label'] ) ? '<span class="wfocu-reg-label">' . $settings['reg_label'] . '</span>' : '';
										$offer_label = isset( $settings['offer_label'] ) ? '<span class="wfocu-offer-label">' . $settings['offer_label'] . '</span>' : '';

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

												$price_output .= $sale_price ? '<span class="offer_wrapper">' . $offer_label . '<span class="wfocu-sale-price">' . $sale_price . '</span></span>' : '';
											}
										}

										echo $price_output;

										if ( isset( $settings['show_signup_fee'] ) && 'on' === $settings['show_signup_fee'] ) {
											$signup_label = isset( $settings['signup_label'] ) ? $settings['signup_label'] : '';
											echo WFOCU_Common::maybe_parse_merge_tags( '{{product_signup_fee key="' . $product_key . '" signup_label="' . $signup_label . '"}}' );
										}

										if ( isset( $settings['show_rec_price'] ) && 'on' === $settings['show_rec_price'] ) {
											$recurring_label = isset( $settings['recurring_label'] ) ? $settings['recurring_label'] : '';
											echo WFOCU_Common::maybe_parse_merge_tags( '{{product_recurring_total_string info="yes" key="' . $product_key . '" recurring_label="' . $recurring_label . '"}}' );
										}
									}
								}
								?>

                            </div>
                        </div>
                    </div>
					<?php
				}
				?>
            </div>
			<?php
			return ob_get_clean();
		}


	}

	return new WFOCU_Offer_Price();
}