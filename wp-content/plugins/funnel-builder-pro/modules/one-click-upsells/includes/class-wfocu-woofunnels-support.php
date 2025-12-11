<?php

class WFOCU_WooFunnels_Support {

	public static $_instance = null;
	public $full_name = 'Upstroke: WooCommerce One Click Upsell';
	public $is_license_needed = true;
	public $license_instance;
	public $slug = 'woofunnels-upstroke-one-click-upsell';
	public $encoded_basename = '';


	/**
	 * WFOCU_WooFunnels_Support constructor.
	 */
	public function __construct() {
		add_filter( 'woofunnels_default_reason_' . WFOCU_PLUGIN_BASENAME, function () {
			return 1;
		} );
		add_filter( 'woofunnels_default_reason_default', function () {
			return 1;
		} );
		add_filter( 'bwf_needs_order_indexing', '__return_true', 999 );

		$this->encoded_basename = sha1( WFOCU_PLUGIN_BASENAME );
		add_filter( 'woofunnels_plugins_license_needed', array( $this, 'add_license_support' ), 10 );
		add_action( 'init', array( $this, 'init_licensing' ), 12 );
		add_action( 'woofunnels_licenses_submitted', array( $this, 'process_licensing_form' ) );
		add_action( 'woofunnels_deactivate_request', array( $this, 'maybe_process_deactivation' ) );
		add_action( 'admin_init', array( $this, 'maybe_handle_license_activation_wizard' ), 1 );
		add_filter( 'woofunnels_global_tracking_data', array( $this, 'add_data_to_tracking' ), 10, 1 );
		add_filter( 'woofunnels_show_reset_tracking', '__return_true', 999 );
		add_action( 'admin_menu', array( $this, 'add_menus' ),81 );
		
	}

