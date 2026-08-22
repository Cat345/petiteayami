<?php
defined( 'ABSPATH' ) || exit;

/**
 * Compatibility: Wompi Portal de Pagos (wompi-portal-de-pagos) — order-status on the thank-you page.
 *
 * Wompi announces non-successful order states (cancelled/refunded/voided) on the native WooCommerce
 * order-received page via the standard woocommerce_thankyou_order_received_text filter. FunnelKit's
 * thank-you page is built from the merchant's own content ("Your order is confirmed" etc.) and never
 * applies that filter, so the state was invisible and contradicted by the success wording.
 *
 * Loaded only when Wompi is active (see WFFN_Plugin_Compatibilities::load_all_compatibilities). Pure
 * hooks — no core template/logic edit. For cancelled/refunded/voided orders it:
 *   1. adds body classes (wffn-thankyou-order-unpaid + wffn-thankyou-order-{status}),
 *   2. renders Wompi's status message as a prominent banner at the top of the funnel content, and
 *   3. hides the merchant's "order confirmed" success block so it does not contradict the status
 *      notice. The success wording is authored page-builder content (no plugin class marks it), so it
 *      is located by the order email it contains (language-independent, not a generated widget id) and
 *      its enclosing .bwf-section-wrap is hidden. Sites can instead target their own selector via the
 *      `wffn_thankyou_hide_success_selector` filter.
 * `failed` is left to FunnelKit's own "pay again" notice in the order-details component.
 */
