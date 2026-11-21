<?php
/** Request_Context: request fields handed to a handler without writing superglobals. */
namespace WFFN\Bundles\Includes\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lets an integration hand this plugin's request readers fields it supplies.
 *
 * Fields given to run() replace the request entirely. Values given to set() or
 * forget() lie over it, for request() readers only, until forgotten.
 */
class Request_Context {

	/**
	 * Supplied fields, unslashed, or null when the real request applies.
	 *
	 * @var array|null
	 */
	private static $fields = null;

	/**
	 * Values laid over the request; null hides the request's own.
	 *
	 * @var array
	 */
	private static $overlay = array();

	/**
	 * Run a handler against the given fields instead of the real request.
	 *
	 * @param array    $fields  Field name => unslashed value.
	 * @param callable $handler Handler to run; it may exit.
	 * @return void
	 */
	public static function run( array $fields, callable $handler ) {
		$previous     = self::$fields;
		self::$fields = $fields;
		try {
			call_user_func( $handler );
		} finally {
			self::$fields = $previous;
		}
	}

	/**
	 * Lay a value over a request field.
	 *
	 * @param string $key   Field name.
	 * @param mixed  $value Unslashed value.
	 * @return void
	 */
	public static function set( $key, $value ) {
		self::$overlay[ $key ] = $value;
	}

	/**
	 * Hide request fields, whether laid over or posted.
	 *
	 * @param string ...$keys Field names.
	 * @return void
	 */
	public static function forget( ...$keys ) {
		foreach ( $keys as $key ) {
			self::$overlay[ $key ] = null;
		}
	}

	/**
	 * Run a callback with request fields hidden, then put back what they held.
	 *
	 * @param string[] $keys     Field names.
	 * @param callable $callback Callback to run.
	 * @return void
	 */
	public static function without( array $keys, callable $callback ) {
		$held = array();
		foreach ( $keys as $key ) {
			$held[ $key ] = array_key_exists( $key, self::$overlay ) ? array( self::$overlay[ $key ] ) : null;
		}
		self::forget( ...$keys );
		try {
			call_user_func( $callback );
		} finally {
			foreach ( $held as $key => $was ) {
				if ( null === $was ) {
					unset( self::$overlay[ $key ] );
				} else {
					self::$overlay[ $key ] = $was[0];
				}
			}
		}
	}

	/**
	 * Whether supplied fields are standing in for the request.
	 *
	 * @return bool
	 */
	public static function active() {
		return null !== self::$fields;
	}

	/**
	 * A POST field, unslashed and unsanitized, or null when absent.
	 *
	 * @param string $key Field name.
	 * @return mixed
	 */
	public static function post( $key ) {
		if ( null !== self::$fields ) {
			return self::$fields[ $key ] ?? null;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingNonceCheck,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- raw accessor; every caller verifies the nonce or documents why none applies, and sanitizes the value.
		return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : null;
	}

	/**
	 * A request field (GET or POST), unslashed and unsanitized, or null when absent.
	 *
	 * @param string $key Field name.
	 * @return mixed
	 */
	public static function request( $key ) {
		if ( array_key_exists( $key, self::$overlay ) ) {
			return self::$overlay[ $key ];
		}
		if ( null !== self::$fields ) {
			return self::$fields[ $key ] ?? null;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingNonceCheck,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- raw accessor; every caller verifies the nonce or documents why none applies, and sanitizes the value.
		return isset( $_REQUEST[ $key ] ) ? wp_unslash( $_REQUEST[ $key ] ) : null;
	}

	/**
	 * A GET value as the request carries it, unslashed and unsanitized, or null when absent.
	 *
	 * Unlike request(), neither supplied fields nor the overlay apply: this is the
	 * real query string. For reads that only route or detect a screen; the caller
	 * sanitizes the value.
	 *
	 * @param string $key Parameter name.
	 * @return mixed
	 */
	public static function raw_get( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingNonceCheck,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- routing and context tests only; nothing is saved and the caller sanitizes the value.
		return isset( $_GET[ $key ] ) ? wp_unslash( $_GET[ $key ] ) : null;
	}

	/**
	 * A POST value as the request carries it, unslashed and unsanitized, or null when absent.
	 *
	 * Unlike post(), supplied fields do not apply. For reads where the plugin that owns
	 * the request has already verified the nonce, or that only route; the caller
	 * sanitizes the value.
	 *
	 * @param string $key Field name.
	 * @return mixed
	 */
	public static function raw_post( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingNonceCheck,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- the owning plugin verifies the nonce, or the read only routes; the caller sanitizes the value.
		return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : null;
	}

	/**
	 * A GET or POST value as the request carries it, unslashed and unsanitized, or null when absent.
	 *
	 * Unlike request(), neither supplied fields nor the overlay apply. For routing and
	 * context tests only; the caller sanitizes the value.
	 *
	 * @param string $key Field name.
	 * @return mixed
	 */
	public static function raw_request( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingNonceCheck,FunnelBuilder.CodeAnalysis.FunnelBuilderSpecific.MissingCapabilityCheck -- routing and context tests only; the owning plugin or handler checks its own nonce, and the caller sanitizes the value.
		return isset( $_REQUEST[ $key ] ) ? wp_unslash( $_REQUEST[ $key ] ) : null;
	}

	/**
	 * A request field as sanitized text, or '' when absent or not scalar.
	 *
	 * @param string $key Field name.
	 * @return string
	 */
	public static function text( $key ) {
		$value = self::request( $key );
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}
}
