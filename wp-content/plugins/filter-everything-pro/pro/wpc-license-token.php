<?php
/**
 * Filter Everything PRO — offline license-token verification.
 *
 * connect.filtereverything.pro signs a compact, domain-bound token with an RSA
 * private key (kept only on the server). Here we verify it with the embedded
 * PUBLIC key, so a licensed install keeps working without any network round-trip
 * until the token's own expiry (`texp`). Consequences by design:
 *   - a connect outage never locks a paying customer (their token is still valid);
 *   - blocking/spoofing connect cannot grant indefinite use — it only stops token
 *     refresh, so the clock runs down to `texp` and the site then locks.
 *
 * Verification uses ext-openssl (RSA-2048 / SHA-256). openssl cannot be disabled
 * without breaking HTTPS/WP itself, which removes the "turn off one extension to
 * bypass the check" lever. If openssl is somehow absent, verification returns
 * null and callers FAIL OPEN — we never lock a customer over a missing extension.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * When true, a valid signed token is REQUIRED to be considered licensed. While
 * false (initial rollout), an install that was legitimately activated (has a
 * stored license key) stays licensed even before its token has been fetched, so
 * updating to this version cannot lock an existing customer. Flip to true in a
 * later release, once tokens have propagated to the installed base.
 */
if ( ! defined( 'FLRT_TOKEN_STRICT' ) ) {
    define( 'FLRT_TOKEN_STRICT', false );
}

/** Option that stores the signed token (kept separate from wpc_filter_license). */
if ( ! defined( 'FLRT_LICENSE_TOKEN_OPTION' ) ) {
    define( 'FLRT_LICENSE_TOKEN_OPTION', 'wpc_filter_token' );
}

/**
 * Public keys connect signs tokens with, keyed by `kid`. Two slots let the
 * signing key be rotated without an outage: ship k2 in a release BEFORE connect
 * starts signing with it, then retire k1 later.
 */
function flrt_token_public_keys() {
    $k1 = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEArKboTgwcDtZnVTr397ub
PSHWXa0l/g9JkxMs9u4mGgVvt4YHvyGoDwH9qywFd+Nki9EzkJtMMSCuGYA/9KYZ
zZ7OB8Hdn4OL1gGb+UnOkn+HOWMdDN/iQk+WMiqk+g/+/KHx5grBN5JbQiA73PBh
QC+hxQSbcbwcgw2uocuwpcRQ7CXIKrT7TqXMz+ZW4xKse+x9cgqHnQ4Dep/E+nGB
SD7PbmwMe7BGnHAPFdS/igbfFommKGsjdklRXCZ2rKbOAGN+p9unfT7EERBKdeDM
gpVGrcElS4QIFh9ts39tQk2K0JamVn7PRB0VK7MB8gpWgzwA6TIqCWVm0w4X2biY
NQIDAQAB
-----END PUBLIC KEY-----
PEM;

    return array(
        'k1' => $k1,
    );
}

function flrt_token_b64url_decode( $txt ) {
    return base64_decode( strtr( $txt, '-_', '+/' ) );
}

/**
 * Normalises a URL exactly like connect's Endpoints::clean_url(), so the domain
 * the token is bound to matches what this install computes from home_url().
 * (lowercase, strip protocol, trim slashes — no www/port/path changes.)
 */
function flrt_clean_url( $url ) {
    $url = function_exists( 'mb_strtolower' ) ? mb_strtolower( $url ) : strtolower( $url );
    $url = str_replace( array( 'http://', 'https://' ), '', $url );
    return trim( $url, '/' );
}

/**
 * Verify a license token.
 *
 * @param string $token         the "payload.signature" string
 * @param string $expected_home flrt_clean_url( home_url() )
 * @return array|false|null  payload on success; false when invalid/expired/tampered/
 *                           wrong-domain; null when verification is impossible
 *                           (openssl missing) so the caller can fail open.
 */
