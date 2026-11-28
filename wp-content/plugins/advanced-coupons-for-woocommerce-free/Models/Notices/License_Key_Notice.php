<?php
namespace ACFWF\Models\Notices;

use ACFWF\Abstracts\Abstract_Main_Plugin_Class;
use ACFWF\Abstracts\Base_Model;
use ACFWF\Helpers\Helper_Functions;
use ACFWF\Helpers\Plugin_Constants;
use ACFWF\Interfaces\Model_Interface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Model that shows an admin notice when the stored Advanced Coupons Premium license key
 * is from an unofficial copy or is not a valid key.
 *
 * The check runs locally on the stored key. It makes no network request and it does not block any feature.
 *
 * @since 4.7.7
 */
class License_Key_Notice extends Base_Model implements Model_Interface {
    /*
    |--------------------------------------------------------------------------
    | Class Properties
    |--------------------------------------------------------------------------
     */

    /**
     * Notice type for a license key that matches a crack signature.
     *
     * @since 4.7.7
     */
    const UNOFFICIAL_COPY = 'unofficial_copy';

    /**
     * Notice type for a license key that is not in the format we issue.
     *
     * @since 4.7.7
     */
    const INVALID_KEY = 'invalid_key';

    /**
     * Number of days a dismissed notice stays hidden.
     *
     * @since 4.7.7
     */
    const DISMISS_DAYS = 30;

    /**
     * AJAX action and nonce action to dismiss a license key notice.
     *
     * @since 4.7.7
     */
    const DISMISS_ACTION = 'acfwf_dismiss_license_key_notice';

    /*
    |--------------------------------------------------------------------------
    | Class Methods
    |--------------------------------------------------------------------------
     */

    /**
     * Class constructor.
     *
     * @since 4.7.7
     * @access public
     *
     * @param Abstract_Main_Plugin_Class $main_plugin      Main plugin object.
     * @param Plugin_Constants           $constants        Plugin constants object.
     * @param Helper_Functions           $helper_functions Helper functions object.
     */
    public function __construct( Abstract_Main_Plugin_Class $main_plugin, Plugin_Constants $constants, Helper_Functions $helper_functions ) {
        parent::__construct( $main_plugin, $constants, $helper_functions );

        $main_plugin->add_to_all_plugin_models( $this );
    }

    /*
    |--------------------------------------------------------------------------
    | Implementation.
    |--------------------------------------------------------------------------
     */

