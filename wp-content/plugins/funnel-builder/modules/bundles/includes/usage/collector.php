<?php
/**
 * Bundles usage data for the host plugin's usage tracker.
 *
 * @package WFFN_Bundles
 */

namespace WFFN\Bundles\Includes\Usage;

use WFFN\Bundles\Includes\Rest\Settings_Controller;
use WFFN\Bundles\Includes\Storage\Bundle_Store;
use WFFN\Bundles\Includes\Storage\Install;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reports whether Bundles is on, its store-wide settings and the bundles
 * configured, per type. Collected on the tracker's own schedule, saved to an
 * option, then read back when the tracker sends.
 *
 * Loaded only when the tracker asks for its collectors; never on a page request.
 */
class Collector extends \WooFunnels_Usage_Collector_Abstract {

	/** Key of this data in the tracker payload; the registry entry uses the same key. */
	const PLUGIN_ID = 'bundles';

	/** @var string Plugin identifier, read by the tracker. */
	protected $plugin_id = self::PLUGIN_ID;

	/** @var string Module type: the tracker asks a lite collector for the site's opt-in. */
	protected $module = 'lite';

	/** @var string Shape version of the data. */
	protected $usage_version = '1.0.0';

	/**
	 * Whether the site allows this data to be collected.
	 *
	 * The site's opt-in first, then the host's rule for which installs send plugin data.
	 *
	 * @return bool
	 */
	public function should_collect() {
		if ( ! parent::should_collect() ) {
			return false;
		}
		$host_rule = array( 'WFFN_Usage_Collector', 'should_collect_plugin_usage_data' );
		return is_callable( $host_rule ) && (bool) call_user_func( $host_rule );
	}

	/**
	 * The usage data.
	 *
	 * @return array
	 */
	public function collect() {
		if ( ! $this->should_collect() ) {
			return array();
		}

		try {
			$data = array(
				'module'              => $this->module,
				'installation_config' => $this->installation_config(),
				'feature'             => $this->feature(),
				'feature_performance' => array(),
			);

			/**
			 * Filters the Bundles usage data before the tracker saves it.
			 *
			 * An add-on adds its own keys, such as how the bundles are performing,
			 * under `feature_performance`.
			 *
			 * @since 1.0.0
			 *
			 * @param array $data {module, installation_config, feature, feature_performance}.
			 */
			$filtered = apply_filters( 'wffn_bundles_usage_data', $data );

			return is_array( $filtered ) ? $filtered : $data;
		} catch ( \Throwable $e ) {
			return array();
		}
	}

	/**
	 * Collect and save the data, on the tracker's per-collector schedule.
	 *
	 * @return bool
	 */
	public function setup_data() {
		if ( ! $this->should_collect() ) {
			return false;
		}
		$data = $this->collect();
		if ( empty( $data ) ) {
			return false;
		}
		$data['_collected_at'] = current_time( 'mysql' );
		update_option( $this->get_option_key(), $data, false );
		return true;
	}

	/**
	 * The saved data, read when the tracker sends.
	 *
	 * @return array
	 */
	public function return_data() {
		$data = get_option( $this->get_option_key(), array() );
		if ( ! is_array( $data ) ) {
			return array();
		}
		unset( $data['_collected_at'] );
		return $data;
	}

	/**
	 * Option the data is saved in between collecting and sending.
	 *
	 * @return string
	 */
	public function get_option_key() {
		return 'fk_usage_data_' . self::PLUGIN_ID;
	}

	/**
	 * Whether Bundles is switched on, and its store-wide settings.
	 *
	 * @return array
	 */
	private function installation_config() {
		$state = Install::get_state();
		$data  = array(
			'bundles/state'  => null === $state ? 'not_chosen' : $state,
			'bundles/in_use' => Install::is_in_use() ? 1 : 0,
		);

		// Scalars only: a list setting is an add-on's to summarise.
		foreach ( Settings_Controller::read() as $key => $value ) {
			if ( Install::SETTINGS_STATE !== $key && ( is_scalar( $value ) || null === $value ) ) {
				$data[ 'settings/' . $key ] = $value;
			}
		}
		return $data;
	}

	/**
	 * How many bundles are configured, and of which types.
	 *
	 * @return array
	 */
	private function feature() {
		$data = array(
			'bundles/total'  => 0,
			'bundles/active' => 0,
			'bundles/types'  => array(),
		);
		foreach ( Bundle_Store::type_counts() as $type => $count ) {
			$key                     = self::type_key( $type );
			$data['bundles/total']  += $count['total'];
			$data['bundles/active'] += $count['active'];
			$data['bundles/types'][] = $key;

			$data[ 'bundles/' . $key . '/total' ]  = $count['total'];
			$data[ 'bundles/' . $key . '/active' ] = $count['active'];
		}
		return $data;
	}

	/**
	 * A bundle type's key in the usage data: its slug, or `type_<id>` for a type nothing names.
	 *
	 * @param int $type Bundle type id.
	 * @return string
	 */
	public static function type_key( $type ) {
		$slugs = array(
			'fixed_bundles'              => Bundle_Store::TYPE_FIXED_BUNDLES,
			'frequently_bought_together' => Bundle_Store::TYPE_FREQUENTLY_BOUGHT_TOGETHER,
		);
		/** This filter is documented in includes/storage/bundle-store.php */
		$slugs = (array) apply_filters( 'wffn_bundles_bundle_type_slugs', $slugs );
		$slug  = array_search( (int) $type, array_map( 'intval', $slugs ), true );

		return false !== $slug ? (string) $slug : 'type_' . (int) $type;
	}
}
