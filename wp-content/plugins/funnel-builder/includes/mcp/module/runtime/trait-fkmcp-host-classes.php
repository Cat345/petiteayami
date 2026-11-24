<?php

defined( 'ABSPATH' ) || exit;

/**
 * Lets one shared MCP class reach the host's own subclass of another.
 *
 * The shared classes call each other - the permission gate writes to the audit
 * log, the settings screen asks the gate whether writes are on. Written as
 * `FKMCP_Log::event()`, that is a non-forwarding call: it resets late static
 * binding, so the base's seams answer instead of the host's, and the line goes
 * to error_log() rather than the host's log store while the settings screen
 * reports a permission the host never granted.
 *
 * A host subclasses the shared classes under one prefix of its own, so naming
 * that prefix once per subclass is enough to find all of its siblings.
 */
trait FKMCP_Host_Classes {
	/**
	 * Host seam: the prefix this host's MCP subclasses share, e.g. `WFFN_MCP_`
	 * for Funnel Builder's `WFFN_MCP_Log`. The base has no host, so it answers
	 * with its own prefix and resolves to itself.
	 *
	 * @return string
	 */
	protected static function host_prefix() {
		return 'FKMCP_';
	}

	/**
	 * This host's subclass of one shared MCP class, or the shared class itself
	 * when the host does not subclass it.
	 *
	 * @param string $name Shared class name without the `FKMCP_` prefix, e.g. `Log`.
	 *
	 * @return string
	 */
	protected static function sibling( $name ) {
		$class = static::host_prefix() . $name;

		return class_exists( $class ) ? $class : 'FKMCP_' . $name;
	}

	/**
	 * Calls a static method on this host's subclass of a shared class.
	 *
	 * @param string $name   Shared class name without the `FKMCP_` prefix.
	 * @param string $method Static method to call on it.
	 * @param mixed  ...$args
	 *
	 * @return mixed
	 */
	protected static function sibling_call( $name, $method, ...$args ) {
		return call_user_func_array( array( static::sibling( $name ), $method ), $args );
	}
}
