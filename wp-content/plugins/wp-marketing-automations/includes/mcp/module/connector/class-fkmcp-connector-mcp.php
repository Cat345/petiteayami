<?php
/**
 * The MCP endpoint: stateless JSON-RPC over the WordPress Abilities API. Only
 * initialize, ping, tools/list and tools/call exist, because those are the
 * only methods the gateway forwards (spec 9). No sessions, no SSE, no batches.
 */

defined( 'ABSPATH' ) || exit;

final class FKMCP_Connector_Mcp {
	const PROTOCOL    = '2025-06-18';
	const PREFIX      = 'funnelkit/';
	const PAGE_BYTES  = 120000; // JSON bytes of tool items per tools/list page; the gateway reads 200 KB per reply
	const MESSAGE_MAX = 1000; // characters of structuredContent.error.message; the longest message we author is ~330

	/** The error classes structuredContent.error.code may hold - the FunnelKit AI gateway counts failed calls by them. */
	const ERROR_CLASSES = array(
		'missing_argument',
		'invalid_argument',
		'not_found',
		'permission_denied',
		'license_required',
		'plugin_incompatible',
		'conflict',
		'rate_limited',
		'result_too_large',
		'internal_error',
		'ai_disabled',
		'writes_paused',
	);

	/** Refusal codes (the Abilities API's, the module's, the packs') whose class their name does not say. */
	const ERROR_CLASS_OF = array(
		'ability_invalid_permissions' => 'permission_denied',
		'ai_disabled'                 => 'ai_disabled',
		'writes_paused'               => 'writes_paused',
		'fkmcp_forbidden'             => 'permission_denied',
		'fkmcp_rate_limited'          => 'rate_limited',
		'fkmcp_result_too_large'      => 'result_too_large',
		'fkmcp_plugin_missing'        => 'plugin_incompatible',
		'fkmcp_unknown_tool'          => 'not_found',
		'fkmcp_pro_required'          => 'license_required',
		'fkmcp_search_required'       => 'missing_argument',
		'fkmcp_ask_clone_or_new'      => 'missing_argument',
		'fkmcp_needs_template'        => 'missing_argument',
		'fkmcp_needs_steps'           => 'missing_argument',
		'fkmcp_needs_mode'            => 'missing_argument',
		'fkmcp_stale'                 => 'conflict',
		'fkmcp_review_stale'          => 'conflict',
		'fkmcp_stale_template'        => 'conflict',
		'fkmcp_exists'                => 'conflict',
		'fkmcp_busy'                  => 'conflict',
		'fkmcp_checksum_mismatch'     => 'conflict',
		'fkmcp_controller_exit'       => 'internal_error',
		'fkmcp_exception'             => 'internal_error',
		'fkmcp_registry_unavailable'  => 'internal_error',
	);

	/** Any other refusal code, by what its name ends in. */
	const ERROR_CLASS_BY_SUFFIX = array(
		'_not_found'   => 'not_found',
		'_unavailable' => 'plugin_incompatible',
		'_missing'     => 'plugin_incompatible',
		'_failed'      => 'internal_error',
		'_required'    => 'missing_argument',
		'_stale'       => 'conflict',
	);

	/** Then by the HTTP status the registry attached (FKMCP_Registry::ERROR_STATUS). */
	const ERROR_CLASS_BY_STATUS = array(
		400 => 'invalid_argument',
		401 => 'permission_denied',
		403 => 'permission_denied',
		404 => 'not_found',
		409 => 'conflict',
		413 => 'result_too_large',
		429 => 'rate_limited',
		501 => 'plugin_incompatible',
	);

	/**
	 * The status a refusal reports when its WP_Error carried none, so the
	 * gateway's log always names the kind of failure. Roughly the reverse of
	 * ERROR_CLASS_BY_STATUS; a class missing here reports 500.
	 */
	const STATUS_OF_CLASS = array(
		'missing_argument'    => 400,
		'invalid_argument'    => 400,
		'not_found'           => 404,
		'permission_denied'   => 403,
		'license_required'    => 403,
		'plugin_incompatible' => 501,
		'conflict'            => 409,
		'rate_limited'        => 429,
		'result_too_large'    => 413,
		'internal_error'      => 500,
		'ai_disabled'         => 403,
		'writes_paused'       => 403,
	);

