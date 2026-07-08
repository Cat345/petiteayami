<?php
defined( 'ABSPATH' ) || exit;

/**
 * Adds a FunnelKit tab to WooCommerce product edit page
 * with recommended addon notices (e.g., Sublium Subscriptions).
 *
 * Smart detection: If checkout pro already registered a FunnelKit tab
 * (fk_checkout_redirect), we inject into that panel instead of creating a new one.
 */
#[\AllowDynamicProperties]
class WFFN_Product_Tab_Addon {

	private static $ins          = null;
	private $addon_notices_cache = null;

	public static function get_instance() {
		if ( null === self::$ins ) {
			self::$ins = new self();
		}
		return self::$ins;
	}

	public function __construct() {
		// Register tab at priority 91 so checkout pro (priority default/80) runs first
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_data_tab' ), 91 );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_data_panel' ) );
		add_action( 'admin_head', array( $this, 'output_styles' ) );
		add_action( 'admin_footer', array( $this, 'output_scripts' ) );
	}

	private function is_product_edit_screen() {
		$screen = get_current_screen();
		return $screen && 'product' === $screen->post_type;
	}

	/**
	 * Check if there are any addon notices to display.
	 */
	private function has_addon_notices() {
		if ( null !== $this->addon_notices_cache ) {
			return $this->addon_notices_cache;
		}
		$sublium_status = WFFN_Common::get_plugin_status( 'sublium-subscriptions-for-woocommerce/sublium-subscriptions-for-woocommerce.php' );
		if ( 'activated' === $sublium_status ) {
			$this->addon_notices_cache = false;
			return false;
		}
		$user_id   = get_current_user_id();
		$userdata  = get_user_meta( $user_id, '_bwf_notifications_close', true ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.user_meta_get_user_meta
		$dismissed = is_array( $userdata ) && in_array( 'sublium_product_addon', $userdata, true );

		$this->addon_notices_cache = ! $dismissed;
		return $this->addon_notices_cache;
	}

	/**
	 * Check if checkout pro already registered a FunnelKit tab.
	 */
	private function checkout_tab_exists( $tabs ) {
		return isset( $tabs['fk_checkout_redirect'] );
	}

	/**
	 * Register the FunnelKit tab only if checkout pro hasn't already.
	 */
	public function add_product_data_tab( $tabs ) {
		if ( ! $this->has_addon_notices() ) {
			return $tabs;
		}

		// If checkout pro already registered a FunnelKit tab, don't add another
		if ( $this->checkout_tab_exists( $tabs ) ) {
			return $tabs;
		}

		// Register our own tab
		$tabs['funnelkit'] = array(
			'label'    => __( 'FunnelKit', 'funnel-builder' ),
			'target'   => 'funnelkit_product_data',
			'class'    => array(),
			'priority' => 90,
		);
		return $tabs;
	}

	/**
	 * Render the panel content.
	 * If checkout pro exists, we inject into its panel via JS.
	 * Otherwise we render our own panel.
	 */
	public function render_product_data_panel() {
		if ( ! $this->has_addon_notices() ) {
			// Still need to output empty panel if our tab key was registered
			if ( ! class_exists( 'FK_Checkout_Redirect_Admin' ) ) {
				return;
			}
			return;
		}

		$has_checkout = class_exists( 'FK_Checkout_Redirect_Admin' );

		if ( ! $has_checkout ) {
			// Our own standalone panel
			echo '<div id="funnelkit_product_data" class="panel woocommerce_options_panel hidden">';
			$this->render_sublium_addon_notice();
			echo '</div>';
		} else {
			// Checkout pro panel exists — render addon notice in a hidden div, JS will move it
			echo '<div id="wffn-sublium-addon-inject" style="display:none;">';
			$this->render_sublium_addon_notice();
			echo '</div>';
		}
	}

	/**
	 * Render Sublium recommended addon notice.
	 */
	private function render_sublium_addon_notice() {
		$sublium_status = WFFN_Common::get_plugin_status( 'sublium-subscriptions-for-woocommerce/sublium-subscriptions-for-woocommerce.php' );

		if ( 'activated' === $sublium_status ) {
			return;
		}

		$user_id  = get_current_user_id();
		$userdata = get_user_meta( $user_id, '_bwf_notifications_close', true ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.user_meta_get_user_meta
		if ( is_array( $userdata ) && in_array( 'sublium_product_addon', $userdata, true ) ) {
			return;
		}

		$button_text = ( 'install' === $sublium_status ) ? __( 'Install', 'funnel-builder' ) : __( 'Activate', 'funnel-builder' );
		?>
		<div class="wffn-product-addon-notice-wrap" id="wffn-sublium-addon-notice">
			<div class="wffn-product-addon-notice__header">
				<strong><?php echo esc_html__( 'Recommended Addon', 'funnel-builder' ); ?></strong>
			</div>
			<div class="wffn-product-addon-notice">
			<div class="wffn-product-addon-notice__icon">
				<svg width="44" height="44" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
					<rect width="44" height="44" rx="8" fill="#563AF3"/>
					<g clip-path="url(#sublium_prodtab_c)">
						<path d="M19.323 18.9924L18.8754 23.0592C18.8531 23.2624 18.6307 23.3765 18.4535 23.2745L18.3463 23.2128C14.4901 20.9915 8.99051 18.3972 10.4558 14.1951C10.7719 13.2886 11.2965 12.6676 11.8836 12.2523C13.4941 11.113 15.6065 11.9775 17.3603 12.8806C19.6359 14.0524 21.8289 15.2124 20.9649 14.8821C19.7472 14.4165 19.4773 17.3273 19.3242 18.9788L19.323 18.9924Z" fill="white"/>
						<path d="M22.3806 7.16118L33.191 13.0153C33.6892 13.2851 33.9996 13.8061 33.9996 14.3727C33.9996 18.3005 29.7549 20.7629 26.3418 18.8191C20.5287 15.5085 14.5371 12.1164 14.1249 11.9548C13.5134 11.7151 12.4942 11.9972 12.1375 12.0971C14.3797 10.7489 19.2618 7.79038 20.2402 7.31099C21.2187 6.8316 22.0748 7.01137 22.3806 7.16118Z" fill="white"/>
						<path d="M24.677 25.0076L25.1246 20.9408C25.1469 20.7376 25.3693 20.6235 25.5465 20.7255L25.6537 20.7872C29.5099 23.0085 35.0095 25.6028 33.5442 29.8049C33.2281 30.7114 32.7035 31.3324 32.1164 31.7477C30.5059 32.887 28.3935 32.0225 26.6397 31.1194C24.3641 29.9476 22.1711 28.7876 23.0351 29.1179C24.2528 29.5835 24.5227 26.6727 24.6758 25.0212L24.677 25.0076Z" fill="white"/>
						<path d="M21.6192 36.8388L10.8088 30.9847C10.3105 30.7149 10.0002 30.1939 10.0002 29.6273C10.0002 25.6995 14.2449 23.2371 17.6579 25.1809C23.471 28.4915 29.4627 31.8836 29.8748 32.0452C30.4864 32.2849 31.5056 32.0028 31.8623 31.9029C29.62 33.2511 24.738 36.2096 23.7595 36.689C22.7811 37.1684 21.925 36.9886 21.6192 36.8388Z" fill="white"/>
					</g>
					<defs><clipPath id="sublium_prodtab_c"><rect width="24" height="30" fill="white" transform="translate(10 7)"/></clipPath></defs>
				</svg>
			</div>
			<div class="wffn-product-addon-notice__content">
				<span class="wffn-product-addon-notice__title"><?php echo esc_html__( 'Sublium Subscriptions', 'funnel-builder' ); ?></span>
				<span class="wffn-product-addon-notice__desc"><?php echo esc_html__( 'Sublium is modern subscription plugin that lets you turn one time sales into recurring revenue.', 'funnel-builder' ); ?></span>
			</div>
			<div class="wffn-product-addon-notice__actions">
				<button type="button" class="button wffn-sublium-install-btn" data-status="<?php echo esc_attr( $sublium_status ); ?>">
					<?php echo esc_html( $button_text ); ?>
				</button>
				<button type="button" class="wffn-sublium-dismiss-btn" title="<?php echo esc_attr__( 'Dismiss', 'funnel-builder' ); ?>">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Output inline styles on product edit screens.
	 */
	public function output_styles() {
		if ( ! $this->is_product_edit_screen() ) {
			return;
		}
		if ( ! $this->has_addon_notices() ) {
			return;
		}

		$has_checkout = class_exists( 'FK_Checkout_Redirect_Admin' );
		?>
		<style>
			<?php if ( ! $has_checkout ) : ?>
			/* Tab icon — only when we own the tab */
			#woocommerce-product-data ul.wc-tabs li.funnelkit_options a {
				display: flex;
				align-items: center;
				justify-content: start;
			}
			#woocommerce-product-data ul.wc-tabs li.funnelkit_options a:before,
			#woocommerce-product-data ul.wc-tabs li.funnelkit_options.active a:before {
				content: '';
				display: inline-block;
				width: 13px;
				height: 14px;
				margin-right: 0;
				background-repeat: no-repeat;
				background-position: center;
				background-size: contain;
				background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 231 141'%3E%3Cpolygon fill='%231DAAFC' points='229.3,2.3 147.7,141.6 115.2,141.6 196.3,2.3'/%3E%3Cpolygon fill='%23070045' points='164.7,2.3 84.1,141.6 2.6,2.3 100.2,2.3 85.6,30.9 83.7,30.9 52.1,30.9 83.7,86.8 131.7,2.3'/%3E%3C/svg%3E");
				filter: brightness(0) saturate(100%) invert(39%) sepia(67%) saturate(459%) hue-rotate(164deg) brightness(93%) contrast(116%);
			}
			#woocommerce-product-data ul.wc-tabs li.funnelkit_options.active a:before {
				filter: brightness(0) saturate(100%) invert(32%) sepia(1%) saturate(0%) hue-rotate(135deg) brightness(96%) contrast(84%);
			}
			<?php endif; ?>

			/* Addon notice styles */
			.wffn-product-addon-notice-wrap {
				padding: 0 0 16px;
			}
			.wffn-product-addon-notice__header {
				padding: 12px 16px;
				margin-bottom: 16px;
				border-bottom: 1px solid #e0e0e0;
				font-size: 14px;
				color: #1d2327;
			}
			<?php if ( $has_checkout ) : ?>
			/* When injected into checkout panel, add top separator */
			#fk_checkout_redirect_data .wffn-product-addon-notice__header {
				border-top: 1px solid #e0e0e0;
				margin-top: 16px;
				padding-top: 16px;
			}
			/* Add "Settings" header to existing checkout content */
			#fk_checkout_redirect_data .wffn-fk-settings-header {
				padding: 12px 16px;
				border-bottom: 1px solid #e0e0e0;
				font-size: 14px;
				font-weight: 700;
				color: #1d2327;
			}
			<?php endif; ?>
			.wffn-product-addon-notice {
				display: flex;
				align-items: flex-start;
				gap: 16px;
				padding: 16px;
			}
			.wffn-product-addon-notice__icon {
				flex-shrink: 0;
			}
			.wffn-product-addon-notice__content {
				flex: 1;
				display: flex;
				flex-direction: column;
				gap: 4px;
			}
			.wffn-product-addon-notice__title {
				font-size: 16px;
				font-weight: 500;
				line-height: 24px;
				color: #353030;
			}
			.wffn-product-addon-notice__desc {
				font-size: 12px;
				font-weight: 400;
				line-height: 18px;
				color: #353030;
			}
			.wffn-product-addon-notice__actions {
				display: flex;
				align-items: center;
				gap: 24px;
				flex-shrink: 0;
				align-self: center;
			}
			.wffn-sublium-install-btn.button {
				background: #0073aa !important;
				border-color: #0073aa !important;
				color: #fff !important;
				min-height: 36px;
				padding: 8px 16px !important;
				font-size: 14px;
				font-weight: 500;
				line-height: 20px;
				border-radius: 4px;
				position: relative;
			}
			.wffn-sublium-install-btn.button:hover {
				background: #005f8d !important;
				border-color: #005f8d !important;
				color: #fff !important;
			}
			.wffn-sublium-install-btn.button.is-busy {
				color: transparent !important;
				background: #0073aa !important;
				border-color: #0073aa !important;
				opacity: 1 !important;
			}
			.wffn-sublium-dismiss-btn {
				background: none;
				border: none;
				cursor: pointer;
				padding: 4px;
				color: #82838E;
				display: flex;
				align-items: center;
				justify-content: center;
				line-height: 1;
			}
			.wffn-sublium-dismiss-btn:hover {
				color: #1d2327;
			}
			.wffn-sublium-dismiss-btn .dashicons {
				font-size: 20px;
				width: 20px;
				height: 20px;
			}
			.wffn-loading-ring {
				position: absolute;
				left: 50%;
				top: 50%;
				transform: translate(-50%, -50%);
				width: 20px;
				height: 20px;
			}
			.wffn-loading-ring div {
				box-sizing: border-box;
				display: block;
				position: absolute;
				width: 16px;
				height: 16px;
				margin: 2px;
				border: 2px solid #fff;
				border-radius: 50%;
				animation: wffn-loading-ring 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
				border-color: #fff transparent transparent transparent;
			}
			.wffn-loading-ring div:nth-child(1) { animation-delay: -0.45s; }
			.wffn-loading-ring div:nth-child(2) { animation-delay: -0.3s; }
			.wffn-loading-ring div:nth-child(3) { animation-delay: -0.15s; }
			@keyframes wffn-loading-ring {
				0% { transform: rotate(0deg); }
				100% { transform: rotate(360deg); }
			}
		</style>
		<?php
	}

	/**
	 * Output inline scripts on product edit screens.
	 */
	public function output_scripts() {
		if ( ! $this->is_product_edit_screen() ) {
			return;
		}
		if ( ! $this->has_addon_notices() ) {
			return;
		}

		$has_checkout = class_exists( 'FK_Checkout_Redirect_Admin' );
		$user_id      = get_current_user_id();
		?>
		<script>
			jQuery(document).ready(function($) {
				<?php if ( $has_checkout ) : ?>
				// Inject into checkout pro's panel: add "Settings" header before existing content, then append addon notice
				var $panel = $('#fk_checkout_redirect_data');
				if ($panel.length) {
					// Prepend "Settings" header before existing checkout content
					$panel.children().first().before('<div class="wffn-fk-settings-header"><strong><?php echo esc_js( __( 'Settings', 'funnel-builder' ) ); ?></strong></div>');

					// Move addon notice into the panel
					var $addon = $('#wffn-sublium-addon-inject').children();
					$panel.append($addon);
					$('#wffn-sublium-addon-inject').remove();
				}
				<?php endif; ?>

				$('.wffn-sublium-dismiss-btn').on('click', function() {
					var notice = $('#wffn-sublium-addon-notice');
					if (typeof wpApiSettings === 'undefined') {
						notice.fadeOut(300);
						return;
					}
					$.ajax({
						url: wpApiSettings.root + 'funnelkit-app/user-preference',
						type: 'POST',
						data: JSON.stringify({
							action: 'notice_close',
							key: 'sublium_product_addon',
							user_id: <?php echo intval( $user_id ); ?>
						}),
						contentType: 'application/json',
						beforeSend: function(xhr) {
							xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
						},
						complete: function() {
							notice.fadeOut(300);
						}
					});
				});

				var loadingRing = '<div class="wffn-loading-ring"><div></div><div></div><div></div><div></div></div>';

				$('.wffn-sublium-install-btn').on('click', function() {
					if (typeof wpApiSettings === 'undefined') return;
					var btn = $(this);
					var btnText = btn.text();
					var status = btn.data('status');
					btn.prop('disabled', true).addClass('is-busy').append(loadingRing);

					$.ajax({
						url: wpApiSettings.root + 'funnelkit-app/activate_plugin',
						type: 'POST',
						data: JSON.stringify({
							basename: 'sublium-subscriptions-for-woocommerce/sublium-subscriptions-for-woocommerce.php',
							slug: 'sublium-subscriptions-for-woocommerce'
						}),
						contentType: 'application/json',
						beforeSend: function(xhr) {
							xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
						},
						success: function() {
							btn.removeClass('is-busy').find('.wffn-loading-ring').remove();
							btn.text('<?php echo esc_js( __( 'Activated!', 'funnel-builder' ) ); ?>');
							setTimeout(function() {
								window.location.href = 'admin.php?page=sublium';
							}, 500);
						},
						error: function() {
							btn.prop('disabled', false).removeClass('is-busy').find('.wffn-loading-ring').remove();
							btn.text(btnText);
						}
					});
				});
			});
		</script>
		<?php
	}
}

