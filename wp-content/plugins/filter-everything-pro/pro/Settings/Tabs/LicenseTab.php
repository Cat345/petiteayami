<?php


namespace FilterEverything\Filter;

use FilterEverything\Filter\Pro\Api\ApiRequests;

if ( ! defined('ABSPATH') ) {
    exit;
}

class LicenseTab extends BaseSettings
{
    protected $page = 'wpc-filter-admin-license';

    protected $group = 'wpc_filter_license';

    //protected $optionName = 'wpc_filter_license';

    protected $api_link = 'https://api.envato.com/authorization?response_type=code&client_id='.FLRT_ENVATO_APP_CLIENT_ID.'&redirect_uri=https://connect.filtereverything.pro/envato.php';

    public function __construct( $optionName )
    {
        $this->optionName = $optionName;
    }

    public function init()
    {
        add_action( 'admin_init', array($this, 'initSettings') );
        add_action( 'wpc_after_settings_fields_title', array( $this, 'explanationMessage' ) );
        add_action( 'wpc_before_sections_settings_fields', array( $this, 'removeSubmitButton' ) );
        add_filter( 'pre_update_option_wpc_filter_license', [ $this, 'preUpdateLicense' ], 10, 3 );
    }

    public function initSettings()
    {
        register_setting($this->group, $this->optionName);
        /**
         * @see https://developer.wordpress.org/reference/functions/add_settings_field/
         */
        $saved_value = get_option( $this->optionName );
        $field_type  = ( isset( $saved_value['license_key'] ) && $saved_value['license_key'] ) ? 'license' : 'text';

        $settings = array(
            'license' => array(
                'label'  => esc_html__('License Information', 'filter-everything'),
                'fields' => array(
                    'license_key'        => array(
                        'type'      => $field_type,
                        'title'     => esc_html__('License Key', 'filter-everything'),
                        'id'        => 'license_key',
                        'default'   => '',
                        'label'     => ''
                    ),
                )
            )
        );

        $this->registerSettings($settings, $this->page, $this->optionName);
    }

    public function explanationMessage( $page )
    {
        if( $page === $this->page ){
            $saved_value = get_option( $this->optionName );

            if ( isset( $saved_value['license_key'] ) && $saved_value['license_key'] ) {
                $message = esc_html__( 'Everything is fine, you have activated your license.', 'filter-everything' );
            } else {
                $tri = get_option( 'wpc_trident' );

                if ( isset( $tri[ 'first_install' ] ) && ( $tri[ 'first_install' ] + MONTH_IN_SECONDS * 2 ) < time() ) {
                    echo '<div class="wpc-license-lock"><strong>' . esc_html__( 'The plugin is locked.', 'filter-everything' ) . '</strong> '
                        . esc_html__( 'It has been running for over two months without a license key, so Filter Sets and SEO Rules cannot be saved until a key is activated. Filtering on the site keeps working.', 'filter-everything' )
                        . '</div>' . "\r\n";
                }

                $this->purchaseSourceCards();
                return;
            }
            echo '<p>'.$message.'</p>'."\r\n";
        }
    }