	/** True for a non-empty sequential list; false for an empty array or an object. */
	private static function is_list( array $a ) {
		return ! empty( $a ) && array_keys( $a ) === range( 0, count( $a ) - 1 );
	}

	/** One JSON-RPC message in, one response array out. */
	public static function handle( $body, array $headers, $user_id ) {
		// Approving the first AI client is what turns AI connections on, so until then
		// FunnelKit AI's discovery still has to get the sign-in challenge that names
		// this store's metadata. A signed-in call is refused by gate(), as ai_disabled.
		if ( (int) $user_id < 1 ) {
			return FKMCP_Connector_Bearer::unauthorized();
		}

		$msg = json_decode( (string) $body, true );

		if ( ! is_array( $msg ) ) {
			return self::json( self::error( null, -32700, 'Parse error' ) );
		}

		if ( isset( $msg[0] ) ) {
			return self::json( self::error( null, -32600, 'Invalid Request: batches are not supported' ) );
		}

		$id = array_key_exists( 'id', $msg ) ? $msg['id'] : null;

		if ( null !== $id && ! is_string( $id ) && ! is_int( $id ) && ! is_float( $id ) ) {
			return self::json( self::error( null, -32600, 'Invalid Request: id must be a string or a number' ) );
		}

		if ( ! isset( $msg['jsonrpc'] ) || '2.0' !== $msg['jsonrpc'] || ! isset( $msg['method'] ) || ! is_string( $msg['method'] ) ) {
			return self::json( self::error( $id, -32600, 'Invalid Request' ) );
		}

		if ( null === $id ) {
			return array(
				'status'  => 202,
				'headers' => array(),
				'empty'   => true,
			); // a notification
		}

		$params = ( isset( $msg['params'] ) && is_array( $msg['params'] ) ) ? $msg['params'] : array();

		switch ( $msg['method'] ) {
			case 'initialize':
				return self::json( self::result( $id, self::initialize( $headers ) ) );
			case 'ping':
				return self::json( self::result( $id, new stdClass() ) );
			case 'tools/list':
				$gate = self::gate( $id );
				return self::json( true === $gate ? self::tools_list( $id, $params ) : $gate );
			case 'tools/call':
				$gate = self::gate( $id );
				return self::json( true === $gate ? self::call( $id, $params ) : $gate );
			default:
				return self::json( self::error( $id, -32601, 'Method not found' ) );
		}
	}

	/** The module registry, loaded on demand (it loads lazily); false when it is absent or failed to load. */
	private static function registry() {
		if ( ! class_exists( 'FKMCP_Registry', false ) && class_exists( 'FKMCP_Module', false ) ) {
			try {
				FKMCP_Module::load_runtime();
			} catch ( Throwable $e ) {
				return false;
			}
		}

		return class_exists( 'FKMCP_Registry', false );
	}

	/**
	 * FKA's request-level gate (logged in, read access, transport rate limit): true, or an isError result.
	 * Fails closed when the module is there but its registry would not load; open only without the
	 * module at all (the connector's unit tests).
	 */
	private static function gate( $id ) {
		$verdict = self::access();

		return true === $verdict ? true : self::result( $id, self::error_result( $verdict ) );
	}

