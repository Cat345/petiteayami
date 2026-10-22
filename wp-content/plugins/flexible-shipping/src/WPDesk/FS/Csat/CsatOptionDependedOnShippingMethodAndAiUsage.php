<?php

namespace WPDesk\FS\Csat;

use FSVendor\Octolize\Csat\CsatOptionDependedOnShippingMethod;

class CsatOptionDependedOnShippingMethodAndAiUsage extends CsatOptionDependedOnShippingMethod {

	/**
	 * @param mixed $settings
	 *
	 * @return mixed
	 */
	public function update_settings( $settings ) {
		if ( is_array( $settings ) && isset( $settings['used_rules_table_paste'] ) ) {
			$this->increase();
		}

		return $settings;
	}
}