    /**
     * "Where did you buy the plugin?" — two cards with the three steps for each
     * purchase source. Replaces the old single paragraph: a quarter of all
     * license tickets were customers pasting their Envato purchase code, or
     * generating the key under the wrong Envato account.
     */
    public function purchaseSourceCards()
    {
        $identity  = self::siteIdentity();
        $allowed   = array( 'strong' => array(), 'code' => array(), 'br' => array() );
        $paste     = wp_kses( __( 'Paste it below and click <strong>Activate License</strong>.', 'filter-everything' ), $allowed );
        ?>
        <div class="wpc-license-sources">
            <h3><?php esc_html_e( 'Where did you buy Filter Everything PRO?', 'filter-everything' ); ?></h3>
            <p class="description"><?php esc_html_e( 'The license key depends on where you bought the plugin. Pick your case and follow the three steps.', 'filter-everything' ); ?></p>
            <div class="wpc-license-cards">
                <div class="wpc-license-card">
                    <h4><?php esc_html_e( 'On CodeCanyon (Envato)', 'filter-everything' ); ?></h4>
                    <p class="wpc-license-card-sub"><?php esc_html_e( 'You have an Envato account and a purchase code from Envato.', 'filter-everything' ); ?></p>
                    <ol>
                        <li><?php echo wp_kses( __( 'Click <strong>Get your License Key</strong> and sign in with the Envato account that made the purchase (for agencies this is often the client’s account).', 'filter-everything' ), $allowed ); ?></li>
                        <li><?php esc_html_e( 'Copy the license key shown next to your purchase.', 'filter-everything' ); ?></li>
                        <li><?php echo $paste; // already escaped ?></li>
                    </ol>
                    <p class="wpc-license-card-warn"><?php echo wp_kses( __( 'Your Envato purchase code (<code>a1b2c3d4-…</code>) is <strong>not</strong> the license key. It is only used on the next page to generate the key.', 'filter-everything' ), $allowed ); ?></p>
                    <a id="wpc-get-license-key" href="<?php echo esc_url( $this->api_link ); ?>" class="button button-primary" target="_blank"><?php esc_html_e( 'Get your License Key', 'filter-everything' ); ?></a>
                </div>
                <div class="wpc-license-card">
                    <h4><?php esc_html_e( 'On filtereverything.pro', 'filter-everything' ); ?></h4>
                    <p class="wpc-license-card-sub"><?php esc_html_e( 'You created an account on our website during checkout.', 'filter-everything' ); ?></p>
                    <ol>
                        <li><?php echo wp_kses( sprintf( __( 'Open <strong>My Account → My licenses</strong> and add this site: <code>%s</code>', 'filter-everything' ), esc_html( $identity ) ), $allowed ); ?></li>
                        <li><?php esc_html_e( 'Copy the key generated for this address.', 'filter-everything' ); ?></li>
                        <li><?php echo $paste; // already escaped ?></li>
                    </ol>
                    <p class="wpc-license-card-warn"><?php echo wp_kses( sprintf( __( 'Register exactly <code>%s</code> — that is how this site identifies itself to the license server.', 'filter-everything' ), esc_html( $identity ) ), $allowed ); ?></p>
                    <a href="https://filtereverything.pro/my-licenses/" class="button" target="_blank"><?php esc_html_e( 'Open My licenses', 'filter-everything' ); ?></a>
                </div>
            </div>
            <p class="description"><?php echo wp_kses( sprintf( __( 'Don’t have a license yet? <a href="%s" target="_blank">Purchase it here</a>.', 'filter-everything' ), esc_url( FLRT_LICENSE_SOURCE ) ), array( 'a' => array( 'href' => true, 'target' => true ) ) ); ?></p>
        </div>
        <?php
    }

    /**
     * What did the customer paste? Decided locally, before any request to the
     * license server, so the error can name the actual mistake.
     *
     * @return array{kind:string, url:string} kind: empty | purchase_code | codecanyon | shop | unknown
     */
    public static function classifyKey( $key )
    {
        $key = trim( (string) $key );

        if ( $key === '' ) {
            return array( 'kind' => 'empty', 'url' => '' );
        }
        if ( preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $key ) ) {
            return array( 'kind' => 'purchase_code', 'url' => '' );
        }

        $decoded = base64_decode( $key, true );
        $parts   = $decoded ? explode( '|', $decoded ) : array();

        if ( count( $parts ) >= 3 && $parts[0] === 'codecanyon' ) {
            return array( 'kind' => 'codecanyon', 'url' => '' );
        }
        if ( count( $parts ) >= 3 && $parts[0] === 'filtereverything' ) {
            return array( 'kind' => 'shop', 'url' => trim( $parts[2] ) );
        }

