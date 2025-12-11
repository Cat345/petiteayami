<?php
/**
 * Class WC_Email_New_Order file
 *
 * @package WooCommerce\Emails
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFOCU_WC_Email_Updated_Order_Admin' ) ) :

	/**
	 * New Order Email.
	 *
	 * An email sent to the admin when a new order is received/paid for.
	 *
	 * @class       WFOCU_WC_Email_Updated_Order_Admin
	 * @version     2.0.0
	 * @package     WooCommerce/Classes/Emails
	 * @extends     WC_Email
	 */
	class WFOCU_WC_Email_Updated_Order_Admin extends WC_Email {

		/**
		 * Constructor.
		 */
		public function __construct() {


			$this->id = 'wfocu_admin_updated_order';

			$this->title          = __( 'Updated Order Admin', 'woocommerce' );
			$this->description    = __( 'This is an order email sent to admin when he/she accepts any upsell offer and the current order is updated.', 'woocommerce' );
			$this->template_html  = 'wfocu-customer-updated-order-admin.php';
			$this->template_plain = 'wfocu-customer-updated-order-admin-plain.php';
			$this->template_base  = plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'emails/templates/';
			$this->placeholders   = array(
				'{site_title}'   => $this->get_blogname(),
				'{order_date}'   => '',
				'{order_number}' => '',
			);

			add_action( 'wfocu_offer_accepted_and_processed_notification', array( $this, 'fire_order_updated_mail' ), 999, 5 );

			// Call parent constructor
			parent::__construct();


			// Other settings.
			$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
		}

		/**
		 * Get email subject.
		 *
		 * @return string
		 * @since  3.1.0
		 */
		public function get_default_subject() {
			return __( '[{site_title}] New customer order ({order_number}) - {order_date} has been updated', 'woocommerce' );
		}

		/**
		 * Get email heading.
		 *
		 * @return string
		 * @since  3.1.0
		 */
		public function get_default_heading() {
			return __( 'New customer order updated', 'woocommerce' );
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
		 * @param WC_Order|false $order Order object.
		 */
		public function trigger( $order_id, $order = false ) {
			$this->setup_locale();

			if ( $order_id && ! is_a( $order, 'WC_Order' ) ) {
				$order = wc_get_order( $order_id );
			}

			if ( is_a( $order, 'WC_Order' ) ) {
				$this->object                         = $order;
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
				'sent_to_admin' => true,
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
				'sent_to_admin' => true,
				'plain_text'    => false,
				'email'         => $this,
			), '', $this->template_base );

			return ob_get_clean();
		}

		/**
		 * Initialise settings form fields.
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled'    => array(
					'title'   => __( 'Enable/Disable', 'woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable this email notification', 'woocommerce' ),
					'default' => 'yes',
				),
				'recipient'  => array(
					'title'       => __( 'Recipient(s)', 'woocommerce' ),
					'type'        => 'text',
					/* translators: %s: WP admin email */
					'description' => sprintf( __( 'Enter recipients (comma separated) for this email. Defaults to %s.', 'woocommerce' ), '<code>' . esc_attr( get_option( 'admin_email' ) ) . '</code>' ),
					'placeholder' => '',
					'default'     => '',
					'desc_tip'    => true,
				),
				'subject'    => array(
					'title'       => __( 'Subject', 'woocommerce' ),
					'type'        => 'text',
					'desc_tip'    => true,
					/* translators: %s: list of placeholders */
					'description' => sprintf( __( 'Available placeholders: %s', 'woocommerce' ), '<code>{site_title}, {order_date}, {order_number}</code>' ),
					'placeholder' => $this->get_default_subject(),
					'default'     => '',
				),
				'heading'    => array(
					'title'       => __( 'Email heading', 'woocommerce' ),
					'type'        => 'text',
					'desc_tip'    => true,
					/* translators: %s: list of placeholders */
					'description' => sprintf( __( 'Available placeholders: %s', 'woocommerce' ), '<code>{site_title}, {order_date}, {order_number}</code>' ),
					'placeholder' => $this->get_default_heading(),
					'default'     => '',
				),
				'email_type' => array(
					'title'       => __( 'Email type', 'woocommerce' ),
					'type'        => 'select',
					'description' => __( 'Choose which format of email to send.', 'woocommerce' ),
					'default'     => 'html',
					'class'       => 'email_type wc-enhanced-select',
					'options'     => $this->get_email_type_options(),
					'desc_tip'    => true,
				),
			);
		}
	}

endif;

return new WFOCU_WC_Email_Updated_Order_Admin();
