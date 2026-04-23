<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFACP_Econt_Delivery_Fields' ) ) {
	/**
	 * Econt Express Delivery Fields for FunnelKit Checkout
	 * Adds Econt delivery field placeholder to checkout fields so Econt can render its UI.
	 */
	class WFACP_Econt_Delivery_Fields {

		public function __construct() {
			if ( ! $this->is_enable() ) {
				return;
			}
			add_filter( 'wfacp_advanced_fields', array( $this, 'add_fields' ) );
			add_filter( 'wfacp_html_fields_econt_delivery_fields_1', '__return_false' );
			add_action( 'process_wfacp_html', array( $this, 'process_wfacp_html' ), 10, 2 );
		}

		public function add_fields( $field ) {
			$field['econt_delivery_fields_1'] = array(
				'type'          => 'wfacp_html',
				'default'       => false,
				'label'         => __( 'Econt Delivery Field', 'woofunnels-aero-checkout' ),
				'validate'      => array(),
				'id'            => 'econt_delivery_fields_1',
				'required'      => false,
				'wrapper_class' => array(),
			);
			return $field;
		}

		public function process_wfacp_html( $field, $key ) {
			if ( ! empty( $key ) && 'econt_delivery_fields_1' === $key ) {
				echo '<div id="customer_details"></div>';
			}
		}

		public function is_enable() {
			return defined( 'ECONT_PLUGIN_DIR' ) || class_exists( 'Econt_Express' );
		}
	}

	new WFACP_Econt_Delivery_Fields();
}
