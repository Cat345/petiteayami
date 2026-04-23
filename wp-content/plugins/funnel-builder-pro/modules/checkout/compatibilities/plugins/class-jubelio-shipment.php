<?php

if ( ! class_exists( 'WFACP_Compatibility_With_Jubelio_Shipment' ) ) {
	class WFACP_Compatibility_With_Jubelio_Shipment {
		public function __construct() {
			// Remove address_search fields filter
			add_action( 'funnelkit_rest_checkout_fields', array( $this, 'remove_address_fields' ) );
		}

		public function remove_address_fields() {
			WFACP_Common::remove_actions( 'woocommerce_default_address_fields', 'JubelioShipment', 'jubeship_override_address_fields' );
		}


		public function is_enabled() {
			return class_exists( 'JubelioShipment' );
		}
	}

	new WFACP_Compatibility_With_Jubelio_Shipment();
}