	/**
	 * The request-level verdict tools/list, tools/call and the ability endpoints share:
	 * true, or a WP_Error whose code the refusal is classed by (ai_disabled,
	 * fkmcp_registry_unavailable, the transport gate's own, fkmcp_forbidden).
	 *
	 * @return true|WP_Error
	 */
	public static function access() {
		if ( ! FKMCP_Connector_Bearer::enabled() ) {
			return new WP_Error( 'ai_disabled', __( 'AI connections are switched off in FunnelKit settings. Tell the user to switch them on under FunnelKit > FunnelKit AI.', 'wp-marketing-automations' ), array( 'status' => 403 ) );
		}

		if ( ! class_exists( 'FKMCP_Module', false ) ) {
			return true;
		}

		if ( ! self::registry() ) {
			return new WP_Error(
				'fkmcp_registry_unavailable',
				__( 'The FunnelKit tools could not be loaded. Try again shortly.', 'wp-marketing-automations' ),
				array(
					'status'       => 500,
					'fkai_message' => 'The FunnelKit tool registry did not load.',
				)
			);
		}

		$verdict = FKMCP_Registry::transport_gate();

		if ( true === $verdict || is_wp_error( $verdict ) ) {
			return $verdict;
		}

		return new WP_Error(
			'fkmcp_forbidden',
			__( "You don't have permission to use these tools.", 'wp-marketing-automations' ),
			array(
				'status'       => 403,
				'fkai_message' => 'The user may not use the FunnelKit tools.',
			)
		);
	}

	private static function initialize( array $headers ) {
		$asked   = isset( $headers['mcp-protocol-version'] ) ? (string) $headers['mcp-protocol-version'] : '';
		$version = 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $asked ) ? $asked : self::PROTOCOL;

		$result = array(
			'protocolVersion' => $version,
			'capabilities'    => array( 'tools' => array( 'listChanged' => false ) ),
			'serverInfo'      => array(
				'name'    => 'FunnelKit',
				'version' => FKMCP_VERSION,
			),
			'instructions'    => self::registry() ? FKMCP_Registry::server_instructions() : '',
		);

