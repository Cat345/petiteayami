<?php
/**
 * Every URL the connector derives from the site: the MCP endpoint (the OAuth
 * resource), the issuer, the OAuth endpoints and the two metadata documents.
 * All of them come from rest_url(), so pretty permalinks, a renamed REST
 * prefix, subfolder and multisite installs and plain permalinks all work.
 */

defined( 'ABSPATH' ) || exit;

final class FKMCP_Connector_Urls {
	const NS = 'funnelkit';

	const NETWORK_ID_OPTION = 'fkai_network_id';

	/** The MCP endpoint: the protected resource tokens are bound to. */
	public static function resource() {
		return rest_url( self::NS . '/mcp' );
	}

	/** Whether a URL names the MCP endpoint, ignoring a trailing slash. */
	public static function resource_matches( $url ) {
		$url = untrailingslashit( (string) $url );

		return '' !== $url && hash_equals( untrailingslashit( self::resource() ), $url );
	}

	public static function rest_root() {
		return rest_url( '/' );
	}

	/** Plain permalinks: REST lives at ?rest_route= and the issuer can't carry a query. */
	public static function is_query_style() {
		return false !== strpos( self::rest_root(), 'rest_route=' );
	}

	/**
	 * RFC 8414 issuer. Path-style roots: inside the REST root. Query-style roots:
	 * the REST root's origin and path (https://site/index.php), no query, no slash.
	 */
	public static function issuer() {
		if ( ! self::is_query_style() ) {
			return untrailingslashit( rest_url( self::NS ) );
		}

		$p      = wp_parse_url( self::rest_root() );
		$origin = $p['scheme'] . '://' . $p['host'] . ( isset( $p['port'] ) ? ':' . $p['port'] : '' );

		return $origin . untrailingslashit( isset( $p['path'] ) ? $p['path'] : '' );
	}

	public static function endpoint( $name ) {
		return rest_url( self::NS . '/oauth/' . $name );
	}

	public static function prm_url() {
		return rest_url( self::NS . '/.well-known/oauth-protected-resource' );
	}

	public static function as_url() {
		return rest_url( self::NS . '/.well-known/oauth-authorization-server' );
	}

	/**
	 * The REST route of the current request ('funnelkit/mcp'), or '' when it is
	 * not one. Usable before WP_REST_Request exists (determine_current_user).
	 */
	public static function current_route( array $server, array $get, array $post = array() ) {
		// Core's order: POST rest_route, GET rest_route, then the pretty path.
		if ( isset( $post['rest_route'] ) && is_string( $post['rest_route'] ) ) {
			return trim( $post['rest_route'], '/' );
		}

		if ( isset( $get['rest_route'] ) && is_string( $get['rest_route'] ) ) {
			return trim( $get['rest_route'], '/' );
		}

		$uri  = isset( $server['REQUEST_URI'] ) ? (string) $server['REQUEST_URI'] : '';
		$path = trim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' );
		$home = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );

		if ( '' !== $home ) {
			if ( 0 !== strpos( $path, $home . '/' ) ) {
				return '';
			}
			$path = substr( $path, strlen( $home ) + 1 );
		}

		if ( 0 === strpos( $path, 'index.php/' ) ) {
			$path = substr( $path, 10 );
		}

		$prefix = trim( rest_get_url_prefix(), '/' ) . '/';

		return 0 === strpos( $path, $prefix ) ? substr( $path, strlen( $prefix ) ) : '';
	}

	/** Only WordPress's front controller serves REST: admin-ajax, wp-login, xmlrpc and friends never do. */
	public static function is_front_controller( array $server ) {
		$script = isset( $server['SCRIPT_FILENAME'] ) ? (string) $server['SCRIPT_FILENAME'] : ( isset( $server['SCRIPT_NAME'] ) ? (string) $server['SCRIPT_NAME'] : '' );

		return 'index.php' === basename( $script );
	}

	public static function is_mcp_request( array $server, array $get, array $post = array() ) {
		return self::is_bearer_route( self::current_route( $server, $get, $post ) );
	}

	/** The routes a bearer token is valid on: the MCP endpoint and the ability list and detail under it. */
	public static function is_bearer_route( $route ) {
		$route = trim( (string) $route, '/' );

		return self::NS . '/mcp' === $route
			|| self::NS . '/mcp/abilities' === $route
			|| 1 === preg_match( '#^' . self::NS . '/mcp/abilities/[a-z0-9][a-z0-9_-]*/[a-z0-9][a-z0-9_-]*$#', $route );
	}

	/** The two routes a browser opens with a login cookie and no REST nonce. */
	public static function is_browser_route( array $server, array $get, array $post = array() ) {
		$route = self::current_route( $server, $get, $post );

		return self::NS . '/oauth/authorize' === $route || self::NS . '/oauth/consent' === $route;
	}

	/**
	 * The store's origin, scheme://host[:port]: site_url(), or the WPML/Polylang default-language
	 * home, by WooFunnels_License_check::get_domain()'s rule (FKA Pro and powerpack). A subsite
	 * answers with the main site's. No license is read here; the gateway looks one up by it.
	 */
	public static function store_origin() {
		$url = ( is_multisite() && ! is_main_site() ) ? get_site_url( get_main_site_id() ) : self::store_domain();

		return self::origin( $url );
	}

	/** get_domain()'s rule; the module cannot call it, Lite runs without either Pro. */
	private static function store_domain() {
		global $sitepress;

		$url = site_url();

		try {
			if ( isset( $sitepress ) && is_object( $sitepress ) ) {
				$url = $sitepress->convert_url( $sitepress->get_wp_api()->get_home_url(), $sitepress->get_default_language() );
			}
		} catch ( Throwable $e ) {
			$url = site_url();
		}

		try {
			if ( function_exists( 'pll_default_language' ) && function_exists( 'pll_home_url' ) ) {
				$url = pll_home_url( pll_default_language() );
			}
		} catch ( Throwable $e ) {
			$url = site_url();
		}

		return $url;
	}

	/** scheme://host[:port] of a URL, or '' when it has no scheme or host. */
	public static function origin( $url ) {
		$p = is_string( $url ) ? wp_parse_url( $url ) : false;

		if ( ! is_array( $p ) || empty( $p['scheme'] ) || empty( $p['host'] ) ) {
			return '';
		}

		return $p['scheme'] . '://' . $p['host'] . ( isset( $p['port'] ) ? ':' . $p['port'] : '' );
	}

	/**
	 * One random id per network, created on first use and never regenerated; '' on a
	 * single site. The gateway checks a subsite publishes the same id as its main site.
	 */
	public static function network_id() {
		if ( ! is_multisite() ) {
			return '';
		}

		$id = get_site_option( self::NETWORK_ID_OPTION, '' );

		if ( '' === $id || false === $id ) {
			try {
				$new = 'n_' . rtrim( strtr( base64_encode( random_bytes( 16 ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- random id, not obfuscation.
			} catch ( Throwable $e ) {
				return '';
			}

			// sitemeta has no unique key: a concurrent first request can insert its own row.
			// Drop this request's cached copy and re-read, so every request publishes the row get_site_option() returns.
			add_site_option( self::NETWORK_ID_OPTION, $new );
			wp_cache_delete( get_current_network_id() . ':' . self::NETWORK_ID_OPTION, 'site-options' );
			$id = get_site_option( self::NETWORK_ID_OPTION, '' );
		}

		return ( is_string( $id ) && 1 === preg_match( '/^n_[A-Za-z0-9_-]{22}$/', $id ) ) ? $id : '';
	}
}