        return array( 'kind' => 'unknown', 'url' => '' );
    }

    /**
     * Message for a key that must not even be sent to the server, or '' when the
     * key looks like a real license key.
     */
    public static function localKeyError( $key )
    {
        $allowed = array( 'strong' => array(), 'code' => array() );
        $info    = self::classifyKey( $key );

        switch ( $info['kind'] ) {
            case 'empty':
                return esc_html__( 'Please paste your license key first.', 'filter-everything' );
            case 'purchase_code':
                return wp_kses( __( 'This looks like your Envato <strong>purchase code</strong>, not a license key. The key is a long string that starts with <code>Y29kZWNhbnlvbn</code>. Click <strong>Get your License Key</strong>, sign in with the Envato account that bought the plugin, and copy the key from there.', 'filter-everything' ), $allowed );
            case 'unknown':
                return wp_kses( __( 'This does not look like a Filter Everything license key. Follow the steps above for the place where you bought the plugin and paste the key exactly as shown there.', 'filter-everything' ), $allowed );
        }

        return '';
    }

    /**
     * Turn the server's generic answers into instructions. $result is the decoded
     * API response, or false/null when the server could not be reached.
     */
    public static function serverErrorHints( $result, $key )
    {
        $allowed = array( 'strong' => array(), 'code' => array() );
        $hints   = array();

        if ( ! is_array( $result ) ) {
            $hints[] = esc_html__( 'The license server could not be reached. This is not a problem with your key — please try again in a few minutes, and contact support if it keeps failing.', 'filter-everything' );
            return $hints;
        }

        $messages = isset( $result['messages'] ) && is_array( $result['messages'] ) ? $result['messages'] : array();

        if ( isset( $messages[25] ) ) {
            $hints[] = wp_kses( __( 'This key is already active on two sites. Deactivate it on a site you no longer use (Filters → Settings → License there, or <strong>My licenses</strong> on filtereverything.pro for keys bought on our website) and try again, or contact support with the key.', 'filter-everything' ), $allowed );
        }

        if ( isset( $messages[21] ) ) {
            $mismatch = self::urlMismatchHint( $key );
            if ( $mismatch ) {
                $hints[] = $mismatch;
            } elseif ( self::classifyKey( $key )['kind'] === 'shop' ) {
                $hints[] = wp_kses( sprintf( __( 'This key belongs to a filtereverything.pro account. Make sure <code>%s</code> is added in <strong>My licenses</strong> and paste the key generated for it.', 'filter-everything' ), esc_html( self::siteIdentity() ) ), $allowed );
            }
        }

        return $hints;
    }

    public function removeSubmitButton( $page ){
        if( $page === $this->page ){
            add_filter( 'wpc_settings_submit_button', '__return_false' );
            add_action( 'wpc_after_settings_field', array( $this, 'submitButtons' ) );
        }else{
            remove_filter( 'wpc_settings_submit_button', '__return_false' );
        }
    }

    /**
     * The address this install presents to the license server — the same
     * normalisation connect applies (lower-case, no protocol, no trailing slash),
     * so what the customer registers can be compared with it byte for byte.
     */
    public static function siteIdentity()
    {
        $home = home_url();

        return function_exists( 'flrt_clean_url' )
            ? flrt_clean_url( $home )
            : trim( str_replace( array( 'https://', 'http://' ), '', mb_strtolower( $home ) ), '/' );
    }

    /**
     * Same-host comparison helper: "ebblo.com/de" and "www.ebblo.com" are the
     * same site for licensing purposes (the shop accepts them since 1.9.6).
     */
    private static function hostOf( $url )
    {
        $url  = trim( str_replace( array( 'https://', 'http://' ), '', mb_strtolower( (string) $url ) ), ' /' );
        $host = strtok( $url, '/' );
        $host = is_string( $host ) ? $host : '';
        $host = preg_replace( '/:\d+$/', '', $host );

        return preg_replace( '/^www\./', '', $host );
    }

    /**
     * When an activation fails and the key was generated for a different HOST
     * than this site's identity, explain it instead of leaving the customer with
     * the server's generic "Invalid license key". Returns '' when the key is not
     * decodable or the hosts agree (then the failure has another cause).
     *
     * @param string $license_key raw key as entered
     * @return string escaped HTML or ''
     */
    public static function urlMismatchHint( $license_key )
    {
        $decoded = base64_decode( trim( (string) $license_key ), true );
        if ( ! $decoded ) {
            return '';
        }

        $parts = explode( '|', $decoded );
        // Only keys from filtereverything.pro carry a site address; CodeCanyon
        // keys carry the purchase code there, which must not be read as a host.
        if ( ! isset( $parts[0] ) || $parts[0] !== 'filtereverything' ) {
            return '';
        }
        $key_url = isset( $parts[2] ) ? trim( $parts[2] ) : '';
        if ( $key_url === '' ) {
            return '';
        }

        $identity = self::siteIdentity();
        if ( self::hostOf( $key_url ) === self::hostOf( $identity ) ) {
            return '';
        }

        return wp_kses(
            sprintf(
                /* translators: 1: the address the key was generated for, 2: the address this site identifies itself with */
                __( 'This license key was generated for <strong>%1$s</strong>, but this site identifies itself as <strong>%2$s</strong>. Register this exact address in your account (you can delete the other one to free the slot) and use the key generated for it.', 'filter-everything' ),
                esc_html( $key_url ),
                esc_html( $identity )
            ),
            array( 'strong' => array() )
        );
    }

    public function submitButtons( $field ){
        $saved_value = get_option( $this->optionName );
        $license     = isset( $saved_value['license_key'] ) ? $saved_value['license_key'] : '';

        if( $license ): ?>
            <tr>
                <th>&nbsp;</th>
                <td>
                    <input type="hidden" name="wpc_license_action" value="deactivate" />
                    <input type="submit" value="<?php esc_html_e( 'Deactivate License', 'filter-everything' ); ?>" class="button button-primary">
                </td>
            </tr>
        <?php else: ?>
        <tr>
            <th>&nbsp;</th>
            <td class="td-activate-license">
                <input type="hidden" name="wpc_license_action" value="activate" />
                <input type="submit" value="<?php esc_html_e( 'Activate License', 'filter-everything' ); ?>" class="button button-primary">
            </td>
        </tr>
        <?php endif;
    }

    public function preUpdateLicense( $new_values, $old_values, $option )
    {   // Fires when update l i c e n s e
        if( isset( $new_values['license_key'] ) ) {

            // Let's try to activate license
            if ( $_POST['wpc_license_action'] === 'activate') {

                // Purchase code, empty field, random text: say so without a round trip
                $local_error = self::localKeyError( $new_values['license_key'] );
                if ( $local_error ) {
                    add_settings_error( 'general', 'settings_updated', $local_error, 'error' );
                    return false;
                }

                $apiRequest     = new ApiRequests();
                $activate_data  = $apiRequest->collectPluginData( $new_values['license_key'] );
                $result         = $apiRequest->sendRequest('POST', 'license', $activate_data);

                if ( ! is_array( $result ) || ( isset( $result['error'] ) && $result['error'] === 1 ) ) {
                    if ( is_array( $result ) && ! empty( $result['messages'] ) ) {
                        foreach ( $result['messages'] as $msg ) {
                            add_settings_error( 'general', 'settings_updated', $msg, 'error' );
                        }
                    }

                    // Explain the generic server answers: unreachable server, key
                    // generated for another address, two-sites limit, shop key on
                    // an unregistered site.
                    foreach ( self::serverErrorHints( $result, $new_values['license_key'] ) as $hint ) {
                        add_settings_error( 'general', 'settings_updated', $hint, 'error' );
                    }
                    return false;
                }

                if ( isset( $result['data']['id'] ) && $result['data']['id'] ) {
                    // We have to store in WPDB also id of the entry in connect.fitlereverything.pro
                    // to be available to delete the entry when needed.
                    $data = array(
                        'id'    => $result['data']['id'],
                        'key'   => $new_values['license_key'],
                    );

                    // Success message
                    add_settings_error('general', 'settings_updated', esc_html__('The license was successfully activated.', 'filter-everything'), 'success');
                    // If license was activated, we have to refresh updates info
                    delete_transient(FLRT_VERSION_TRANSIENT );

                    // Store the signed, domain-bound token connect issued at activation.
                    if ( isset( $result['data']['token'] ) && $result['data']['token'] ) {
                        flrt_store_token( $result['data']['token'] );
                    }

                    $new_values['license_key'] = base64_encode( maybe_serialize( $data ) );
                    return $new_values;
                }else{
                    // Something went wrong
                    add_settings_error('general', 'settings_updated', esc_html__('Unknown error.', 'filter-everything'), 'error');
                    return false;
                }

            // Or let's try to delete existing license
            } elseif ( $_POST['wpc_license_action'] === 'deactivate') {

                $saved_value         = get_option( $this->optionName );
                $saved_value_arr     = maybe_unserialize( base64_decode( $saved_value['license_key'] ) );
                $to_send             = $saved_value_arr;
                $to_send['home_url'] = home_url();

                // Make data suitable to send as GET variables
                $to_send = array_map( 'urlencode', $to_send );

                if ( isset( $saved_value_arr['id'] ) && $saved_value_arr['id'] ) {
                    $apiRequest = new ApiRequests();
                    $result     = $apiRequest->sendRequest('DELETE', 'license', $to_send );

                    // Something went wrong
                    if ( isset( $result['error'] ) && $result['error'] === 1 ) {
                        foreach ( $result['messages'] as $msg ) {
                            add_settings_error('general', 'settings_updated', $msg, 'error' );
                        }

                        // License was not found on the server
                        if ( isset( $result['messages'][31] ) && $result['messages'][31] ) {
                            // If license was deactivated, we have to refresh updates info
                            delete_transient(FLRT_VERSION_TRANSIENT );
                            return false;
                        }

                        // Do not remove license key from the WPDB
                        return $old_values;
                    }

                    // Success message
                    add_settings_error('general', 'settings_updated', esc_html__('The license was successfully deactivated.', 'filter-everything' ), 'info' );
                    // If license was deactivated, we have to refresh updates info
                    delete_transient(FLRT_VERSION_TRANSIENT );
                    flrt_clear_token();
                    return false;
                }

            } else {
                // Do not change anything
                return $old_values;
            }

        }

        return false;
    }

    public function getLabel()
    {
        return esc_html__('License', 'filter-everything');
    }

    public function getName()
    {
        return 'license';
    }

    public function valid()
    {
        return true;
    }
}