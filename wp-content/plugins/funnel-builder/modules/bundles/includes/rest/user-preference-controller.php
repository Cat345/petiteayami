<?php
/** REST Controller — per-user admin preferences under wffn-bundles-admin/v1. */
namespace WFFN\Bundles\Includes\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores what one admin user has chosen in the app, such as notices they closed.
 */
class User_Preference_Controller extends Base {

	/** User meta holding the keys of the notices a user has closed. */
	const META_NOTIFICATIONS = '_wffn_bundles_header_notification';

	/**
	 * Register the /user-preferences route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/user-preferences/(?P<user_id>[\d]+)',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_user_preference' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/**
	 * POST /user-preferences/{user_id} — save one preference for the current user.
	 *
	 * Body: `{ data_type: 'notification', data: string|string[] }`.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function update_user_preference( $request ) {
		$user_id = (int) $request['user_id'];
		// A preference belongs to the person setting it, not to anyone who can manage bundles.
		if ( get_current_user_id() !== $user_id ) {
			return self::err( __( 'You can only change your own preferences.', 'funnel-builder' ), 403 );
		}

		$body      = $this->payload( $request );
		$data_type = sanitize_key( (string) ( $body['data_type'] ?? '' ) );
		$data      = $body['data'] ?? null;

		if ( empty( $data ) ) {
			return self::err( __( 'Data is required.', 'funnel-builder' ) );
		}

		switch ( $data_type ) {
			case 'notification':
				$keys = array_filter( array_map( 'sanitize_key', (array) $data ) );
				if ( empty( $keys ) ) {
					return self::err( __( 'Invalid notification key.', 'funnel-builder' ) );
				}
				$closed = array_values( array_unique( array_merge( self::dismissed_notices( $user_id ), $keys ) ) );
				update_user_meta( $user_id, self::META_NOTIFICATIONS, $closed );

				return self::ok( array( 'notifications' => $closed ), __( 'Preference saved.', 'funnel-builder' ) );
			default:
				return self::err( __( 'Invalid data type.', 'funnel-builder' ) );
		}
	}

	/**
	 * The keys of the notices a user has closed.
	 *
	 * @param int $user_id User to read.
	 * @return string[]
	 */
	public static function dismissed_notices( $user_id ) {
		$stored = get_user_meta( (int) $user_id, self::META_NOTIFICATIONS, true );
		return is_array( $stored ) ? array_values( array_filter( array_map( 'strval', $stored ) ) ) : array();
	}
}