    /**
     * Get the notice type for a license key.
     *
     * @since 4.7.7
     * @access private
     *
     * @param mixed $license_key Stored license key value.
     * @return string Notice type (self::UNOFFICIAL_COPY or self::INVALID_KEY), or an empty string when no notice applies.
     */
    private function _get_license_key_notice_type( $license_key ) {
        if ( ! is_string( $license_key ) ) {
            return '';
        }

        $license_key = preg_replace( '/\s+/', '', $license_key );

        // An empty key means no license was entered, so there is no key to check.
        // Note: the premium plugin shows its own "no license" notice when its license data is empty, for any key.
        if ( '' === $license_key ) {
            return '';
        }

        // Genuine keys contain a UUID. Do not anchor it, as keys are sometimes pasted with extra characters.
        if ( preg_match( '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $license_key ) ) {
            return '';
        }

        $crack_signatures = array(
            '/^[*\-]+$/',
            '/^X{4}(-X{4})+$/',
            '/bypass|nullified|weadown/i',
            '/^[0-9A-Z]{32}$/',
        );

        foreach ( $crack_signatures as $crack_signature ) {
            if ( preg_match( $crack_signature, $license_key ) ) {
                return self::UNOFFICIAL_COPY;
            }
        }

        return self::INVALID_KEY;
    }

    /**
     * Get the user meta key that stores the dismissal timestamp of a notice type.
     *
     * @since 4.7.7
     * @access private
     *
     * @param string $notice_type Notice type.
     * @return string User meta key, or an empty string for an unknown notice type.
     */
    private function _get_dismissed_meta_key( $notice_type ) {
        $meta_keys = array(
            self::UNOFFICIAL_COPY => Plugin_Constants::UNOFFICIAL_COPY_NOTICE_DISMISSED,
            self::INVALID_KEY     => Plugin_Constants::INVALID_KEY_NOTICE_DISMISSED,
        );

        return $meta_keys[ $notice_type ] ?? '';
    }

    /**
     * Check if the current user dismissed a notice type in the last 30 days.
     *
     * @since 4.7.7
     * @access private
     *
     * @param string $notice_type Notice type.
     * @return bool True if the notice is dismissed, false otherwise.
     */
    private function _is_notice_dismissed( $notice_type ) {
        $dismissed_at = (int) get_user_meta( get_current_user_id(), $this->_get_dismissed_meta_key( $notice_type ), true );

        return $dismissed_at > 0 && ( time() - $dismissed_at ) < self::DISMISS_DAYS * DAY_IN_SECONDS;
    }

    /**
     * Get the stored Advanced Coupons Premium license key.
     *
     * @since 4.7.7
     * @access private
     *
     * @return mixed Stored license key, or null when the premium plugin is not loaded.
     */
    private function _get_premium_license_key() {
        if ( ! function_exists( 'ACFWP' ) ) {
            return null;
        }

        // Premium Plugin_Constants::__get() throws on an unknown property. It has no __isset(), so isset() is always false.
        try {
            $option_name = \ACFWP()->Plugin_Constants->OPTION_LICENSE_KEY;
        } catch ( \Exception $e ) {
            return null;
        }

        return get_site_option( $option_name );
    }

    /**
     * Get the premium plugin license page URL.
     *
     * @since 4.7.7
     * @access private
     *
     * @return string License page URL, or an empty string when the current user cannot open it.
     */
    private function _get_license_page_url() {
        if ( method_exists( \ACFWP()->Plugin_Constants, 'get_license_page_url' ) ) {
            return (string) \ACFWP()->Plugin_Constants->get_license_page_url();
        }

        // Premium before 3.6.0 has the license page only in the network admin on multisite.
        if ( is_multisite() ) {
            return current_user_can( 'manage_network_plugins' ) ? network_admin_url( 'admin.php?page=advanced-coupons&tab=acfwp_license' ) : '';
        }

        return admin_url( 'admin.php?page=acfw-license' );
    }

    /**
     * Maybe display the license key notice.
     *
     * @since 4.7.7
     * @access public
     */
    public function maybe_display_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( ! $this->_helper_functions->is_plugin_active( Plugin_Constants::PREMIUM_PLUGIN ) ) {
            return;
        }

        $screen = get_current_screen();

        if ( ! $screen || ! $this->_is_acfw_screen( $screen ) ) {
            return;
        }

        $notice_type = $this->_get_license_key_notice_type( $this->_get_premium_license_key() );

        if ( ! $notice_type || $this->_is_notice_dismissed( $notice_type ) ) {
            return;
        }

        $this->_render_notice( $notice_type );
    }

