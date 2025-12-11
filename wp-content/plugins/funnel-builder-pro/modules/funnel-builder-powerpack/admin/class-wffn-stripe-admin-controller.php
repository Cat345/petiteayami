<?php

/**
 * Class to control Stripe functionalities
 */
if ( ! class_exists( 'WFFN_Stripe_Admin_Controller' ) ) {

	class WFFN_Stripe_Admin_Controller {

		private static $instance = null;

		public function __construct() {
			// Add your actions and filters here
			if ( false === wffn_is_wc_active() ) {
				return;
			}

			if ( current_user_can( 'install_plugins' ) ) {
				add_action( 'wp_before_admin_bar_render', [ $this, 'custom_add_fk_stripe_menu' ] );
			}
			add_action( 'wp_ajax_wffn_dismiss_stripe_notice', array( $this, 'ajax_dismiss_admin_notice' ) );
		}

		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		public function custom_add_fk_stripe_menu() {
			global $wp_admin_bar;
			$get_stripe_state = WFFN_Common::stripe_state();
			if ( $get_stripe_state['status'] === 'connected' || WFFN_Core()->admin_notifications->is_user_dismissed( get_current_user_id(), 'stripe-menu-button' ) ) {
				return;
			}

			$first_version = get_option( 'wffn_first_v', '0.0.0' );

			/**
			 * Check if its the existing user or the new one
			 * if old found we simply need to return from here as the notice should not be visible to them
			 */

			if ( true === version_compare( $first_version, WFFN_VERSION, '=' ) ) {

				$adl     = WFFN_Admin::get_instance()->get_lite_activation_date(); // Get the adl value
				$now     = new DateTime( 'now' );
				$adlDate = new DateTime( $adl );


				if ( 24 > ( ( $now->getTimestamp() - $adlDate->getTimestamp() ) / 3600 ) ) {
					return;
				}


			}

			$indicator = "<svg width='21' height='20' viewBox='0 0 21 20' fill='none' xmlns='http://www.w3.org/2000/svg'><rect x='0.259888' width='20.6504' height='20' rx='4' fill='white'/><path fill-rule='evenodd' clip-rule='evenodd' d='M9.96241 7.79563C9.96241 7.32809 10.3585 7.14827 11.0145 7.14827C11.9552 7.14827 13.1435 7.424 14.0842 7.91551V5.09832C13.0569 4.70272 12.0419 4.54688 11.0145 4.54688C8.50182 4.54687 6.83081 5.8176 6.83081 7.93948C6.83081 11.2482 11.5344 10.7207 11.5344 12.1473C11.5344 12.6987 11.0393 12.8785 10.3461 12.8785C9.31876 12.8785 8.00671 12.471 6.96697 11.9195V14.7726C8.11811 15.2522 9.28163 15.456 10.3461 15.456C12.9207 15.456 14.6908 14.2212 14.6908 12.0753C14.6784 8.50292 9.96241 9.13828 9.96241 7.79563Z'/></svg> Stripe";
			$this->get_style();
			$get_stripe_state = WFFN_Common::stripe_state();

			if ( $get_stripe_state['status'] !== 'connected' ) {
				$wp_admin_bar->add_menu( array(
					'id'    => 'funnelkit-stripe-menu',
					'title' => $indicator,
					'href'  => site_url() . '/wp-admin/admin.php?page=bwf&path=/stripe-connect',
				) );
			}

			add_action( 'admin_footer', [ $this, 'admin_print_script' ] );


		}

		public function get_style() {
			?>
            <style>
                #wp-admin-bar-funnelkit-stripe-menu a {
                    box-sizing: border-box;
                    display: inline-flex !important;
                    align-items: center;
                    min-height: 32px;
                    gap: 4px;
                    padding: 0 8px !important;
                    color: #ffffff !important;
                    position: relative !important;
                }

                #wpadminbar:not(.mobile) .ab-top-menu > li#wp-admin-bar-funnelkit-stripe-menu:hover > .ab-item {
                    background: transparent;
                }


                #wp-admin-bar-funnelkit-stripe-menu a > svg {
                    background: #6C63FF;
                    fill: #ffffff !important;
                    border-radius: 4px;
                }

                #wp-admin-bar-funnelkit-stripe-menu svg {
                    border-radius: 4px !important;
                    background: #ffffff;
                }


                .fk-stripe-tooltip .wp-pointer-content {
                    padding: 0 0 12px;
                }

                .fk-stripe-tooltip .wp-pointer-content h3:before {
                    height: 20px;
                    width: 20px;
                    font-size: 14px;
                }

                .fk-stripe-tooltip .wp-pointer-content h3 {
                    font-size: 13px;
                    line-height: 20px;
                    font-weight: 500;
                    margin: 0;
                    padding: 8px 12px 8px 42px;
                    height: 36px;
                    box-sizing: border-box;
                }

                .fk-stripe-tooltip .wp-pointer-content p {
                    padding: 12px 12px 0;
                    margin: 0;
                }

                .fk-stripe-tooltip .wp-pointer-arrow {
                    left: 50%;
                    transform: translateX(-50%);
                    top: 1px;
                }

                .fk-stripe-tooltip .wp-pointer-content ul {
                    padding-left: 32px;
                    margin: 6px 0 0;
                    padding-right: 12px;
                }

                .fk-stripe-tooltip .wp-pointer-content p,
                .fk-stripe-tooltip .wp-pointer-content li {
                    font-size: 12px;
                    line-height: 20px;
                }

                .fk-stripe-tooltip .wp-pointer-content li {
                    list-style: disc;
                    margin-bottom: 0;
                }


                /**
                * RTL
                */
                body.rtl .fk-stripe-tooltip .wp-pointer-content {
                    padding: 0 0 12px;
                }

                body.rtl .fk-stripe-tooltip .wp-pointer-content h3:before {
                    height: 20px;
                    width: 20px;
                    font-size: 14px;
                }

                body.rtl .fk-stripe-tooltip .wp-pointer-content h3 {
                    font-size: 13px;
                    line-height: 20px;
                    font-weight: 500;
                    margin: 0;
                    padding: 8px 42px 8px 12px; /* Swapped left and right padding */
                    height: 36px;
                    box-sizing: border-box;
                }

                body.rtl .fk-stripe-tooltip .wp-pointer-content p {
                    padding: 12px 12px 0;
                    margin: 0;
                }

                body.rtl .fk-stripe-tooltip .wp-pointer-arrow {
                    right: 50%; /* Changed left to right */
                    transform: translateX(50%); /* Adjusted translate direction */
                    top: 1px;
                }

                body.rtl .fk-stripe-tooltip .wp-pointer-content ul {
                    padding-right: 32px; /* Swapped left and right padding */
                    margin: 6px 0 0;
                    padding-left: 12px; /* Adjusted right padding */
                }

                body.rtl .fk-stripe-tooltip .wp-pointer-content p,
                body.rtl .fk-stripe-tooltip .wp-pointer-content li {
                    font-size: 12px;
                    line-height: 20px;
                }

                body.rtl .fk-stripe-tooltip .wp-pointer-content li {
                    list-style: disc;
                    margin-bottom: 0;
                }

                body.rtl .fk-stripe-tooltip .wp-pointer-buttons {

                    right: auto;
                    left: 12px;
                }

                /**
				* RTL
			    */


                .fk-stripe-tooltip .wp-pointer-buttons {
                    position: absolute;
                    bottom: 12px;
                    right: 12px;
                    height: 30px;
                    box-sizing: border-box;
                    display: flex;
                    align-items: center;
                    padding: 5px 0 5px 15px;
                }

                .fk-stripe-tooltip .wp-pointer-buttons .close {
                    font-size: 12px;
                    line-height: 18px;
                    padding-left: 4px;
                    color: #787c82;
                }

                .fk-stripe-tooltip .wp-pointer-buttons a.close:before {
                    line-height: 16px;
                    width: 16px;
                }

                .fk-stripe-tooltip .wp-pointer-buttons a.close:hover:before {
                    color: #787c82;
                }

                .fk-stripe-tooltip .button {
                    margin: 16px 12px 0;
                    padding: 0 12px;
                    border-radius: 4px;
                }

                .fk-stripe-tooltip.wp-pointer-top {
                    padding-top: 8px;
                }

                .fk-stripe-tooltip.wp-pointer-top .wp-pointer-arrow-inner {
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


                .fk-stripe-tooltip button {
                    position: relative;
                }

                .fk-stripe-tooltip button.is-busy span {
                    visibility: hidden;
                }

                .fk-stripe-tooltip button.is-busy:disabled {
                    background: #0073aa !important;
                    color: #ffffff !important;
                }

                .fk-stripe-tooltip button .fk-loading-ring {
                    position: absolute;
                    left: 50%;
                    top: 50%;
                    transform: translate(-50%, -50%);
                }

            </style>
			<?php
		}


		function admin_print_script() {


			$get_stripe_state = WFFN_Common::stripe_state();

			if ( $get_stripe_state['status'] === 'connected' || WFFN_Core()->admin_notifications->is_user_dismissed( get_current_user_id(), 'stripe-menu-button' ) ) {
				return;
			}
			wp_enqueue_script( 'wp-api' );

			wp_enqueue_script( 'wp-pointer' );
			wp_enqueue_style( 'wp-pointer' );


			?>
            <script>
                jQuery(document).ready(function ($) {
                    let wffnStripeToolBarHTML = `
  <h3><?php echo esc_html__( 'Setup FunnelKit Stripe (Recommended)', 'funnel-builder' ); ?></h3>
  <p><?php echo esc_html__( 'Use FunnelKit\'s Stripe for Maximum Compatibility & Trustworthy Support', 'funnel-builder' ); ?></p>
  <ul>
    <li> <?php echo esc_html__( 'Better Express Payment with Apple & Google Pay', 'funnel-builder' ); ?> </li>
    <li><?php echo esc_html__( 'Supports One Click Upsells with Express Pay Options', 'funnel-builder' ); ?> </li>
    <li><?php echo esc_html__( 'Personalised Support for any payments related issues', 'funnel-builder' ); ?></li>
    <li><?php echo esc_html__( 'Increase Revenue with Buy Now Pay Later services such as Affirm, Klarna and AfterPay', 'funnel-builder' ); ?></li>
  </ul>
  <?php if( $get_stripe_state['status'] === 'not_connected' && isset( $get_stripe_state['link'] ) ) { ?>
    <a href="<?php echo esc_url( $get_stripe_state['link'] ); ?>" class="button button-primary"><?php echo esc_html__( 'Connect', 'funnel-builder' ); ?></a>
  <?php } else if( $get_stripe_state['status'] === 'not_activated' ) { ?>
    <button class="button button-primary is-stripe is-activate"><span><?php echo esc_html__( 'Activate' ); ?></span></button>
  <?php } else { ?>
    <button class="button button-primary is-stripe is-activate"><span><?php echo esc_html__( 'Install' ); ?></span></button>
  <?php } ?>
`;


                    $('#wp-admin-bar-funnelkit-stripe-menu').pointer({
                        "content": wffnStripeToolBarHTML,
                        "buttons": function (event, t) {
                            var redirectUrl = '<?php echo( admin_url( 'admin-ajax.php?action=wffn_dismiss_stripe_notice&nstripekey=stripe-menu-button&nonce=' . wp_create_nonce( 'wp_wffn_dismiss_stripe_notice' ) . '&redirect=' . urlencode( $_SERVER['REQUEST_URI'] ) ) ); //phpcs:ignore ?>';
                            var button = $('<a class="close" href="' + redirectUrl + '" onclick="window.location.href=\'' + redirectUrl + '\'"></a>').text(wp.i18n.__('Dismiss Forever'));

                            return button.on('click.pointer', function (e) {
                                e.preventDefault();
                                jQuery('#wp-admin-bar-funnelkit-stripe-menu').remove();
                                window.location.href = redirectUrl;
                                t.element.pointer('close');
                            });
                        },
                        "position": {"edge": "top", "align": "center"},
                        "pointerClass": "fk-stripe-tooltip",
                        "pointerWidth": 310,
                    }).pointer('open');


                    //Api call function
                    const apiService = (path = "", method = "GET", data) => {
                        return new Promise((resolve, reject) => {
                            jQuery.ajax({
                                url: wpApiSettings.root + path,
                                type: method,
                                data: data,
                                beforeSend: function (xhr) {
                                    xhr.setRequestHeader("X-WP-Nonce", wpApiSettings.nonce);
                                },
                                dataType: "json",
                                contentType: "application/json",
                                success: resolve,
                                error: reject
                            });
                        });
                    };

                    const loadingRing = '<div class="fk-loading-ring"><div style="border-color: rgb(255, 255, 255) transparent transparent;"></div><div style="border-color: rgb(255, 255, 255) transparent transparent;"></div><div style="border-color: rgb(255, 255, 255) transparent transparent;"></div><div style="border-color: rgb(255, 255, 255) transparent transparent;"></div></div>';

                    // plugin activate call
                    const addClickEvent = () => jQuery(".fk-stripe-tooltip .is-stripe.is-activate").click(function () {
                        const btn = jQuery(this);
                        const btnPrevState = btn.clone()
                        btn.addClass("is-busy").prop("disabled", true).append(loadingRing);
                        apiService("funnelkit-app/activate_plugin", 'POST', JSON.stringify({
                            basename:
                                "funnelkit-stripe-woo-payment-gateway/funnelkit-stripe-woo-payment-gateway.php",
                            slug: "funnelkit-stripe-woo-payment-gateway",
                        })).then((res) => {
                            if (res.next_action) {
                                apiService(res.next_action, 'GET').then((res) => {
                                    if (res.link) {
                                        jQuery(".fk-stripe-tooltip button.is-activate").replaceWith(`<a href="${res.link}" class="button button-primary is-stripe">Connect</a>`);
                                    } else {
                                        window.location.href = '<?php echo $this->get_stripe_settings_link(); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>';

                                    }

                                }).catch((e) => {
                                    btn.replaceWith(btnPrevState);
                                    addClickEvent();
                                    console.log(e.responseJSON);
                                })
                            }
                        }).catch((e) => {
                            btn.replaceWith(btnPrevState);
                            addClickEvent();
                            console.log(e.responseJSON);
                        })
                    });

                    addClickEvent();


                });
            </script>
			<?php
		}
		public function ajax_dismiss_admin_notice() {
			$notice_key = isset( $_REQUEST['nstripekey'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nstripekey'] ) ) : '';

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX && current_user_can( 'manage_options' ) && $notice_key && isset( $_REQUEST['nonce'] ) && false !== check_ajax_referer( 'wp_wffn_dismiss_stripe_notice', 'nonce', false )

			) {

				$userdata   = get_user_meta( get_current_user_id(), '_bwf_notifications_close', true );
				$userdata   = empty( $userdata ) && ! is_array( $userdata ) ? [] : $userdata;
				$userdata[] = $notice_key;

				update_user_meta( get_current_user_id(), '_bwf_notifications_close', array_values( array_unique( $userdata ) ) ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.user_meta_update_user_meta

			}
			$redirect = isset( $_REQUEST['redirect'] ) ? urldecode( wp_unslash( $_REQUEST['redirect'] ) ) : null;

			wp_safe_redirect( $redirect ?? admin_url( 'admin.php?page=bwf&path=/funnels' ) );
			exit;

		}
		public function get_stripe_settings_link() {
			return admin_url( 'admin.php?page=wc-settings&tab=fkwcs_api_settings' );
		}

	}

	if ( class_exists( 'WFFN_Pro_Core' ) ) {
		WFFN_Pro_Core::register( 'stripe_controller', 'WFFN_Stripe_Admin_Controller' );
	}
}
