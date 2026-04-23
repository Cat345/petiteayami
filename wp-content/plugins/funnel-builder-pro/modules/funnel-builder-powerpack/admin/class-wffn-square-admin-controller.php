<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFFN_Square_Admin_Controller' ) ) {
	#[AllowDynamicProperties]
	class WFFN_Square_Admin_Controller {
		private static $instance = null;

		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public function __construct() {
			if ( false === wffn_is_wc_active() ) {
				return;
			}

			if ( ! method_exists( WFFN_Common::class, 'square_state' ) ) {
				return;
			}

			if ( current_user_can( 'install_plugins' ) ) {
				add_action( 'wp_before_admin_bar_render', array( $this, 'maybe_add_square_menu' ), 99 );
			}
			add_action( 'wp_ajax_wffn_dismiss_square_notice', array( $this, 'dismiss_square_notice' ) );
		}

		public function maybe_add_square_menu() {
			$square_state = WFFN_Common::square_state();

			if ( empty( $square_state['status'] ) || in_array( $square_state['status'], array( 'connected', 'gate_not_met' ), true ) ) {
				return;
			}

			if ( WFFN_Core()->admin_notifications->is_user_dismissed( get_current_user_id(), 'square-menu-button' ) ) {
				return;
			}

			$first_version = get_option( 'wffn_first_v', '0.0.0' );

			/**
			 * Check if its the existing user or the new one.
			 * If newly installed, don't show the notice for 24 hours.
			 */
			if ( true === version_compare( $first_version, WFFN_VERSION, '=' ) ) {
				$adl     = WFFN_Admin::get_instance()->get_lite_activation_date();
				$now     = new DateTime( 'now' );
				$adlDate = new DateTime( $adl );

				if ( 24 > ( ( $now->getTimestamp() - $adlDate->getTimestamp() ) / 3600 ) ) {
					return;
				}
			}

			global $wp_admin_bar;
			$indicator = "<svg width='20' height='20' viewBox='0 0 502 502' fill='none' xmlns='http://www.w3.org/2000/svg'><path d='M501.43 83.79V417.63C501.43 463.9 463.93 501.42 417.64 501.42H83.79C37.51 501.42 0 463.92 0 417.63V83.79C0 37.52 37.52 0 83.79 0H417.63C463.92 0 501.42 37.5 501.42 83.79H501.43ZM410.23 117.65C410.23 103.04 398.38 91.2 383.78 91.2H117.63C103.02 91.2 91.18 103.04 91.18 117.65V383.84C91.18 398.45 103.02 410.29 117.63 410.29H383.8C398.41 410.29 410.25 398.44 410.25 383.84V117.65H410.23ZM182.32 197.6C182.32 189.17 189.11 182.34 197.49 182.34H303.89C312.28 182.34 319.06 189.18 319.06 197.6V303.84C319.06 312.27 312.31 319.1 303.89 319.1H197.49C189.1 319.1 182.32 312.26 182.32 303.84V197.6Z' fill='black'/></svg> Square";
			$wp_admin_bar->add_menu(
				array(
					'id'    => 'funnelkit-square-menu',
					'title' => $indicator,
					'href'  => admin_url( 'admin.php?page=bwf&path=/square-connect' ),
				)
			);

			$this->get_style();
			add_action( 'admin_footer', array( $this, 'admin_print_script' ) );
		}

		public function get_style() {
			?>
			<style>
				#wp-admin-bar-funnelkit-square-menu a {
					box-sizing: border-box;
					display: inline-flex !important;
					align-items: center;
					min-height: 32px;
					gap: 4px;
					padding: 0 8px !important;
					color: #ffffff !important;
					position: relative !important;
				}

				#wpadminbar:not(.mobile) .ab-top-menu > li#wp-admin-bar-funnelkit-square-menu:hover > .ab-item {
					background: transparent;
				}

				#wp-admin-bar-funnelkit-square-menu a > svg {
					border-radius: 4px;
				}

				#wp-admin-bar-funnelkit-square-menu svg {
					border-radius: 4px !important;
					background: #ffffff;
				}

				.fk-square-tooltip .wp-pointer-content {
					padding: 0 0 12px;
				}

				.fk-square-tooltip .wp-pointer-content h3:before {
					height: 20px;
					width: 20px;
					font-size: 14px;
				}

				.fk-square-tooltip .wp-pointer-content h3 {
					font-size: 13px;
					line-height: 20px;
					font-weight: 500;
					margin: 0;
					padding: 8px 12px 8px 42px;
					height: 36px;
					box-sizing: border-box;
				}

				.fk-square-tooltip .wp-pointer-content p {
					padding: 12px 12px 0;
					margin: 0;
				}

				.fk-square-tooltip .wp-pointer-arrow {
					left: 50%;
					transform: translateX(-50%);
					top: 1px;
				}

				.fk-square-tooltip .wp-pointer-content ul {
					padding-left: 32px;
					margin: 6px 0 0;
					padding-right: 12px;
				}

				.fk-square-tooltip .wp-pointer-content p,
				.fk-square-tooltip .wp-pointer-content li {
					font-size: 12px;
					line-height: 20px;
				}

				.fk-square-tooltip .wp-pointer-content li {
					list-style: disc;
					margin-bottom: 0;
				}


				/**
				 * RTL
				 */
				body.rtl .fk-square-tooltip .wp-pointer-content {
					padding: 0 0 12px;
				}

				body.rtl .fk-square-tooltip .wp-pointer-content h3:before {
					height: 20px;
					width: 20px;
					font-size: 14px;
				}

				body.rtl .fk-square-tooltip .wp-pointer-content h3 {
					font-size: 13px;
					line-height: 20px;
					font-weight: 500;
					margin: 0;
					padding: 8px 42px 8px 12px;
					height: 36px;
					box-sizing: border-box;
				}

				body.rtl .fk-square-tooltip .wp-pointer-content p {
					padding: 12px 12px 0;
					margin: 0;
				}

				body.rtl .fk-square-tooltip .wp-pointer-arrow {
					right: 50%;
					transform: translateX(50%);
					top: 1px;
				}

				body.rtl .fk-square-tooltip .wp-pointer-content ul {
					padding-right: 32px;
					margin: 6px 0 0;
					padding-left: 12px;
				}

				body.rtl .fk-square-tooltip .wp-pointer-content p,
				body.rtl .fk-square-tooltip .wp-pointer-content li {
					font-size: 12px;
					line-height: 20px;
				}

				body.rtl .fk-square-tooltip .wp-pointer-content li {
					list-style: disc;
					margin-bottom: 0;
				}

				body.rtl .fk-square-tooltip .wp-pointer-buttons {
					right: auto;
					left: 12px;
				}

				/**
				 * RTL
				 */


				.fk-square-tooltip .wp-pointer-buttons {
					position: absolute;
					bottom: 12px;
					right: 12px;
					height: 30px;
					box-sizing: border-box;
					display: flex;
					align-items: center;
					padding: 5px 0 5px 15px;
				}

				.fk-square-tooltip .wp-pointer-buttons .close {
					font-size: 12px;
					line-height: 18px;
					padding-left: 4px;
					color: #787c82;
				}

				.fk-square-tooltip .wp-pointer-buttons a.close:before {
					line-height: 16px;
					width: 16px;
				}

				.fk-square-tooltip .wp-pointer-buttons a.close:hover:before {
					color: #787c82;
				}

				.fk-square-tooltip .button {
					margin: 16px 12px 0;
					padding: 0 12px;
					border-radius: 4px;
				}

				.fk-square-tooltip.wp-pointer-top {
					padding-top: 8px;
				}

				.fk-square-tooltip.wp-pointer-top .wp-pointer-arrow-inner {
					margin-top: -18px;
				}

				.fk-loading-ring {
					position: relative;
					width: 24px;
					height: 24px;
					margin: auto;
				}

				.fk-loading-ring div {
					box-sizing: border-box;
					display: block;
					position: absolute;
					width: calc(24px - 4px);
					height: calc(24px - 4px);
					margin: 2px;
					border: 2px solid #0073aa;
					border-radius: 50%;
					animation: fk-loading-ring 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
					border-color: #0073aa transparent transparent transparent;
				}

				.fk-loading-ring div:nth-child(1) {
					animation-delay: -0.45s;
				}

				.fk-loading-ring div:nth-child(2) {
					animation-delay: -0.3s;
				}

				.fk-loading-ring div:nth-child(3) {
					animation-delay: -0.15s;
				}

				.fk-loading-ring div.color-white {
					border: 2px solid #fff;
					border-color: #fff transparent transparent transparent;
				}

				@keyframes fk-loading-ring {
					0% {
						transform: rotate(0deg);
					}
					100% {
						transform: rotate(360deg);
					}
				}

				.fk-square-tooltip button {
					position: relative;
				}

				.fk-square-tooltip button.is-busy span {
					visibility: hidden;
				}

				.fk-square-tooltip button.is-busy:disabled {
					background: #0073aa !important;
					color: #ffffff !important;
				}

				.fk-square-tooltip button .fk-loading-ring {
					position: absolute;
					left: 50%;
					top: 50%;
					transform: translate(-50%, -50%);
				}
			</style>
			<?php
		}

		public function admin_print_script() {
			$square_state = WFFN_Common::square_state();

			if ( empty( $square_state['status'] ) || in_array( $square_state['status'], array( 'connected', 'gate_not_met' ), true ) ) {
				return;
			}

			if ( WFFN_Core()->admin_notifications->is_user_dismissed( get_current_user_id(), 'square-menu-button' ) ) {
				return;
			}

			wp_enqueue_script( 'wp-api' );
			wp_enqueue_script( 'wp-pointer' );
			wp_enqueue_style( 'wp-pointer' );

			?>
			<script>
				jQuery(document).ready(function ($) {
					let wffnSquareToolBarHTML = `
	<h3><?php echo esc_html__( 'Setup FunnelKit Square (Recommended)', 'funnel-builder' ); ?></h3>
	<p><?php echo esc_html__( 'Use FunnelKit Square for maximum compatibility and trustworthy support', 'funnel-builder' ); ?></p>
	<ul>
	<li><?php echo esc_html__( 'Accept Credit Cards, Apple Pay, and Google Pay', 'funnel-builder' ); ?></li>
	<li><?php echo esc_html__( 'Accept ACH, Gift Cards, Afterpay, and Cash App', 'funnel-builder' ); ?></li>
	<li><?php echo esc_html__( 'Supports one-click upsells with Square orders', 'funnel-builder' ); ?></li>
	<li><?php echo esc_html__( 'Get personalized support for Square payment setup', 'funnel-builder' ); ?></li>
	</ul>
			<?php if ( 'not_connected' === $square_state['status'] && ! empty( $square_state['link'] ) ) { ?>
	<a href="<?php echo esc_url( $square_state['link'] ); ?>" class="button button-primary"><?php echo esc_html__( 'Connect', 'funnel-builder' ); ?></a>
	<?php } elseif ( 'not_activated' === $square_state['status'] ) { ?>
	<button class="button button-primary is-square is-activate"><span><?php echo esc_html__( 'Activate', 'funnel-builder' ); ?></span></button>
	<?php } else { ?>
	<button class="button button-primary is-square is-activate"><span><?php echo esc_html__( 'Install', 'funnel-builder' ); ?></span></button>
	<?php } ?>
`;

					$('#wp-admin-bar-funnelkit-square-menu').pointer({
						"content": wffnSquareToolBarHTML,
						"buttons": function (event, t) {
							var redirectUrl = '<?php echo esc_url_raw( admin_url( 'admin-ajax.php?action=wffn_dismiss_square_notice&nonce=' . wp_create_nonce( 'wp_wffn_dismiss_square_notice' ) . '&redirect=' . urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) ); //phpcs:ignore ?>';
							var button = $('<a class="close" href="' + redirectUrl + '" onclick="window.location.href=\'' + redirectUrl + '\'"></a>').text(wp.i18n.__('Dismiss Forever', 'funnel-builder'));

							return button.on('click.pointer', function (e) {
								e.preventDefault();
								jQuery('#wp-admin-bar-funnelkit-square-menu').remove();
								window.location.href = redirectUrl;
								t.element.pointer('close');
							});
						},
						"position": {"edge": "top", "align": "center"},
						"pointerClass": "fk-square-tooltip",
						"pointerWidth": 320,
					}).pointer('open');

					// Api call function
					const apiService = (path = '', method = 'GET', data) => {
						return new Promise((resolve, reject) => {
							jQuery.ajax({
								url: wpApiSettings.root + path,
								type: method,
								data: data,
								beforeSend: function (xhr) {
									xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
								},
								dataType: 'json',
								contentType: 'application/json',
								success: resolve,
								error: reject
							});
						});
					};

					const loadingRing = '<div class="fk-loading-ring"><div style="border-color: rgb(255, 255, 255) transparent transparent;"></div><div style="border-color: rgb(255, 255, 255) transparent transparent;"></div><div style="border-color: rgb(255, 255, 255) transparent transparent;"></div><div style="border-color: rgb(255, 255, 255) transparent transparent;"></div></div>';

					// plugin activate call
					const bindActivate = () => jQuery('.fk-square-tooltip .is-square.is-activate').click(function () {
						const btn = jQuery(this);
						const btnPrevState = btn.clone();
						btn.addClass('is-busy').prop('disabled', true).append(loadingRing);

						apiService('funnelkit-app/activate_plugin', 'POST', JSON.stringify({
							basename: 'funnelkit-payment-gateway-square-for-woocommerce/funnelkit-square.php',
							slug: 'funnelkit-payment-gateway-square-for-woocommerce',
						})).then((res) => {
							if (res.next_action) {
								apiService(res.next_action, 'GET').then((resp) => {
									if (resp.link) {
										jQuery('.fk-square-tooltip button.is-activate').replaceWith('<a href="' + resp.link + '" class="button button-primary is-square">Connect</a>');
									} else {
										window.location.href = <?php echo wp_json_encode( esc_url_raw( $this->get_square_settings_link() ) ); ?>;         									}
								}).catch((e) => {
									btn.replaceWith(btnPrevState);
									bindActivate();
									console.log(e.responseJSON);
								});
							}
						}).catch((e) => {
							btn.replaceWith(btnPrevState);
							bindActivate();
							console.log(e.responseJSON);
						});
					});

					bindActivate();
				});
			</script>
			<?php
		}

		public function dismiss_square_notice() {
			$notice_key = 'square-menu-button';

			if ( ! ( defined( 'DOING_AJAX' ) && DOING_AJAX && current_user_can( 'manage_options' ) && isset( $_REQUEST['nonce'] ) && false !== check_ajax_referer( 'wp_wffn_dismiss_square_notice', 'nonce', false ) ) ) {
				wp_die( -1, 403 );
			}

			$userdata   = get_user_meta( get_current_user_id(), '_bwf_notifications_close', true );
			$userdata   = empty( $userdata ) && ! is_array( $userdata ) ? array() : $userdata;
			$userdata[] = $notice_key;

			update_user_meta( get_current_user_id(), '_bwf_notifications_close', array_values( array_unique( $userdata ) ) ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.user_meta_update_user_meta

			$redirect = isset( $_REQUEST['redirect'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect'] ) ) : null;

			wp_safe_redirect( $redirect ?? admin_url( 'admin.php?page=bwf&path=/funnels' ) );
			exit;
		}

		public function get_square_settings_link() {
			return admin_url( 'admin.php?page=wc-settings&tab=fkwcsq_square_api' );
		}
	}
}

WFFN_Square_Admin_Controller::get_instance();
