<?php

namespace ElementorPro\Modules\ThemeBuilder\Conditions;

use ElementorPro\Modules\QueryControl\Module as QueryModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
if ( ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Conditions\WooFunnels_Offers' ) ) {
	class WooFunnels_Offers extends Post {

		public function get_label() {
			return 'FunnelKit Upsell Offer';
		}


		public function register_sub_conditions() {
		}


	}
}