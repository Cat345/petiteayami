<?php

defined( 'ABSPATH' ) || exit;

/**
 * Two rolling windows per user: 60 ability calls/min, enforced from
 * BWFAN_MCP_Log::wrap() (core's Abilities API fires wp_before_execute_ability as a
 * plain action, so there is no filter to short-circuit an execution from), and
 * a wider transport ceiling enforced from BWFAN_MCP_Permissions::transport_gate().
 *
 * The transport window is what covers everything that never reaches an ability:
 * initialize, tools/list, resources/*, and malformed JSON-RPC. Those are cheap
 * individually but unmetered without it.
 */
class BWFAN_MCP_Rate_Limit {
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

		return self::consume( 'fkmcp_rl_', (int) apply_filters( 'fkmcp_rate_limit', 60, $ability_name ) );
	}

	/**
	 * Per-request ceiling, counted before any ability runs. Deliberately far
	 * above the ability limit: this is a flood stop, not a quota, and a single
	 * agent turn legitimately makes several protocol calls per tool call.
	 *
	 * @return WP_Error|null WP_Error when the caller is over the limit.
	 */
	public static function check_transport() {
		return self::consume( 'fkmcp_rlt_', (int) apply_filters( 'fkmcp_transport_rate_limit', 600 ) );
	}

	/**
	 * On a site with a real object cache the counter lives there: one atomic
	 * increment per call and no option row written, where the transient path
	 * costs a read and a write each time.
	 *
	 * @param string $key    Counter key.
	 * @param int    $limit  Calls allowed per window.
	 * @param int    $window Window length in seconds.
	 *
	 * @return WP_Error|null
	 */
	private static function consume_cached( $key, $limit, $window ) {
		$group = 'fkmcp';
		$count = wp_cache_get( $key, $group );

		if ( false === $count ) {
			wp_cache_add( $key, 0, $group, $window );
			$count = 0;
		}

		if ( (int) $count >= $limit ) {
			return self::refusal( $limit );
		}

		if ( false === wp_cache_incr( $key, 1, $group ) ) {
			wp_cache_set( $key, (int) $count + 1, $group, $window );
		}

		return null;
	}

	/**
	 * The refusal both counters answer with.
	 *
	 * @param int $limit Calls allowed per window.
	 *
	 * @return WP_Error
	 */
	private static function refusal( $limit ) {
		return new WP_Error(
			'fkmcp_rate_limited',
			sprintf(
				/* translators: %d: number of calls allowed per minute. */
				__( 'Rate limit reached: %d calls per minute. Wait before retrying.', 'wp-marketing-automations' ),
				$limit
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

		$window = (int) apply_filters( 'fkmcp_rate_limit_window', self::WINDOW, $key_prefix );
		$key    = $key_prefix . get_current_user_id();

		if ( wp_using_ext_object_cache() ) {
			return self::consume_cached( $key, $limit, $window );
		}
		$state  = get_transient( $key );
		$now    = time();

		if ( ! is_array( $state ) || ! isset( $state['start'], $state['count'] ) || ( $now - (int) $state['start'] ) >= $window ) {
			$state = array(
				'start' => $now,
				'count' => 0,
			);
		}

		if ( $state['count'] >= $limit ) {
			return self::refusal( $limit );
		}

		++$state['count'];

		if ( ! set_transient( $key, $state, $window ) && class_exists( 'BWFAN_MCP_Log' ) ) {
			BWFAN_MCP_Log::event( sprintf( 'RATE_LIMIT counter not stored for %s: the window is not being counted', $key_prefix ) );
		}

		return null;
	}
}
