<?php
/**
 * Ability discovery for the FunnelKit AI relay: GET /funnelkit/mcp/abilities (the
 * abilities the token's user may run, cheap, no schemas) and
 * GET /funnelkit/mcp/abilities/<vendor>/<ability> (one ability with its schemas).
 * Read-only; running an ability stays on the MCP endpoint's tools/call. Same bearer,
 * same gate and the same schemas as tools/list, so the two never disagree.
 */

defined( 'ABSPATH' ) || exit;

final class FKMCP_Connector_Abilities {
	const PER_PAGE     = 100;
	const MAX_PER_PAGE = 200;
	const SHORT        = 500; // discovery description, characters; the long form is the detail's

	/** The list: sorted by name so pages never overlap or skip. */
	public static function index( array $params, $user_id ) {
		$denied = self::denied( $user_id );

		if ( $denied ) {
			return $denied;
		}

		$page     = self::positive( $params, 'page', 1 );
		$per_page = self::positive( $params, 'per_page', self::PER_PAGE );
		$category = array_key_exists( 'category', $params ) ? $params['category'] : null;

		if ( null === $page || null === $per_page || ( null !== $category && ! is_string( $category ) ) ) {
			return self::error( 400, 'fkmcp_bad_request', __( 'page and per_page take whole numbers from 1, and category one slug.', 'wp-marketing-automations' ) );
		}

		$per_page  = min( $per_page, self::MAX_PER_PAGE );
		$abilities = array();

		foreach ( FKMCP_Connector_Mcp::tools_for_user() as $ability ) {
			if ( null === $category || self::category( $ability ) === $category ) {
				$abilities[ $ability->get_name() ] = $ability;
			}
		}

		ksort( $abilities, SORT_STRING );

		$store = FKMCP_Connector_Mcp::store_meta();
		$items = array();

		foreach ( array_slice( $abilities, ( $page - 1 ) * $per_page, $per_page ) as $ability ) {
			$items[] = self::summary( $ability, self::short( (string) $ability->get_description() ), $store );
		}

		return self::json(
			200,
			array(
				'api_version' => FKMCP_Connector_Discovery::ABILITIES_API_VERSION,
				'abilities'   => $items,
				'total'       => count( $abilities ),
				'page'        => $page,
				'per_page'    => $per_page,
				'has_more'    => $page * $per_page < count( $abilities ),
			)
		);
	}

	/** One ability, with the schemas tools/call validates against. */
	public static function show( $vendor, $slug, $user_id ) {
		$denied = self::denied( $user_id );

		if ( $denied ) {
			return $denied;
		}

		$name = (string) $vendor . '/' . (string) $slug;

		// Phase 1: only what tools/call can run is served.
		$known = FKMCP_Connector_Mcp::PREFIX === (string) $vendor . '/' && 1 === preg_match( '/^[a-z0-9][a-z0-9_-]*$/', (string) $slug ) && wp_has_ability( $name );

		if ( ! $known ) {
			/* translators: %s: an ability name, e.g. funnelkit/get-funnels */
			return self::error( 404, 'fkmcp_unknown_ability', sprintf( __( 'No ability named %s is registered on this site. Call the abilities list to see what is available.', 'wp-marketing-automations' ), $name ), array( 'ability' => $name ) );
		}

		$ability = wp_get_ability( $name );

		try {
			$allowed = true === $ability->check_permissions();
		} catch ( Throwable $e ) {
			return self::error( 500, 'fkmcp_exception', __( 'The store could not check this ability. Try again shortly.', 'wp-marketing-automations' ), array( 'ability' => $name ) );
		}

		if ( ! $allowed ) {
			$why = FKMCP_Connector_Mcp::withheld( $ability );

			return self::error( 403, $why->get_error_code(), $why->get_error_message(), array( 'ability' => $name ) );
		}

		$doc                 = array( 'api_version' => FKMCP_Connector_Discovery::ABILITIES_API_VERSION );
		$doc                += self::summary( $ability, trim( (string) $ability->get_description() ), FKMCP_Connector_Mcp::store_meta() );
		$doc['input_schema'] = FKMCP_Connector_Mcp::object_schema( $ability->get_input_schema() );
		$output              = FKMCP_Connector_Mcp::output_schema( $ability );

		if ( null !== $output ) {
			$doc['output_schema'] = $output;
		}

		return self::json( 200, $doc );
	}

