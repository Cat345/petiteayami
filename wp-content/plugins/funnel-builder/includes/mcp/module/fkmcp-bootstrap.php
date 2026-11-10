<?php

defined( 'ABSPATH' ) || exit;

/**
 * Shared-module loader: each FunnelKit plugin bundling the module registers a
 * candidate at file load; the highest version boots on plugins_loaded:0 and
 * every candidate's host adapter is registered with it, so the module knows
 * all the FunnelKit plugins on the site whichever copy won.
 * Function-guarded so every bundled copy of this file is requireable.
 */
if ( ! function_exists( 'fkmcp_register_module' ) ) {
	/**
	 * @param string $version Module version bundled by this plugin.
	 * @param string $path    Directory of the bundled module copy.
	 * @param array  $host    { file: path of the host adapter class file, class: FKMCP_Host subclass, namespace: the host's settings REST namespace }.
	 */
	function fkmcp_register_module( $version, $path, array $host ) {
		// Recorded even when late: FKMCP_Module::needs_abilities() reads every
		// host's settings namespace from here. Arbitration has already run by
		// then, so the late copy still cannot win.
		$GLOBALS['fkmcp_module_candidates'][] = array(
			'version' => (string) $version,
			'path'    => untrailingslashit( (string) $path ),
			'host'    => $host,
		);

		if ( class_exists( 'FKMCP_Module' ) ) {
			fkmcp_register_late_host( $host );
		}
	}

	/**
	 * A host that registered after arbitration had already run - its plugin
	 * loaded later than plugins_loaded:0. Its module copy cannot win any more,
	 * but its packs and settings entries still belong on the screen, so the
	 * adapter is added and registered on the spot.
	 *
	 * @param array $host { file, class, namespace } of the late host.
	 *
	 * @return void
	 */
	function fkmcp_register_late_host( array $host ) {
		$file  = isset( $host['file'] ) ? (string) $host['file'] : '';
		$class = isset( $host['class'] ) ? (string) $host['class'] : '';

		_doing_it_wrong(
			'fkmcp_register_module',
			sprintf( 'Registered after the MCP module booted; %s is added late. Register on plugins_loaded:0 or earlier.', esc_html( '' !== $class ? $class : 'the host' ) ),
			esc_html( defined( 'FKMCP_VERSION' ) ? FKMCP_VERSION : '' )
		);

		if ( '' === $file || '' === $class || ! is_readable( $file ) ) {
			return;
		}

		require_once $file;

		if ( ! class_exists( $class ) || ! is_subclass_of( $class, 'FKMCP_Host' ) ) {
			return;
		}

		$late = new $class();

		FKMCP_Hosts::add( $late );
		$late->register();

		if ( class_exists( 'FKMCP_Registry' ) ) {
			FKMCP_Registry::reset();
		}
	}

	/**
	 * Whether the module has to boot on this request: wp-admin, AJAX, WP-CLI,
	 * the FunnelKit AI connector's REST routes (funnelkit/mcp, funnelkit/oauth/…, funnelkit/.well-known/…) or a host's
	 * settings REST namespace. Everything else - every other REST call and
	 * every front-end page - skips every require.
	 *
	 * @param array $candidates Registered module candidates.
	 *
	 * @return bool
	 */
	function fkmcp_module_should_boot( array $candidates ) {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return true;
		}

		// ?rest_route[]=x arrives as an array: not a route, and casting it would warn.
		$route = ( isset( $_GET['rest_route'] ) && is_string( $_GET['rest_route'] ) ) ? $_GET['rest_route'] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- path comparison only.

		if ( '' === $route ) {
			$route = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- path comparison only.
		}

		if ( '' === $route ) {
			return false;
		}

		$route = strtolower( $route );

		// The connector's routes boot whatever the switch says: discovery answers "AI is off".
		// Exact prefixes: Funnel Builder's own REST namespace is funnelkit-app.
		$path = '/' . ltrim( $route, '/' );
		foreach ( array( '/funnelkit/mcp', '/funnelkit/oauth/', '/funnelkit/.well-known/' ) as $connector_route ) {
			if ( false !== strpos( $path, $connector_route ) ) {
				return true;
			}
		}

		foreach ( $candidates as $candidate ) {
			$namespace = isset( $candidate['host']['namespace'] ) ? strtolower( trim( (string) $candidate['host']['namespace'], '/' ) ) : '';

			if ( '' !== $namespace && false !== strpos( $route, $namespace ) ) {
				return true;
			}
		}

		return false;
	}

	function fkmcp_boot_module() {
		if ( class_exists( 'FKMCP_Module' ) ) {
			return;
		}

		$candidates = isset( $GLOBALS['fkmcp_module_candidates'] ) ? (array) $GLOBALS['fkmcp_module_candidates'] : array();

		if ( ! fkmcp_module_should_boot( $candidates ) ) {
			return;
		}

		usort(
			$candidates,
			static function ( $a, $b ) {
				return version_compare( $b['version'], $a['version'] );
			}
		);

		foreach ( $candidates as $winner ) {
			$file = $winner['path'] . '/class-fkmcp-module.php';

			if ( ! is_readable( $file ) ) {
				continue;
			}

			if ( ! defined( 'FKMCP_VERSION' ) ) {
				define( 'FKMCP_VERSION', $winner['version'] );
			}

			require_once $winner['path'] . '/class-fkmcp-host.php';
			require_once $file;

			foreach ( $candidates as $candidate ) {
				$spec = isset( $candidate['host'] ) ? (array) $candidate['host'] : array();

				if ( empty( $spec['file'] ) || empty( $spec['class'] ) || ! is_readable( $spec['file'] ) ) {
					continue;
				}

				require_once $spec['file'];

				if ( class_exists( $spec['class'] ) && is_subclass_of( $spec['class'], 'FKMCP_Host' ) ) {
					FKMCP_Hosts::add( new $spec['class']() );
				}
			}

			// <plugin>/includes/mcp/module -> plugin directory slug.
			$slug = basename( dirname( $winner['path'], 3 ) );
			FKMCP_Hosts::set_booted( $slug );

			FKMCP_Module::init();

			return;
		}
	}

	add_action( 'plugins_loaded', 'fkmcp_boot_module', 0 );
}
