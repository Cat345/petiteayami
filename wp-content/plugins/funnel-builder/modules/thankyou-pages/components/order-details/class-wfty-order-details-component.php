<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WFTY_Order_Details_Component' ) ) {
	#[AllowDynamicProperties]
	class WFTY_Order_Details_Component extends WFTY_Shortcode_Component_Abstract {

		public function __construct( $shortcode_args = array() ) {
			parent::__construct( $shortcode_args );
			add_filter( 'wc_get_template', array( $this, 'subs_get_template' ), 10, 5 );

			if ( is_rtl() ) {
				add_filter( 'woocommerce_order_formatted_line_subtotal', array( $this, 'wrap_price_rtl' ), 999, 3 );
				add_filter( 'woocommerce_get_order_item_totals', array( $this, 'wrap_order_totals_rtl' ), 999, 3 );
			}

			if ( class_exists( 'WC_Subscriptions_Order' ) ) {
				remove_action( 'woocommerce_order_details_after_order_table', array( 'WC_Subscriptions_Order', 'add_subscriptions_to_view_order_templates' ), 10 );
				add_action( 'wfty_woocommerce_order_subscription', array( 'WC_Subscriptions_Order', 'add_subscriptions_to_view_order_templates' ), 10, 1 );
				add_action( 'wfty_subscription_notice', array( 'WC_Subscriptions_Order', 'subscription_thank_you' ) );
			}
		}

		public function get_meta() {
			return array(
				'border_style'       => 'solid',
				'border_width'       => '1',
				'border_color'       => '#d9d9d9',
				'component_bg_color' => '#ffffff',
			);
		}

		public function setup_data() {
			$order_id                                 = ( $this->order instanceof WC_Order ) ? $this->order->get_id() : 0;
			$this->data['order_details_img']          = isset( $this->data['order_details_img'] ) ? $this->data['order_details_img'] : WFFN_Core()->thank_you_pages->get_optionsShortCode( 'order_details_img', $order_id );
			$this->data['order_details_heading']      = isset( $this->data['order_details_heading'] ) ? $this->data['order_details_heading'] : WFFN_Core()->thank_you_pages->get_optionsShortCode( 'order_details_heading', $order_id );
			$this->data['order_subscription_heading'] = isset( $this->data['order_subscription_heading'] ) ? $this->data['order_subscription_heading'] : WFFN_Core()->thank_you_pages->get_optionsShortCode( 'order_subscription_heading', $order_id );
			$this->data['order_downloads_btn_text']   = isset( $this->data['order_downloads_btn_text'] ) ? $this->data['order_downloads_btn_text'] : WFFN_Core()->thank_you_pages->get_optionsShortCode( 'order_downloads_btn_text', $order_id );
			$this->data['order_downloads_show_file_downloads'] = isset( $this->data['order_downloads_show_file_downloads'] ) ? $this->data['order_downloads_show_file_downloads'] : WFFN_Core()->thank_you_pages->get_optionsShortCode( 'order_downloads_show_file_downloads', $order_id );
			$this->data['order_downloads_show_file_expiry']    = isset( $this->data['order_downloads_show_file_expiry'] ) ? $this->data['order_downloads_show_file_expiry'] : WFFN_Core()->thank_you_pages->get_optionsShortCode( 'order_downloads_show_file_expiry', $order_id );
			$this->data['order_download_heading']              = isset( $this->data['order_download_heading'] ) ? $this->data['order_download_heading'] : WFFN_Core()->thank_you_pages->get_optionsShortCode( 'order_download_heading', $order_id );

			// Sublium-specific settings (only if Sublium is available)
			if ( function_exists( 'sublium_init' ) ) {
				$this->data['sublium_subscriptions_heading']   = isset( $this->data['sublium_subscriptions_heading'] ) ? $this->data['sublium_subscriptions_heading'] : WFFN_Core()->thank_you_pages->get_optionsShortCode( 'sublium_subscriptions_heading', $order_id );
				$this->data['sublium_subscriptions_view_text'] = isset( $this->data['sublium_subscriptions_view_text'] ) ? $this->data['sublium_subscriptions_view_text'] : WFFN_Core()->thank_you_pages->get_optionsShortCode( 'sublium_subscriptions_view_text', $order_id );
			}
		}

		public function subs_get_template( $located, $template_name, $args, $template_path, $default_path ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter, VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable

			if ( 'myaccount/related-subscriptions.php' === $template_name ) {
				// Check if Sublium is active and this is a Sublium subscription
				if ( class_exists( 'Sublium\Includes\database\Subscriptions' ) && $this->is_sublium_subscription() ) {
					return __DIR__ . '/views/sublium-related-subscriptions.php';
				}
				return __DIR__ . '/views/related-subscriptions.php';
			}

			return $located;
		}

		/**
		 * Check if the current order has Sublium subscriptions
		 *
		 * @return bool
		 */
		private function is_sublium_subscription() {
			// First check if Sublium is available
			if ( ! function_exists( 'sublium_init' ) ) {
				return false;
			}

			if ( ! $this->order instanceof WC_Order ) {
				return false;
			}

			// Check if this is a Sublium subscription renewal
			$is_renewal = $this->order->get_meta( '_sublium_subscription_renewal' ) === 'yes';
			if ( $is_renewal ) {
				return true;
			}

			// Check if there are Sublium subscriptions for this order
			if ( class_exists( 'Sublium\Includes\database\Subscriptions' ) ) {
				$subscriptionDB = new Sublium\Includes\database\Subscriptions();
				$subscriptions  = $subscriptionDB->read( array( 'parent_order_id' => $this->order->get_id() ) );
				return ! empty( $subscriptions );
			}

			return false;
		}

		public function get_slug() {
			return '_wfty_order_details_shortcode_component';
		}

		public function render() {
			if ( false !== $this->order ) {
				$this->setup_data();
				WFFN_Core()->thank_you_pages->data->component_order_details = $this;
				echo '<div class="wfty_wrap wfty_frontend_view">';
				include __DIR__ . '/views/view.php';
				echo '</div>';
			}
		}

		/**
		 * Wrap price HTML with LTR isolate for correct currency display in RTL mode.
		 *
		 * @param string                $formatted_subtotal Formatted line subtotal.
		 * @param WC_Order_Item_Product $item Order item.
		 * @param WC_Order              $order Order object.
		 * @return string
		 */
		public function wrap_price_rtl( $formatted_subtotal, $item, $order ) {
			return "\u{2066}" . $formatted_subtotal . "\u{2069}";
		}

		/**
		 * Wrap order totals values with LTR isolate for correct currency display in RTL mode.
		 *
		 * @param array    $total_rows Order totals.
		 * @param WC_Order $order Order object.
		 * @param string   $tax_display Tax display type.
		 * @return array
		 */
		public function wrap_order_totals_rtl( $total_rows, $order, $tax_display ) {
			foreach ( $total_rows as $key => $row ) {
				if ( isset( $row['value'] ) && ! empty( $row['value'] ) ) {
					$total_rows[ $key ]['value'] = "\u{2066}" . $row['value'] . "\u{2069}";
				}
			}
			return $total_rows;
		}

		public function render_dummy() {
			$this->setup_data();
			echo '<div class="wfty_wrap">';
			include __DIR__ . '/views/view_dummy.php';
			echo '</div>';
		}
	}
}