    /**
     * Check if the current screen is an Advanced Coupons admin screen.
     *
     * @since 4.7.7
     * @access private
     *
     * @param \WP_Screen $screen Current screen object.
     * @return bool True if the screen is an Advanced Coupons screen, false otherwise.
     */
    private function _is_acfw_screen( $screen ) {
        $post_type = get_post_type();

        if ( ! $post_type && isset( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $post_type = sanitize_key( wp_unslash( $_GET['post_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }

        return \ACFWF()->Notices->is_acfw_screen( $screen, $post_type );
    }

    /**
     * Render the license key notice.
     *
     * @since 4.7.7
     * @access private
     *
     * @param string $notice_type Notice type.
     */
    private function _render_notice( $notice_type ) {
        $license_page_url = $this->_get_license_page_url();
        $dismiss_nonce    = wp_create_nonce( self::DISMISS_ACTION );
        $dismiss_action   = self::DISMISS_ACTION;

        if ( self::UNOFFICIAL_COPY === $notice_type ) {
            $notice = array(
                'type'       => $notice_type,
                'class'      => 'notice-error',
                'heading'    => __( 'Your copy of Advanced Coupons Premium isn\'t from us', 'advanced-coupons-for-woocommerce-free' ),
                'paragraphs' => array(
                    __( 'The licence key on this site wasn\'t issued by Advanced Coupons. That usually means the plugin came from a third-party download site, sometimes installed by a developer without the store owner knowing.', 'advanced-coupons-for-woocommerce-free' ),
                    __( 'Unofficial copies can be modified, often carry hidden code, and never receive our security updates or support. Your coupons and discounts run through your checkout, so that\'s a risk worth fixing.', 'advanced-coupons-for-woocommerce-free' ),
                    __( 'Switch to the official version and keep every coupon and setting exactly as it is.', 'advanced-coupons-for-woocommerce-free' ),
                ),
                'actions'    => array(
                    array(
                        'key'         => 'get-official',
                        'text'        => __( 'Get the official version', 'advanced-coupons-for-woocommerce-free' ),
                        'link'        => 'https://advancedcouponsplugin.com/pricing/?utm_source=acfwf&utm_medium=admin-notice&utm_campaign=unofficial-copy&utm_content=get-official',
                        'is_primary'  => true,
                        'is_external' => true,
                    ),
                    array(
                        'key'         => 'enter-key',
                        'text'        => __( 'Already bought it? Enter your licence key', 'advanced-coupons-for-woocommerce-free' ),
                        'link'        => $license_page_url,
                        'is_primary'  => false,
                        'is_external' => false,
                    ),
                ),
            );
        } else {
            $notice = array(
                'type'       => $notice_type,
                'class'      => 'notice-warning',
                'heading'    => __( 'Your Advanced Coupons Premium licence key isn\'t valid', 'advanced-coupons-for-woocommerce-free' ),
                'paragraphs' => array(
                    __( 'The licence key saved on this site doesn\'t match a key we issue, so this site isn\'t receiving updates or support. This can happen when a browser autofills a password into the key field, or when only part of the key was pasted.', 'advanced-coupons-for-woocommerce-free' ),
                    __( 'Copy your key from your account page and enter it again.', 'advanced-coupons-for-woocommerce-free' ),
                ),
                'actions'    => array(
                    array(
                        'key'         => 'enter-key',
                        'text'        => __( 'Enter your licence key', 'advanced-coupons-for-woocommerce-free' ),
                        'link'        => $license_page_url,
                        'is_primary'  => true,
                        'is_external' => false,
                    ),
                    array(
                        'key'         => 'find-key',
                        'text'        => __( 'Find my key', 'advanced-coupons-for-woocommerce-free' ),
                        'link'        => 'https://advancedcouponsplugin.com/my-account/?utm_source=acfwf&utm_medium=admin-notice&utm_campaign=invalid-key&utm_content=find-key',
                        'is_primary'  => false,
                        'is_external' => true,
                    ),
                ),
            );
        }

        // Hide an action that has no link (e.g. the license page on a multisite sub-site).
        $notice['actions'] = array_filter(
            $notice['actions'],
            function ( $notice_action ) {
                return ! empty( $notice_action['link'] );
            }
        );

        include $this->_constants->VIEWS_ROOT_PATH . 'notices/view-license-key-notice.php';
    }

    /*
    |--------------------------------------------------------------------------
    | AJAX Functions
    |--------------------------------------------------------------------------
     */

    /**
     * AJAX handler to dismiss a license key notice for the current user.
     *
     * @since 4.7.7
     * @access public
     */
    public function ajax_dismiss_notice() {
        check_ajax_referer( self::DISMISS_ACTION, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'advanced-coupons-for-woocommerce-free' ), 403 );
        }

        $notice_type = isset( $_POST['notice_type'] ) ? sanitize_key( wp_unslash( $_POST['notice_type'] ) ) : '';
        $meta_key    = $this->_get_dismissed_meta_key( $notice_type );

        if ( ! $meta_key ) {
            wp_send_json_error( __( 'Invalid notice type.', 'advanced-coupons-for-woocommerce-free' ), 400 );
        }

        update_user_meta( get_current_user_id(), $meta_key, time() );
        wp_send_json_success();
    }

    /*
    |--------------------------------------------------------------------------
    | Fulfill implemented interface contracts
    |--------------------------------------------------------------------------
     */

    /**
     * Execute License_Key_Notice class.
     *
     * @since 4.7.7
     * @access public
     * @inherit ACFWF\Interfaces\Model_Interface
     */
    public function run() {
        add_action( 'admin_notices', array( $this, 'maybe_display_notice' ) );
        add_action( 'wp_ajax_' . self::DISMISS_ACTION, array( $this, 'ajax_dismiss_notice' ) );
    }
}
