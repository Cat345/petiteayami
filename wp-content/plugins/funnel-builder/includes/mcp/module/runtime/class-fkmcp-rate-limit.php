<?php

defined( 'ABSPATH' ) || exit;

/**
 * Three per-user counters, each over a fixed one-minute window: 240 ability
 * calls/min for writes and 240 for reads, enforced from FKMCP_Log::wrap()
 * (core's Abilities API fires wp_before_execute_ability as a plain action, so
 * there is no filter to short-circuit an execution from), and a wider
 * transport ceiling enforced from FKMCP_Permissions::transport_gate().
 *
 * The transport window is what covers everything that never reaches an ability:
 * initialize, tools/list, resources/*, and malformed JSON-RPC. Those are cheap
 * individually but unmetered without it.
 *
 * Read-only abilities (the `readonly` annotation) count in a bucket of their
 * own, fkmcp_rlr_, beside the writes' fkmcp_rl_: a burst of edits must not
 * stop an agent reading back what it changed. Every refusal carries
 * `retry_after`, the seconds left in its window, which the connector relays
 * to the client with the error's other data, and status 429.
 */
class FKMCP_Rate_Limit {
	use FKMCP_Host_Classes;

	const WINDOW = 60;

	/**
	 * @param string $ability_name
	 *
	 * @return WP_Error|null WP_Error when the caller is over the limit.
	 */
	public static function check( $ability_name ) {
		if ( 0 !== strpos( (string) $ability_name, 'funnelkit/' ) ) {
			return null;
		}

		$key_prefix = self::is_read( $ability_name ) ? 'fkmcp_rlr_' : 'fkmcp_rl_';

		return self::consume( $key_prefix, (int) apply_filters( 'fkmcp_rate_limit', 240, $ability_name ) );
	}

	/**
	 * An ability that cannot be looked up counts as a write.
	 *
	 * @param string $ability_name
	 *
	 * @return bool
	 */
	private static function is_read( $ability_name ) {
		$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( $ability_name ) : null;

		if ( ! is_object( $ability ) || ! method_exists( $ability, 'get_meta_item' ) ) {
			return false;
		}

		$annotations = $ability->get_meta_item( 'annotations', array() );

		return is_array( $annotations ) && ! empty( $annotations['readonly'] );
	}

	/**
	 * Per-request ceiling, counted before any ability runs. Deliberately far
	 * above the ability limit: this is a flood stop, not a quota, and a single
	 * agent turn legitimately makes several protocol calls per tool call.
	 *
	 * @return WP_Error|null WP_Error when the caller is over the limit.
	 */
	public static function check_transport() {
		return self::consume( 'fkmcp_rlt_', (int) apply_filters( 'fkmcp_transport_rate_limit', 2400 ) );
	}

	/**
	 * On a site with a real object cache the counter lives there: one atomic
	 * increment per call and no option row written, where the transient path
	 * costs a read and a write each time.
	 *
	 * The key is named after the clock-aligned window it counts, so the
	 * seconds left need no stored start time, and a counter left without its
	 * TTL - a drop-in whose incr() rewrites the key without one - is abandoned
	 * when its window ends instead of refusing every call after it.
	 *
	 * @param string $key    Counter key.
	 * @param int    $limit  Calls allowed per window.
	 * @param int    $window Window length in seconds.
	 *
	 * @return WP_Error|null
	 */
	private static function consume_cached( $key, $limit, $window ) {
		$group = 'fkmcp';
		$now   = time();
		$key  .= '_' . (int) floor( $now / $window );
		$count = wp_cache_get( $key, $group );

		if ( false === $count ) {
			wp_cache_add( $key, 0, $group, $window );
			$count = 0;
		}

		if ( (int) $count >= $limit ) {
			return self::refusal( $limit, $window - ( $now % $window ) );
		}

		if ( false === wp_cache_incr( $key, 1, $group ) ) {
			wp_cache_set( $key, (int) $count + 1, $group, $window );
		}

		return null;
	}

	/**
	 * The refusal both counters answer with.
	 *
	 * @param int $limit       Calls allowed per window.
	 * @param int $retry_after Seconds until the window resets.
	 *
	 * @return WP_Error
	 */
	private static function refusal( $limit, $retry_after ) {
		$retry_after = max( 1, (int) $retry_after );

		return new WP_Error(
			'fkmcp_rate_limited',
			sprintf(
				/* translators: 1: number of calls allowed per minute, 2: seconds until calls are accepted again. */
				_n(
					'Rate limit reached: %1$d calls per minute. Retry in %2$d second.',
					'Rate limit reached: %1$d calls per minute. Retry in %2$d seconds.',
					$retry_after,
					'funnel-builder'
				),
				$limit,
				$retry_after
			),
			array(
				'status'      => 429,
				'retry_after' => $retry_after,
			)
		);
	}

	/**
	 * ponytail: read-modify-write on a transient, so the increment is not
	 * atomic - concurrent requests can read the same count and overshoot the
	 * limit by roughly the request concurrency. Fine for a flood stop, which
	 * this is. A counter the store refuses to write is the one way it does
	 * fail open - the next call cannot see this one - so that is logged. Swap
	 * to wp_cache_add()/wp_cache_incr() if an exact ceiling ever matters on an
	 * object-cached site.
	 *
	 * @param string $key_prefix Transient key prefix, per counter.
	 * @param int    $limit      Calls allowed per window; <= 0 disables it.
	 *
	 * @return WP_Error|null
	 */
	private static function consume( $key_prefix, $limit ) {
		if ( $limit <= 0 ) {
			return null;
		}

		$window = max( 1, (int) apply_filters( 'fkmcp_rate_limit_window', self::WINDOW, $key_prefix ) );
		$key    = $key_prefix . get_current_user_id();

		if ( wp_using_ext_object_cache() ) {
			return self::consume_cached( $key, $limit, $window );
		}
		$state = get_transient( $key );
		$now   = time();

		if ( ! is_array( $state ) || ! isset( $state['start'], $state['count'] ) || ( $now - (int) $state['start'] ) >= $window ) {
			$state = array(
				'start' => $now,
				'count' => 0,
			);
		}

		if ( $state['count'] >= $limit ) {
			return self::refusal( $limit, (int) $state['start'] + $window - $now );
		}

		++$state['count'];

		if ( ! set_transient( $key, $state, $window ) && class_exists( 'FKMCP_Log' ) ) {
			static::sibling_call( 'Log', 'event', sprintf( 'RATE_LIMIT counter not stored for %s: the window is not being counted', $key_prefix ) );
		}

		return null;
	}
}
