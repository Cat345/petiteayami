<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFOB_Checkout_WC_Objectiv' ) ) {
	#[\AllowDynamicProperties]
	class WFOB_Checkout_WC_Objectiv {
		private $already_printed = array();

		public function __construct() {
			add_filter( 'cfw_checkout_is_enabled', array( $this, 'actions' ), 989 );
			add_action( 'cfw_checkout_before_order_review_container', array( $this, 'add_input_hidden' ) );
		}

		public function actions( $status ) {
			add_action( 'wp_head', array( $this, 'css' ) );
			add_filter( 'wfob_print_placeholder', array( $this, 'disallow_multi_time_below_payment' ), 10, 2 );

			return $status;
		}

		public function add_input_hidden() {
			?>
			<input type="hidden" name="wfob_input_hidden_data" id="wfob_input_hidden_data">
			<input type="hidden" name="wfob_input_bump_shown_ids" id="wfob_input_bump_shown_ids">
			<?php
		}

		public function disallow_multi_time_below_payment( $status, $slug ) {
			if ( isset( $this->already_printed[ $slug ] ) ) {
				return false;
			}
			$this->already_printed[ $slug ] = true;

			return $status;
		}

		public function css() {
			?>
			<style>
				.wfob_bump_wrapper * {
					box-sizing: border-box;
				}
			</style>
			<?php
		}
	}

	new WFOB_Checkout_WC_Objectiv();
}