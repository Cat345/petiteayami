<?php

defined( 'ABSPATH' ) || exit;

/**
 * Status provider for the FunnelKit AI settings tab.
 */
class WFFN_MCP_Admin {
	public static function endpoint() {
		return class_exists( 'FKMCP_Connector_Urls' ) ? FKMCP_Connector_Urls::resource() : rest_url( 'funnelkit/mcp' );
	}

	public static function status() {
		$lite = 0;
		$pro  = 0;

		if ( class_exists( 'FKMCP_Registry' ) ) {
			// Definitions, not registered abilities: with the switch off nothing registers, and the screen still counts the tools on offer.
			foreach ( FKMCP_Registry::packs() as $pack ) {
				if ( ! FKMCP_Pack::is_compatible( $pack ) || ! $pack->is_available() ) {
					continue;
				}
				$n = count( (array) $pack->definitions() );
				if ( $pack instanceof FKMCP_Pack && $pack->is_pro() ) {
					$pro += $n;
				} else {
					$lite += $n;
				}
			}
		}

		$pro_active = defined( 'WFFN_PRO_VERSION' );
		$host       = class_exists( 'FKMCP_Hosts' ) ? FKMCP_Hosts::get( 'funnel-builder' ) : null;

		return array(
			'abilities_api' => function_exists( 'wp_register_ability' ),
			'wp_version'    => get_bloginfo( 'version' ),
			'pro_active'    => $pro_active,
			'pro_ok'        => $pro_active && $host instanceof FKMCP_Host && $host->pro_ok(),
			'enabled'       => class_exists( 'FKMCP_Connector' ) && FKMCP_Connector::enabled(),
			'https'         => is_ssl() || ( defined( 'FKAI_ALLOW_HTTP' ) && FKAI_ALLOW_HTTP ),
			'endpoint'      => self::endpoint(),
			'tools'         => $lite + $pro,
			'tools_by_pack' => array(
				'lite' => $lite,
				'pro'  => $pro,
			),
			'is_local_dev'  => self::is_local_dev(),
		);
	}

	/**
	 * Only explicit signals count. This answer turns certificate verification
	 * off for the connection test, so it must never be a guess. The old DNS
	 * probe called a site local whenever its own hostname resolved to a
	 * private IP, which is the normal arrangement on cPanel/Plesk hosts and
	 * inside Docker/k8s, i.e. on production.
	 */
	private static function is_local_dev() {
		if ( function_exists( 'wp_get_environment_type' )
			&& in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
			return true;
		}

		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
			return true;
		}

		foreach ( array( '.test', '.local', '.localhost', '.lab' ) as $tld ) {
			if ( substr( $host, -strlen( $tld ) ) === $tld ) {
				return true;
			}
		}

		return (bool) apply_filters( 'fkmcp_is_local_dev', false, $host );
	}
}
