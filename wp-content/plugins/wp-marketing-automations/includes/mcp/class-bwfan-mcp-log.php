<?php

defined( 'ABSPATH' ) || exit;

/**
 * Logs the handful of events an administrator would want to find later - the
 * server turned on or off, a credential minted or revoked, a client's first
 * connection - and wraps execution so agents get a generic error + correlation
 * id while SQL/paths never cross the wire.
 *
 * Per-invocation auditing is off by default: a line per tool call buries those
 * events on a busy site. Sites that want the full trail turn it on with
 * add_filter( 'fkmcp_audit_invocations', '__return_true' ).
 *
 * Wrapping every callback also makes this the one place every result passes
 * on its way to an agent, so it is where returned text gets flattened - see
 * scrub().
 */
class BWFAN_MCP_Log {
	const LOG_FILE = 'mcp';

	/** Longest string an ability may return per field; longer is truncated. */
	const MAX_TEXT = 500;

	public static function wrap( $ability_name, $callback ) {
		return function ( $input = array() ) use ( $ability_name, $callback ) {
			// Auditing and rate limiting hang off this wrapper rather than a
			// core hook: the Abilities API exposes wp_before_execute_ability
			// only as an action, so nothing there can refuse an execution.
			$limited = BWFAN_MCP_Rate_Limit::check( $ability_name );

			if ( is_wp_error( $limited ) ) {
				self::event( sprintf( 'RATE_LIMITED %s', $ability_name ) );

				return $limited;
			}

			if ( apply_filters( 'fkmcp_audit_invocations', false ) ) {
				self::audit( $ability_name, $input );
			}

			try {
				$result = call_user_func( $callback, $input );

				if ( is_wp_error( $result ) ) {
					return self::sanitize_error( $ability_name, $result, $input );
				}

				// A pack may declare one result raw - an export payload whose
				// long strings are the point - and takes on the guardrail itself.
				return apply_filters( 'fkmcp_scrub_result', true, $ability_name ) ? self::scrub( $result ) : $result;
			} catch ( \Throwable $e ) {
				$error_id = substr( md5( uniqid( 'fkmcp_', true ) ), 0, 12 );

				do_action( 'fkmcp_tool_exception', $e, $ability_name, self::redact( $input ), $error_id );

				self::write(
					sprintf(
						"[%s] EXCEPTION %s\nTool: %s\nException: %s\nMessage: %s\nAt: %s:%d\n%s",
						gmdate( 'c' ),
						$error_id,
						$ability_name,
						get_class( $e ),
						$e->getMessage(),
						$e->getFile(),
						$e->getLine(),
						$e->getTraceAsString()
					)
				);

				return new WP_Error(
					'failed',
					__( 'The tool failed with an internal error. Quote the error id to the site administrator to look it up in the FunnelKit logs.', 'wp-marketing-automations' ),
					array(
						'tool'     => $ability_name,
						'error_id' => $error_id,
					)
				);
			}
		};
	}

	private static function sanitize_error( $ability_name, WP_Error $error, $input = null ) {
		$codes = $error->get_error_codes();

		foreach ( $codes as $code ) {
			if ( 0 !== strpos( (string) $code, 'fkmcp_' ) ) {
				return self::sanitize_foreign_error( $ability_name, $error, $input );
			}
		}

		return $error;
	}

	private static function sanitize_foreign_error( $ability_name, WP_Error $error, $input = null ) {
		$error_id = substr( md5( uniqid( 'fkmcp_', true ) ), 0, 12 );

		do_action( 'fkmcp_tool_error', $error, $ability_name, self::redact( $input ), $error_id );

		self::write(
			sprintf(
				"[%s] FOREIGN_ERROR %s\nTool: %s\nCodes: %s\nMessages: %s\nData: %s",
				gmdate( 'c' ),
				$error_id,
				$ability_name,
				wp_json_encode( $error->get_error_codes() ),
				wp_json_encode(
					array_map(
						function ( $code ) use ( $error ) {
							return $error->get_error_message( $code );
						},
						$error->get_error_codes()
					)
				),
				wp_json_encode(
					array_map(
						function ( $code ) use ( $error ) {
							return $error->get_error_data( $code );
						},
						$error->get_error_codes()
					)
				)
			)
		);

		return new WP_Error(
			'failed',
			__( 'The tool failed with an internal error. Quote the error id to the site administrator to look it up in the FunnelKit logs.', 'wp-marketing-automations' ),
			array(
				'tool'     => $ability_name,
				'error_id' => $error_id,
			)
		);
	}

	/**
	 * One line for an event worth keeping: the server switched on or off, a
	 * credential created or revoked, a client connecting for the first time.
	 * Rare and security-relevant, so it is written whatever the site's logging
	 * setting says - unlike the per-call audit trail.
	 *
	 * @param string $message
	 *
	 * @return void
	 */
	public static function event( $message ) {
		$user_id = get_current_user_id();
		$user    = $user_id ? get_userdata( $user_id ) : null;

		self::write(
			sprintf(
				'[%s] %s user=%d(%s)',
				gmdate( 'c' ),
				$message,
				$user_id,
				$user ? $user->user_login : 'anonymous'
			)
		);
	}