function flrt_verify_license_token( $token, $expected_home ) {
    if ( ! is_string( $token ) || $token === '' || strpos( $token, '.' ) === false ) {
        return false;
    }

    if ( ! function_exists( 'openssl_verify' ) ) {
        return null; // cannot verify -> caller fails open, never bricks a paying customer
    }

    list( $signing_input, $sig_b64 ) = explode( '.', $token, 2 );

    $payload = json_decode( flrt_token_b64url_decode( $signing_input ), true );
    if ( ! is_array( $payload ) || ! isset( $payload['kid'], $payload['home'], $payload['texp'] ) ) {
        return false;
    }

    $keys = flrt_token_public_keys();
    if ( ! isset( $keys[ $payload['kid'] ] ) ) {
        return false;
    }

    $ok = openssl_verify(
        $signing_input,
        flrt_token_b64url_decode( $sig_b64 ),
        $keys[ $payload['kid'] ],
        OPENSSL_ALGO_SHA256
    );
    if ( $ok !== 1 ) {
        return false; // bad signature: tampered payload or wrong key
    }

    // Domain binding: a token minted for another site cannot license this one.
    if ( ! hash_equals( (string) $payload['home'], (string) $expected_home ) ) {
        return false;
    }

    // Offline grace window.
    if ( (int) $payload['texp'] <= time() ) {
        return false;
    }

    return $payload;
}

/* ------------------------------------------------------------------ storage */

function flrt_get_stored_token() {
    return (string) get_option( FLRT_LICENSE_TOKEN_OPTION, '' );
}

function flrt_store_token( $token ) {
    if ( ! is_string( $token ) || $token === '' ) {
        return false;
    }
    return update_option( FLRT_LICENSE_TOKEN_OPTION, $token );
}

function flrt_clear_token() {
    return delete_option( FLRT_LICENSE_TOKEN_OPTION );
}

/* ---------------------------------------------------------------- predicate */

/**
 * Current site holds a legitimately shaped license key (3 "|"-separated parts).
 * Used as the rollout-phase fallback before FLRT_TOKEN_STRICT.
 */
function flrt_has_shaped_license_key() {
    $key = flrt_get_license_key();
    if ( ! $key ) {
        return false;
    }
    return count( explode( '|', base64_decode( $key ) ) ) === 3;
}

/**
 * Main site of the network holds a legitimately shaped key. Used for multisite
 * subsites, which inherit the network license from the main site.
 */
function flrt_main_site_has_shaped_key() {
    if ( ! function_exists( 'get_main_site_id' ) ) {
        return false;
    }
    $data = get_blog_option( get_main_site_id(), FLRT_LICENSE_KEY );
    if ( is_array( $data ) && isset( $data['license_key'] ) ) {
        $decoded = maybe_unserialize( base64_decode( $data['license_key'] ) );
        if ( is_array( $decoded ) && ! empty( $decoded['key'] ) ) {
            return count( explode( '|', base64_decode( $decoded['key'] ) ) ) === 3;
        }
    }
    return false;
}

/**
 * The unlock predicate: is this install licensed?
 *
 * - A valid, domain-bound, unexpired signed token -> licensed.
 * - openssl unavailable -> fail open (licensed) so a missing extension never locks.
 * - Otherwise, until FLRT_TOKEN_STRICT, a legitimately shaped stored license key
 *   keeps the install licensed while tokens roll out.
 *
 * Multisite subsites inherit the network license from the main site's key; token
 * domain-binding is per-site, so it is not applied to subsites here.
 *
 * @return bool
 */
function flrt_is_licensed() {
    if ( is_multisite() && ! is_main_site() ) {
        return flrt_main_site_has_shaped_key();
    }

    $token = flrt_get_stored_token();
    if ( $token !== '' ) {
        $payload = flrt_verify_license_token( $token, flrt_clean_url( home_url() ) );
        if ( is_array( $payload ) ) {
            return true;
        }
        if ( $payload === null ) {
            return true; // cannot verify (no openssl) -> fail open, never brick
        }
        // else: token present but invalid/expired -> fall through
    }

    if ( ! FLRT_TOKEN_STRICT ) {
        return flrt_has_shaped_license_key();
    }

    return false;
}
