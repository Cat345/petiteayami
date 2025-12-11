<?php
if ( ! class_exists( 'WFOCU_Guten_Offer_Price' ) ) {
	class WFOCU_Guten_Offer_Price extends WFOCU_Guten_Field {
		public $slug = 'wfocu_offer_price';
		protected $id = 'wfocu_offer_price';

		public function __construct() {
			$this->name = __( "WF Offer Price" );
			$this->ajax = true;
			parent::__construct();
		}


		public function html( $settings ) {//phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter

			$sel_product_key = isset( $settings['product'] ) ? $settings['product'] : '';
			$product_key     = WFOCU_Common::default_selected_product_key( $sel_product_key );
			$product_key     = ( $product_key !== false ) ? $product_key : $sel_product_key;

			if ( ! isset( $product_key ) || empty( $product_key ) ) {
				return;
			}

			if ( ! isset( WFOCU_Core()->template_loader->product_data->products ) ) {
				return;
			}

			$product_data = WFOCU_Core()->template_loader->product_data->products;
			$product      = '';
			if ( isset( $product_data->{$product_key} ) ) {
				$product = $product_data->{$product_key}->data;
			}
			if ( ! $product instanceof WC_Product ) {
				return;
			}

			if ( isset( $settings['offer_slider_enabled'] ) && wc_string_to_bool( $settings['offer_slider_enabled'] ) ) {
				?>
                <style>
                    body #bwf_block-<?php echo esc_attr($settings['widget_block_id'])?> .wfocu-price-wrapper > span {
                        display: block;
                    }
                </style>
				<?php
			} ?>


            <div class="wp-block-wrap">
                <div class="wp-offer-price-inner">
					<?php
					/** Price */
					$regular_price     = ( wc_string_to_bool( $settings['show_reg_price'] ) ) ? WFOCU_Common::maybe_parse_merge_tags( '{{product_regular_price info="no" key="' . $product_key . '"}}' ) : 0;
					$sale_price        = ( wc_string_to_bool( $settings['show_offer_price'] ) ) ? WFOCU_Common::maybe_parse_merge_tags( '{{product_offer_price info="no" key="' . $product_key . '"}}' ) : 0;
					$regular_price_raw = WFOCU_Common::maybe_parse_merge_tags( '{{product_regular_price_raw key="' . $product_key . '"}}' );
					$sale_price_raw    = WFOCU_Common::maybe_parse_merge_tags( '{{product_sale_price_raw key="' . $product_key . '"}}' );

					$reg_label   = isset( $settings['reg_label'] ) ? '<span class="wfocu-reg-label">' . $settings['reg_label'] . '</span>' : '';
					$offer_label = isset( $settings['offer_label'] ) ? '<span class="wfocu-offer-label">' . $settings['offer_label'] . '</span>' : '';

					$price_output = '';
					if ( round( $sale_price_raw, 2 ) !== round( $regular_price_raw, 2 ) ) {
						if ( isset( $settings['show_reg_price'] ) && wc_string_to_bool( $settings['show_reg_price'] ) ) {

							$price_output .= '<span class="reg_wrapper bwf-price-wrap bwf-regular">' . $reg_label . '<span class="wfocu-regular-price">' . $regular_price . '</span></span>';
						}
						if ( isset( $settings['show_offer_price'] ) && wc_string_to_bool( $settings['show_offer_price'] ) ) {
							$price_output .= '<span class="offer_wrapper bwf-price-wrap bwf-offer">' . $offer_label . '<span class="wfocu-sale-price">' . $sale_price . '</span></span>';
						}
					} else {

						if ( 'variable' === $product->get_type() ) {
							$price_output .= sprintf( '<span class="wfocu-regular-price"><span class="wfocu_variable_price_regular" style="display: none;" data-key="%s"></span></span>', $product_key );
							$price_output .= $sale_price ? '<span class="offer_wrapper bwf-price-wrap bwf-offer">' . $offer_label . '<span class="wfocu-sale-price">' . $sale_price . '</span></span>' : '';
						} else {

							$price_output .= $sale_price ? '<span class="offer_wrapper bwf-price-wrap bwf-offer">' . $offer_label . '<span class="wfocu-sale-price">' . $sale_price . '</span></span>' : '';
						}
					}

					echo $price_output;//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					$is_subscription = in_array($product->get_type(), ['subscription', 'subscription_variation', 'variable-subscription']);

					if ( $is_subscription && isset( $settings['show_signup_fee'] ) && wc_string_to_bool( $settings['show_signup_fee'] ) ) {
						$signup_label = isset( $settings['signup_label'] ) ? $settings['signup_label'] : '';
						echo WFOCU_Common::maybe_parse_merge_tags( '{{product_signup_fee key="' . $product_key . '" signup_label="' . $signup_label . '"}}' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					if ( $is_subscription && isset( $settings['show_rec_price'] ) && wc_string_to_bool( $settings['show_rec_price'] ) ) {
						$recurring_label = isset( $settings['recurring_label'] ) ? $settings['recurring_label'] : '';
						echo WFOCU_Common::maybe_parse_merge_tags( '{{product_recurring_total_string info="yes" key="' . $product_key . '" recurring_label="' . $recurring_label . '"}}' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					} ?>

                </div>
            </div>
			<?php
		}


	}

	return new WFOCU_Guten_Offer_Price;
}