	/** Opt-in per-invocation trail; see fkmcp_audit_invocations. */
	public static function audit( $ability_name, $input = null ) {
		try {
			if ( 0 !== strpos( (string) $ability_name, 'funnelkit/' ) ) {
				return;
			}

			$user_id  = get_current_user_id();
			$redacted = self::redact( $input );

			do_action( 'fkmcp_invocation', $ability_name, $redacted, $user_id );

			$user  = $user_id ? get_userdata( $user_id ) : null;
			$login = $user ? $user->user_login : 'anonymous';

			self::write(
				sprintf(
					'[%s] %s user=%d(%s) args=%s',
					gmdate( 'c' ),
					$ability_name,
					$user_id,
					$login,
					wp_json_encode( $redacted )
				)
			);
		} catch ( \Throwable $e ) {
			// Best-effort: a broken audit trail must never break the tool call
			// that triggered it, but a fully silent failure here would leave no
			// trace to debug from. error_log() does not depend on the plugin logger.
			error_log( 'FunnelKit MCP audit() failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Flattens the text an ability returns before it reaches an agent.
	 *
	 * Much of what these tools report is written by strangers - Contact names
	 * come from an opt-in form, cart billing fields from an anonymous
	 * checkout POST, product titles from whoever may edit products - and an
	 * agent reading a result cannot tell a stored sentence from an
	 * instruction. Take away what an injected line needs to look like
	 * anything but data: newlines and control characters, which is how text
	 * forges a message boundary or a fenced block, and unbounded length.
	 *
	 * ponytail: this neutralises the delivery, not the intent - one short
	 * hostile sentence still arrives intact, and escaping cannot fix that.
	 * The other half is the provenance warning on the tool descriptions,
	 * telling the agent this text is data to report on and never an
	 * instruction to follow.
	 *
	 * @param mixed $value
	 * @param int   $depth
	 *
	 * @return mixed Strings flattened and capped; every other type untouched.
	 */
	private static function scrub( $value, $depth = 0 ) {
		if ( is_string( $value ) ) {
			// Byte-level class, deliberately without /u: UTF-8 continuation
			// bytes all sit above 0x7F, so multibyte text passes through and
			// invalid UTF-8 cannot make preg_replace bail to null.
			$flat = preg_replace( '/[\x00-\x1F\x7F]+/', ' ', $value );

			if ( null !== $flat ) {
				$value = $flat;
			}

			// Ellipsis included in the budget, so the cap is what the constant
			// says and scrubbing an already-scrubbed string is a no-op rather
			// than shaving a character off it.
			if ( mb_strlen( $value ) > self::MAX_TEXT ) {
				$value = mb_substr( $value, 0, self::MAX_TEXT - 1 ) . '…';
			}

			return $value;
		}

		// Result shapes are built by our own packs, never by the caller, so
		// nothing can nest its way past this to dodge the scrub.
		if ( ! is_array( $value ) || $depth >= 8 ) {
			return $value;
		}

		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::scrub( $item, $depth + 1 );
		}

		return $value;
	}

	private static function redact( $input, $depth = 0 ) {
		if ( ! is_array( $input ) ) {
			return $input;
		}

		if ( $depth >= 8 ) {
			return '***depth-capped***';
		}

		$masked = array(
			'password',
			'pass',
			'token',
			'api_key',
			'secret',
			'key',
			'authorization',
			'client_secret',
			'access_token',
			'refresh_token',
			'review_token',
		);

		foreach ( $input as $key => $value ) {
			if ( in_array( strtolower( (string) $key ), $masked, true ) ) {
				$input[ $key ] = '***';
			} elseif ( is_array( $value ) ) {
				$input[ $key ] = self::redact( $value, $depth + 1 );
			}
		}

		return $input;
	}

	/** Appends to the Automations log store (BWFAN_Common::log_test_data, forced on). */
	private static function write( $line ) {
		try {
			if ( ! class_exists( 'BWFAN_Common' ) ) {
				error_log( 'FunnelKit MCP: ' . $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

				return;
			}

			// log_test_data( ..., $force = true ) adds bwfan_before_making_logs
			// => __return_true and never takes it off, which would switch on
			// every other FunnelKit log for the rest of the request. Put it
			// back, unless something else had already asked for it.
			$forced_elsewhere = false !== has_filter( 'bwfan_before_making_logs', '__return_true' );

			BWFAN_Common::log_test_data( $line, self::LOG_FILE, true );

			if ( ! $forced_elsewhere ) {
				remove_filter( 'bwfan_before_making_logs', '__return_true' );
			}
		} catch ( \Throwable $e ) {
			// Best-effort: logging must never break the tool call that
			// triggered it, but silently losing the line entirely leaves no
			// trace to debug from. error_log() does not depend on the plugin logger.
			error_log( 'FunnelKit MCP log write failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
