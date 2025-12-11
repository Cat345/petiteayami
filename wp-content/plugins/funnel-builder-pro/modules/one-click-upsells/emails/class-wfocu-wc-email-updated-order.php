<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


if ( ! class_exists( 'WFOCU_WC_Email_Updated_Order', false ) ) :

	/**
	 * Customer Updated Order Email.
	 *
	 * An email sent to the customer when an order is updated by the upsell offer acceptance.
	 *
	 * @class       WFOCU_WC_Email_Updated_Order
	 * @version     1.0.0
	 * @author      FunnelKit
	 * @extends     WC_Email
	 */
	class WFOCU_WC_Email_Updated_Order extends WC_Email {

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->id             = 'wfocu_customer_updated_order';
			$this->customer_email = true;

			$this->title          = __( 'Updated order', 'woocommerce' );
			$this->description    = __( 'This is an order email sent to customer when he/she accepts any upsell offer and the current order is updated.', 'woocommerce' );
			$this->template_html  = 'wfocu-customer-updated-order.php';
			$this->template_plain = 'wfocu-customer-updated-order-plain.php';
			$this->template_base  = plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'emails/templates/';
			$this->placeholders   = array(
				'{site_title}'   => $this->get_blogname(),
				'{order_date}'   => '',
				'{order_number}' => '',
			);

			add_action( 'wfocu_offer_accepted_and_processed_notification', array( $this, 'fire_order_updated_mail' ), 999, 5 );

			// Call parent constructor
			parent::__construct();
		}

		/**
		 * Get email subject.
		 *
		 * @return string
		 * @since  3.1.0
		 */
		public function get_default_subject() {
			return __( 'Your {site_title} order {order_number} has been updated', 'woocommerce' );
		}

		/**
		 * Get email heading.
		 *
		 * @return string
		 * @since  3.1.0
		 */
		public function get_default_heading() {
			return __( 'Your order has been updated', 'woocommerce' );
		}

		public function fire_order_updated_mail( $get_offer_id, $get_package, $get_parent_order, $new_order, $get_transaction_id ) {

			if ( true === is_a( $new_order, 'WC_Order' ) ) {
				return;
			}

			if ( false === is_a( $get_parent_order, 'WC_Order' ) ) {
				return;
			}
			if ( 'start' !== WFOCU_Core()->data->get_option( 'send_processing_mail_on' ) ) {
				return;
			}

			$this->trigger( WFOCU_WC_Compatibility::get_order_id( $get_parent_order ), $get_parent_order );
		}

		/**
		 * Trigger the sending of this email.
		 *
		 * @param int $order_id The order ID.
		 * @param WC_Order $order Order object.
		 */
		public function trigger( $order_id, $order = false ) {
			$this->setup_locale();

			if ( $order_id && ! is_a( $order, 'WC_Order' ) ) {
				$order = wc_get_order( $order_id );
			}

			if ( is_a( $order, 'WC_Order' ) ) {
				$this->object                         = $order;
				$this->recipient                      = $this->object->get_billing_email();
				$this->placeholders['{order_date}']   = wc_format_datetime( $this->object->get_date_created() );
				$this->placeholders['{order_number}'] = $this->object->get_order_number();
			}

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}

			$this->restore_locale();
		}

		/**
		 * Get content html.
		 *
		 * @access public
		 * @return string
		 */
		public function get_content_html() {

			ob_start();
			wc_get_template( $this->template_html, array(
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => false,
				'email'         => $this,
			), '', $this->template_base );

			return ob_get_clean();

		}

		/**
		 * Get content plain.
		 *
		 * @access public
		 * @return string
		 */
		public function get_content_plain() {
			ob_start();
			wc_get_template( $this->template_html, array(
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => true,
				'email'         => $this,
			), '', $this->template_base );

			return ob_get_clean();
		}
	}

endif;

return new WFOCU_WC_Email_Updated_Order();