	/**
	 * @return null|WFOCU_WooFunnels_Support
	 */
	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self;
		}

		return self::$_instance;
	}


	public function woofunnels_page() {
		if ( ! isset( $_GET['tab'] ) ) {   // phpcs:ignore WordPress.Security.NonceVerification.Missing
			WooFunnels_dashboard::$selected = 'licenses';
		}
		WooFunnels_dashboard::load_page();
	}


	public function is_license_present() {
		$plugins = WooFunnels_License_check::get_plugins();

		if ( ! isset( $plugins[ $this->encoded_basename ] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * License management helper function to create a slug that is friendly with edd
	 *
	 * @param type $name
	 *
	 * @return type
	 */
	public function slugify_module_name( $name ) {
		return preg_replace( '/[^a-zA-Z0-9_\s]/', '', str_replace( ' ', '_', strtolower( $name ) ) );
	}

	public function add_license_support( $plugins ) {
		$status  = 'invalid';
		$renew   = 'Please Activate';
		$license = array(
			'key'     => '',
			'email'   => '',
			'expires' => '',
		);

		$plugins_in_database = WooFunnels_License_check::get_plugins();

		if ( is_array( $plugins_in_database ) && isset( $plugins_in_database[ $this->encoded_basename ] ) && count( $plugins_in_database[ $this->encoded_basename ] ) > 0 ) {
			$status  = 'active';
			$renew   = '';
			$license = array(
				'key'     => $plugins_in_database[ $this->encoded_basename ]['data_extra']['api_key'],
				'email'   => $plugins_in_database[ $this->encoded_basename ]['data_extra']['license_email'],
				'expires' => $plugins_in_database[ $this->encoded_basename ]['data_extra']['expires'],
			);
		}

		$plugins[ $this->encoded_basename ] = array(
			'plugin'            => $this->full_name,
			'product_version'   => WFOCU_VERSION,
			'product_status'    => $status,
			'license_expiry'    => $renew,
			'product_file_path' => $this->encoded_basename,
			'existing_key'      => $license,
		);

		return $plugins;
	}

	public function woofunnels_slugify_module_name( $name ) {
		return preg_replace( '/[^a-zA-Z0-9_\s]/', '', str_replace( ' ', '_', strtolower( $name ) ) );
	}

	public function init_licensing() {
		if ( class_exists( 'WooFunnels_License_check' ) && $this->is_license_needed ) {
			$this->license_instance = new WooFunnels_License_check( $this->encoded_basename );

			$plugins = WooFunnels_License_check::get_plugins();
			if ( isset( $plugins[ $this->encoded_basename ] ) && count( $plugins[ $this->encoded_basename ] ) > 0 ) {
				$data = array(
					'plugin_slug' => WFOCU_PLUGIN_BASENAME,
					'plugin_name' => WFOCU_FULL_NAME,
					'license_key' => $plugins[ $this->encoded_basename ]['data_extra']['api_key'],
					'product_id'  => $this->full_name,
					'version'     => WFOCU_VERSION,
				);
				$this->license_instance->setup_data( $data );
				$this->license_instance->start_updater();
			}
		}
	}

	public function process_licensing_form( $posted_data ) {

		if ( isset( $posted_data['license_keys'][ $this->encoded_basename ] ) ) {
			$key  = $posted_data['license_keys'][ $this->encoded_basename ]['key'];
			$data = array(
				'plugin_slug' => WFOCU_PLUGIN_BASENAME,
				'plugin_name' => WFOCU_FULL_NAME,

				'license_key' => $key,
				'product_id'  => $this->full_name,
				'version'     => WFOCU_VERSION,
			);
			$this->license_instance->setup_data( $data );
			$this->license_instance->activate_license();
		}
	}

	/**
	 * Validate is it is for email product deactivation
	 *
	 * @param type $posted_data
	 */
	public function maybe_process_deactivation( $posted_data ) {
		if ( isset( $posted_data['filepath'] ) && $posted_data['filepath'] === $this->encoded_basename ) {
			$plugins = WooFunnels_License_check::get_plugins();
			if ( isset( $plugins[ $this->encoded_basename ] ) && count( $plugins[ $this->encoded_basename ] ) > 0 ) {
				$data = array(
					'plugin_slug' => WFOCU_PLUGIN_BASENAME,
					'plugin_name' => WFOCU_FULL_NAME,
					'license_key' => $plugins[ $this->encoded_basename ]['data_extra']['api_key'],
					'product_id'  => $this->full_name,
					'version'     => WFOCU_VERSION,
				);
				$this->license_instance->setup_data( $data );
				$this->license_instance->deactivate_license();
				wp_safe_redirect( 'admin.php?page=' . $posted_data['page'] . '&tab=' . $posted_data['tab'] );
				exit;
			}
		}
	}


	public function maybe_handle_license_activation_wizard() {

		if ( filter_input( INPUT_POST, 'wfocu_verify_license', FILTER_UNSAFE_RAW ) !== null ) {
			$data = array(
				'plugin_slug' => WFOCU_PLUGIN_BASENAME,
				'plugin_name' => WFOCU_FULL_NAME,
				'license_key' => filter_input( INPUT_POST, 'license_key', FILTER_UNSAFE_RAW ),
				'product_id'  => $this->full_name,
				'version'     => WFOCU_VERSION,
			);
			$this->license_instance->setup_data( $data );
			$data_response = $this->license_instance->activate_license();

			if ( is_array( $data_response ) && $data_response['activated'] === true ) {
				WFOCU_Wizard::set_license_state( true );
				do_action( 'wfocu_license_activated', 'woofunnels-upstroke-one-click-upsell' );
				if ( filter_input( INPUT_POST, '_redirect_link', FILTER_UNSAFE_RAW ) !== null ) {
					wp_redirect( filter_input( INPUT_POST, '_redirect_link', FILTER_UNSAFE_RAW ) );
					exit;
				}
			} else {
				WFOCU_Wizard::set_license_state( false );
				WFOCU_Wizard::set_license_key( filter_input( INPUT_POST, 'license_key', FILTER_UNSAFE_RAW ) );

			}
		}
	}

	/**
	 * Append WooFunnels data to tracking data
	 *
	 * @param $tracking_data
	 *
	 * @return mixed
	 */
	public function add_data_to_tracking( $tracking_data ) {

		$woofunnels = array();
		/**
		 *
		 *
		 * [woofunnels] => Array
		 * (
		 * [total_funnels] =>
		 * [total_active_funnels] =>
		 * [funnel_init_count] =>
		 * [offer_accepted_count] =>
		 * [offer_rejected_count] =>
		 * [settings] =>
		 * )
		 */

		$get_funnels                 = WFOCU_Common::get_post_table_data();
		$woofunnels['total_funnels'] = $get_funnels['found_posts'];
		$items_active                = array_filter( $get_funnels['items'], function ( $var ) {

			return ( $var['status'] === 'publish' );
		} );

		$woofunnels['total_active_funnels'] = count( $items_active );

		$results = WFOCU_Core()->track->query_results( array(
			'data'       => array(
				'ID' => array(
					'type'     => 'col',
					'function' => 'COUNT',
					'name'     => 'total_count',
				),
			),
			'where'      => array(

				array(
					'key'      => 'events.action_type_id',
					'value'    => 1,
					'operator' => '=',
				),
			),
			'order_by'   => 'events.id DESC',
			'query_type' => 'get_var',
		) );

		$woofunnels['funnel_init_count'] = $results;

		$results = WFOCU_Core()->track->query_results( array(
			'data'       => array(
				'ID' => array(
					'type'     => 'col',
					'function' => 'COUNT',
					'name'     => 'total_count',
				),
			),
			'where'      => array(

				array(
					'key'      => 'events.action_type_id',
					'value'    => 4,
					'operator' => '=',
				),
			),
			'order_by'   => 'events.id DESC',
			'query_type' => 'get_var',
		) );

		$woofunnels['offer_accepted_count'] = $results;

		$results = WFOCU_Core()->track->query_results( array(
			'data'       => array(
				'ID' => array(
					'type'     => 'col',
					'function' => 'COUNT',
					'name'     => 'total_count',
				),
			),
			'where'      => array(

				array(
					'key'      => 'events.action_type_id',
					'value'    => 6,
					'operator' => '=',
				),
			),
			'order_by'   => 'events.id DESC',
			'query_type' => 'get_var',
		) );

		$woofunnels['offer_rejected_count'] = $results;

		$woofunnels['settings'] = WFOCU_Core()->data->get_option();

		$tracking_data['upstroke'] = $woofunnels;

		return $tracking_data;

	}

/**
	 * Adding WooCommerce sub-menu for global options
	 */
	public function add_menus() {
		if ( ! WooFunnels_dashboard::$is_core_menu ) {
			add_menu_page( __( 'WooFunnels', 'woofunnels-upstroke-one-click-upsell' ), __( 'WooFunnels', 'woofunnels-upstroke-one-click-upsell' ), 'manage_woocommerce', 'woofunnels', array(
				$this,
				'woofunnels_page',
			), '', 59 );
			add_submenu_page( 'woofunnels', __( 'Licenses', 'woofunnels-upstroke-one-click-upsell' ), __( 'License', 'woofunnels-upstroke-one-click-upsell' ), 'manage_woocommerce', 'woofunnels' );
			WooFunnels_dashboard::$is_core_menu = true;
		}
	}

}

if ( class_exists( 'WFOCU_WooFunnels_Support' ) ) {
	WFOCU_Core::register( 'support', 'WFOCU_WooFunnels_Support' );
}