if ( ! class_exists( 'WFFN_Wompi_Compatibility' ) ) {

	#[\AllowDynamicProperties]
	class WFFN_Wompi_Compatibility {

		public function __construct() {
			add_filter( 'body_class', array( $this, 'add_status_body_class' ), 9999 );
			add_action( 'wp_footer', array( $this, 'maybe_render_status_banner' ), 5 );
		}

		public static function get_plugin_nicename() {
			return 'Wompi Portal de Pagos';
		}

		/**
		 * Statuses treated as "not successfully paid" for this notice.
		 *
		 * @return array
		 */
		protected function statuses() {
			return apply_filters( 'wffn_thankyou_order_status_notice_statuses', array( 'cancelled', 'refunded', 'voided' ) );
		}

		/**
		 * Resolve the current thank-you order when it is in a non-successful state.
		 *
		 * @return WC_Order|false
		 */
		protected function get_unpaid_order() {
			if ( ! class_exists( 'WFTY_Data' ) || ! function_exists( 'WFFN_Core' ) ) {
				return false;
			}
			if ( ! WFFN_Core()->thank_you_pages->is_wfty_page() ) {
				return false;
			}
			$order = WFTY_Data::get_instance()->get_order();
			if ( ! $order instanceof WC_Order || ! $order->has_status( $this->statuses() ) ) {
				return false;
			}

			return $order;
		}

		/**
		 * Flag the page so success wording can be hidden with CSS.
		 *
		 * @param array $classes Body classes.
		 *
		 * @return array
		 */
		public function add_status_body_class( $classes ) {
			$order = $this->get_unpaid_order();
			if ( $order instanceof WC_Order ) {
				$classes[] = 'wffn-thankyou-order-unpaid';
				$classes[] = 'wffn-thankyou-order-' . sanitize_html_class( $order->get_status() );
			}

			return $classes;
		}

		/**
		 * Print the gateway status message as a top banner, moved to the top of the funnel content
		 * client-side (there is no server-side "before content" hook on the thank-you page).
		 *
		 * @return void
		 */
		public function maybe_render_status_banner() {
			$order = $this->get_unpaid_order();
			if ( ! $order instanceof WC_Order ) {
				return;
			}

			$notice = apply_filters( 'woocommerce_thankyou_order_received_text', '', $order );
			if ( empty( $notice ) ) {
				/* translators: %s: order status. */
				$notice = '<div class="woocommerce-error">' . sprintf( esc_html__( 'This order changed to status &ldquo;%s&rdquo;. Please contact us if you need assistance.', 'funnel-builder' ), esc_html( wc_get_order_status_name( $order->get_status() ) ) ) . '</div>';
			}
			?>
			<style>
				body.wffn-thankyou-order-unpaid .wffn-thankyou-status-banner { margin: 0 0 24px; }
				body.wffn-thankyou-order-unpaid .wffn-thankyou-status-banner .woocommerce-error,
				body.wffn-thankyou-order-unpaid .wffn-thankyou-status-banner > * { margin: 0; }
			</style>
			<div class="wffn-thankyou-status-banner wfty-order-notice" style="display:none;"><?php echo wp_kses_post( $notice ); ?></div>
			<script>
				( function () {
					var banner = document.querySelector( '.wffn-thankyou-status-banner' );
					if ( ! banner ) { return; }
					var target = document.querySelector( '.wfty_wrap' ) || document.querySelector( '.wffn_wfty_main' ) || document.querySelector( 'main' );
					if ( target && banner.parentNode !== target ) {
						target.insertBefore( banner, target.firstChild );
					}
					banner.style.display = '';
				} )();
			</script>
			<?php
			$this->maybe_hide_success_block( $order );
		}

		/**
		 * Hide the merchant's "order confirmed" success block for an unpaid order.
		 *
		 * The success wording is page-builder content, so there is no plugin class to target. A site may
		 * supply its own selector via the `wffn_thankyou_hide_success_selector` filter (recommended: add a
		 * stable CSS class to that section in the builder). Otherwise the block is auto-detected by the
		 * order email it contains — language-independent (Google Translate leaves an address untouched) and
		 * not a generated widget id — and its enclosing .bwf-section-wrap is flagged for hiding. Detection
		 * runs once at load with a short bounded retry (the headings are server-rendered, so no observer is
		 * needed) and stops as soon as the block is found.
		 *
		 * @param WC_Order $order Current unpaid order.
		 *
		 * @return void
		 */
		protected function maybe_hide_success_block( $order ) {
			$selector = (string) apply_filters( 'wffn_thankyou_hide_success_selector', '', $order );
			$email    = $order->get_billing_email();
			?>
			<style>
				<?php if ( '' !== $selector ) : ?>
					<?php echo esc_html( $selector ); ?> { display: none !important; }
				<?php endif; ?>
				.fk-thankyou-hide-success { display: none !important; }
			</style>
			<?php if ( '' !== $email ) : ?>
			<script>
				( function () {
					var EMAIL = <?php echo wp_json_encode( $email ); ?>;
					function hunt() {
						var heads = document.querySelectorAll( '.bwf-adv-heading' );
						for ( var i = 0; i < heads.length; i++ ) {
							if ( ( heads[ i ].textContent || '' ).indexOf( EMAIL ) > -1 ) {
								var sec = heads[ i ].closest( '.bwf-section-wrap' ) || heads[ i ].parentNode;
								if ( sec ) { sec.classList.add( 'fk-thankyou-hide-success' ); }
								return true;
							}
						}
						return false;
					}
					// Server-rendered content: found immediately in the common case. A few bounded retries
					// cover any delayed builder hydration / Google Translate pass, then it stops for good.
					if ( hunt() ) { return; }
					if ( 'loading' === document.readyState ) {
						document.addEventListener( 'DOMContentLoaded', hunt, { once: true } );
					}
					var tries = 0;
					var timer = setInterval( function () {
						if ( hunt() || ++tries >= 5 ) { clearInterval( timer ); }
					}, 300 );
				} )();
			</script>
			<?php endif; ?>
			<?php
		}
	}

	if ( function_exists( 'wffn_is_wc_active' ) && wffn_is_wc_active() ) {
		WFFN_Plugin_Compatibilities::register( new WFFN_Wompi_Compatibility(), 'wompi' );
	}
}
