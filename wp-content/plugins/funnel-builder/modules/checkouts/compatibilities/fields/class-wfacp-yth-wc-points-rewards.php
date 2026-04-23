<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * YITH WooCommerce Points and Rewards Premium by YITH up to (2.0.7)
 * Plugin Path: https://yithemes.com/themes/plugins/yith-woocommerce-points-and-rewards/
 */
if ( ! class_exists( 'WFACP_Compatibility_With_YTH_WC_Points_Rewards' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_YTH_WC_Points_Rewards {
		public $instance = null;

		public function __construct() {
			/* Add field in the advanced option */
			add_filter( 'wfacp_advanced_fields', array( $this, 'add_field' ), 20 );
			add_filter( 'wfacp_html_fields_yith_wc_birthday', '__return_false' );
			/* Display the field */
			add_action( 'process_wfacp_html', array( $this, 'process_wfacp_html' ), 10, 2 );
			/* Remove Checkout field and initialize object  */
			add_action( 'wfacp_after_checkout_page_found', array( $this, 'action' ) );
			/* styling for tipping field */
			add_action( 'wfacp_internal_css', array( $this, 'wfacp_internal_css' ) );

			/* prevent third party fields and wrapper*/

			add_action( 'wfacp_add_billing_shipping_wrapper', '__return_false' );
		}

		public function action() {
			if ( ! $this->is_enabled() ) {
				return;
			}
			$this->instance = WFACP_Common::remove_actions( 'woocommerce_checkout_fields', 'YITH_WC_Points_Rewards_Frontend', 'add_birthday_field_checkout' );
		}

		public function is_enabled() {
			if ( ! class_exists( 'YITH_WC_Points_Rewards_Frontend' ) ) {
				return false;
			}
			$available_places = get_option( 'ywpar_birthday_date_field_where', array( 'my-account', 'register_form', 'checkout' ) );

			if ( ! in_array( 'checkout', $available_places ) ) {
				return false;
			}

			return true;
		}

		public function add_field( $fields ) {
			if ( ! $this->is_enabled() ) {
				return $fields;
			}
			$fields['yith_wc_birthday'] = array(
				'type'       => 'wfacp_html',
				'class'      => array( 'wfacp-col-full', 'wfacp-form-control-wrapper', 'wfacp_anim_wrap', 'yith_wc_birthday' ),
				'id'         => 'yith_wc_birthday',
				'field_type' => 'yith_wc_birthday',
				'label'      => __( 'Yth WC Birthday', 'woofunnels-aero-checkout' ),
			);

			return $fields;
		}

		public function process_wfacp_html( $field, $key ) {
			if ( ! $this->is_enabled() || 'yith_wc_birthday' !== $key || ! $this->instance instanceof YITH_WC_Points_Rewards_Frontend ) {
				return;
			}
			$all_fields = $this->instance->add_birthday_field_checkout( (array) WC()->checkout() );
			if ( isset( $all_fields['billing']['yith_birthday'] ) ) {
				$yith_birthday_field = $all_fields['billing']['yith_birthday'];
				echo '<div id=wfacp_yith_wc_birthday>';
				$yith_birthday_field['input_class'] = array_merge( array( 'wfacp-form-control' ), $yith_birthday_field['input_class'] );
				$label_class                        = array();
				if ( isset( $yith_birthday_field['label_class'] ) ) {
					$label_class = $yith_birthday_field['label_class'];
				}
				$yith_birthday_field['label_class'] = array_merge( array( 'wfacp-form-control-label' ), $label_class );
				$yith_birthday_field['class']       = array_merge( array( 'wfacp-form-control-wrapper wfacp-col-left-half ' ), $yith_birthday_field['class'] );
				$yith_birthday_field['cssready']    = array( 'wfacp-col-left-half' );
				woocommerce_form_field( 'yith_birthday', $yith_birthday_field );
				echo '</div>';
			}
		}

		public function wfacp_internal_css() {
			if ( ! $this->is_enabled() ) {
				return;
			}
			?>
			<style>
				body #wfacp-sec-wrapper #wfacp_yith_wc_birthday {
					clear: both;
				}
				body #wfacp-sec-wrapper .wfacp_main_form.woocommerce .woocommerce-info#yith-par-message-cart {
					padding: 8px 12px !important;
					display: block;
					background: #f1f1f1;
					border-radius: 5px;
					margin-bottom: 16px !important;
				}
				body #wfacp-sec-wrapper .wfacp_main_form.woocommerce form.ywpar_apply_discounts input[type="text"] {
					width: auto;
					min-height: 1px;
					text-align: center;
					max-width: 80px;
					min-width: 30px;
					padding: 5px 5px;
				}

				#wfacp-sec-wrapper .wfacp_main_form button#ywpar_apply_discounts {
					width: auto;
					padding: 10px;
					font-size: 13px;
					min-height: 1px;
					height: auto;
					margin: 5px 0;
				}

				body #wfacp-sec-wrapper .wfacp_main_form.woocommerce div#yith-par-message-reward-cart {
					padding: 8px 12px !important;
					display: block;
					background: #f1f1f1;
					border-radius: 5px;
				}
				body #wfacp-sec-wrapper .wfacp_main_form.woocommerce .default-layout .ywpar_apply_discounts {
					display: inline-block;
					width: 100%;
				}

				body #wfacp-sec-wrapper .wfacp_main_form.woocommerce .default-layout .ywpar_apply_discounts span {
					display: inline-block;
				}

				body #wfacp-sec-wrapper .wfacp_main_form.woocommerce .default-layout .ywpar_apply_discounts *,
				body #wfacp-sec-wrapper .wfacp_main_form.woocommerce .default-layout .ywpar_apply_discounts,
				body #wfacp-sec-wrapper .wfacp_main_form.woocommerce .woocommerce-info#yith-par-message-cart *,
				body #wfacp-sec-wrapper .wfacp_main_form.woocommerce .woocommerce-info#yith-par-message-cart {
					font-size: 13px !important;
					line-height: 1.5;
				}

				body #wfacp-sec-wrapper div#yith-par-message-reward-cart strong {
					margin: 0;
				}

			</style>
			<?php
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_YTH_WC_Points_Rewards(), 'wfacp-yth-wc-points-rewards' );
}