	/** null when the request may read abilities, else the refusal. */
	private static function denied( $user_id ) {
		if ( (int) $user_id < 1 ) {
			$challenge = FKMCP_Connector_Bearer::unauthorized();

			return self::error( 401, 'fkmcp_invalid_token', __( 'The access token is missing, invalid or expired.', 'wp-marketing-automations' ), array(), $challenge['headers'] );
		}

		$verdict = FKMCP_Connector_Mcp::access();

		if ( true === $verdict ) {
			return null;
		}

		$code = (string) $verdict->get_error_code();
		$data = $verdict->get_error_data( $code );

		return self::error(
			is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 403,
			'fkmcp_rate_limited' === $code ? 'rate_limited' : $code,
			$verdict->get_error_message()
		);
	}

	private static function summary( $ability, $description, array $store ) {
		$item = array(
			'name'        => (string) $ability->get_name(),
			'label'       => (string) $ability->get_label(),
			'description' => $description,
		);

		if ( '' !== self::category( $ability ) ) {
			$item['category'] = self::category( $ability );
		}

		$item['annotations'] = self::annotations( $ability );
		$meta                = array();
		$license             = $ability->get_meta_item( 'fkai_license', null );

		if ( is_array( $license ) ) {
			$meta['fkai_license'] = $license;
		}

		if ( array() !== $store ) {
			$meta['fkai_store'] = $store;
		}

		if ( array() !== $meta ) {
			$item['meta'] = $meta;
		}

		return $item;
	}

	/** All three, always. Unset reads as the unsafe value - except that a read-only ability destroys nothing and can run twice. */
	public static function annotations( $ability ) {
		$a         = $ability->get_meta_item( 'annotations', array() );
		$a         = is_array( $a ) ? $a : array();
		$read_only = isset( $a['readonly'] ) && true === $a['readonly'];

		return array(
			'read_only'   => $read_only,
			'destructive' => isset( $a['destructive'] ) && is_bool( $a['destructive'] ) ? $a['destructive'] : ! $read_only,
			'idempotent'  => isset( $a['idempotent'] ) && is_bool( $a['idempotent'] ) ? $a['idempotent'] : $read_only,
		);
	}

	/** One line of at most SHORT characters, cut after the last whole sentence that fits. */
	public static function short( $text ) {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', (string) $text ) );

		if ( mb_strlen( $text, 'UTF-8' ) <= self::SHORT ) {
			return $text;
		}

		$out = '';

		foreach ( preg_split( '/(?<=[.!?])\s+/u', $text ) as $sentence ) {
			$next = '' === $out ? $sentence : $out . ' ' . $sentence;

			if ( mb_strlen( $next, 'UTF-8' ) > self::SHORT ) {
				break;
			}

			$out = $next;
		}

		return '' !== $out ? $out : rtrim( mb_substr( $text, 0, self::SHORT - 3, 'UTF-8' ) ) . '...';
	}

	private static function category( $ability ) {
		return method_exists( $ability, 'get_category' ) ? (string) $ability->get_category() : '';
	}

	/** A positive whole number from the query, the default when absent, null when malformed. */
	private static function positive( array $params, $key, $default ) {
		if ( ! array_key_exists( $key, $params ) ) {
			return $default;
		}

		$value = $params[ $key ];

		if ( is_int( $value ) ) {
			return $value >= 1 ? $value : null;
		}

		return ( is_string( $value ) && 1 === preg_match( '/^[1-9]\d{0,8}$/', $value ) ) ? (int) $value : null;
	}

	private static function json( $status, array $body, array $headers = array() ) {
		return array(
			'status'  => $status,
			'headers' => array( 'Cache-Control' => 'no-store' ) + $headers,
			'json'    => $body,
		);
	}

	/**
	 * The WordPress REST error shape, with meta.fkai_store as the list sends it. A 401 is the one
	 * refusal sent before sign-in (denied() runs first), so it says the module version only.
	 */
	private static function error( $status, $code, $message, array $data = array(), array $headers = array() ) {
		$body = array(
			'code'    => $code,
			'message' => $message,
			'data'    => array( 'status' => $status ) + $data,
		);
		$meta = 401 === (int) $status ? FKMCP_Connector_Mcp::public_meta() : FKMCP_Connector_Mcp::store_meta();

		if ( array() !== $meta ) {
			$body['meta'] = array( 'fkai_store' => $meta );
		}

		return self::json( $status, $body, $headers );
	}
}
