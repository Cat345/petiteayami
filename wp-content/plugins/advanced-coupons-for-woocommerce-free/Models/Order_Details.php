<?php
namespace ACFWF\Models;

use ACFWF\Abstracts\Abstract_Main_Plugin_Class;
use ACFWF\Helpers\Helper_Functions;
use ACFWF\Helpers\Plugin_Constants;
use ACFWF\Interfaces\Model_Interface;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Model that houses the logic of extending the coupon system of woocommerce.
 * It houses the logic of handling coupon url.
 * Public Model.
 *
 * @since 1.0
 */
class Order_Details implements Model_Interface {
    /*
    |--------------------------------------------------------------------------
    | Class Properties
    |--------------------------------------------------------------------------
     */

    /**
     * Property that holds the single main instance of URL_Coupon.
     *
     * @since 1.0
     * @access private
     * @var Order_Details
     */
    private static $_instance;

    /**
     * Model that houses all the plugin constants.
     *
     * @since 1.0
     * @access private
     * @var Plugin_Constants
     */
    private $_constants;

    /**
     * Property that houses all the helper functions of the plugin.
     *
     * @since 1.0
     * @access private
     * @var Helper_Functions
     */
    private $_helper_functions;

    /*
    |--------------------------------------------------------------------------
    | Class Methods
    |--------------------------------------------------------------------------
     */

    /**
     * Class constructor.
     *
     * @since 1.0
     * @access public
     *
     * @param Abstract_Main_Plugin_Class $main_plugin      Main plugin object.
     * @param Plugin_Constants           $constants        Plugin constants object.
     * @param Helper_Functions           $helper_functions Helper functions object.
     */
    public function __construct( Abstract_Main_Plugin_Class $main_plugin, Plugin_Constants $constants, Helper_Functions $helper_functions ) {
        $this->_constants        = $constants;
        $this->_helper_functions = $helper_functions;

        $main_plugin->add_to_all_plugin_models( $this );
        $main_plugin->add_to_public_models( $this );
    }

    /**
     * Ensure that only one instance of this class is loaded or can be loaded ( Singleton Pattern ).
     *
     * @since 1.0
     * @access public
     *
     * @param Abstract_Main_Plugin_Class $main_plugin      Main plugin object.
     * @param Plugin_Constants           $constants        Plugin constants object.
     * @param Helper_Functions           $helper_functions Helper functions object.
     * @return Order_Details
     */
    public static function get_instance( Abstract_Main_Plugin_Class $main_plugin, Plugin_Constants $constants, Helper_Functions $helper_functions ) {
        if ( ! self::$_instance instanceof self ) {
            self::$_instance = new self( $main_plugin, $constants, $helper_functions );
        }

        return self::$_instance;
    }

    /*
    |--------------------------------------------------------------------------
    | Order Preview Popup
    |--------------------------------------------------------------------------
     */

    /**
     * Add coupons list in order preview popup.
     * Moved and refactored from \ACFWF\Models\Edit_Coupon.
     *
     * @since 1.3.7
     * @access public
     *
     * @param array    $data  Order preview data.
     * @param WC_Order $order Order object.
     */
    public function add_coupons_list_in_order_preview_popup( $data, $order ) {
        $coupons_list = $this->_get_used_coupons_list_markup( $order, true );

        ob_start();
        include $this->_constants->VIEWS_ROOT_PATH . 'orders' . DIRECTORY_SEPARATOR . 'order-preview-popup-coupons-list.php';
        $markup = ob_get_clean();

        $data['item_html'] = $markup . $data['item_html'];

        return $data;
    }

    /**
     * Add used coupons in email order totals that are sent to admin.
     *
     * @since 1.3.7
     * @access public
     *
     * @param array    $total_rows Email total rows data.
     * @param WC_Order $order      Order object.
     * @return array   Filtered total email total rows data.
     */
    public function add_coupons_in_email_after_order_table( $total_rows, $order ) {
        $coupons_list  = $this->_get_used_coupons_list_markup( $order );
        $filtered_rows = array();
        $index         = isset( $total_rows['discount'] ) ? 'discount' : 'cart_subtotal';

        foreach ( $total_rows as $key => $row ) {
            $filtered_rows[ $key ] = $row;

            // add coupons row after either subtotal or discount row.
            if ( $index === $key ) {
                $filtered_rows['acfw_coupons'] = array(
                    'label' => __( 'Coupons:', 'advanced-coupons-for-woocommerce-free' ),
                    'value' => $coupons_list,
                );
            }
        }

        return $filtered_rows;
    }

    /**
     * Register hook for used coupons list in email when it is sent to admin.
     *
     * @since 1.3.7
     * @access public
     *
     * @param WC_Order $order         Order object.
     * @param bool     $sent_to_admin Toggle if email sent to admin.
     */
    public function coupons_list_email_register_hook( $order, $sent_to_admin ) {
        if ( $sent_to_admin ) {
            add_filter( 'woocommerce_get_order_item_totals', array( $this, 'add_coupons_in_email_after_order_table' ), 10, 2 );
        }
    }

