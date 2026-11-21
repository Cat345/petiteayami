<?php
/**
 * Settings_Controller — GET/PUT /settings, the AdminSettings blob.
 *
 * Stored in the single autoloaded option named by Install::OPTION_SETTINGS.
 * PUT is a shallow patch-merge that responds with the merged blob.
 */
namespace WFFN\Bundles\Includes\Rest;

use WFFN\Bundles\Includes\Frontend\Product_Display;
use WFFN\Bundles\Includes\Storage\Install;
use WFFN\Bundles\Includes\Storage\Settings_Schema;
use WFFN\Bundles\Includes\Skins\Options_Display;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin REST endpoints for the store-wide settings blob.
 */
class Settings_Controller extends Base {

	/**
	 * Register the /settings route.
	 *
	 * @return void
	 */
	public function register_routes() {
		$perm = array( $this, 'can_manage' );

		register_rest_route(
			$this->namespace,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => $perm,
					'args'                => array(
						'include' => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'put_settings' ),
					'permission_callback' => array( $this, 'can_write' ),
				),
			)
		);
	}

	/**
	 * GET /settings — the full stored blob merged over defaults.
	 *
	 * `include` names what else the caller wants beside it, and asking at all
	 * wraps the reply. The screen is the form plus its values, so it asks for
	 * both in one request rather than spending a round trip on each.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function get_settings( $request ) {
		if ( null === $request->get_param( 'include' ) ) {
			return self::ok( self::read() );
		}

		$include = array_filter( array_map( 'trim', explode( ',', (string) $request->get_param( 'include' ) ) ) );
		$out     = array( 'settings' => self::read() );

		if ( in_array( 'schema', $include, true ) ) {
			$out['schema'] = Settings_Schema::get();
		}

		return self::ok( $out );
	}

	/**
	 * PUT /settings — shallow patch-merge into the stored blob.
	 *
	 * Unknown top-level keys refuse the save; known keys are validated before the merge.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function put_settings( $request ) {
		$saved = self::save( $this->payload( $request ) );
		if ( is_wp_error( $saved ) ) {
			return self::err( $saved->get_error_message(), 400 );
		}

		return self::ok( $saved, __( 'Settings saved.', 'funnel-builder' ) );
	}

	/**
	 * Validate a patch and shallow-merge it into the stored blob.
	 *
	 * Unknown top-level keys refuse the save; known keys are validated before the merge.
	 *
	 * @param array $patch Settings keys to change.
	 * @return array|\WP_Error The stored settings, as read() returns them, or why the patch was refused.
	 */
	public static function save( array $patch ) {
		$sanitized = array();

		if ( array_key_exists( Install::SETTINGS_VARIATION_OPTIONS_DISPLAY, $patch ) ) {
			$mode = $patch[ Install::SETTINGS_VARIATION_OPTIONS_DISPLAY ];
			if ( ! is_string( $mode ) || ! in_array( $mode, Options_Display::MODES, true ) ) {
				return new \WP_Error( 'wffn_bundles_invalid_setting', __( 'Invalid product options display mode.', 'funnel-builder' ) );
			}
			$sanitized[ Install::SETTINGS_VARIATION_OPTIONS_DISPLAY ] = $mode;
		}

		if ( array_key_exists( Install::SETTINGS_PLACEMENT, $patch ) ) {
			$where = $patch[ Install::SETTINGS_PLACEMENT ];
			if ( ! is_string( $where ) || ! array_key_exists( $where, Product_Display::PLACEMENTS ) ) {
				return new \WP_Error( 'wffn_bundles_invalid_setting', __( 'Invalid bundle placement.', 'funnel-builder' ) );
			}
			$sanitized[ Install::SETTINGS_PLACEMENT ] = $where;
		}

		$state = $patch[ Install::SETTINGS_STATE ] ?? null;
		// Empty means the merchant has not chosen yet; that is not a choice to store.
		if ( null !== $state && '' !== $state && Install::STATE_ENABLED !== $state && Install::STATE_DISABLED !== $state ) {
			return new \WP_Error( 'wffn_bundles_invalid_setting', __( 'Invalid Bundles status.', 'funnel-builder' ) );
		}

		/**
		 * Filters the validated settings patch, for keys an add-on owns.
		 *
		 * Return a WP_Error to refuse the save with its message.
		 *
		 * @since 1.0.0
		 *
		 * @param array $sanitized Validated keys so far.
		 * @param array $patch     The raw incoming patch.
		 */
		$sanitized = apply_filters( 'wffn_bundles_settings_sanitize', $sanitized, $patch );
		if ( is_wp_error( $sanitized ) ) {
			return $sanitized;
		}

		$unknown = array_keys( array_diff_key( $patch, self::defaults() ) );
		if ( ! empty( $unknown ) ) {
			return new \WP_Error(
				'wffn_bundles_invalid_setting',
				sprintf(
					/* translators: %s: comma-separated setting names that are not recognised. */
					__( 'Unknown settings: %s. Nothing was saved.', 'funnel-builder' ),
					implode( ', ', array_map( 'sanitize_key', $unknown ) )
				)
			);
		}

		// Every stored key survives, so a save here never erases one an add-on owns.
		$stored = get_option( Install::OPTION_SETTINGS, array() );
		$merged = array_merge( is_array( $stored ) ? $stored : array(), (array) $sanitized );
		// Autoloaded: the storefront reads the placement on every request.
		update_option( Install::OPTION_SETTINGS, $merged, true );

		if ( null !== $state && '' !== $state ) {
			Install::set_state( $state );
		}

		return self::read();
	}

	/**
	 * Every settings key returned, and what it falls back to.
	 *
	 * @return array<string,mixed>
	 */
	private static function defaults() {
		/**
		 * Filters the settings keys returned and their defaults.
		 *
		 * A key absent here is never returned, so an add-on registers its own.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,mixed> $defaults Settings key to default value.
		 */
		return (array) apply_filters(
			'wffn_bundles_settings_defaults',
			array(
				Install::SETTINGS_PLACEMENT                 => Product_Display::DEFAULT_PLACEMENT,
				Install::SETTINGS_VARIATION_OPTIONS_DISPLAY => Options_Display::DEFAULT_MODE,
				Install::SETTINGS_STATE                     => null,
			)
		);
	}

	/**
	 * The stored settings, defaults filled in, in the admin app's shape.
	 *
	 * @return array<string,mixed>
	 */
	public static function read() {
		$stored = get_option( Install::OPTION_SETTINGS, array() );
		$stored = is_array( $stored ) ? $stored : array();

		// Key by key rather than merged: a name this plugin has stopped using is
		// still in the option on an install that once saved it, and merging the
		// whole blob would hand it back for as long as the row exists.
		$blob = array();
		foreach ( self::defaults() as $key => $default ) {
			$blob[ $key ] = $stored[ $key ] ?? $default;
		}

		$mode = $blob[ Install::SETTINGS_VARIATION_OPTIONS_DISPLAY ];
		$blob[ Install::SETTINGS_VARIATION_OPTIONS_DISPLAY ] = in_array( $mode, Options_Display::MODES, true )
			? $mode
			: Options_Display::DEFAULT_MODE;

		$blob[ Install::SETTINGS_STATE ] = Install::get_state();

		return $blob;
	}
}
