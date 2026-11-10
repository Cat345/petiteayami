<?php
/**
 * Grants: one row per connection. Tokens are random, carried only in the token
 * response, and stored as SHA-256 hashes. Refresh rotation is one conditional
 * UPDATE; the previous refresh token stays valid for GRACE seconds so a lost
 * response does not disconnect the merchant (spec 6.4).
 */

defined( 'ABSPATH' ) || exit;

final class FKMCP_Connector_Grants {
	const ACCESS_TTL  = 3600;
	const REFRESH_TTL = 2592000;
	const GRACE       = 60;
	const DB_VERSION  = 1;
	const TOUCH_EVERY = 60; // seconds between last_used_at writes for one grant

	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 'fkai_grants';
	}

	/** dbDelta-shaped schema: two spaces after PRIMARY KEY, one column per line. */
	public static function schema() {
		global $wpdb;

		return 'CREATE TABLE ' . self::table() . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  client_id varchar(255) NOT NULL,
  access_hash char(64) NOT NULL,
  access_expires_at bigint(20) unsigned NOT NULL,
  refresh_hash char(64) NOT NULL,
  prev_refresh_hash char(64) NULL,
  rotated_at bigint(20) unsigned NOT NULL,
  refresh_expires_at bigint(20) unsigned NOT NULL,
  created_at bigint(20) unsigned NOT NULL,
  last_used_at bigint(20) unsigned NULL,
  account_email varchar(254) NULL,
  scopes varchar(64) NOT NULL DEFAULT 'mcp',
  PRIMARY KEY  (id),
  UNIQUE KEY access_hash (access_hash),
  UNIQUE KEY refresh_hash (refresh_hash),
  KEY prev_refresh_hash (prev_refresh_hash),
  KEY user_id (user_id)
) " . $wpdb->get_charset_collate() . ';';
	}

	public static function install() {
		if ( (int) get_option( 'fkai_db_version', 0 ) >= self::DB_VERSION ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( self::schema() );
		update_option( 'fkai_db_version', self::DB_VERSION, true );
	}

	public static function hash( $token ) {
		return hash( 'sha256', (string) $token );
	}

	private static function mint( $prefix ) {
		return $prefix . rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
	}

	private static function pair( $access, $refresh ) {
		return array(
			'access_token'  => $access,
			'token_type'    => 'Bearer',
			'expires_in'    => self::ACCESS_TTL,
			'refresh_token' => $refresh,
			'scope'         => 'mcp',
		);
	}

	/** New grant for a user; returns the token pair, or false on DB failure. $account_email is display-only (typed on FunnelKit AI, never verified). */
	public static function create( $user_id, $client_id, $now, $account_email = '' ) {
		global $wpdb;

		$access  = self::mint( 'fka_' );
		$refresh = self::mint( 'fkr_' );

		$n = $wpdb->insert(
			self::table(),
			array(
				'user_id'            => (int) $user_id,
				'client_id'          => (string) $client_id,
				'access_hash'        => self::hash( $access ),
				'access_expires_at'  => (int) $now + self::ACCESS_TTL,
				'refresh_hash'       => self::hash( $refresh ),
				'prev_refresh_hash'  => null,
				'rotated_at'         => (int) $now,
				'refresh_expires_at' => (int) $now + self::REFRESH_TTL,
				'created_at'         => (int) $now,
				'last_used_at'       => null,
				'account_email'      => '' === (string) $account_email ? null : (string) $account_email,
				'scopes'             => 'mcp',
			)
		);

		if ( false === $n ) {
			return false;
		}

		self::log( sprintf( 'CONNECTOR_GRANT_CREATED id=%d user=%d client=%s', (int) $wpdb->insert_id, (int) $user_id, (string) wp_parse_url( (string) $client_id, PHP_URL_HOST ) ) );

		return self::pair( $access, $refresh );
	}

	/** The row behind a live access token: null when unknown or expired, false on a DB error. */
	public static function by_access( $token, $now ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE access_hash = %s', self::table(), self::hash( $token ) ) );

		if ( ! $row && '' !== (string) $wpdb->last_error ) {
			return false;
		}

		return ( $row && (int) $row->access_expires_at > (int) $now && null !== FKMCP_Connector_Clients::get( $row->client_id ) ) ? $row : null;
	}

	public static function touch( $id, $now, $last_used_at = null ) {
		if ( null !== $last_used_at && (int) $now - (int) $last_used_at < self::TOUCH_EVERY ) {
			return;
		}

		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET last_used_at = %d WHERE id = %d', self::table(), (int) $now, (int) $id ) );
	}

	/**
	 * Rotate on a refresh token. Returns the new pair, null for invalid_grant (unknown, expired, reused),
	 * or false on temporary DB failure.
	 */
	public static function refresh( $token, $now, $client_id ) {
		global $wpdb;

		$t   = self::table();
		$h   = self::hash( $token );
		$now = (int) $now;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE refresh_hash = %s OR prev_refresh_hash = %s', $t, $h, $h ) );

		if ( ! $row ) {
			return '' !== (string) $wpdb->last_error ? false : null;
		}

		// Bound to the client that obtained it (RFC 6749 section 6); refused, not revoked.
		if ( ! hash_equals( (string) $row->client_id, (string) $client_id ) ) {
			return null;
		}

		if ( (int) $row->refresh_expires_at <= $now ) {
			self::revoke( (int) $row->id, 'expired' );

			return null;
		}

		// P1-13: only administrators keep access, whatever changed their capabilities.
		if ( ! user_can( (int) $row->user_id, 'manage_options' ) ) {
			self::revoke( (int) $row->id, 'capability' );

			return null;
		}

		if ( hash_equals( (string) $row->refresh_hash, $h ) ) {
			$access  = self::mint( 'fka_' );
			$refresh = self::mint( 'fkr_' );
			$n       = $wpdb->query(
				$wpdb->prepare(
					'UPDATE %i SET access_hash = %s, access_expires_at = %d, refresh_hash = %s, prev_refresh_hash = %s, rotated_at = %d, refresh_expires_at = %d WHERE id = %d AND refresh_hash = %s',
					$t,
					self::hash( $access ),
					$now + self::ACCESS_TTL,
					self::hash( $refresh ),
					$h,
					$now,
					$now + self::REFRESH_TTL,
					(int) $row->id,
					$h
				)
			);

			if ( false === $n ) {
				return false;
			}

			if ( 1 === (int) $n ) {
				return self::pair( $access, $refresh );
			}

			// A concurrent refresh rotated first: re-read and fall into the grace path.
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $t, (int) $row->id ) );

			if ( ! $row ) {
				return '' !== (string) $wpdb->last_error ? false : null;
			}
		}

		if ( null !== $row->prev_refresh_hash && hash_equals( (string) $row->prev_refresh_hash, $h ) && $now - (int) $row->rotated_at <= self::GRACE ) {
			$access  = self::mint( 'fka_' );
			$refresh = self::mint( 'fkr_' );
			// prev_refresh_hash and rotated_at stay as they are, so the window never extends.
			$n = $wpdb->query(
				$wpdb->prepare(
					'UPDATE %i SET access_hash = %s, access_expires_at = %d, refresh_hash = %s WHERE id = %d AND prev_refresh_hash = %s AND rotated_at >= %d',
					$t,
					self::hash( $access ),
					$now + self::ACCESS_TTL,
					self::hash( $refresh ),
					(int) $row->id,
					$h,
					$now - self::GRACE
				)
			);

			if ( false === $n ) {
				return false;
			}

			if ( 1 === (int) $n ) {
				return self::pair( $access, $refresh );
			}
		}

		// Reuse of an old token: the whole grant is revoked (OAuth 2.1 §4.3.1).
		self::revoke( (int) $row->id, 'reuse' );

		return null;
	}

	/** /oauth/revoke: the grant behind any of its tokens. The audit line carries the row count, never the token. */
	public static function revoke_token( $token ) {
		global $wpdb;

		$t    = self::table();
		$h    = self::hash( $token );
		$rows = $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE access_hash = %s OR refresh_hash = %s OR prev_refresh_hash = %s', $t, $h, $h, $h ) );
		self::log( sprintf( 'CONNECTOR_TOKEN_REVOKED rows=%d', (int) $rows ) );
	}

	/**
	 * @param int    $id     Grant row.
	 * @param string $reason admin (disconnected on the settings screen), expired (refresh past its lifetime), reuse (an old refresh token came back) or capability (the user is not an administrator any more).
	 */
	public static function revoke( $id, $reason = 'admin' ) {
		global $wpdb;

		$wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
		self::log( sprintf( 'CONNECTOR_GRANT_REVOKED id=%d reason=%s', (int) $id, (string) $reason ) );
	}

	/**
	 * Ends a grant only when it is this administrator's own: one administrator
	 * never ends another's connection, and a guessed id deletes nothing. Owner
	 * and delete are one statement, so nothing can change between the two.
	 *
	 * @param int $id      Grant row.
	 * @param int $user_id The administrator the row must belong to.
	 *
	 * @return bool Whether a connection ended.
	 */
	public static function revoke_own( $id, $user_id ) {
		global $wpdb;

		$rows = $wpdb->delete(
			self::table(),
			array(
				'id'      => (int) $id,
				'user_id' => (int) $user_id,
			),
			array( '%d', '%d' )
		);

		if ( 1 !== (int) $rows ) {
			return false;
		}

		self::log( sprintf( 'CONNECTOR_GRANT_REVOKED id=%d reason=admin user=%d', (int) $id, (int) $user_id ) );

		return true;
	}

	public static function revoke_user( $user_id ) {
		global $wpdb;

		$wpdb->delete( self::table(), array( 'user_id' => (int) $user_id ), array( '%d' ) );
		self::log( sprintf( 'CONNECTOR_USER_REVOKED user=%d', (int) $user_id ) );
	}

	public static function revoke_all() {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', self::table() ) );
		self::log( 'CONNECTOR_ALL_REVOKED' );
	}

	/** Audit line to the module's host log; never carries a token. */
	private static function log( $line ) {
		if ( class_exists( 'FKMCP_Hosts', false ) && is_object( FKMCP_Hosts::primary() ) ) {
			FKMCP_Hosts::primary()->log( $line );
		}
	}

	/** Deletes grants past their refresh lifetime, and grants from a client no longer trusted. */
	public static function purge_expired( $now ) {
		global $wpdb;

		$clients = array_keys( FKMCP_Connector_Clients::all() );

		if ( empty( $clients ) ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE refresh_expires_at <= %d', self::table(), (int) $now ) );

			return;
		}

		$in = implode( ', ', array_fill( 0, count( $clients ), '%s' ) );

		$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE refresh_expires_at <= %d OR client_id NOT IN ( $in )", array_merge( array( self::table(), (int) $now ), $clients ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $in is one %s per client, passed in the array.
	}

	/**
	 * Every row, newest first. Site-wide maintenance only: a connections list
	 * belongs to one administrator, and reads self::for_user() instead.
	 */
	public static function all() {
		global $wpdb;

		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, user_id, client_id, created_at, last_used_at, refresh_expires_at, account_email, scopes FROM %i ORDER BY created_at DESC, id DESC', self::table() ) );
	}

	/** One administrator's rows, newest first: a connection belongs to whoever approved it. */
	public static function for_user( $user_id ) {
		global $wpdb;

		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, user_id, client_id, created_at, last_used_at, refresh_expires_at, account_email, scopes FROM %i WHERE user_id = %d ORDER BY created_at DESC, id DESC', self::table(), (int) $user_id ) );
	}
}