    /**
     * Unregister hook for used coupons list in email when it is sent to admin.
     *
     * @since 1.3.7
     * @access public
     *
     * @param WC_Order $order         Order object.
     * @param bool     $sent_to_admin Toggle if email sent to admin.
     */
    public function coupons_list_email_unregister_hook( $order, $sent_to_admin ) {
        if ( $sent_to_admin ) {
            remove_filter( 'woocommerce_get_order_item_totals', array( $this, 'add_coupons_in_email_after_order_table' ), 10, 2 );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | WC REST API Overrides.
    |--------------------------------------------------------------------------
     */

    /**
     * Register hooks for WC REST API overrides.
     *
     * @since 4.5.6
     * @deprecated 4.7.7 The REST responses are filtered by remove_empty_advanced_coupon_meta_from_coupon_response()
     *                   and remove_empty_advanced_coupon_meta_from_order_response(), registered in run().
     * @access public
     */
    public function wc_rest_api_override_hooks() {
        wc_deprecated_function( __METHOD__, '4.7.7' );
    }

    /**
     * Remove empty meta data for advanced coupon fields.
     *
     * The old filter of the data store read hid empty meta rows from a coupon save in the same request, which added
     * duplicate rows. This stub returns the meta data list unchanged, so a callback that is still hooked does no harm.
     *
     * @since 1.4.2
     * @deprecated 4.7.7 Use remove_empty_advanced_coupon_meta_from_coupon_response() or
     *                   remove_empty_advanced_coupon_meta_from_order_response().
     * @access public
     *
     * @param array $metadata Meta data list.
     * @param mixed $coupon   Object passed in filter.
     * @return array Meta data list, unchanged.
     */
    public function remove_empty_advanced_coupon_meta_data( $metadata, $coupon ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
        wc_deprecated_function( __METHOD__, '4.7.7', __CLASS__ . '::remove_empty_advanced_coupon_meta_from_coupon_response' );
        return $metadata;
    }

    /**
     * Hide empty advanced coupon meta data in the WC REST API coupon response.
     *
     * This only changes the response data. It does not change the meta data that the coupon object loads, so a
     * coupon save in the same request still finds its existing meta rows and does not add duplicates.
     *
     * @since 4.7.7 Replaces remove_empty_advanced_coupon_meta_data() (1.4.2), which filtered the data store read.
     * @access public
     *
     * @param \WP_REST_Response $response REST response object.
     * @return \WP_REST_Response Filtered REST response object.
     */
    public function remove_empty_advanced_coupon_meta_from_coupon_response( $response ) {
        if ( ! $response instanceof \WP_REST_Response ) {
            return $response;
        }

        $data = $response->get_data();

        if ( isset( $data['meta_data'] ) && is_array( $data['meta_data'] ) ) {
            $data['meta_data'] = $this->_remove_empty_advanced_coupon_meta( $data['meta_data'] );
            $response->set_data( $data );
        }

        return $response;
    }

    /**
     * Hide empty advanced coupon meta data in the coupon data of order coupon lines in the WC REST API order response.
     *
     * @since 4.7.7 Replaces remove_empty_advanced_coupon_meta_data() (1.4.2), which filtered the data store read.
     * @access public
     *
     * @param \WP_REST_Response $response REST response object.
     * @return \WP_REST_Response Filtered REST response object.
     */
    public function remove_empty_advanced_coupon_meta_from_order_response( $response ) {
        if ( ! $response instanceof \WP_REST_Response ) {
            return $response;
        }

        $data = $response->get_data();

        if ( empty( $data['coupon_lines'] ) || ! is_array( $data['coupon_lines'] ) ) {
            return $response;
        }

        foreach ( $data['coupon_lines'] as $line_index => $coupon_line ) {
            if ( empty( $coupon_line['meta_data'] ) || ! is_array( $coupon_line['meta_data'] ) ) {
                continue;
            }

            // The order REST response formats coupon line meta entries as arrays.
            foreach ( $coupon_line['meta_data'] as $meta_index => $meta ) {
                if ( ! is_array( $meta ) || 'coupon_data' !== ( $meta['key'] ?? null ) ) {
                    continue;
                }

                foreach ( array( 'value', 'display_value' ) as $prop ) {
                    $coupon_data = $meta[ $prop ] ?? null;

                    if ( is_array( $coupon_data ) && isset( $coupon_data['meta_data'] ) && is_array( $coupon_data['meta_data'] ) ) {
                        $coupon_data['meta_data'] = $this->_remove_empty_advanced_coupon_meta( $coupon_data['meta_data'] );
                        $meta[ $prop ]            = $coupon_data;
                    }
                }

                $data['coupon_lines'][ $line_index ]['meta_data'][ $meta_index ] = $meta;
            }
        }

        $response->set_data( $data );

        return $response;
    }

    /*
    |--------------------------------------------------------------------------
    | Utilities
    |--------------------------------------------------------------------------
     */

    /**
     * Backward compatibility helper to fetch used coupons for an order.
     *
     * @since 1.3.7
     * @access private
     *
     * @param WC_Order $order Order object.
     * @return array List of used coupon codes for the order.
     */
    private function _get_order_used_coupons( $order ) {
        return method_exists( $order, 'get_coupon_codes' ) ? $order->get_coupon_codes() : $order->get_used_coupons();
    }

    /**
     * Get used couponst list markup for order.
     *
     * @since 1.3.7
     * @access private
     *
     * @param WC_Order $order Order object.
     * @param bool     $is_edit Toggle if coupons needs to be linked to edit screen.
     * @return string Coupons list markup.
     */
    private function _get_used_coupons_list_markup( $order, $is_edit = false ) {
        $used_coupons = $this->_get_order_used_coupons( $order );
        return array_reduce(
            $used_coupons,
            function ( $c, $coupon ) use ( $is_edit ) {

            // add comma if there is already coupon present in the loop.
            $c = $c ? $c . ', ' : $c;

            $coupon_id   = wc_get_coupon_id_by_code( $coupon );
            $edit_coupon = $coupon_id && $is_edit ? admin_url( 'post.php?post=' . $coupon_id . '&action=edit' ) : '';
            $coupon_html = $edit_coupon ? sprintf( '<a class="used-coupon" href="%s">%s</a>', $edit_coupon, $coupon ) : sprintf( '<span class="used-coupon">%s</span>', $coupon );

            return $c . $coupon_html;
            },
            ''
        );
    }

    /**
     * Remove empty advanced coupon meta entries from a meta data list.
     *
     * @since 4.7.7
     * @access private
     *
     * @param array $meta_data List of meta entries (\WC_Meta_Data objects or arrays).
     * @return array Filtered list, reindexed.
     */
    private function _remove_empty_advanced_coupon_meta( $meta_data ) {
        return array_values(
            array_filter(
                $meta_data,
                function ( $meta ) {
                    return ! $this->_is_advanced_coupon_meta_data_empty(
                        (string) $this->_get_meta_prop( $meta, 'key' ),
                        $this->_get_meta_prop( $meta, 'value' )
                    );
                }
            )
        );
    }

    /**
     * Get a property of a meta entry.
     *
     * The entries are \WC_Meta_Data objects (coupon response) or arrays (the legacy `coupon_data` of an order coupon
     * line). WooCommerce and the plugin only write these two shapes, so any other shape (e.g. stdClass) returns null
     * and the entry is kept in the response.
     *
     * @since 4.7.7
     * @access private
     *
     * @param mixed  $meta Meta entry (\WC_Meta_Data object or array).
     * @param string $prop Property name.
     * @return mixed Property value, or null when not set.
     */
    private function _get_meta_prop( $meta, $prop ) {
        if ( $meta instanceof \WC_Meta_Data ) {
            $data = $meta->get_data();
            return $data[ $prop ] ?? null;
        }

        if ( is_array( $meta ) ) {
            return $meta[ $prop ] ?? null;
        }

        return null;
    }

    /**
     * Check if a given meta key is for ACFW and if the value is empty or not.
     *
     * @since 1.4.2
     * @access private
     *
     * @param string $key   Meta key.
     * @param mixed  $value Meta value.
     * @return bool True if empty, false otherwise.
     */
    private function _is_advanced_coupon_meta_data_empty( $key, $value ) {
        if ( str_contains( $key, '_acfw_' ) ) {
            $value = maybe_unserialize( $value );

            if ( is_array( $value ) ) {
                return empty( $value );
            } else {
                return '' === $value;
            }
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Fulfill implemented interface contracts
    |--------------------------------------------------------------------------
     */

    /**
     * Execute Order_Details class.
     *
     * @since 1.0
     * @access public
     * @inherit ACFWF\Interfaces\Model_Interface
     */
    public function run() {
        add_filter( 'woocommerce_admin_order_preview_get_order_details', array( $this, 'add_coupons_list_in_order_preview_popup' ), 10, 2 );
        add_action( 'woocommerce_email_before_order_table', array( $this, 'coupons_list_email_register_hook' ), 10, 2 );
        add_action( 'woocommerce_email_after_order_table', array( $this, 'coupons_list_email_unregister_hook' ), 10, 2 );
        add_filter( 'woocommerce_rest_prepare_shop_coupon_object', array( $this, 'remove_empty_advanced_coupon_meta_from_coupon_response' ) );
        add_filter( 'woocommerce_rest_prepare_shop_order_object', array( $this, 'remove_empty_advanced_coupon_meta_from_order_response' ) );
    }
}
