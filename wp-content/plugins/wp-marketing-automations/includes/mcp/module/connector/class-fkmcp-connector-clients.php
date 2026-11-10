<?php
/**
 * The OAuth clients this server accepts: the FunnelKit AI gateway, pinned by
 * client ID and redirect URI. No client document is fetched and no dynamic
 * registration exists, so no other client can obtain a token.
 */

defined( 'ABSPATH' ) || exit;

final class FKMCP_Connector_Clients {

	/**
	 * The FunnelKit AI gateway. Every URL the connector trusts or shows is built
	 * from these, so a gateway move is a one-line change. A grant from a client
	 * no longer listed is dead: refused at use and purged. Live stores trust
	 * production only; test sites add the staging gateway through fkai_clients.
	 */
	const GATEWAY = 'https://app.funnelkit.com';

	/** @return array<string, array{name: string, redirect_uri: string}> */
	public static function all() {
		$clients = array(
			self::GATEWAY . '/mcp/oauth/client.json' => array(
				'name'         => 'FunnelKit AI',
				'redirect_uri' => self::GATEWAY . '/mcp/oauth/store-callback',
			),
		);

		/**
		 * Filters the pinned clients. Server-side only; no request input reaches it.
		 *
		 * @param array $clients client_id => { name, redirect_uri }.
		 */
		$filtered = apply_filters( 'fkai_clients', $clients );

		return is_array( $filtered ) ? $filtered : $clients;
	}

	/** The client for an exact client_id, with 'client_id' added, or null. */
	public static function get( $client_id ) {
		$all = self::all();

		if ( ! is_string( $client_id ) || ! isset( $all[ $client_id ] ) ) {
			return null;
		}

		return $all[ $client_id ] + array( 'client_id' => $client_id );
	}

	/** The host the consent screen names, taken from the client ID URL. */
	public static function host( $client_id ) {
		return (string) wp_parse_url( (string) $client_id, PHP_URL_HOST );
	}
}
