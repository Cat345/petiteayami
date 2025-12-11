<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use Elementor\Modules\DynamicTags\Module;
if ( ! class_exists( 'WFOCU_Elementor_Tag_Countdown' ) ) {
	/**
	 * Class WFOCU_Elementor_Tag_Countdown
	 */
	class WFOCU_Elementor_Tag_Countdown extends \Elementor\Core\DynamicTags\Tag {

		public function get_name() {
			return 'wfocu-next-offer-url';
		}

		public function get_title() {
			return __( 'Next Offer URL', 'elementor-pro' );
		}

		public function get_group() {
			return 'upstroke';
		}

		public function get_categories() {
			return [ Module::URL_CATEGORY ];
		}

		public function render() {
			$next_offer_url = WFOCU_Core()->wc_api->get_api_url( 'offer_expiry', array( 'log_event' => 'no' ) );
			echo $next_offer_url;
		}
	}
}