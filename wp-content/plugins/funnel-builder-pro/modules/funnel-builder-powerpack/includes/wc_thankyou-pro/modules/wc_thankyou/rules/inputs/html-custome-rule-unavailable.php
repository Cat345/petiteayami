<?php
if ( ! class_exists( 'wfty_Input_Customer_Rule_Unavailable' ) ) {
	class wfty_Input_Customer_Rule_Unavailable {
		public function __construct() {
			// vars
			$this->type = 'Customer_Rule_Unavailable';

			$this->defaults = array(
				'default_value' => '',
				'class'         => '',
				'placeholder'   => ''
			);
		}

		public function render( $field, $value = null ) {
			$state = absint( WooFunnels_Dashboard::$classes['WooFunnels_DB_Updater']->get_upgrade_state() );

			if ( 3 === $state ) {
				_e( 'Indexing of orders is underway. This setting will work once the process completes.', 'funnel-builder-powerpack' );
			} else {
				_e( 'This rule needs indexing of past orders. Go to <a target="_blank" href="' . esc_url( admin_url( 'admin.php?page=woofunnels&tab=tools' ) ) . '">Tools > Index Orders</a> and click \'Start\' to index orders', 'funnel-builder-powerpack' );
			}
		}

	}
}