<?php
/**
 * The two discovery documents the gateway reads: RFC 9728 protected resource
 * metadata (named by the MCP 401) and RFC 8414 authorization server metadata
 * (always at the REST route under /funnelkit/, never the site root).
 */

defined( 'ABSPATH' ) || exit;

final class FKMCP_Connector_Discovery {
	/** The ability discovery contract the endpoints speak (FKMCP_Connector_Abilities). */
	const ABILITIES_API_VERSION = 1;

	public static function protected_resource() {
		$doc = array(
			'resource'                 => FKMCP_Connector_Urls::resource(),
			'authorization_servers'    => array( FKMCP_Connector_Urls::issuer() ),
			'scopes_supported'         => array( 'mcp' ),
			'bearer_methods_supported' => array( 'header' ),
			// The gateway connects only to marked metadata, never to an old FKA's own MCP server on the same route.
			'fkai_connector'           => 1,
			// The relay derives /funnelkit/mcp/abilities from the REST root itself; no URL here, so a token never follows one.
			'fkai_abilities'           => array( 'api_version' => self::ABILITIES_API_VERSION ),
		);

		// What the gateway looks the store's license up by; it decides the license, never the plugin.
		$origin = FKMCP_Connector_Urls::store_origin();
		if ( '' !== $origin ) {
			$doc['fkai_url'] = $origin;
		}

		$network = FKMCP_Connector_Urls::network_id();
		if ( '' !== $network ) {
			$doc['fkai_network_id'] = $network;
		}

		return $doc;
	}

	public static function server_metadata() {
		return array(
			'issuer'                                     => FKMCP_Connector_Urls::issuer(),
			'authorization_endpoint'                     => FKMCP_Connector_Urls::endpoint( 'authorize' ),
			'token_endpoint'                             => FKMCP_Connector_Urls::endpoint( 'token' ),
			'revocation_endpoint'                        => FKMCP_Connector_Urls::endpoint( 'revoke' ),
			'response_types_supported'                   => array( 'code' ),
			'response_modes_supported'                   => array( 'query' ),
			'grant_types_supported'                      => array( 'authorization_code', 'refresh_token' ),
			'code_challenge_methods_supported'           => array( 'S256' ),
			'token_endpoint_auth_methods_supported'      => array( 'none' ),
			'revocation_endpoint_auth_methods_supported' => array( 'none' ),
			'scopes_supported'                           => array( 'mcp' ),
			'client_id_metadata_document_supported'      => true,
			'authorization_response_iss_parameter_supported' => true,
		);
	}

	public static function response( array $doc ) {
		return array(
			'status'  => 200,
			'headers' => array( 'Cache-Control' => 'no-store' ),
			'json'    => $doc,
		);
	}
}