		return $result;
	}

	/**
	 * What the FunnelKit AI gateway splits failure rates by, as `_meta.fkai_store`
	 * on every JSON-RPC result (initialize, ping, tools/list, tools/call and the
	 * gate's refusals), so a call is traceable to the code that answered it on
	 * its own: the host plugin, its version, its Pro companion's version and the
	 * license level its Pro tools answer by, plus `module`, the version of the
	 * module copy that booted. The host decides its keys
	 * (FKMCP_Host::store_meta()); nothing when no host is registered, as in the
	 * connector's own tests.
	 */
	private static function with_store_meta( array $result ) {
		$meta = self::store_meta();

		if ( array() !== $meta ) {
			$result['_meta'] = array( 'fkai_store' => $meta );
		}

		return $result;
	}

	/** The `fkai_store` object (see with_store_meta()); the ability endpoints send it as meta.fkai_store. */
	public static function store_meta() {
		if ( ! class_exists( 'FKMCP_Hosts', false ) || array() === FKMCP_Hosts::all() ) {
			return array();
		}

		$host = FKMCP_Hosts::primary();
		$meta = $host ? $host->store_meta() : array();

		if ( ! is_array( $meta ) || array() === $meta ) {
			return array();
		}

		if ( defined( 'FKMCP_VERSION' ) ) {
			$meta['module'] = (string) FKMCP_VERSION;
		}

		return $meta;
	}

	/** Every funnelkit/* ability the current user may run. */
	public static function tools_for_user() {
		$out = array();

		foreach ( wp_get_abilities() as $ability ) {
			if ( 0 === strpos( $ability->get_name(), self::PREFIX ) && true === $ability->check_permissions() ) {
				$out[] = $ability;
			}
		}

		return $out;
	}

	/** One page of tools, stopping under PAGE_BYTES (a real store lists 144 tools ≈ 360 KB); cursor = next offset. */
	private static function tools_list( $id, array $params ) {
		$cursor = array_key_exists( 'cursor', $params ) ? $params['cursor'] : '0';

		if ( ! is_string( $cursor ) || 1 !== preg_match( '/^\d{1,9}$/', $cursor ) ) {
			return self::error( $id, -32602, 'Invalid params: cursor' );
		}

		$all   = self::tools_for_user();
		$total = count( $all );
		$items = array();
		$bytes = 0;

		for ( $i = (int) $cursor; $i < $total; $i++ ) {
			$item = self::tool_item( $all[ $i ] );
			$size = strlen( (string) wp_json_encode( $item ) );

			if ( $items && $bytes + $size > self::PAGE_BYTES ) {
				break;
			}

			$items[] = $item;
			$bytes  += $size;
		}

		$result = array( 'tools' => $items );

		if ( $i < $total ) {
			$result['nextCursor'] = (string) $i;
		}

		return self::result( $id, $result );
	}

	public static function tool_name( $ability_name ) {
		return 'funnelkit-' . substr( (string) $ability_name, strlen( self::PREFIX ) );
	}

	/** The ability behind a tool name: its own name (funnelkit/x), or the MCP spelling with - or _ after "funnelkit". */
	public static function ability_for( $tool_name ) {
		if ( ! is_string( $tool_name ) || 1 !== preg_match( '#^funnelkit[-_/]([a-z0-9_-]{1,64})$#', $tool_name, $m ) ) {
			return null;
		}

		$name = self::PREFIX . $m[1];
		if ( wp_has_ability( $name ) ) {
			return wp_get_ability( $name );
		}

		$alt = self::PREFIX . str_replace( '_', '-', $m[1] );
		return wp_has_ability( $alt ) ? wp_get_ability( $alt ) : null;
	}

	public static function tool_item( $ability ) {
		$item = array(
			'name'        => self::tool_name( $ability->get_name() ),
			'title'       => (string) $ability->get_label(),
			'description' => trim( (string) $ability->get_description() ),
			'inputSchema' => self::object_schema( $ability->get_input_schema() ),
		);

		$output = self::output_schema( $ability );
		if ( null !== $output ) {
			$item['outputSchema'] = $output;
		}

		$annotations = array( 'title' => (string) $ability->get_label() );
		$hints       = array(
			'readonly'    => 'readOnlyHint',
			'destructive' => 'destructiveHint',
			'idempotent'  => 'idempotentHint',
		);
		$meta        = $ability->get_meta_item( 'annotations', array() );

		foreach ( $hints as $key => $hint ) {
			if ( is_array( $meta ) && isset( $meta[ $key ] ) && is_bool( $meta[ $key ] ) ) {
				$annotations[ $hint ] = $meta[ $key ];
			}
		}

		$item['annotations'] = $annotations;

		$license = $ability->get_meta_item( 'fkai_license', null );
		if ( is_array( $license ) ) {
			$item['_meta'] = array( 'fkai_license' => $license );
		}

		return $item;
	}

	/** MCP input schemas must describe an object; anything else is wrapped under "input". */
	public static function object_schema( $schema ) {
		if ( ! is_array( $schema ) || empty( $schema ) ) {
			return self::plain_root( array() );
		}

		if ( ! isset( $schema['type'] ) || 'object' === $schema['type'] ) {
			return self::plain_root( $schema );
		}

		return array(
			'type'       => 'object',
			'properties' => array( 'input' => $schema ),
			'required'   => array( 'input' ),
		);
	}

	/** MCP output schemas: wrap non-objects at {result}. Returns null if the ability has no output schema. */
	public static function output_schema( $ability ) {
		$schema = $ability->get_output_schema();

		if ( ! is_array( $schema ) || empty( $schema ) ) {
			return null;
		}

		if ( ! isset( $schema['type'] ) || 'object' === $schema['type'] ) {
			return self::plain_root( $schema );
		}

		return array(
			'type'       => 'object',
			'properties' => array( 'result' => $schema ),
			'required'   => array( 'result' ),
		);
	}

	/**
	 * A schema root the way Claude and ChatGPT accept it: `type: object` with
	 * `properties` and no composition keyword - one at the root makes a client
	 * drop every tool of the server. Root alternatives (contact_id or email, the
	 * Pro notice) fold into one object: the root's own properties win, then each
	 * branch's in order; their `required` lists go. Nothing is checked less: the
	 * Abilities API still validates input against the ability's own schema.
	 */
	private static function plain_root( array $schema ) {
		foreach ( array( 'anyOf', 'oneOf', 'allOf' ) as $key ) {
			foreach ( ( isset( $schema[ $key ] ) && is_array( $schema[ $key ] ) ) ? $schema[ $key ] : array() as $branch ) {
				if ( is_array( $branch ) && ! empty( $branch['properties'] ) && is_array( $branch['properties'] ) ) {
					$own                  = ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) ? $schema['properties'] : array();
					$schema['properties'] = $own + $branch['properties'];
				}
			}

			unset( $schema[ $key ] );
		}

		unset( $schema['not'], $schema['$ref'] );
		$schema['type'] = 'object';

		if ( ! isset( $schema['properties'] ) || array() === $schema['properties'] ) {
			$schema['properties'] = new stdClass();
		}

		return $schema;
	}

	private static function is_wrapped( $ability ) {
		$schema = $ability->get_input_schema();

		return is_array( $schema ) && isset( $schema['type'] ) && 'object' !== $schema['type'];
	}

	private static function call( $id, array $params ) {
		if ( ! isset( $params['name'] ) || ! is_string( $params['name'] ) ) {
			return self::error( $id, -32602, 'Invalid params: name is required' );
		}

		$args = array_key_exists( 'arguments', $params ) ? $params['arguments'] : array();

		if ( ! is_array( $args ) || self::is_list( $args ) ) {
			return self::error( $id, -32602, 'Invalid params: arguments must be an object' );
		}

		try {
			$ability = self::ability_for( $params['name'] );

			if ( ! $ability ) {
				return self::error( $id, -32601, 'Tool not found' );
			}

			// Withheld, not missing: "not found" would send the agent looking for another tool.
			if ( true !== $ability->check_permissions() ) {
				return self::result( $id, self::error_result( self::withheld( $ability ), $ability->get_name() ) );
			}

			$input = self::is_wrapped( $ability ) ? ( array_key_exists( 'input', $args ) ? $args['input'] : null ) : $args;

			// Core refuses any non-null input on an ability without an input schema.
			if ( ! self::is_wrapped( $ability ) && empty( $args ) && empty( $ability->get_input_schema() ) ) {
				$input = null;
			}
			$value = $ability->execute( $input );
		} catch ( Throwable $e ) {
			// The tool failed, not the protocol: isError, so the gateway gets the cause and the agent can say so.
			$tool = substr( preg_replace( '/[^a-z0-9_-]/i', '', $params['name'] ), 0, 80 );

			return self::result(
				$id,
				self::refusal(
					'internal_error',
					'fkmcp_exception',
					sprintf( 'Unhandled %s in %s.', get_class( $e ), $tool ),
					__( 'The tool failed on this store. Try again; if it keeps failing, tell the user to contact FunnelKit support.', 'wp-marketing-automations' ),
					self::error_data( ! empty( $ability ) ? array( 'ability' => $ability->get_name() ) : array(), 'internal_error' )
				)
			);
		}

		if ( is_wp_error( $value ) ) {
			if ( 'ability_invalid_input' === $value->get_error_code() ) {
				return self::error( $id, -32602, $value->get_error_message() );
			}

			return self::result( $id, self::error_result( $value, $ability->get_name() ) );
		}

		return self::result( $id, self::shape_result( $value ) );
	}

	/**
	 * Why the current user may not run an ability: writes_paused for a write
	 * while no host admits writes (stop and tell the merchant), otherwise
	 * fkmcp_forbidden (stop).
	 *
	 * @return WP_Error
	 */
	public static function withheld( $ability ) {
		$annotations = $ability->get_meta_item( 'annotations', array() );
		$write       = ! ( is_array( $annotations ) && ! empty( $annotations['readonly'] ) );

		if ( $write && class_exists( 'FKMCP_Registry', false ) && ! FKMCP_Registry::writes_allowed() ) {
			return new WP_Error( 'writes_paused', __( 'Changes by AI are paused for this user in FunnelKit settings. Tell the user; do not try another tool to make the change.', 'wp-marketing-automations' ), array( 'status' => 403 ) );
		}

		return new WP_Error( 'fkmcp_forbidden', __( "You don't have permission to use this tool.", 'wp-marketing-automations' ), array( 'status' => 403 ) );
	}

	/**
	 * A refusal the agent can act on: the message as text, and for the FunnelKit
	 * AI gateway - which never reads result text, since it can hold site data -
	 * the error's class, code, an engineers' message and the data (minus the
	 * HTTP status) as structured content. A pack that wants an exact message or
	 * class sets `fkai_message` / `fkai_code` in the error data; both are
	 * consumed here, never exported.
	 */
	public static function error_result( WP_Error $error, $ability_name = '' ) {
		$code    = (string) $error->get_error_code();
		$message = (string) $error->get_error_message();
		$text    = 'ability_invalid_permissions' === $code ? __( "You don't have permission to use this tool.", 'wp-marketing-automations' ) : $message;
		$data    = $error->get_error_data( $code );
		$data    = is_array( $data ) ? $data : array();

		// The relay's next call needs to know which ability refused; a pack's own value wins.
		if ( '' !== (string) $ability_name && ! isset( $data['ability'] ) ) {
			$data['ability'] = (string) $ability_name;
		}

		$class  = isset( $data['fkai_code'] ) && in_array( $data['fkai_code'], self::ERROR_CLASSES, true ) ? $data['fkai_code'] : self::error_class( $code, $message, $data );
		$detail = isset( $data['fkai_message'] ) && is_string( $data['fkai_message'] ) ? $data['fkai_message'] : self::error_detail( $text );

		return self::refusal(
			$class,
			$code,
			$detail,
			$text,
			self::error_data( $data, $class )
		);
	}

	/**
	 * A refusal's `data`: the error's own data, minus the connector's private
	 * keys, always carrying `status` so the gateway's log and the agent can
	 * name the kind of failure. A WP_Error that set no status reports its
	 * class's conventional one (STATUS_OF_CLASS). `fkai_code` / `fkai_message`
	 * are consumed by error_result() and never exported.
	 */
	private static function error_data( array $data, $class ) {
		$status = isset( $data['status'] ) ? (int) $data['status'] : 0;

		if ( $status < 100 ) {
			$status = isset( self::STATUS_OF_CLASS[ $class ] ) ? self::STATUS_OF_CLASS[ $class ] : 500;
		}

		return array( 'status' => $status ) + array_diff_key(
			$data,
			array(
				'status'       => true,
				'fkai_code'    => true,
				'fkai_message' => true,
			)
		);
	}

	/**
	 * An isError result. The text is for the agent. structuredContent.error is
	 * the gateway's contract: code (one of ERROR_CLASSES, so failures count by
	 * cause), reason (the refusing code itself), message (the one field the
	 * gateway exports, as fkai.error_detail - engineers' text only, see
	 * error_detail()) and data.
	 */
	public static function refusal( $class, $reason, $detail, $text, array $data = array() ) {
		return array(
			'content'           => array(
				array(
					'type' => 'text',
					'text' => (string) $text,
				),
			),
			'structuredContent' => array(
				'error' => array(
					'code'    => in_array( $class, self::ERROR_CLASSES, true ) ? $class : 'internal_error',
					'reason'  => (string) $reason,
					'message' => (string) $detail,
					'data'    => array() === $data ? new stdClass() : $data,
				),
			),
			'isError'           => true,
		);
	}

	/**
	 * The gateway's class for a refusal code: the known codes by name, any
	 * other by what its name says (..._not_found, ..._unavailable, ..._failed),
	 * then by the HTTP status the registry gave it, then by who raised it - a
	 * FunnelKit pack refusing without a status refused the input.
	 */
	public static function error_class( $code, $message = '', array $data = array() ) {
		$code = (string) $code;

		if ( isset( self::ERROR_CLASS_OF[ $code ] ) ) {
			return self::ERROR_CLASS_OF[ $code ];
		}

		if ( 'fkmcp_invalid_input' === $code ) {
			return 1 === preg_match( '/\brequired\b|\bis missing\b|^pass\b/i', (string) $message ) ? 'missing_argument' : 'invalid_argument';
		}

		foreach ( self::ERROR_CLASS_BY_SUFFIX as $suffix => $class ) {
			if ( substr( $code, -strlen( $suffix ) ) === $suffix ) {
				return $class;
			}
		}

		$status = isset( $data['status'] ) ? (int) $data['status'] : 0;

		if ( isset( self::ERROR_CLASS_BY_STATUS[ $status ] ) ) {
			return self::ERROR_CLASS_BY_STATUS[ $status ];
		}

		if ( $status >= 500 ) {
			return 'internal_error';
		}

		if ( $status >= 400 || 0 === strpos( $code, 'fkmcp_' ) || 0 === strpos( $code, 'fkai_' ) ) {
			return 'invalid_argument';
		}

		return 'internal_error';
	}

	/**
	 * A refusal's message as the gateway exports it: a short sentence for
	 * engineers, never site data. Tags go; quoted values (a typed country code,
	 * a template slug, a title) become "..."; e-mail addresses and URLs are
	 * blanked; everything after the first colon - the lists of allowed values,
	 * ids and names - is dropped; and the rest is capped at MESSAGE_MAX
	 * characters - counted as characters, not bytes, so a translated message is
	 * not cut short in a multi-byte locale. Digits (ids, counts) stay: the
	 * gateway scrubs those itself.
	 */
	public static function error_detail( $message ) {
		$text = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( (string) $message ) : strip_tags( (string) $message ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- the fallback runs only outside WordPress (the CLI self-checks).
		$text = preg_replace( '/"[^"]*"/u', '"..."', $text );
		$text = preg_replace( '/[^\s"(<]+@[^\s")>]+/u', '[email]', $text );
		$text = preg_replace( '~[a-z][a-z0-9+.-]*://[^\s")>]+~iu', '[url]', $text );
		$text = preg_replace( '/\s+/u', ' ', trim( (string) $text ) );
		$cut  = strpos( $text, ': ' );

		if ( false !== $cut ) {
			$text = substr( $text, 0, $cut );
		}

		$text   = rtrim( $text, " \t;,-" );
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );

		if ( $length > self::MESSAGE_MAX ) {
			$text = rtrim( function_exists( 'mb_substr' ) ? mb_substr( $text, 0, self::MESSAGE_MAX - 1, 'UTF-8' ) : substr( $text, 0, self::MESSAGE_MAX - 1 ) ) . '...';
		}

		return $text;
	}

	/** Tool result: the JSON as text, plus structuredContent as an object. */
	public static function shape_result( $value ) {
		if ( is_array( $value ) ) {
			if ( empty( $value ) ) {
				$structured = new stdClass();
			} elseif ( ! self::is_list( $value ) ) {
				$structured = $value;
			} else {
				$structured = array( 'result' => $value );
			}
		} else {
			$structured = array( 'result' => $value );
		}

		return array(
			'content'           => array(
				array(
					'type' => 'text',
					'text' => wp_json_encode( $value ),
				),
			),
			'structuredContent' => $structured,
			'isError'           => false,
		);
	}

	/** Every result goes out stamped with the store metadata; an empty one stays an object. */
	private static function result( $id, $result ) {
		if ( is_array( $result ) || $result instanceof stdClass ) {
			$result = self::with_store_meta( (array) $result );

			if ( array() === $result ) {
				$result = new stdClass();
			}
		}

		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => $result,
		);
	}

	private static function error( $id, $code, $message ) {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => array(
				'code'    => $code,
				'message' => $message,
			),
		);
	}

	private static function json( array $payload ) {
		return array(
			'status'  => 200,
			'headers' => array( 'Cache-Control' => 'no-store' ),
			'json'    => $payload,
		);
	}
}
