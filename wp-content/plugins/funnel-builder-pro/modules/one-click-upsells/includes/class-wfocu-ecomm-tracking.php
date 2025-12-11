<?php

if ( ! class_exists( 'WFOCU_Ecomm_Tracking' ) ) {
	/**
	 * This class take care of ecommerce tracking setup
	 * It renders necessary javascript code to fire events as well as creates dynamic data for the tracking
	 * @author woofunnels.
	 */
	class WFOCU_Ecomm_Tracking {
		private static $ins = null;
		public $api_events = [];
		public $gtag_rendered = false;
		/**
		 * @var BWF_Admin_General_Settings|null
		 */
		public $admin_general_settings = null;

		public function __construct() {


			/**
			 * Global settings script should render on every mode, they should not differentiate between preview and real funnel
			 */
			add_action( 'wfocu_footer_before_print_scripts', array( $this, 'render_global_external_scripts' ), 999 );
			add_action( 'wp_head', array( $this, 'render_global_external_scripts_head' ), 999 );
			add_action( 'wfocu_header_print_in_head', array( $this, 'render_global_external_scripts_head' ), 999 );

			if ( true === WFOCU_Core()->template_loader->is_customizer_preview() ) {
				return;
			}
			/**
			 * Print js on pages
			 */
			add_action( 'wfocu_header_print_in_head', array( $this, 'render_fb' ), 90 );
			add_action( 'wfocu_header_print_in_head', array( $this, 'render_ga' ), 95 );
			add_action( 'wfocu_header_print_in_head', array( $this, 'render_gad' ), 100 );
			add_action( 'wfocu_header_print_in_head', array( $this, 'render_general_data' ), 100 );

			add_action( 'wfocu_header_print_in_head', array( $this, 'maybe_remove_track_data' ), 9999 );

			/**
			 * Tracking js on custom pages/thankyou page
			 */
			add_action( 'wp_head', array( $this, 'render_fb' ), 90 );
			add_action( 'wp_head', array( $this, 'render_ga' ), 95 );
			add_action( 'wp_head', array( $this, 'render_gad' ), 100 );
			add_action( 'wp_head', array( $this, 'render_general_data' ), 100 );

			add_action( 'wp_head', array( $this, 'maybe_remove_track_data' ), 9999 );

			/**
			 * Offer view and offer success script on upsell pages
			 */
			add_action( 'wfocu_header_print_in_head', array( $this, 'render_offer_view_script' ), 106 );
			add_action( 'wfocu_header_print_in_head', array( $this, 'render_offer_success_script' ), 107 );

			/**
			 * Offer view and offer success script on upsell pages for custom pages/thankyou page
			 */
			add_action( 'wp_head', array( $this, 'render_offer_view_script' ), 106 );
			add_action( 'wp_head', array( $this, 'render_offer_success_script' ), 107 );

			/**
			 * Funnel success on thank you page
			 */
			add_action( 'woocommerce_thankyou', array( $this, 'render_funnel_end' ), 200 );

			/**
			 * Generate data on these events that will further used by print functions
			 */
			add_action( 'woocommerce_checkout_order_processed', array( $this, 'maybe_save_order_data' ), 999, 3 );
			add_action( 'woocommerce_before_pay_action', [ $this, 'maybe_save_order_data' ], 11, 1 );
			add_action( 'wfocu_offer_accepted_and_processed', array( $this, 'maybe_save_data_offer_accepted' ), 10, 4 );

			/**
			 * Generate and save the analytics data in session for general services rendering
			 */
			add_action( 'woocommerce_checkout_order_processed', array( $this, 'maybe_save_order_data_general' ), 999, 3 );
			add_action( 'woocommerce_before_pay_action', [ $this, 'maybe_save_order_data_general' ], 11, 1 );
			add_action( 'wfocu_offer_accepted_and_processed', array( $this, 'maybe_save_data_offer_accepted_general' ), 10, 4 );


			add_action( 'wfocu_header_print_in_head', array( $this, 'render_js_to_track_referer' ), 10 );


			add_action( 'wfocu_header_print_in_head', array( $this, 'render_pint' ), 100 );
			add_action( 'wp_head', array( $this, 'render_pint' ), 100 );
			add_action( 'wfocu_header_print_in_head', array( $this, 'render_tiktok' ), 101 );
			add_action( 'wp_head', array( $this, 'render_tiktok' ), 101 );
			add_action( 'wfocu_header_print_in_head', array( $this, 'render_snapchat' ), 101 );
			add_action( 'wp_head', array( $this, 'render_snapchat' ), 101 );
			add_action( 'wp_enqueue_scripts', array( $this, 'tracking_log_js' ) );
			$this->admin_general_settings = BWF_Admin_General_Settings::get_instance();


			/**
			 * Conversion API related hooks
			 */
			add_action( 'wfocu_header_print_in_head', array( $this, 'maybe_render_conv_api_js' ), 100 );
			add_action( 'wp_head', array( $this, 'maybe_render_conv_api_js' ), 100 );

			add_action( 'wfocu_header_print_in_head', array( $this, 'custom_tracking_action' ), 106 );
			add_action( 'wp_head', array( $this, 'custom_tracking_action' ), 106 );

			add_action( 'wp_head', array( $this, 'fire_tracking' ), 105 );
			add_action( 'wfocu_header_print_in_head', array( $this, 'fire_tracking' ), 105 );

		}

		public static function get_instance() {
			if ( self::$ins === null ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		/**
		 * render script to load facebook pixel core js
		 */

		public function render_fb() {

			if ( $this->is_tracking_on() && false !== $this->is_fb_pixel() && $this->should_render() ) {
				$fb_advanced_pixel_data = array_merge( $this->get_advanced_pixel_data( 'fb' ), WFOCU_Common::pixel_advanced_matching_data() );
				?>
                <!-- Facebook Analytics Script Added By WooFunnels -->
                <script type="text/javascript" <?php echo esc_attr( apply_filters( 'wfocu_script_tags', '', 'fb' ) ); ?>>

                    function WfocuGetCookie(cname) {
                        let name = cname + "=";
                        let decodedCookie = decodeURIComponent(document.cookie);
                        let ca = decodedCookie.split(';');
                        for (let i = 0; i < ca.length; i++) {
                            let c = ca[i];
                            while (c.charAt(0) == ' ') {
                                c = c.substring(1);
                            }
                            if (c.indexOf(name) == 0) {
                                return c.substring(name.length, c.length);
                            }
                        }
                        return "";
                    }

                    function wfocuFbTrackingIn() {
                        var wfocu_shouldRender = 1;
						<?php do_action( 'wfocu_allow_tracking_inline_js' ); ?>
                        if (1 === wfocu_shouldRender) {
                            !function (f, b, e, v, n, t, s) {
                                if (f.fbq) return;
                                n = f.fbq = function () {
                                    n.callMethod ?
                                        n.callMethod.apply(n, arguments) : n.queue.push(arguments)
                                };
                                if (!f._fbq) f._fbq = n;
                                n.push = n;
                                n.loaded = !0;
                                n.version = '2.0';
                                n.queue = [];
                                t = b.createElement(e);
                                t.async = !0;
                                t.src = v;
                                s = b.getElementsByTagName(e)[0];
                                s.parentNode.insertBefore(t, s)
                            }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');

							<?php

							$get_all_fb_pixel = $this->is_fb_pixel();
							$get_each_pixel_id = explode( ',', $get_all_fb_pixel );
							if ( is_array( $get_each_pixel_id ) && count( $get_each_pixel_id ) > 0 ) {
							foreach ( $get_each_pixel_id as $pixel_id ) {
							?>
							<?php if ( true === $this->is_fb_advanced_tracking_on() && count( $fb_advanced_pixel_data ) > 0 ) { ?>
                            var wfocuPixelData = JSON.parse('<?php echo wp_json_encode( $fb_advanced_pixel_data ); ?>');

                            if ('' !== WfocuGetCookie('_fbp') && (typeof wfocuPixelData.external_id === "undefined")) {
                                wfocuPixelData.external_id = WfocuGetCookie('_fbp');
                            }

                            fbq('init', '<?php echo esc_js( trim( $pixel_id ) ); ?>', wfocuPixelData);
							<?php } else { ?>
                            fbq('init', '<?php echo esc_js( trim( $pixel_id ) ); ?>');
							<?php } ?>
							<?php
							}
							?>

							<?php  $this->render_fb_view(); ?>
							<?php  $this->render_fb_custom_event(); ?>
							<?php  $this->maybe_print_fb_script(); ?>
							<?php
							}
							?>
                        }
                    }
                </script>
				<?php
			}
		}

		/**
		 * render script to print general data.
		 */
		public function render_general_data() {
			if ( $this->should_render() ) {
				$general_data = WFOCU_Core()->data->get( 'general_data', array(), 'track' );
				if ( is_array( $general_data ) && count( $general_data ) > 0 ) { ?>
                    <script type="text/javascript" <?php echo esc_attr( apply_filters( 'wfocu_script_tags', '', 'general' ) ); ?>>
                        let wfocu_tracking_data =<?php echo wp_json_encode( $general_data ); ?>;


                    </script>
					<?php

				}
			}
		}

		/**
		 * render script to print general data.
		 */
		public function custom_tracking_action() {
			if ( $this->should_render() ) {
				$general_data = WFOCU_Core()->data->get( 'general_data', array(), 'track' );
				if ( is_array( $general_data ) && count( $general_data ) > 0 ) {

					do_action( 'wfocu_custom_purchase_tracking', $general_data );

				}
			}
		}

		/**
		 * render script to load facebook pixel core js
		 */
		public function render_pint() {

			if ( $this->is_tracking_on() && $this->pint_code() && ( false !== $this->do_track_pint() || false !== $this->do_track_pint_view() ) && $this->should_render() ) {
				$get_each_pixel_id = explode( ',', $this->pint_code() );
				?>
                <!-- Pinterest Pixel Base Code -->
                <script type="text/javascript" <?php echo esc_attr( apply_filters( 'wfocu_script_tags', '', 'pinterest' ) ); ?>>
                    function wfocuPintTrackingIn() {
                        var wfocu_shouldRender = 1;
						<?php do_action( 'wfocu_allow_tracking_inline_js' ); ?>
                        if (1 === wfocu_shouldRender) {
                            !function (e) {
                                if (!window.pintrk) {
                                    window.pintrk = function () {
                                        window.pintrk.queue.push(
                                            Array.prototype.slice.call(arguments))
                                    };
                                    var
                                        n = window.pintrk;
                                    n.queue = [], n.version = "3.0";
                                    var
                                        t = document.createElement("script");
                                    t.async = !0, t.src = e;
                                    var
                                        r = document.getElementsByTagName("script")[0];
                                    r.parentNode.insertBefore(t, r)
                                }
                            }("https://s.pinimg.com/ct/core.js");
							<?php
							$get_track_data = WFOCU_Core()->data->get( 'data', array(), 'track' );
							if ( isset( $get_track_data['pint'] ) && isset( $get_track_data['pint']['email'] ) ) {
							foreach ( $get_each_pixel_id as $id ) { ?>
                            pintrk('load', '<?php echo esc_js( $id ) ?>', {em: '<?php echo esc_js( $get_track_data['pint']['email'] ); ?>'});
							<?php if ( $this->do_track_pint_view() ) { ?>
                            pintrk('page');
							<?php }
							}
							} else {
							foreach ( $get_each_pixel_id as $id ) { ?>
                            pintrk('load', '<?php echo esc_js( $id ) ?>');
							<?php if ( $this->do_track_pint_view() ) { ?>
                            pintrk('page');
							<?php }
							}
							}
							?>
                        }
                    }
                </script>
				<?php
				foreach ( $get_each_pixel_id as $id ) { ?>
                    <noscript>
                        <img height="1" width="1" style="display:none;" alt="" src="https://ct.pinterest.com/v3/?tid=<?php echo esc_attr( $id ); ?>&noscript=1"/>
                    </noscript>
					<?php
				}
				?>

				<?php if ( false !== $this->do_track_pint() ) { ?>
                    <!-- End Pinterest Pixel Base Code -->
                    <script type="text/javascript" <?php echo esc_attr( apply_filters( 'wfocu_script_tags', '', 'pint' ) ); ?>>
                        function wfocuPintTrackingBaseIn() {
                            var wfocu_shouldRender = 1;
							<?php do_action( 'wfocu_allow_tracking_inline_js' ); ?>
                            if (1 === wfocu_shouldRender) {
								<?php  $this->render_pint_custom_event(); ?>
								<?php  $this->maybe_print_pint_script(); ?>
                            }
                        }
                    </script>
				<?php } ?>
				<?php
			}
		}

		/**
		 * render script to load facebook pixel core js
		 */
		public function render_tiktok() {

			if ( $this->is_tracking_on() && $this->tiktok_code() && $this->should_render() ) {
				$get_each_pixel_id   = explode( ',', $this->tiktok_code() );
				$advanced_pixel_data = array_merge( $this->get_advanced_pixel_data( 'tiktok' ), WFOCU_Common::tiktok_advanced_matching_data() );

				?>
                <!-- Tiktok Pixel Base Code -->
                <script type="text/javascript" <?php echo esc_attr( apply_filters( 'wfocu_script_tags', '', 'tiktok' ) ); ?>>
                    function wfocuTiktokTrackingIn() {
                        var wfocu_shouldRender = 1;
						<?php do_action( 'wfocu_allow_tracking_inline_js' ); ?>
                        if (1 === wfocu_shouldRender) {
                            !function (w, d, t) {
                                w.TiktokAnalyticsObject = t;
                                var ttq = w[t] = w[t] || [];
                                ttq.methods = ["page", "track", "identify", "instances", "debug", "on", "off", "once", "ready", "alias", "group", "enableCookie", "disableCookie"];
                                ttq.setAndDefer = function (t, e) {
                                    t[e] = function () {
                                        t.push([e].concat(Array.prototype.slice.call(arguments, 0)))
                                    }
                                };
                                for (var i = 0; i < ttq.methods.length; i++)
                                    ttq.setAndDefer(ttq, ttq.methods[i]);
                                ttq.instance = function (t) {
                                    for (var e = ttq._i[t] || [], n = 0; n < ttq.methods.length; n++) ttq.setAndDefer(e, ttq.methods[n]);
                                    return e
                                };
                                ttq.load = function (e, n) {
                                    var i = "https://analytics.tiktok.com/i18n/pixel/events.js";
                                    ttq._i = ttq._i || {}, ttq._i[e] = [], ttq._i[e]._u = i, ttq._t = ttq._t || {}, ttq._t[e] = +new Date, ttq._o = ttq._o || {}, ttq._o[e] = n || {};
                                    var o = document.createElement("script");
                                    o.type = "text/javascript", o.async = !0, o.src = i + "?sdkid=" + e + "&lib=" + t;
                                    var a = document.getElementsByTagName("script")[0];
                                    a.parentNode.insertBefore(o, a)
                                };


                            }(window, document, 'ttq');

							<?php foreach ( $get_each_pixel_id as $id ) { ?>

                            ttq.load('<?php echo esc_js( $id ) ?>');
							<?php if ( count( $advanced_pixel_data ) > 0 ) { ?>
                            ttq.instance('<?php echo esc_js( $id ); ?>').identify(<?php echo wp_json_encode( $advanced_pixel_data ); ?>);  <?php //phpcs:ignore  WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php } ?>

							<?php if ( $this->do_track_tiktok_view() ) { ?>
                            ttq.page();
							<?php } ?>

							<?php } ?>
                        }
                    }
                </script>

				<?php if ( $this->do_track_tiktok() ) { ?>
                    <!-- END Tiktok Pixel Base Code -->
                    <script type="text/javascript" <?php echo esc_attr( apply_filters( 'wfocu_script_tags', '', 'tiktok' ) ); ?>>
                        function wfocuTiktokTrackingBaseIn() {
                            var wfocu_shouldRender = 1;
							<?php do_action( 'wfocu_allow_tracking_inline_js' ); ?>
                            if (1 === wfocu_shouldRender) {
                                setTimeout(function () {
									<?php foreach ( $get_each_pixel_id as $id ) {
									$this->maybe_print_tiktok_script( $id, $this->do_track_tiktok() );
								} ?>
                                }, 1200);
                            }
                        }
                    </script>
					<?php
				}
			}
		}

		/**
		 * render script to load facebook pixel core js
		 */
		public function render_snapchat() {

			if ( $this->is_tracking_on() && $this->snapchat_code() && $this->should_render() ) {
				$get_each_pixel_id = explode( ',', $this->snapchat_code() );
				?>
                <!-- Snapchat Pixel Base Code -->
                <script type="text/javascript" <?php echo esc_attr( apply_filters( 'wfocu_script_tags', '', 'snapchat' ) ); ?>>
                    function wfocuSnapchatTrackingIn() {
                        var wfocu_shouldRender = 1;
						<?php do_action( 'wfocu_allow_tracking_inline_js' ); ?>
                        if (1 === wfocu_shouldRender) {
                            (function (win, doc, sdk_url) {
                                if (win.snaptr) {
                                    return;
                                }

                                var tr = win.snaptr = function () {
                                    tr.handleRequest ? tr.handleRequest.apply(tr, arguments) : tr.queue.push(arguments);
                                };
                                tr.queue = [];
                                var s = 'script';
                                var new_script_section = doc.createElement(s);
                                new_script_section.async = !0;
                                new_script_section.src = sdk_url;
                                var insert_pos = doc.getElementsByTagName(s)[0];
                                insert_pos.parentNode.insertBefore(new_script_section, insert_pos);
                            })(window, document, 'https://sc-static.net/scevent.min.js');

                            <!-- END Snapchat Pixel Base Code -->

							<?php foreach ( $get_each_pixel_id as $id ) {

							$general_data = WFOCU_Core()->data->get( 'general_data', array(), 'track' );
							if ( ! empty( $general_data ) ) {
							?>

                            snaptr('init', '<?php echo esc_js( $id ); ?>', {
                                integration: 'woocommerce',
                                user_email: '<?php echo esc_attr( $general_data["email"] ); ?>'
                            });
							<?php
							} else {
							?>

                            snaptr('init', '<?php echo esc_js( $id ); ?>', {
                                integration: 'woocommerce'
                            });
							<?php
							}
							} ?>
                        }
                    }
                </script>
                <script type="text/javascript" <?php echo esc_attr( apply_filters( 'wfocu_script_tags', '', 'snapchat' ) ); ?>>
                    function wfocuSnapchatTrackingBaseIn() {
                        var wfocu_shouldRender = 1;
						<?php do_action( 'wfocu_allow_tracking_inline_js' ); ?>
                        if (1 === wfocu_shouldRender) {

							<?php
							if ( $this->is_tracking_on() && $this->do_track_snapchat_view() ) {

							?>
                            snaptr('track', 'PAGE_VIEW');
							<?php

							}
							$data = WFOCU_Core()->data->get( 'data', array(), 'track' );


							if ( isset( $data['snapchat'] ) && $this->is_tracking_on() && $this->do_track_snapchat() ) {

							?>

                            snaptr('track', 'PURCHASE', <?php echo wp_json_encode( $data['snapchat'] ); ?>);

							<?php
							}

							?>
                        }
                    }
                </script>

				<?php
			}
		}

		public function is_tracking_on() {
			return apply_filters( 'wfocu_front_ecomm_tracking', true );
		}

		public function is_fb_pixel() {

			$get_pixel_key = apply_filters( 'wfocu_fb_pixel_ids', $this->admin_general_settings->get_option( 'fb_pixel_key' ) );

			return empty( $get_pixel_key ) ? false : $get_pixel_key;

		}

		/**
		 * Decide whether script should render or not
		 * Bases on condition given and based on the action we are in there exists some boolean checks
		 *
		 * @param bool $allow_thank_you whether consider thank you page
		 * @param bool $without_offer render without an valid offer (valid funnel)
		 *
		 * @return bool
		 */
		public function should_render( $allow_thank_you = true, $without_offer = false ) {

			/**
			 * For customizer templates
			 */
			if ( current_action() === 'wfocu_header_print_in_head' && ( $without_offer === true || ( false === $without_offer && false === WFOCU_Core()->public->is_preview ) ) ) {
				return true;
			}

			/**
			 * For custom pages and single offer post front request
			 */
			$allow_thank_you = apply_filters( 'wfocu_allow_thankyou_page_scripts', $allow_thank_you );

			if ( current_action() === 'wp_head' && ( ( did_action( 'wfocu_front_before_custom_offer_page' ) || did_action( 'wfocu_front_before_single_page_load' ) ) && ( $without_offer === true || ( false === $without_offer && false === WFOCU_Core()->public->is_preview ) ) || ( $allow_thank_you && is_order_received_page() ) ) ) {

				return true;
			}

			return apply_filters( 'wfocu_should_render_scripts', false, $allow_thank_you, $without_offer, current_action() );
		}

		public function get_advanced_pixel_data( $type ) {
			$data = WFOCU_Core()->data->get( 'data', array(), 'track' );

			if ( ! is_array( $data ) ) {
				return array();
			}

			if ( ! isset( $data[ $type ] ) ) {
				return array();
			}

			if ( ! isset( $data[ $type ]['advanced'] ) ) {
				return array();
			}

			return $data[ $type ]['advanced'];
		}

		public function is_fb_enable_content_on() {
			$is_fb_enable_content_on = $this->admin_general_settings->get_option( 'is_fb_enable_content' );
			if ( is_array( $is_fb_enable_content_on ) && count( $is_fb_enable_content_on ) > 0 && 'yes' === $is_fb_enable_content_on[0] ) {
				return true;
			}
		}

		public function is_fb_advanced_tracking_on() {
			$is_fb_advanced_tracking_on = $this->admin_general_settings->get_option( 'is_fb_advanced_event' );
			if ( is_array( $is_fb_advanced_tracking_on ) && count( $is_fb_advanced_tracking_on ) > 0 && 'yes' === $is_fb_advanced_tracking_on[0] ) {
				return true;
			}

		}

		/**
		 * maybe render script to fire fb pixel view event
		 */
		public function render_fb_view() {

			if ( $this->is_tracking_on() && $this->do_track_fb_view() && $this->should_render() ) {
				$event_id = $this->get_event_id( 'PageView' );
				?>
                fbq('track', 'PageView',(typeof wffnAddTrafficParamsToEvent !== "undefined")?wffnAddTrafficParamsToEvent({} ):{},{'eventID': '<?php echo esc_attr( $event_id ); ?>'});
				<?php
				if ( $this->is_conversion_api() ) {
					$this->api_events[] = array( 'event' => 'PageView', 'event_id' => $event_id );
				}
			}
		}

		/**
		 * maybe render script to fire fb pixel view event
		 */
		public function render_fb_custom_event() {

			if ( $this->is_tracking_on() && $this->is_enable_custom_event() ) {

				if ( WFOCU_Core()->public->if_is_offer() ) {
					$get_type_of_offer = WFOCU_Core()->data->get( '_current_offer_type' );
					$event             = 'WooFunnels_' . ucfirst( $get_type_of_offer );
				} else {

					if ( ! function_exists( 'WFFN_Core' ) || ! WFFN_Core()->thank_you_pages->is_wfty_page() ) {
						return;
					}
					$event = 'WooFunnels_Thankyou';
				}
				$event_id = $this->get_event_id( $event );
				?>
                fbq('trackCustom', '<?php echo esc_attr( $event ); ?>', (typeof wffnAddTrafficParamsToEvent !== "undefined")?wffnAddTrafficParamsToEvent(<?php echo $this->get_custom_event_params(); ?>):<?php echo $this->get_custom_event_params(); ?>,{'eventID': '<?php echo esc_attr( $event_id ); ?>'}); <?php //phpcs:ignore  WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				<?php
				if ( $this->is_conversion_api() ) {
					$this->api_events[] = array( 'event' => $event, 'event_id' => $event_id );
				}
			}
		}

		public function get_custom_event_params() {

			$params = [];
			if ( ! function_exists( 'WFFN_Core' ) ) {
				return wp_json_encode( $params );
			}
			$funnel = WFFN_Core()->data->get_session_funnel();
			if ( wffn_is_valid_funnel( $funnel ) ) {

				if ( is_singular() ) {
					global $post;
					if ( is_object( $post ) && $post instanceof WP_Post ) {
						$params['page_title'] = $post->post_title;
						$params['post_id']    = $post->ID;
					}
				}
				$params['funnel_id']    = $funnel->get_id();
				$params['funnel_title'] = $funnel->get_title();


			}

			return wp_json_encode( $params );

		}

		/**
		 * maybe render script to fire fb pixel view event
		 */
		public function render_pint_custom_event() {

			if ( $this->is_tracking_on() && $this->is_enable_custom_event_pint() ) {

				if ( WFOCU_Core()->public->if_is_offer() ) {
					$get_type_of_offer = WFOCU_Core()->data->get( '_current_offer_type' );
					$event             = 'WooFunnels_' . ucfirst( $get_type_of_offer );
				} else {

					if ( ! function_exists( 'WFFN_Core' ) || ! WFFN_Core()->thank_you_pages->is_wfty_page() ) {
						return;
					}
					$event = 'WooFunnels_Thankyou';
				}

				?>
                pintrk('track', '<?php echo esc_attr( $event ); ?>', (typeof wffnAddTrafficParamsToEvent !== "undefined")?wffnAddTrafficParamsToEvent({} ):{});
				<?php

			}
		}

		/**
		 * maybe render script to fire fb pixel view event
		 */
		public function render_gtag_custom_event( $k, $code, $label, $mode ) {

			if ( $this->is_tracking_on() && ( ( $mode === 'ga' && $this->is_enable_custom_event_ga() ) || ( $mode === 'gad' && $this->is_enable_custom_event_gad() ) ) ) {

				if ( WFOCU_Core()->public->if_is_offer() ) {
					$get_type_of_offer = WFOCU_Core()->data->get( '_current_offer_type' );
					$event             = 'WooFunnels_' . ucfirst( $get_type_of_offer );
				} else {

					if ( ! function_exists( 'WFFN_Core' ) || ! WFFN_Core()->thank_you_pages->is_wfty_page() ) {
						return;
					}
					$event = 'WooFunnels_Thankyou';
				}
				?>
                gtag('event','<?php echo esc_attr( $event ); ?>',{send_to: '<?php echo esc_attr( $code ); ?>'});
				<?php
			}
		}


		public function is_enable_custom_event() {
			$is_fb_custom_events = $this->admin_general_settings->get_option( 'is_fb_custom_events' );

			if ( '1' === $is_fb_custom_events ) {
				return true;
			}

			return false;
		}

		public function is_enable_custom_event_ga() {
			$is_ga_custom_events = $this->admin_general_settings->get_option( 'is_ga_custom_events' );

			if ( '1' === $is_ga_custom_events ) {
				return true;
			}

			return false;
		}

		public function is_enable_custom_event_gad() {
			$is_ga_custom_events = $this->admin_general_settings->get_option( 'is_gad_custom_events' );

			if ( '1' === $is_ga_custom_events ) {
				return true;
			}

			return false;
		}

		public function is_enable_custom_event_pint() {
			$is_pint_custom_events = $this->admin_general_settings->get_option( 'is_pint_custom_events' );

			if ( '1' === $is_pint_custom_events ) {
				return true;
			}

			return false;
		}


		public function do_track_fb_view() {
			if ( true === wc_string_to_bool( $this->admin_general_settings->get_option( 'is_fb_page_view_global' ) ) ) {
				return true;
			}

			$fb_tracking = $this->admin_general_settings->get_option( 'is_fb_purchase_page_view' );

			if ( is_array( $fb_tracking ) && count( $fb_tracking ) > 0 && 'yes' === $fb_tracking[0] ) {
				return true;
			}

			return false;

		}

		public function do_track_snapchat_view() {
			if ( true === wc_string_to_bool( $this->admin_general_settings->get_option( 'is_snapchat_page_view_global' ) ) ) {
				return true;
			}

			$fb_tracking = $this->admin_general_settings->get_option( 'is_snapchat_pageview_event' );

			if ( is_array( $fb_tracking ) && count( $fb_tracking ) > 0 && 'yes' === $fb_tracking[0] ) {
				return true;
			}

			return false;

		}

		public function do_track_pint_view() {
			if ( true === wc_string_to_bool( $this->admin_general_settings->get_option( 'is_pint_page_view_global' ) ) ) {
				return true;
			}

			$fb_tracking = $this->admin_general_settings->get_option( 'is_pint_pageview_event' );
			if ( is_array( $fb_tracking ) && count( $fb_tracking ) > 0 && 'yes' === $fb_tracking[0] ) {
				return true;
			}

			return false;
		}

		public function do_track_tiktok_view() {
			if ( true === wc_string_to_bool( $this->admin_general_settings->get_option( 'is_tiktok_page_view_global' ) ) ) {
				return true;
			}

			$fb_tracking = $this->admin_general_settings->get_option( 'is_tiktok_pageview_event' );

			if ( is_array( $fb_tracking ) && count( $fb_tracking ) > 0 && 'yes' === $fb_tracking[0] ) {
				return true;
			}

			return false;

		}

		/**
		 * Maybe print facebook pixel javascript
		 * @see WFOCU_Ecomm_Tracking::render_fb();
		 */
		public function maybe_print_fb_script() {
			$data = WFOCU_Core()->data->get( 'data', array(), 'track' ); //phpcs:ignore

			if ( $this->do_track_fb_purchase_event() ) {
				include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'views/js-blocks/wfocu-analytics-fb.phtml'; //phpcs:ignore WordPressVIPMinimum.Files.IncludingNonPHPFile.IncludingNonPHPFile
			}
			if ( $this->do_track_fb_general_event() ) {
				$id                     = $this->get_event_id( 'trackCustom' );
				$get_offer              = WFOCU_Core()->data->get_current_offer();
				$getEventName           = $this->admin_general_settings->get_option( 'general_event_name' );
				$params                 = array();
				$params['post_type']    = 'wfocu_offer';
				$params['content_name'] = get_the_title( $get_offer );
				$params['post_id']      = $get_offer;

				?>
                var wfocuGeneralData = <?php echo wp_json_encode( $params ); ?>;
                wfocuGeneralData = (typeof wffnAddTrafficParamsToEvent !== "undefined")?wffnAddTrafficParamsToEvent(wfocuGeneralData):wfocuGeneralData;
                fbq('trackCustom', '<?php echo esc_js( $getEventName ); ?>', wfocuGeneralData,{'eventID': '<?php echo esc_attr( $id ); ?>'});
				<?php
				if ( $this->is_conversion_api() ) {

					$this->api_events[] = array( 'event' => 'trackCustom', 'event_id' => $id );

				}
			}

		}

		/**
		 * Maybe print facebook pixel javascript
		 * @see WFOCU_Ecomm_Tracking::render_pint();
		 */
		public function maybe_print_pint_script() {
			$data = WFOCU_Core()->data->get( 'data', array(), 'track' ); //phpcs:ignore
			include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'views/js-blocks/wfocu-analytics-pint.phtml'; //phpcs:ignore WordPressVIPMinimum.Files.IncludingNonPHPFile.IncludingNonPHPFile
		}

		/**
		 * Maybe print facebook pixel javascript
		 * @see WFOCU_Ecomm_Tracking::render_tiktok();
		 */
		public function maybe_print_tiktok_script( $id, $purchase = false ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis
			$data = WFOCU_Core()->data->get( 'data', array(), 'track' ); //phpcs:ignore
			include_once plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'views/js-blocks/wfocu-analytics-tiktok.phtml'; //phpcs:ignore WordPressVIPMinimum.Files.IncludingNonPHPFile.IncludingNonPHPFile
		}

		public function do_track_fb_synced_purchase() {

			return true;
		}

		public function do_track_fb_purchase_event() {

			$do_track_fb_purchase_event = $this->admin_general_settings->get_option( 'is_fb_purchase_event' );

			if ( is_array( $do_track_fb_purchase_event ) && count( $do_track_fb_purchase_event ) > 0 && 'yes' === $do_track_fb_purchase_event[0] ) {
				return true;
			}

			return false;
		}

		public function do_track_fb_general_event() {

			$enable_general_event = $this->admin_general_settings->get_option( 'enable_general_event' );
			if ( is_array( $enable_general_event ) && count( $enable_general_event ) > 0 && 'yes' === $enable_general_event[0] ) {
				return true;
			}

			return false;
		}

		/**
		 * render google analytics core script to load framework
		 */
		public function render_ga() {
			$get_tracking_code = $this->ga_code();

			if ( false === $get_tracking_code ) {
				return;
			}

			$get_tracking_code = explode( ",", $get_tracking_code );

			if ( $this->is_tracking_on() && ( $this->do_track_ga_purchase() || $this->do_track_ga_view() ) && ( is_array( $get_tracking_code ) && ! empty( $get_tracking_code ) ) && $this->should_render() ) {
				?>
                <!-- Google Analytics Script Added By WooFunnels -->
                <script type="text/javascript" <?php echo esc_attr( apply_filters( 'wfocu_script_tags', '', 'gtag' ) ); ?>>
                    function wfocuGaTrackingIn() {
                        var wfocu_shouldRender = 1;
						<?php do_action( 'wfocu_allow_tracking_inline_js' ); ?>
                        if (1 === wfocu_shouldRender) {
							<?php if ( false === $this->gtag_rendered ) {
							$this->load_gtag( $get_tracking_code[0] );

						}
							foreach ( $get_tracking_code as $k => $code ) {
								echo "gtag('config', '" . esc_js( trim( $code ) ) . "');";
								$label = false;
								esc_js( $this->render_gtag_custom_event( $k, $code, $label, 'ga' ) );
								$this->maybe_print_gtag_script( $k, $code, $label, $this->do_track_ga_purchase() ); //phpcs:ignore
							}
							?>
                        }
                    }
                </script>
				<?php
			}
		}

		public function ga_code() {
			$get_ga_key = apply_filters( 'wfocu_get_ga_key', $this->admin_general_settings->get_option( 'ga_key' ) );

			return empty( $get_ga_key ) ? false : $get_ga_key;
		}

		public function is_ga4_tracking() {

			$ga_id = $this->admin_general_settings->get_option( 'ga_key' );
			if ( ! empty( $ga_id ) && strpos( $ga_id, "G-" ) !== false ) {
				return true;
			}

			return false;
		}


		public function do_track_ga_purchase() {

			$do_track_ga_purchase = $this->admin_general_settings->get_option( 'is_ga_purchase_event' );

			if ( is_array( $do_track_ga_purchase ) && count( $do_track_ga_purchase ) > 0 && 'yes' === $do_track_ga_purchase[0] ) {
				return true;
			}

			return false;

		}

		public function do_track_pint() {
			$do_track_ga_purchase = $this->admin_general_settings->get_option( 'is_pint_purchase_event' );
			if ( is_array( $do_track_ga_purchase ) && count( $do_track_ga_purchase ) > 0 && 'yes' === $do_track_ga_purchase[0] ) {
				return true;
			}

			return false;
		}

		public function do_track_tiktok() {
			$do_track_purchase = $this->admin_general_settings->get_option( 'is_tiktok_purchase_event' );
			if ( is_array( $do_track_purchase ) && count( $do_track_purchase ) > 0 && 'yes' === $do_track_purchase[0] ) {
				return true;
			}

			return false;
		}

		public function do_track_cp_tiktok() {
			$do_cp_purchase = $this->admin_general_settings->get_option( 'is_tiktok_complete_payment_event' );
			if ( is_array( $do_cp_purchase ) && count( $do_cp_purchase ) > 0 && 'yes' === $do_cp_purchase[0] ) {
				return true;
			}

			return false;
		}

		public function do_track_snapchat() {
			$do_track_purchase = $this->admin_general_settings->get_option( 'is_snapchat_purchase_event' );
			if ( is_array( $do_track_purchase ) && count( $do_track_purchase ) > 0 && 'yes' === $do_track_purchase[0] ) {
				return true;
			}

			return false;
		}

		public function do_track_ga_view() {
			if ( true === wc_string_to_bool( $this->admin_general_settings->get_option( 'is_ga_page_view_global' ) ) ) {
				return true;
			}

			$ga_tracking = $this->admin_general_settings->get_option( 'is_ga_purchase_page_view' );

			if ( is_array( $ga_tracking ) && count( $ga_tracking ) > 0 && 'yes' === $ga_tracking[0] ) {
				return true;
			}

			return false;

		}

		/**
		 * render google analytics core script to load framework
		 */
		public function render_gad() {
			$get_tracking_code = $this->gad_code();

			if ( false === $get_tracking_code ) {
				return;
			}

			$get_tracking_code = explode( ",", $get_tracking_code );

			if ( ( $this->do_track_gad_purchase() || $this->is_gad_pageview_event() ) && $this->is_tracking_on() && ( is_array( $get_tracking_code ) && ! empty( $get_tracking_code ) ) && $this->should_render() ) {
				?>
                <!-- Google Ads Script Added By WooFunnels -->
                <script type="text/javascript" <?php echo esc_attr( apply_filters( 'wfocu_script_tags', '', 'gtag' ) ); ?>>
                    function wfocuGadTrackingIn() {
                        var wfocu_shouldRender = 1;
						<?php do_action( 'wfocu_allow_tracking_inline_js' ); ?>
                        if (1 === wfocu_shouldRender) {
							<?php
							if ( false === $this->gtag_rendered ) {
								$this->load_gtag( $get_tracking_code[0] );

							}

							foreach ( $get_tracking_code as $k => $code ) {
								echo "gtag('config', '" . esc_js( trim( $code ) ) . "');";
								if ( $this->is_gad_pageview_event() ) {
									echo "gtag('event', 'page_view', {send_to: '" . esc_js( trim( $code ) ) . "'});";
								}
								$label = false;
								if ( false !== $this->gad_purchase_label() ) {
									$gad_labels = explode( ",", $this->gad_purchase_label() );
									$label      = isset( $gad_labels[ $k ] ) ? $gad_labels[ $k ] : $gad_labels[0];
								}
								esc_js( $this->render_gtag_custom_event( $k, $code, $label, 'gad' ) );
								$this->maybe_print_gtag_script( $k . 'gad', $code, $label, $this->do_track_gad_purchase(), true );
							}

							?>
                        }
                    }
                </script>
				<?php
			}
		}

		public function gad_code() {

			$get_gad_key = apply_filters( 'wfocu_get_gad_key', $this->admin_general_settings->get_option( 'gad_key' ) );

			return empty( $get_gad_key ) ? false : $get_gad_key;
		}

		public function pint_code() {

			$get_pint_key = apply_filters( 'wfocu_get_pint_key', $this->admin_general_settings->get_option( 'pint_key' ) );

			return empty( $get_pint_key ) ? false : $get_pint_key;
		}

		public function gad_purchase_label() {

			$get_gad_conversion_label = apply_filters( 'wfocu_get_conversion_label', $this->admin_general_settings->get_option( 'gad_conversion_label' ) );

			return empty( $get_gad_conversion_label ) ? false : $get_gad_conversion_label;
		}

		public function tiktok_code() {

			$get_key = apply_filters( 'wfocu_get_tiktok_key', $this->admin_general_settings->get_option( 'tiktok_pixel' ) );

			return empty( $get_key ) ? false : $get_key;
		}

		public function snapchat_code() {

			$get_key = apply_filters( 'wfocu_get_snapchat_key', $this->admin_general_settings->get_option( 'snapchat_pixel' ) );

			return empty( $get_key ) ? false : $get_key;
		}

		/**
		 * Maybe print google analytics/google ads javascript
		 * @see WFOCU_Ecomm_Tracking::render_ga();
		 * @see WFOCU_Ecomm_Tracking::render_gad();
		 */
		public function maybe_print_gtag_script( $k, $code, $label, $track = false, $is_gads = false ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis
			$data = WFOCU_Core()->data->get( 'data', array(), 'track' );

			if ( true === $track && is_array( $data ) && ( isset( $data['ga'] ) || isset( $data['gad'] ) ) ) {

				include plugin_dir_path( WFOCU_PLUGIN_FILE ) . 'views/js-blocks/wfocu-analytics-gtag.phtml'; //phpcs:ignore WordPressVIPMinimum.Files.IncludingNonPHPFile.IncludingNonPHPFile
			}

		}

		public function do_track_gad_purchase() {

			$do_track_gad_purchase = $this->admin_general_settings->get_option( 'is_gad_purchase_event' );
			if ( is_array( $do_track_gad_purchase ) && count( $do_track_gad_purchase ) > 0 && 'yes' === $do_track_gad_purchase[0] ) {
				return true;
			}

			return false;
		}

		public function is_gad_pageview_event() {
			if ( true === wc_string_to_bool( $this->admin_general_settings->get_option( 'is_gad_page_view_global' ) ) ) {
				return true;
			}

			$is_gad_pageview_event = $this->admin_general_settings->get_option( 'is_gad_pageview_event' );
			if ( is_array( $is_gad_pageview_event ) && count( $is_gad_pageview_event ) > 0 && 'yes' === $is_gad_pageview_event[0] ) {
				return true;
			}

			return false;
		}

		/**
		 * @hooked over `woocommerce_checkout_order_processed`
		 * Just after funnel initiated we try and setup cookie data for the parent order
		 * That will be further used by WFOCU_Ecomm_Tracking::render_ga() && WFOCU_Ecomm_Tracking::render_ga()
		 *
		 * @param WC_Order $order
		 */
		public function maybe_save_order_data( $order_id, $posted_data = array(), $order = null ) {
			if ( $this->is_tracking_on() ) {
				if ( ! $order instanceof WC_Order ) {
					$order = wc_get_order( $order_id );
				}
				$order_id            = $order->get_id();
				$items               = $order->get_items( 'line_item' );
				$content_ids         = [];
				$content_name        = [];
				$category_names      = [];
				$num_qty             = 0;
				$products            = [];
				$google_ads_products = [];
				$pint_products       = [];
				$google_products     = [];
				$tiktok_contents     = [];
				$billing_email       = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_email' );
				foreach ( $items as $item ) {
					$pid     = $item->get_product_id();
					$product = wc_get_product( $pid );
					if ( $product instanceof WC_product ) {

						$category       = $product->get_category_ids();
						$content_name[] = $product->get_title();
						$get_content_id = $content_ids[] = $this->get_content_id( $item->get_product() );

						$category_name = '';

						if ( is_array( $category ) && count( $category ) > 0 ) {
							$category_id = $category[0];
							if ( is_numeric( $category_id ) && $category_id > 0 ) {
								$cat_term = get_term_by( 'id', $category_id, 'product_cat' );
								if ( $cat_term ) {
									$category_name    = $cat_term->name;
									$category_names[] = $category_name;
								}
							}
						}
						$num_qty    += $item->get_quantity();
						$products[] = array_map( 'html_entity_decode', array(
							'name'       => $product->get_title(),
							'category'   => ( $category_name ),
							'id'         => $get_content_id,
							'quantity'   => $item->get_quantity(),
							'item_price' => $order->get_line_subtotal( $item ),
						) );


						$get_content_id_pint = $this->get_content_id( $item->get_product(), 'pint' );

						$pint_products[] = array_map( 'html_entity_decode', array(
							'product_name'     => $product->get_title(),
							'product_category' => ( $category_name ),
							'product_id'       => $get_content_id_pint,
							'product_quantity' => $item->get_quantity(),
							'product_price'    => $order->get_line_total( $item ),
						) );


						$get_content_id_gad = $this->get_content_id( $item->get_product(), 'google_ads' );

						$google_ads_products[] = array_map( 'html_entity_decode', array(
							'id'       => apply_filters( 'wfocu_ga_ecomm_id', $get_content_id_gad, $product ),
							'sku'      => empty( $product->get_sku() ) ? $product->get_id() : $product->get_sku(),
							'category' => $category_name,
							'name'     => $product->get_title(),
							'quantity' => $item->get_quantity(),
							'price'    => $order->get_line_total( $item ),
						) );


						$get_content_id_ga = $this->get_content_id( $item->get_product(), 'google_ua' );

						$google_products[] = array_map( 'html_entity_decode', array(
							'id'       => apply_filters( 'wfocu_ga_ecomm_id', $get_content_id_ga, $product ),
							'sku'      => empty( $product->get_sku() ) ? $product->get_id() : $product->get_sku(),
							'category' => $category_name,
							'name'     => $product->get_title(),
							'quantity' => $item->get_quantity(),
							'price'    => ( $item->get_quantity() ) > 1 ? $order->get_line_total( $item ) / $item->get_quantity() : $order->get_line_total( $item ),
							'variant'  => $item->get_product()->is_type( 'variation' ) ? implode( "/", $item->get_product()->get_variation_attributes() ) : '',
						) );
						$tiktok_contents[] = array_map( 'html_entity_decode', array(
							'content_id'   => $product->get_id(),
							'quantity'     => $item->get_quantity(),
							'content_type' => 'product'
						) );

					}
				}

				$advanced = array();


				if ( ! empty( $billing_email ) ) {
					$advanced['em'] = $billing_email;
				}

				$billing_phone = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_phone' );
				if ( ! empty( $billing_phone ) ) {
					$advanced['ph'] = $billing_phone;
				}

				$billing_first_name = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_first_name' );
				if ( ! empty( $billing_first_name ) ) {
					$advanced['fn'] = $billing_first_name;
				}

				$billing_last_name = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_last_name' );
				if ( ! empty( $billing_last_name ) ) {
					$advanced['ln'] = $billing_last_name;
				}

				$billing_city = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_city' );
				if ( ! empty( $billing_city ) ) {
					$advanced['ct'] = $billing_city;
				}

				$billing_state = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_state' );
				if ( ! empty( $billing_state ) ) {
					$advanced['st'] = $billing_state;
				}

				$billing_postcode = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_postcode' );
				if ( ! empty( $billing_postcode ) ) {
					$advanced['zp'] = $billing_postcode;
				}
				$billing_country = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_country' );
				if ( ! empty( $billing_country ) ) {
					$advanced['country'] = $billing_country;
				}


				$get_bumps = $order->get_meta( '_wfob_report_data', true );
				$bumps     = [];
				if ( ! empty( $get_bumps ) ) {

					foreach ( $get_bumps as $bump ) {
						$bumps[] = array( 'name' => get_the_title( $bump['bid'] ), 'converted' => $bump['converted'] );
					}
				}

				$fb_advanced = array_merge( $advanced, WFOCU_Common::pixel_advanced_matching_data() );
				if ( $order->get_customer_id() > 0 ) {
					$fb_advanced['external_id'] = $order->get_customer_id();
				} elseif ( isset( $_COOKIE['_fbp'] ) && ! empty( $_COOKIE['_fbp'] ) ) {
					$fb_advanced ['external_id'] = wc_clean( $_COOKIE['_fbp'] ); //phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
				}

				$fb_total = $this->get_total_order_value( $order, 'order', 'fb' );

				$tiktok_advanced = array();

				if ( isset( $advanced['em'] ) && $advanced["em"] !== "" ) {
					$tiktok_advanced['sha256_email'] = hash( 'sha256', $advanced['em'] );
				}
				if ( isset( $advanced['ph'] ) && $advanced["ph"] !== "" ) {
					$tiktok_advanced['sha256_phone_number'] = hash( 'sha256', $advanced['ph'] );
				}

				if ( $order->get_customer_id() > 0 ) {
					$tiktok_advanced['external_id'] = hash( 'sha256', $order->get_customer_id() );
				}

				$tiktok_advanced = array_merge( $tiktok_advanced, WFOCU_Common::tiktok_advanced_matching_data() );

				$get_order_id = WFOCU_WC_Compatibility::get_order_id( $order );

				$purchase_data = array(
					'fb'   => apply_filters( 'wfocu_ecomm_tracking_fb_params', array(
						'products'       => $products,
						'total'          => ( 0.00 === $fb_total || '0.00' === $fb_total ) ? 0 : $fb_total,
						'currency'       => WFOCU_WC_Compatibility::get_order_currency( $order ),
						'advanced'       => $fb_advanced,
						'content_ids'    => $content_ids,
						'content_name'   => $content_name,
						'category_name'  => array_map( 'html_entity_decode', $category_names ),
						'num_qty'        => $num_qty,
						'additional'     => $this->purchase_custom_aud_params( $order ),
						'transaction_id' => $get_order_id,
						'is_order'       => $get_order_id,
						'order_id'       => $get_order_id,
						'bumps'          => $bumps,
					), false, $order ),
					'pint' => array(
						'order_id'       => $get_order_id,
						'products'       => $pint_products,
						'total'          => $this->get_total_order_value( $order, 'order', 'pint' ),
						'currency'       => WFOCU_WC_Compatibility::get_order_currency( $order ),
						'email'          => $billing_email,
						'post_type'      => get_post_type(),
						'order_quantity' => $num_qty,
						'shipping'       => WFOCU_WC_Compatibility::get_order_shipping_total( $order ),
						'page_title'     => get_the_title(),
						'post_id'        => get_the_ID(),
						'event_url'      => $this->getRequestUri(),
						'eventID'        => WFOCU_Core()->data->generate_transient_key()
					),
				);

				$gad = apply_filters( 'wfocu_ecomm_tracking_gad_params', array(
					'event_category'   => 'ecommerce',
					'transaction_id'   => (string) $get_order_id,
					'value'            => $this->get_total_order_value( $order, 'order', 'gad' ),
					'currency'         => WFOCU_WC_Compatibility::get_order_currency( $order ),
					'items'            => $google_ads_products,
					'tax'              => $order->get_total_tax(),
					'shipping'         => WFOCU_WC_Compatibility::get_order_shipping_total( $order ),
					'ecomm_prodid'     => wp_list_pluck( $google_ads_products, 'id' ),
					'ecomm_pagetype'   => 'purchase',
					'ecomm_totalvalue' => array_sum( wp_list_pluck( $google_ads_products, 'price' ) ),
					'email'            => $billing_email,
					'fname'            => WFOCU_WC_Compatibility::get_order_data( $order, 'billing_first_name' ),
					'lname'            => WFOCU_WC_Compatibility::get_order_data( $order, 'billing_last_name' ),
					'address'          => $advanced,
				), false, $order );
				$ga  = apply_filters( 'wfocu_ecomm_tracking_ga_params', array(
					'event_category'   => 'ecommerce',
					'transaction_id'   => (string) $get_order_id,
					'value'            => $this->get_total_order_value( $order, 'order', 'ga' ),
					'currency'         => WFOCU_WC_Compatibility::get_order_currency( $order ),
					'items'            => $google_products,
					'tax'              => $order->get_total_tax(),
					'shipping'         => WFOCU_WC_Compatibility::get_order_shipping_total( $order ),
					'ecomm_prodid'     => wp_list_pluck( $google_products, 'id' ),
					'ecomm_pagetype'   => 'purchase',
					'ecomm_totalvalue' => array_sum( wp_list_pluck( $google_products, 'price' ) ),

				), false, $order );

				$tiktok = apply_filters( 'wfocu_ecomm_tracking_tiktok_params', [

					'contents'         => $tiktok_contents,
					'currency'         => WFOCU_WC_Compatibility::get_order_currency( $order ),
					'value'            => $this->get_total_order_value( $order, 'order' ),
					'content_name'     => implode( ', ', $content_name ),
					'content_category' => implode( ', ', array_map( 'html_entity_decode', $category_names ) ),
					'advanced'         => $tiktok_advanced

				], false, $order );

				$purchase_data['ga']       = $this->update_ga4_event_data( $ga, $order, $category_names );
				$purchase_data['gad']      = $gad;
				$purchase_data['tiktok']   = $tiktok;
				$purchase_data['snapchat'] = [
					'item_ids'       => $content_ids,
					'currency'       => WFOCU_WC_Compatibility::get_order_currency( $order ),
					'price'          => $this->get_total_order_value( $order, 'order' ),
					'number_items'   => count( $products ),
					'transaction_id' => $get_order_id
				];

				WFOCU_Core()->data->set( 'data', $purchase_data, 'track' );
				WFOCU_Core()->data->save( 'track' );
				WFOCU_Core()->log->log( 'Order #' . $order_id . ': Data for the parent order collected successfully.' );
			}
		}

		public function do_treat_variable_as_simple( $mode = 'pixel' ) {

			$do_treat_variable_as_simple = $this->admin_general_settings->get_option( $mode . '_variable_as_simple' );

			if ( ( 'pixel' === $mode ) && ( true !== $this->is_fb_enable_content_on() ) ) {
				return false;
			}

			if ( 1 === absint( $do_treat_variable_as_simple ) ) {
				return true;
			}

			return false;
		}

		public function get_woo_product_content_id( $product_id, $service = 'pixel' ) {

			$prefix            = '';
			$suffix            = '';
			$content_id_format = '';

			if ( ( 'pixel' === $service ) && ( true === $this->is_fb_enable_content_on() ) ) {
				$prefix            = $this->admin_general_settings->get_option( $service . '_content_id_prefix' );
				$suffix            = $this->admin_general_settings->get_option( $service . '_content_id_suffix' );
				$content_id_format = $this->admin_general_settings->get_option( $service . '_content_id_type' );
			}

			if ( 'pixel' !== $service ) {
				$prefix            = $this->admin_general_settings->get_option( $service . '_content_id_prefix' );
				$suffix            = $this->admin_general_settings->get_option( $service . '_content_id_suffix' );
				$content_id_format = $this->admin_general_settings->get_option( $service . '_content_id_type' );
			}

			if ( $content_id_format === 'product_sku' ) {
				$content_id = get_post_meta( $product_id, '_sku', true );
			} else {
				$content_id = $product_id;
			}
			$content_id = apply_filters( 'wfocu_get_product_content_id', $content_id, $product_id );

			$value = $prefix . $content_id . $suffix;

			return ( $value );

		}

		public function gad_product_id( $product_id ) {

			$prefix = $this->admin_general_settings->get_option( 'id_prefix_gad' );
			$suffix = $this->admin_general_settings->get_option( 'id_suffix_gad' );

			$value = $prefix . $product_id . $suffix;

			return $value;
		}

		/**
		 * Get the value of purchase event for the different cases of calculations.
		 *
		 * @param WC_Order/offer_Data $data
		 * @param string $type type for which this function getting called, order|offer
		 *
		 * @return string the modified order value
		 */
		public function get_total_order_value( $data, $type = 'order', $mode = '' ) {

			$disable_shipping = $this->is_disable_shipping( $mode );
			$disable_taxes    = $this->is_disable_taxes( $mode );

			if ( 'order' === $type ) {
				//process order
				if ( ! $disable_taxes && ! $disable_shipping ) {

					//send default total
					$total = $data->get_total();

				} elseif ( ! $disable_taxes && $disable_shipping ) {

					$cart_total     = floatval( $data->get_total( 'edit' ) );
					$shipping_total = floatval( $data->get_shipping_total( 'edit' ) );
					$shipping_tax   = floatval( $data->get_shipping_tax( 'edit' ) );

					$total = $cart_total - $shipping_total - $shipping_tax;
				} elseif ( $disable_taxes && ! $disable_shipping ) {

					$cart_subtotal = $data->get_subtotal();

					$discount_total = floatval( $data->get_discount_total( 'edit' ) );
					$shipping_total = floatval( $data->get_shipping_total( 'edit' ) );

					$total = $cart_subtotal - $discount_total + $shipping_total;
				} else {
					$cart_subtotal = $data->get_subtotal();

					$discount_total = floatval( $data->get_discount_total( 'edit' ) );

					$total = $cart_subtotal - $discount_total;
				}
			} else {
				//process offer
				if ( ! $disable_taxes && ! $disable_shipping ) {

					//send default total
					$total = $data['total'];

				} elseif ( ! $disable_taxes && $disable_shipping ) {
					//total - shipping cost - shipping tax
					$total = $data['total'] - ( isset( $data['shipping']['diff'] ) && isset( $data['shipping']['diff']['cost'] ) ? $data['shipping']['diff']['cost'] : 0 ) - ( isset( $data['shipping']['diff'] ) && isset( $data['shipping']['diff']['tax'] ) ? $data['shipping']['diff']['tax'] : 0 );

				} elseif ( $disable_taxes && ! $disable_shipping ) {
					//total - taxes
					$total = $data['total'] - ( isset( $data['taxes'] ) ? $data['taxes'] : 0 );

				} else {

					//total - taxes - shipping cost
					$total = $data['total'] - ( isset( $data['taxes'] ) ? $data['taxes'] : 0 ) - ( isset( $data['shipping']['diff'] ) && isset( $data['shipping']['diff']['cost'] ) ? $data['shipping']['diff']['cost'] : 0 );

				}
			}

			$total = apply_filters( 'wfocu_ecommerce_pixel_tracking_value', $total, $data, $mode, $this->admin_general_settings );

			return number_format( $total, wc_get_price_decimals(), '.', '' );
		}

		public function is_disable_shipping( $party = 'fb' ) {
			if ( $party === 'fb' ) {
				$exclude_from_total = $this->admin_general_settings->get_option( 'exclude_from_total' );
			} elseif ( $party === 'ga' ) {
				$exclude_from_total = $this->admin_general_settings->get_option( 'ga_exclude_from_total' );
			} elseif ( $party === 'gad' ) {
				$exclude_from_total = $this->admin_general_settings->get_option( 'gad_exclude_from_total' );
			} elseif ( $party === 'pint' ) {
				$exclude_from_total = $this->admin_general_settings->get_option( 'pint_exclude_from_total' );
			} else {
				return false;
			}

			if ( is_array( $exclude_from_total ) && count( $exclude_from_total ) > 0 && in_array( 'is_disable_shipping', $exclude_from_total, true ) ) {
				return true;
			}

			return false;

		}

		public function is_disable_taxes( $party = 'fb' ) {
			if ( $party === 'fb' ) {
				$exclude_from_total = $this->admin_general_settings->get_option( 'exclude_from_total' );
			} elseif ( $party === 'ga' ) {
				$exclude_from_total = $this->admin_general_settings->get_option( 'ga_exclude_from_total' );
			} elseif ( $party === 'gad' ) {
				$exclude_from_total = $this->admin_general_settings->get_option( 'gad_exclude_from_total' );
			} elseif ( $party === 'pint' ) {
				$exclude_from_total = $this->admin_general_settings->get_option( 'pint_exclude_from_total' );
			} else {
				return false;
			}

			if ( is_array( $exclude_from_total ) && count( $exclude_from_total ) > 0 && in_array( 'is_disable_taxes', $exclude_from_total, true ) ) {
				return true;
			}

			return false;

		}

		/**
		 * @param WC_Order $order
		 *
		 * @return array
		 */
		public function purchase_custom_aud_params( $order ) {

			$params = array();


			$params['town']    = $order->get_billing_city();
			$params['state']   = $order->get_billing_state();
			$params['country'] = $order->get_billing_country();


			$params['payment'] = $order->get_payment_method_title();


			// shipping method
			$shipping_methods = $order->get_items( 'shipping' );
			if ( $shipping_methods ) {

				$labels = array();
				foreach ( $shipping_methods as $shipping ) {
					$labels[] = $shipping['name'] ? $shipping['name'] : null;
				}

				$params['shipping'] = implode( ', ', $labels );

			}

			// coupons
			$coupons = $order->get_items( 'coupon' );
			if ( $coupons ) {

				$labels = array();
				foreach ( $coupons as $coupon ) {
					if ( $coupon instanceof WC_Order_Item ) {
						$labels[] = $coupon->get_code();
					} else {
						$labels[] = $coupon['name'] ? $coupon['name'] : null;

					}
				}

				$params['coupon_used'] = 'yes';
				$params['coupon_name'] = implode( ', ', $labels );

			} else {

				$params['coupon_used'] = 'no';

			}

			return $params;

		}

		/**
		 * @hooked over `wfocu_offer_accepted_and_processed`
		 * Sets up a cookie data for tracking based on the offer/upsell accepted by the customer
		 *
		 * @param int $get_current_offer Current offer
		 * @param array $get_package current package
		 */
		public function maybe_save_data_offer_accepted( $get_current_offer, $get_package, $get_parent_order, $new_order ) {
			$get_offer_Data = WFOCU_Core()->data->get( '_current_offer' );
			if ( $this->is_tracking_on() ) {
				$content_ids         = [];
				$content_name        = [];
				$category_names      = [];
				$num_qty             = 0;
				$products            = [];
				$google_ads_products = [];
				$pint_products       = [];
				$google_products     = [];
				$tiktok_contents     = [];

				foreach ( $get_package['products'] as $product ) {

					$pid         = $fbpid = $product['id'];
					$product_obj = wc_get_product( $pid );
					if ( $product_obj instanceof WC_product ) {
						$content_name[] = $product_obj->get_title();
						$content_ids[]  = $fbpid = $this->get_content_id( $product_obj );

						$category      = $product_obj->get_category_ids();
						$category_name = '';
						if ( is_array( $category ) && count( $category ) > 0 ) {
							$category_id = $category[0];
							if ( is_numeric( $category_id ) && $category_id > 0 ) {
								$cat_term = get_term_by( 'id', $category_id, 'product_cat' );
								if ( $cat_term ) {
									$category_name    = $cat_term->name;
									$category_names[] = $cat_term->name;
								}
							}
						}


						$get_content_id_pint = $this->get_content_id( $product_obj, 'pint' );
						$get_content_id_ga   = $this->get_content_id( $product_obj, 'google_ua' );
						$get_content_id_gad  = $this->get_content_id( $product_obj, 'google_ads' );


						$num_qty         += $product['qty'];
						$products[]      = array_map( 'html_entity_decode', array(
							'name'       => $product['_offer_data']->name,
							'category'   => esc_attr( $category_name ),
							'id'         => $fbpid,
							'quantity'   => $product['qty'],
							'item_price' => $product['args']['total'],
						) );
						$pint_products[] = array_map( 'html_entity_decode', array(
							'product_id'       => $get_content_id_pint,
							'product_category' => $category_name,
							'product_name'     => $product['_offer_data']->name,
							'product_quantity' => $product['qty'],
							'product_price'    => $product['args']['total'],
						) );

						$google_ads_products[] = array_map( 'html_entity_decode', array(
							'id'       => apply_filters( 'wfocu_ga_ecomm_id', $get_content_id_gad, $product_obj ),
							'sku'      => empty( $product_obj->get_sku() ) ? $product_obj->get_id() : $product_obj->get_sku(),
							'category' => $category_name,
							'name'     => $product['_offer_data']->name,
							'quantity' => $product['qty'],
							'price'    => $product['args']['total'] / $product['qty'],
						) );

						$google_products[] = array_map( 'html_entity_decode', array(
							'id'       => apply_filters( 'wfocu_ga_ecomm_id', $get_content_id_ga, $product_obj ),
							'sku'      => empty( $product_obj->get_sku() ) ? $product_obj->get_id() : $product_obj->get_sku(),
							'category' => $category_name,
							'name'     => $product['_offer_data']->name,
							'quantity' => $product['qty'],
							'price'    => $product['args']['total'] / $product['qty'],
							'variant'  => $product_obj->is_type( 'variation' ) ? implode( "/", $product_obj->get_variation_attributes() ) : '',

						) );
						$tiktok_contents[] = array_map( 'html_entity_decode', array(
							'content_id'   => $product_obj->get_id(),
							'quantity'     => $product['qty'],
							'content_type' => 'product'
						) );


					}
				}
				$order         = WFOCU_Core()->data->get_current_order();
				$billing_email = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_email' );
				$advanced      = array();


				if ( ! empty( $billing_email ) ) {
					$advanced['em'] = $billing_email;
				}

				$billing_phone = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_phone' );
				if ( ! empty( $billing_phone ) ) {
					$advanced['ph'] = $billing_phone;
				}

				$billing_first_name = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_first_name' );
				if ( ! empty( $billing_first_name ) ) {
					$advanced['fn'] = $billing_first_name;
				}

				$billing_last_name = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_last_name' );
				if ( ! empty( $billing_last_name ) ) {
					$advanced['ln'] = $billing_last_name;
				}

				$billing_city = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_city' );
				if ( ! empty( $billing_city ) ) {
					$advanced['ct'] = $billing_city;
				}

				$billing_state = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_state' );
				if ( ! empty( $billing_state ) ) {
					$advanced['st'] = $billing_state;
				}

				$billing_postcode = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_postcode' );
				if ( ! empty( $billing_postcode ) ) {
					$advanced['zp'] = $billing_postcode;
				}
				$billing_country = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_country' );
				if ( ! empty( $billing_country ) ) {
					$advanced['country'] = $billing_country;
				}
				$fb_advanced = array_merge( $advanced, WFOCU_Common::pixel_advanced_matching_data() );

				if ( $order->get_customer_id() > 0 ) {
					$fb_advanced['external_id'] = $order->get_customer_id();
				} elseif ( isset( $_COOKIE['_fbp'] ) && ! empty( $_COOKIE['_fbp'] ) ) {
					$fb_advanced ['external_id'] = wc_clean( $_COOKIE['_fbp'] ); //phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
				}

				$fb_total        = $this->get_total_order_value( $get_package, 'offer', 'fb' );
				$tiktok_advanced = array();
				if ( isset( $advanced['em'] ) ) {
					$tiktok_advanced['sha256_email'] = hash( 'sha256', $advanced['em'] );
				}
				if ( isset( $advanced['ph'] ) ) {
					$tiktok_advanced['sha256_phone_number'] = hash( 'sha256', $advanced['ph'] );
				}

				if ( $order->get_customer_id() > 0 ) {
					$tiktok_advanced['external_id'] = hash( 'sha256', $order->get_customer_id() );
				}

				$tiktok_advanced = array_merge( $tiktok_advanced, WFOCU_Common::tiktok_advanced_matching_data() );

				$billing_country = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_country' );
				if ( ! empty( $billing_country ) ) {
					$advanced['country'] = $billing_country;
				}

				$get_order_id   = WFOCU_WC_Compatibility::get_order_id( $order );
				$offer_order_id = $get_order_id . '-' . $get_current_offer;
				/**
				 * Check if new order created by upsell
				 * then we send here new order id
				 */
				if ( $new_order instanceof WC_Order ) {
					$offer_order_id = $new_order->get_id();
				}

				$purchase_data = array(
					'fb'                      => apply_filters( 'wfocu_ecomm_tracking_fb_params', array(
						'products'       => $products,
						'total'          => ( 0.00 === $fb_total || '0.00' === $fb_total ) ? 0 : $fb_total,
						'currency'       => WFOCU_WC_Compatibility::get_order_currency( $order ),
						'advanced'       => $fb_advanced,
						'content_ids'    => $content_ids,
						'content_name'   => $content_name,
						'category_name'  => array_map( 'html_entity_decode', $category_names ),
						'num_qty'        => $num_qty,
						'additional'     => $this->purchase_custom_aud_params( $order ),
						'transaction_id' => $offer_order_id,
						'is_offer'       => $get_current_offer,
						'is_order'       => $get_order_id,
						'order_id'       => $offer_order_id,
					), true, $order, $get_package ),
					'pint'                    => array(
						'order_id'       => $offer_order_id,
						'products'       => $pint_products,
						'total'          => $this->get_total_order_value( $get_package, 'offer', 'pint' ),
						'currency'       => WFOCU_WC_Compatibility::get_order_currency( $order ),
						'email'          => $billing_email,
						'post_type'      => get_post_type(),
						'order_quantity' => $num_qty,
						'shipping'       => ( $get_package['shipping'] && isset( $get_package['shipping']['diff']['cost'] ) ) ? $get_package['shipping']['diff']['cost'] : 0,
						'page_title'     => get_the_title(),
						'post_id'        => get_the_ID(),
						'event_url'      => $this->getRequestUri(),
						'eventID'        => WFOCU_Core()->data->generate_transient_key()
					),
					'success_offer'           => $get_offer_Data->settings->upsell_page_purchase_code,
					'purchase_script_enabled' => $get_offer_Data->settings->check_add_offer_purchase,
				);


				$gad = apply_filters( 'wfocu_ecomm_tracking_gad_params', array(
					'event_category'   => 'ecommerce',
					'transaction_id'   => $offer_order_id,
					'value'            => $this->get_total_order_value( $get_package, 'offer', 'gad' ),
					'currency'         => WFOCU_WC_Compatibility::get_order_currency( $order ),
					'items'            => $google_ads_products,
					'tax'              => $get_package['taxes'],
					'shipping'         => ( $get_package['shipping'] && isset( $get_package['shipping']['diff']['cost'] ) ) ? $get_package['shipping']['diff']['cost'] : 0,
					'ecomm_prodid'     => wp_list_pluck( $google_ads_products, 'id' ),
					'ecomm_pagetype'   => 'purchase',
					'ecomm_totalvalue' => array_sum( wp_list_pluck( $google_ads_products, 'price' ) ),
					'email'            => $billing_email,
					'fname'            => WFOCU_WC_Compatibility::get_order_data( $order, 'billing_first_name' ),
					'lname'            => WFOCU_WC_Compatibility::get_order_data( $order, 'billing_last_name' ),
					'address'          => $advanced,
				), true, $order, $get_package );

				$ga     = apply_filters( 'wfocu_ecomm_tracking_ga_params', array(
					'event_category'   => 'ecommerce',
					'transaction_id'   => $offer_order_id,
					'value'            => $this->get_total_order_value( $get_package, 'offer', 'ga' ),
					'currency'         => WFOCU_WC_Compatibility::get_order_currency( $order ),
					'items'            => $google_products,
					'tax'              => $get_package['taxes'],
					'shipping'         => ( $get_package['shipping'] && isset( $get_package['shipping']['diff']['cost'] ) ) ? $get_package['shipping']['diff']['cost'] : 0,
					'ecomm_prodid'     => wp_list_pluck( $google_products, 'id' ),
					'ecomm_pagetype'   => 'purchase',
					'ecomm_totalvalue' => array_sum( wp_list_pluck( $google_products, 'price' ) ),

				), true, $order, $get_package );
				$tiktok = apply_filters( 'wfocu_ecomm_tracking_tiktok_params', [

					'contents'         => $tiktok_contents,
					'currency'         => WFOCU_WC_Compatibility::get_order_currency( $order ),
					'value'            => $this->get_total_order_value( $get_package, 'offer' ),
					'content_name'     => implode( ', ', $content_name ),
					'content_category' => implode( ', ', array_map( 'html_entity_decode', $category_names ) ),
					'advanced'         => $tiktok_advanced,
				], true, $order, $get_package );

				$purchase_data['ga']       = $this->update_ga4_event_data( $ga, $order, $category_names );
				$purchase_data['gad']      = $gad;
				$purchase_data['tiktok']   = $tiktok;
				$purchase_data['snapchat'] = [
					'item_ids'       => $content_ids,
					'currency'       => WFOCU_WC_Compatibility::get_order_currency( $order ),
					'price'          => $this->get_total_order_value( $get_package, 'offer' ),
					'number_items'   => count( $products ),
					'transaction_id' => $offer_order_id
				];


				WFOCU_Core()->data->set( 'data', $purchase_data, 'track' );
				WFOCU_Core()->data->save( 'track' );
			}

		}

		public function render_global_external_scripts() {

			if ( '' !== WFOCU_Core()->data->get_option( 'scripts' ) ) {
				echo WFOCU_Core()->data->get_option( 'scripts' );  //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		public function render_global_external_scripts_head() {

			if ( $this->should_render( false, true ) && '' !== WFOCU_Core()->data->get_option( 'scripts_head' ) ) {
				echo WFOCU_Core()->data->get_option( 'scripts_head' );  //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		/**
		 * Render Offer View script
		 */
		public function render_offer_view_script() {
			$get_offer_Data = WFOCU_Core()->data->get( '_current_offer' );
			if ( $this->should_render( false ) && $get_offer_Data && is_object( $get_offer_Data ) && true === $get_offer_Data->settings->check_add_offer_script && '' !== $get_offer_Data->settings->upsell_page_track_code ) {
				echo $get_offer_Data->settings->upsell_page_track_code;   //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		/**
		 * Render successful offer script
		 */
		public function render_offer_success_script() {
			$data = WFOCU_Core()->data->get( 'data', array(), 'track' );
			if ( ! is_array( $data ) ) {
				return;
			}

			if ( ! isset( $data['success_offer'] ) || ( isset( $data['purchase_script_enabled'] ) && false === $data['purchase_script_enabled'] ) ) {
				return;
			}

			echo $data['success_offer'];  //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		/**
		 * Render funnel end script
		 */
		public function render_funnel_end() {
			$funnel_id = WFOCU_Core()->data->get_funnel_id();

			if ( empty( $funnel_id ) ) {
				return;
			}

			$script = WFOCU_Core()->funnels->setup_funnel_options( $funnel_id )->get_funnel_option( 'funnel_success_script' );

			if ( '' === $script ) {
				return;
			}

			echo $script;  //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		public function maybe_remove_track_data() {


			$get_gen_tracking_data = WFOCU_Core()->data->get( 'general_data', array(), 'track' );
			$get_tracking_data     = WFOCU_Core()->data->get( 'data', array(), 'track' );
			/**
			 * only set it blank when it exists
			 */
			if ( ! empty( $get_tracking_data ) && ! empty( $get_gen_tracking_data ) && ! is_wc_endpoint_url( 'order-pay' ) ) {
				$data = array();
				if ( ! $this->is_conversion_api() ) {
					WFOCU_Core()->data->set( 'data', $data, 'track' );
				}

				WFOCU_Core()->data->set( 'general_data', $data, 'track' );
				WFOCU_Core()->data->save( 'track' );
			}

		}

		public function should_render_utm_script() {
			if ( true === wc_string_to_bool( $this->admin_general_settings->get_option( 'track_utms' ) ) ) {
				return true;
			}

			return false;
		}

		public function render_js_to_track_referer() {

			BWF_Ecomm_Tracking_Common::get_instance()->render();
		}

		/**
		 * Add Generic event params to the data in events
		 * @return array
		 */
		public function get_generic_event_params() {

			$user = wp_get_current_user();

			if ( $user->ID !== 0 ) {
				$user_roles = implode( ',', $user->roles );
			} else {
				$user_roles = 'guest';
			}

			return array(
				'user_roles' => $user_roles,
				'plugin'     => 'WooFunnels Upsells',
			);

		}

		/**
		 * @param string $taxonomy Taxonomy name
		 * @param int $post_id (optional) Post ID. Current will be used of not set
		 *
		 * @return string|array List of object terms
		 */
		public function get_object_terms( $taxonomy, $post_id = null, $implode = true ) {

			$post_id = isset( $post_id ) ? $post_id : get_the_ID();
			$terms   = get_the_terms( $post_id, $taxonomy );

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				return $implode ? '' : array();
			}

			$results = array();

			foreach ( $terms as $term ) {
				$results[] = html_entity_decode( $term->name );
			}

			if ( $implode ) {
				return implode( ', ', $results );
			} else {
				return $results;
			}

		}


		public function get_localstorage_hash( $key ) {
			$data = WFOCU_Core()->data->get( 'data', array(), 'track' );
			if ( ! isset( $data[ $key ] ) ) {
				return 0;
			}

			return md5( wp_json_encode( array( 'key' => WFOCU_Core()->data->get_transient_key(), 'data' => $data[ $key ] ) ) );
		}

		public function tracking_log_js() {
			wp_add_inline_script( 'jquery-core', $this->maybe_clear_local_storage_for_tracking_log() );
		}

		/**
		 * We track in localstorage if we pushed ecommerce event for certain data or not
		 * Unfortunetly we cannot remove the storge on thank you as user still can press the back button and events will fire again
		 * So the next most logical way to remove the storage is during the next updated checkout action.
		 */
		public function maybe_clear_local_storage_for_tracking_log() {
			$js = '';
			if ( is_checkout() ) {
				$js = "if (window.jQuery) {
                    (function ($) {
                        if (!String.prototype.startsWith) {
                            String.prototype.startsWith = function (searchString, position) {
                                position = position || 0;
                                return this.indexOf(searchString, position) === position;
                            };
                        }
                        window.addEventListener('DOMContentLoaded', (event) => {
							$(document.body).on('updated_checkout', function () {
								if (localStorage.length > 0) {
									var len = localStorage.length;
									var wfocuRemoveLS = [];
									for (var i = 0; i < len; ++i) {
										var storage_key = localStorage.key(i);
										if (storage_key.startsWith('wfocuH_') === true) {
											wfocuRemoveLS.push(storage_key);
										}
									}
									for (var eachLS in wfocuRemoveLS) {
										localStorage.removeItem(wfocuRemoveLS[eachLS]);
									}

								}
							});
                        });

                    })(jQuery);
                }";

			}

			return $js;
		}

		/**
		 * @hooked over `woocommerce_checkout_order_processed`
		 * Just after funnel initiated we try and setup cookie data for the parent order
		 * That will be further used by WFOCU_Ecomm_Tracking::render_general
		 *
		 * @param WC_Order $order
		 */
		public function maybe_save_order_data_general( $order_id, $posted_data = array(), $order = null ) {
			if ( ! $order instanceof WC_Order ) {
				$order = wc_get_order( $order_id );
			}
			$order_id       = $order->get_id();
			$items          = $order->get_items( 'line_item' );
			$content_ids    = [];
			$content_name   = [];
			$category_names = [];
			$num_qty        = 0;
			$products       = [];
			$billing_email  = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_email' );
			foreach ( $items as $item ) {
				$pid     = $item->get_product_id();
				$product = wc_get_product( $pid );
				if ( $product instanceof WC_product ) {

					$category       = $product->get_category_ids();
					$content_name[] = $product->get_title();
					$variation_id   = $item->get_variation_id();
					$get_content_id = 0;
					if ( empty( $variation_id ) ) {
						$get_content_id = $content_ids[] = $this->get_woo_product_content_id( $pid );
					} else {
						$get_content_id = $content_ids[] = $this->get_woo_product_content_id( $variation_id );
						$product        = wc_get_product( $variation_id );

					}
					$category_name = '';

					if ( is_array( $category ) && count( $category ) > 0 ) {
						$category_id = $category[0];
						if ( is_numeric( $category_id ) && $category_id > 0 ) {
							$cat_term = get_term_by( 'id', $category_id, 'product_cat' );
							if ( $cat_term ) {
								$category_name    = $cat_term->name;
								$category_names[] = $category_name;
							}
						}
					}
					$num_qty    += $item->get_quantity();
					$products[] = array_map( 'html_entity_decode', array(
						'name'       => $product->get_title(),
						'pid'        => $pid,
						'category'   => $category_name,
						'id'         => $get_content_id,
						'sku'        => $product->get_sku(),
						'quantity'   => $item->get_quantity(),
						'item_total' => $order->get_item_subtotal( $item ),
						'line_total' => $order->get_line_subtotal( $item ),
					) );

				}
			}

			$advanced = array();

			if ( ! empty( $billing_email ) ) {
				$advanced['em'] = $billing_email;
			}

			$billing_phone = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_phone' );
			if ( ! empty( $billing_phone ) ) {
				$advanced['ph'] = $billing_phone;
			}

			$billing_first_name = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_first_name' );
			if ( ! empty( $billing_first_name ) ) {
				$advanced['fn'] = $billing_first_name;
			}

			$billing_last_name = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_last_name' );
			if ( ! empty( $billing_last_name ) ) {
				$advanced['ln'] = $billing_last_name;
			}

			$billing_city = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_city' );
			if ( ! empty( $billing_city ) ) {
				$advanced['ct'] = $billing_city;
			}

			$billing_state = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_state' );
			if ( ! empty( $billing_state ) ) {
				$advanced['st'] = $billing_state;
			}

			$billing_postcode = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_postcode' );
			if ( ! empty( $billing_postcode ) ) {
				$advanced['zp'] = $billing_postcode;
			}

			WFOCU_Core()->data->set( 'general_data', array(
				'products'       => $products,
				'total'          => $this->get_total_order_value( $order, 'order', 'fb' ),
				'currency'       => WFOCU_WC_Compatibility::get_order_currency( $order ),
				'advanced'       => $advanced,
				'content_ids'    => $content_ids,
				'content_name'   => $content_name,
				'category_name'  => array_map( 'html_entity_decode', $category_names ),
				'num_qty'        => $num_qty,
				'additional'     => $this->purchase_custom_aud_params( $order ),
				'transaction_id' => WFOCU_WC_Compatibility::get_order_id( $order ),
				'order_id'       => WFOCU_WC_Compatibility::get_order_id( $order ),
				'email'          => $billing_email,
				'first_name'     => WFOCU_WC_Compatibility::get_order_data( $order, 'billing_first_name' ),
				'last_name'      => WFOCU_WC_Compatibility::get_order_data( $order, 'billing_last_name' ),
				'affiliation'    => esc_attr( get_bloginfo( 'name' ) ),
				'shipping'       => WFOCU_WC_Compatibility::get_order_shipping_total( $order ),
				'tax'            => $order->get_total_tax(),

			), 'track' );
			WFOCU_Core()->data->save( 'track' );
			WFOCU_Core()->log->log( 'Order #' . $order_id . ': General Data for the parent order collected successfully.' );
		}

		/**
		 * @hooked over `wfocu_offer_accepted_and_processed`
		 * Sets up a cookie data for tracking based on the offer/upsell accepted by the customer
		 *
		 * @param int $get_current_offer Current offer
		 * @param array $get_package current package
		 */
		public function maybe_save_data_offer_accepted_general( $get_current_offer, $get_package, $get_parent_order, $new_order ) {
			$get_offer_Data = WFOCU_Core()->data->get( '_current_offer' );

			$content_ids         = [];
			$content_name        = [];
			$category_names      = [];
			$num_qty             = 0;
			$products            = [];
			$google_ads_products = [];
			$content_id_format   = $this->admin_general_settings->get_option( 'content_id_value' );

			foreach ( $get_package['products'] as $product ) {
				$pid         = $fbpid = $product['id'];
				$product_obj = wc_get_product( $pid );
				if ( $product_obj instanceof WC_product ) {
					$content_name[] = $product_obj->get_title();


					$content_ids[] = $this->get_woo_product_content_id( $product_obj->get_id() );
					$fbpid         = $product_obj->get_id();

					$category      = $product_obj->get_category_ids();
					$category_name = '';
					if ( is_array( $category ) && count( $category ) > 0 ) {
						$category_id = $category[0];
						if ( is_numeric( $category_id ) && $category_id > 0 ) {
							$cat_term = get_term_by( 'id', $category_id, 'product_cat' );
							if ( $cat_term ) {
								$category_name    = $cat_term->name;
								$category_names[] = $cat_term->name;
							}
						}
					}
					$num_qty    += $product['qty'];
					$products[] = array_map( 'html_entity_decode', array(
						'name'       => $product['_offer_data']->name,
						'category'   => $category_name,
						'id'         => ( 'product_sku' === $content_id_format ) ? get_post_meta( $fbpid, '_sku', true ) : $fbpid,
						'sku'        => $product_obj->get_sku(),
						'quantity'   => $product['qty'],
						'item_price' => $product['args']['total'],
						'price'      => $product['args']['total'],
						'product_id' => $pid,
					) );
				}
			}
			$order         = WFOCU_Core()->data->get_current_order();
			$billing_email = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_email' );
			$advanced      = array();


			if ( ! empty( $billing_email ) ) {
				$advanced['em'] = $billing_email;
			}

			$billing_phone = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_phone' );
			if ( ! empty( $billing_phone ) ) {
				$advanced['ph'] = $billing_phone;
			}

			$billing_first_name = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_first_name' );
			if ( ! empty( $billing_first_name ) ) {
				$advanced['fn'] = $billing_first_name;
			}

			$billing_last_name = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_last_name' );
			if ( ! empty( $billing_last_name ) ) {
				$advanced['ln'] = $billing_last_name;
			}

			$billing_city = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_city' );
			if ( ! empty( $billing_city ) ) {
				$advanced['ct'] = $billing_city;
			}

			$billing_state = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_state' );
			if ( ! empty( $billing_state ) ) {
				$advanced['st'] = $billing_state;
			}

			$billing_postcode = WFOCU_WC_Compatibility::get_order_data( $order, 'billing_postcode' );
			if ( ! empty( $billing_postcode ) ) {
				$advanced['zp'] = $billing_postcode;
			}

			if ( $new_order instanceof WC_Order ) {
				$ga_transaction_id = WFOCU_WC_Compatibility::get_order_id( $new_order );
			} else {
				$ga_transaction_id = WFOCU_WC_Compatibility::get_order_id( $get_parent_order );
			}
			WFOCU_Core()->data->set( 'general_data', array(

				'products'                => $products,
				'total'                   => $this->get_total_order_value( $get_package, 'offer', 'fb' ),
				'currency'                => WFOCU_WC_Compatibility::get_order_currency( $order ),
				'advanced'                => $advanced,
				'content_ids'             => $content_ids,
				'content_name'            => $content_name,
				'category_name'           => array_map( 'html_entity_decode', $category_names ),
				'num_qty'                 => $num_qty,
				'additional'              => $this->purchase_custom_aud_params( $order ),
				'transaction_id'          => WFOCU_WC_Compatibility::get_order_id( $order ) . '-' . $get_current_offer,
				'email'                   => $billing_email,
				'first_name'              => WFOCU_WC_Compatibility::get_order_data( $order, 'billing_first_name' ),
				'last_name'               => WFOCU_WC_Compatibility::get_order_data( $order, 'billing_last_name' ),
				'ga_transaction_id'       => $ga_transaction_id,
				'affiliation'             => esc_attr( get_bloginfo( 'name' ) ),
				'revenue'                 => $get_package['total'],
				'offer'                   => $get_current_offer,
				'shipping'                => ( $get_package['shipping'] && isset( $get_package['shipping']['diff']['cost'] ) ) ? $get_package['shipping']['diff']['cost'] : 0,
				'tax'                     => $get_package['taxes'],
				'ecomm_prod_ids'          => wp_list_pluck( $google_ads_products, 'id' ),
				'purchase_script_enabled' => $get_offer_Data->settings->check_add_offer_purchase,

			), 'track' );
			WFOCU_Core()->data->save( 'track' );

		}

		/************************************ Conversion API related methods starts here ***************************/
		/**
		 * Maybe insert logs for the conversion API
		 *
		 * @param string $content
		 */
		public function maybe_insert_log( $content ) {

			if ( $this->is_enabled_log() ) {
				wc_get_logger()->log( 'info', $content, array( 'source' => 'bwf_facebook_conversion_api' ) );
			}
		}

		/**
		 * Check if logs are enabled or not for the conversion API
		 * @return bool
		 */
		public function is_enabled_log() {
			$is_conversion_api_log = $this->admin_general_settings->get_option( 'is_fb_conversion_api_log' );
			if ( is_array( $is_conversion_api_log ) && count( $is_conversion_api_log ) > 0 && 'yes' === $is_conversion_api_log[0] ) {
				return true;
			}

			return false;
		}

		/**
		 * Get current hour in the format supported by Facebook
		 * @return string string
		 */
		public function getHour() {
			$array = [
				'00-01',
				'01-02',
				'02-03',
				'03-04',
				'04-05',
				'05-06',
				'06-07',
				'07-08',
				'08-09',
				'09-10',
				'10-11',
				'11-12',
				'12-13',
				'13-14',
				'14-15',
				'15-16',
				'16-17',
				'17-18',
				'18-19',
				'19-20',
				'20-21',
				'21-22',
				'22-23',
				'23-24'
			];

			return $array[ current_time( "G" ) ];

		}

		/**
		 * Check all possible UTMs value saved in cookies
		 * @return array
		 */
		public function get_utms() {
			$wfocuUtm_terms = [ "utm_source", "utm_medium", "utm_campaign", "utm_term", "utm_content" ];
			$utms           = [];
			foreach ( $wfocuUtm_terms as $term ) {
				if ( isset( $_COOKIE[ 'wffn_' . $term ] ) && ! empty( $_COOKIE[ 'wffn_' . $term ] ) ) {
					$utms[ $term ] = wc_clean( $_COOKIE[ 'wffn_' . $term ] ); //phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
				}

			}

			return $utms;
		}

		/**
		 * Get traffic source saved in cookie for the conversion API
		 * @return array|false|string
		 */
		public function get_traffic_source() {
			$referrer = wc_get_raw_referer();

			$direct = empty( $referrer ) ? false : true;
			if ( $direct ) {
				$internal = false;
			} else {
				if ( false !== strpos( $referrer, site_url() ) ) {
					$internal = true;
				} else {
					$internal = false;
				}
			}

			if ( ! ( $direct || $internal ) ) {
				$external = true;
			} else {
				$external = false;
			}
			if ( isset( $_COOKIE['wfocu_fb_pixel_traffic_source'] ) && ! empty( $_COOKIE['wfocu_fb_pixel_traffic_source'] ) ) {
				$cookie = wc_clean( $_COOKIE['wfocu_fb_pixel_traffic_source'] ); //phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
			} else {
				$cookie = false;
			}

			if ( $external === false ) {
				return $cookie ?: 'direct';
			} else {
				return $cookie && $cookie === $referrer ? $cookie : $referrer;
			}


		}

		/**
		 * Is conversion API enabled from global settings
		 * @return bool
		 */
		public function is_conversion_api() {
			if ( !empty( $this->admin_general_settings->get_option( 'conversion_api_access_token' ) ) ) {
				return true;
			}
			return false;
		}

		/**
		 * Render a JS to fire async ajax calls to fire further events
		 */
		public function maybe_render_conv_api_js() {
			/**
			 * Special handling for the order received page
			 */
			if ( $this->is_tracking_on() && is_order_received_page() && $this->is_conversion_api() && $this->do_track_fb_purchase_event() ) {
				$get_data_from_session = WFOCU_Core()->data->get( 'data', array(), 'track' );
				if ( isset( $get_data_from_session['fb'] ) ) {
					$this->maybe_render_conv_api();
				}
				$this->api_events = [];
				?>
                <script type="text/javascript">
                    function wfocuConvTrackingIn() {
                        var wfocu_shouldRender = 1;
						<?php do_action( 'wfocu_allow_tracking_inline_js' ); ?>
                        if (1 === wfocu_shouldRender) {
                            if (window.history.pushState) {
                                history.pushState(null, document.title, location.href);
                                window.addEventListener('popstate', function (event) {
                                    history.pushState(null, document.title, location.href);
                                });
                            }
                        }
                    }
                </script>
				<?php
			}
			if ( $this->should_render( true ) && $this->is_tracking_on() && $this->is_conversion_api() ) {
				?>
                <script type="text/javascript" <?php echo esc_attr( apply_filters( 'wfocu_script_tags', '', 'window_capi' ) ); ?>>
                    function wfocuConvBaseTrackingIn() {
                        var wfocu_shouldRender = 1;
						<?php do_action( 'wfocu_allow_tracking_inline_js' ); ?>
                        if (1 === wfocu_shouldRender) {
                            window.wfocuCapiEvents = [];
                            window.wfocuSetCapiEvents = function (event, eventID, args) {
                                if (typeof args === 'undefined') {
                                    args = [];
                                }
                                window.wfocuCapiEvents.push({'event': event, 'event_id': eventID, 'args': args});
                            }
                            window.wfocuGetCapiEvents = function () {
                                var server = JSON.parse('<?php echo wp_json_encode( $this->api_events ) ?>');
                                return server.concat(window.wfocuCapiEvents);
                            }
                            window.addEventListener('load', (event) => {
                                var wfocu_wc_ajax_url = '<?php echo esc_url( WC_AJAX::get_endpoint( '%%endpoint%%' ) ) ?>';
                                var xhr = new XMLHttpRequest();
                                var inst = this;
                                xhr.open("POST", wfocu_wc_ajax_url.toString().replace('%%endpoint%%', 'wfocu_fire_conv_api_event'), true);
                                //Send the proper header information along with the request
                                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

                                var finalq = 'data=' + JSON.stringify(window.wfocuGetCapiEvents());
                                var finalq = finalq + '&_wpnonce=' + '<?php echo esc_attr( wp_create_nonce( 'wfocu_fire_conv_api_event' ) ); ?>';
                                xhr.send(finalq);
                            });
                        }
                    }
                </script>
				<?php
			}


		}

		public function maybe_render_conv_api( $events = [], $is_ajax = false ) {
			/**
			 * Special handling for the order received page
			 */
			if ( $this->is_conversion_api() ) {
				$events = ! empty( $events ) && $is_ajax ? $events : $this->api_events;

				$get_all_fb_pixel = $this->is_fb_pixel();
				$access_token     = $this->get_conversion_api_access_token();

				if ( empty( $get_all_fb_pixel ) || empty( $access_token ) ) {
					return;
				}

				$get_each_pixel_id = explode( ',', $get_all_fb_pixel );
				$access_token      = explode( ',', $access_token );
				if ( ! is_array( $access_token ) || 0 === count( $access_token ) ) {
					return;
				}

				if ( is_array( $get_each_pixel_id ) && count( $get_each_pixel_id ) > 0 ) {
					foreach ( $get_each_pixel_id as $key => $pixel_id ) {
						/**
						 * continue if access token empty
						 */
						if ( empty( $access_token[ $key ] ) ) {
							continue;
						}
						$is_clear = false;
						foreach ( $events as $event ) {
							$this->fire_conv_api_event( $event, $pixel_id, $access_token[ $key ], $key );
                            if ( true === $is_ajax && isset( $event['event'] ) && 'Purchase' === $event['event'] ) {
	                            $is_clear = true;
							}
                        }

					}
					/**
					 * clear data for last pixel purchase events
					 */
					if (true === $is_clear) {
						WFOCU_Core()->data->set('data', [], 'track');
						WFOCU_Core()->data->save('track');
					}
				}

			}
		}

		public function get_conversion_api_access_token() {
			return apply_filters( 'wfocu_tracking_conversion_api_access_token', $this->admin_general_settings->get_option( 'conversion_api_access_token' ) );
		}

		/**
         * Ajax callback modal method to handle firing of multiple events in conv api
		 * @param $event
		 * @param $pixel_id
		 * @param $access_token
		 * @param $key
		 *
		 * @return void|null
		 */
		public function fire_conv_api_event( $event, $pixel_id, $access_token, $key ) {
			$type     = $event['event'];
			$event_id = $event['event_id'];
			$args     = isset( $event['args'] ) ? $event['args'] : [];

			BWF_Facebook_Sdk_Factory::setup( trim( $pixel_id ), trim( $access_token ) );
			$get_test      = apply_filters( 'wfocu_tracking_conversion_api_test_event_code', $this->admin_general_settings->get_option( 'conversion_api_test_event_code' ) );
			$get_test      = explode( ',', $get_test );
			$is_test_event = $this->admin_general_settings->get_option( 'is_fb_conv_enable_test' );
			if ( is_array( $is_test_event ) && count( $is_test_event ) > 0 && $is_test_event[0] === 'yes' && is_array( $get_test ) && count( $get_test ) > 0 ) {
				if ( isset( $get_test[ $key ] ) && ! empty( $get_test[ $key ] ) ) {
					BWF_Facebook_Sdk_Factory::set_test( trim( $get_test[ $key ] ) );
				}
			}


			BWF_Facebook_Sdk_Factory::set_partner( 'woofunnels' );
			$instance = BWF_Facebook_Sdk_Factory::create();
			if ( is_null( $instance ) ) {
				return null;
			}

			$getEventparams = $this->get_generic_event_params_for_conv_api();
			switch ( $type ) {
				case 'PageView':
					$instance->set_event_id( $event_id );
					$instance->set_user_data( $this->get_user_data( $type ) );
					$instance->set_event_source_url( WFOCU_Core()->offers->get_the_link( WFOCU_Core()->data->get_current_offer() ) );
					$instance->set_event_data( 'PageView', $getEventparams );
					break;
				case 'Purchase':
					$get_data_from_session = WFOCU_Core()->data->get( 'data', array(), 'track' );
					if ( is_array( $get_data_from_session ) && isset( $get_data_from_session['fb'] ) ) {
						$get_offer = WFOCU_Core()->data->get_current_offer();
						if ( ! empty( $get_offer ) ) {
							$instance->set_event_source_url( WFOCU_Core()->offers->get_the_link( $get_offer ) );
						} else {

							$instance->set_event_source_url( wc_get_order( $get_data_from_session['fb']['is_order'] )->get_checkout_order_received_url() );

						}
						$instance->set_event_id( $event_id );
						$instance->set_user_data( $this->get_user_data( $type ) );
						$instance->set_event_data( 'Purchase', $this->get_purchase_params() );
					}

					break;
				case 'trackCustom':
					$instance->set_event_id( $event_id );
					$instance->set_user_data( $this->get_user_data( $type ) );
					$instance->set_event_source_url( WFOCU_Core()->offers->get_the_link( WFOCU_Core()->data->get_current_offer() ) );
					$getEventName = $this->admin_general_settings->get_option( 'general_event_name' );
					$instance->set_event_data( $getEventName, $getEventparams );
					break;
				case 'WooFunnels_Thankyou':
				case 'WooFunnels_Upsell':
				case 'WooFunnels_Downsell':
					$instance->set_event_id( $event_id );
					$instance->set_user_data( $this->get_user_data( $type ) );
					$instance->set_event_source_url( WFOCU_Core()->offers->get_the_link( WFOCU_Core()->data->get_current_offer() ) );

					$instance->set_event_data( $type, $getEventparams );
					break;
				default:
					$instance->set_event_id( $event_id );
					$instance->set_user_data( $this->get_user_data( 'purchase' ) );
					$instance->set_event_source_url( WFOCU_Core()->offers->get_the_link( WFOCU_Core()->data->get_current_offer() ) );
					$getEventparams = is_array( $args ) && count( $args ) > 0 ? $args : $getEventparams;
					$instance->set_event_data( $type, $getEventparams );
			}

			if ( empty( $instance->get_event_id() ) ) {
				return null;
			}

			$response = $instance->execute();
			if ( 'Purchase' === $type ) {
				$this->maybe_insert_log( '----Facebook conversion API v' . WFOCU_VERSION . '----------- for pixel id ' . $pixel_id . '-----------' . print_r( $response, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			}
		}


		/**
		 * Get User data for the specific event
		 *
		 * @param string $type
		 *
		 * @return array
		 */
		public function get_user_data( $type ) {
			$user_data             = [];
			$get_data_from_session = WFOCU_Core()->data->get( 'data', array(), 'track' );
			if ( isset( $get_data_from_session['fb'] ) && isset( $get_data_from_session['fb']['advanced'] ) ) {
				$user_data ['email']        = isset( $get_data_from_session['fb']['advanced']['em'] ) ? $get_data_from_session['fb']['advanced']['em'] : '';
				$user_data ['phone']        = isset( $get_data_from_session['fb']['advanced']['ph'] ) ? $get_data_from_session['fb']['advanced']['ph'] : '';
				$user_data ['last_name']    = isset( $get_data_from_session['fb']['advanced']['ln'] ) ? $get_data_from_session['fb']['advanced']['ln'] : '';
				$user_data ['first_name']   = isset( $get_data_from_session['fb']['advanced']['fn'] ) ? $get_data_from_session['fb']['advanced']['fn'] : '';
				$user_data ['city']         = isset( $get_data_from_session['fb']['advanced']['ct'] ) ? strtolower( $get_data_from_session['fb']['advanced']['ct'] ) : '';
				$user_data ['state']        = isset( $get_data_from_session['fb']['advanced']['st'] ) ? strtolower( $get_data_from_session['fb']['advanced']['st'] ) : '';
				$user_data ['country_code'] = isset( $get_data_from_session['fb']['advanced']['country'] ) ? strtolower( $get_data_from_session['fb']['advanced']['country'] ) : '';
				$user_data ['zip_code']     = isset( $get_data_from_session['fb']['advanced']['zp'] ) ? $get_data_from_session['fb']['advanced']['zp'] : '';
			}

			if ( $type === 'Purchase' ) {
				$get_data_from_session = WFOCU_Core()->data->get( 'data', array(), 'track' );
				if ( isset( $get_data_from_session['fb'] ) && isset( $get_data_from_session['fb']['advanced'] ) ) {
					$order_id = isset( $get_data_from_session['fb']['is_order'] ) ? $get_data_from_session['fb']['is_order'] : 0;
					if ( $order_id > 0 ) {
						$order = wc_get_order( $order_id );
						if ( $order instanceof WC_Order && $order->get_customer_id() > 0 ) {
							$user_data ['external_id'] = $order->get_customer_id();
						}
					}
				}
			}

			$user_data['client_ip_address'] = ! empty( WC_Geolocation::get_ip_address() ) ? WC_Geolocation::get_ip_address() : '127.0.0.1';

			$user_data['client_user_agent'] = wc_get_user_agent();
			if ( isset( $_COOKIE['_fbp'] ) && ! empty( $_COOKIE['_fbp'] ) ) {
				$user_data['_fbp'] = wc_clean( $_COOKIE['_fbp'] ); //phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
				$user_data['fbp']  = wc_clean( $_COOKIE['_fbp'] ); //phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
				if ( ! isset( $user_data ['external_id'] ) || empty( $user_data ['external_id'] ) ) {
					$user_data ['external_id'] = $user_data['fbp'];
				}
			}
			if ( isset( $_COOKIE['_fbc'] ) && ! empty( $_COOKIE['_fbc'] ) ) {
				$user_data['_fbc'] = wc_clean( $_COOKIE['_fbc'] ); //phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
				$user_data['fbc']  = wc_clean( $_COOKIE['_fbc'] ); //phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
			} elseif ( isset( $_COOKIE['wffn_fbclid'] ) && isset( $_COOKIE['wffn_flt'] ) && ! empty( $_COOKIE['wffn_fbclid'] ) ) {
				$user_data['_fbc'] = 'fb.1.' . strtotime( $_COOKIE['wffn_flt'] ) . '.' . $_COOKIE['wffn_fbclid']; //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
			}

			return $user_data;

		}

		/**
		 * Get generic event params for pass with the general event
		 * @return array
		 */
		public function get_generic_event_params_for_conv_api() {
			$get_offer              = WFOCU_Core()->data->get_current_offer();
			$params                 = array();
			$params['post_type']    = 'wfocu_offer';
			$params['content_name'] = get_the_title( $get_offer );
			$params['post_id']      = $get_offer;

			$event_data = $this->get_event_data();
			if ( is_array( $event_data ) ) {
				$params = array_merge( $params, $event_data );
			}

			return $params;
		}

		/**
		 * Get all purchase event params prepared using data saved in sessions
		 * @return array
		 */
		public function get_purchase_params() {
			$get_data_from_session = WFOCU_Core()->data->get( 'data', array(), 'track' );
			$purchase_params       = array(
				'value'            => $get_data_from_session['fb']['total'],
				'currency'         => $get_data_from_session['fb']['currency'],
				'content_name'     => ! empty( $get_data_from_session['fb']['content_name'] ) ? join( ',', $get_data_from_session['fb']['content_name'] ) : __( 'BuildWooFunnels', 'woofunnels-upstroke-one-click-upsells' ),
				'content_category' => ! empty( $get_data_from_session['fb']['category_name'] ) ? join( ',', $get_data_from_session['fb']['category_name'] ) : '',
				'content_ids'      => $get_data_from_session['fb']['content_ids'],
				'content_type'     => 'product',
				'contents'         => $this->get_contents_for_conv_api( $get_data_from_session['fb']['products'] ),
				'transaction_id'   => $get_data_from_session['fb']['transaction_id'],
				'order_id'         => $get_data_from_session['fb']['order_id'],
			);
			$event_data            = $this->get_event_data();
			if ( is_array( $event_data ) ) {
				$purchase_params = array_merge( $purchase_params, $event_data );
			}

			return $purchase_params;
		}

		public function get_event_data() {
			$event_data = array(
				'plugin'         => 'WooFunnels Upsells',
				'event_day'      => current_time( "l" ),
				'event_month'    => current_time( "F" ),
				'event_hour'     => $this->getHour(),
				'traffic_source' => $this->get_traffic_source(),
			);
			$utms       = $this->get_utms();
			if ( is_array( $utms ) ) {
				$event_data = array_merge( $event_data, $utms );
			}

			return $event_data;
		}

		/**
		 * Format content property to be pass with the conversion API
		 *
		 * @param array $products
		 *
		 * @return array|mixed
		 */
		public function get_contents_for_conv_api( $products ) {
			if ( is_array( $products ) && count( $products ) > 0 ) {
				foreach ( $products as &$prod ) {
					unset( $prod['name'] );
					unset( $prod['category'] );
				}
			}

			return $products;
		}

		public function get_event_id( $event ) {
			return $event . "_" . time();
		}

		public function get_order_id_from_transaction_id( $id ) {
			$expl = explode( '-', $id );

			return $expl[0];
		}

		public function getRequestUri( $removeQuery = false ) {
			$request_uri = null;

			if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
				$start       = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? "https" : "http" ) . "://";
				$request_uri = $start . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];//phpcs:ignore
			}
			if ( $removeQuery && isset( $_SERVER['QUERY_STRING'] ) ) {
				$request_uri = str_replace( "?" . $_SERVER['QUERY_STRING'], "", $request_uri );//phpcs:ignore
			}

			return $request_uri;
		}

		public function load_gtag( $id ) {
			?>
            (function (window, document, src) {
            var a = document.createElement('script'),
            m = document.getElementsByTagName('script')[0];
            a.async = 1;
            a.src = src;
            m.parentNode.insertBefore(a, m);
            })(window, document, '//www.googletagmanager.com/gtag/js?id=<?php echo esc_js( trim( $id ) ); ?>');

            window.dataLayer = window.dataLayer || [];
            window.gtag = window.gtag || function gtag() {
            dataLayer.push(arguments);
            };

            gtag('js', new Date());
			<?php
			$this->gtag_rendered = true;
		}

		public function get_content_id( $product_obj, $mode = 'pixel' ) {
			if ( $product_obj->is_type( 'variation' ) && false === $this->do_treat_variable_as_simple( $mode ) ) {
				$get_content_id = $this->get_woo_product_content_id( $product_obj->get_id(), $mode );

			} else {
				if ( $product_obj->is_type( 'variation' ) ) {
					$get_content_id = $this->get_woo_product_content_id( $product_obj->get_parent_id(), $mode );

				} else {
					$get_content_id = $this->get_woo_product_content_id( $product_obj->get_id(), $mode );

				}
			}

			return $get_content_id;
		}

		public function fire_tracking() {
			if ( $this->is_tracking_on() && $this->should_render() ) {

				?>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {

                        if (typeof wfocuFbTrackingIn === 'function') {
                            wfocuFbTrackingIn();
                        }
                        if (typeof wfocuConvTrackingIn === 'function') {
                            wfocuConvTrackingIn();
                        }
                        if (typeof wfocuConvBaseTrackingIn === 'function') {
                            wfocuConvBaseTrackingIn();
                        }
                        if (typeof wfocuGaTrackingIn === 'function') {
                            wfocuGaTrackingIn();
                        }
                        if (typeof wfocuGadTrackingIn === 'function') {
                            wfocuGadTrackingIn();
                        }
                        if (typeof wfocuPintTrackingIn === 'function') {
                            wfocuPintTrackingIn();
                        }
                        if (typeof wfocuPintTrackingBaseIn === 'function') {
                            wfocuPintTrackingBaseIn();
                        }
                        if (typeof wfocuTiktokTrackingIn === 'function') {
                            wfocuTiktokTrackingIn();
                        }
                        if (typeof wfocuTiktokTrackingBaseIn === 'function') {
                            wfocuTiktokTrackingBaseIn();
                        }
                        if (typeof wfocuSnapchatTrackingIn === 'function') {
                            wfocuSnapchatTrackingIn();
                        }
                        if (typeof wfocuSnapchatTrackingBaseIn === 'function') {
                            wfocuSnapchatTrackingBaseIn();
                        }
                    });
                </script>
				<?php
			}
		}

		/**
		 * @param $ga
		 * @param $order
		 * @param $category_names
		 *
		 * @return mixed
		 */
		public function update_ga4_event_data( $ga, $order, $category_names ) {
			if ( $this->is_ga4_tracking() ) {
				$ga['value']    = ! empty( $ga['value'] ) ? floatval( $ga['value'] ) : $ga['value'];
				$ga['tax']      = ! empty( $ga['tax'] ) ? floatval( $ga['tax'] ) : $ga['tax'];
				$ga['shipping'] = ! empty( $ga['shipping'] ) ? floatval( $ga['shipping'] ) : $ga['shipping'];
				if ( is_array( $ga['items'] ) && count( $ga['items'] ) > 0 ) {
					$count = 0;
					foreach ( $ga['items'] as &$ga_item ) {
						$ga_item['item_id']   = $ga_item['id'];
						$ga_item['item_name'] = $ga_item['name'];
						$ga_item['price']     = floatval( $ga_item['price'] );
						$ga_item['quantity']  = ! empty( $ga_item['quantity'] ) ? floatval( $ga_item['quantity'] ) : $ga_item['quantity'];

						if ( isset( $ga_item['variant'] ) && ! empty( $ga_item['variant'] ) ) {
							$ga_item['item_variant'] = $ga_item['variant'];
						}
						$ga_item['currency'] = WFOCU_WC_Compatibility::get_order_currency( $order );
						$ga_item['index']    = $count;
						$cat_count           = 0;
						if ( is_array( $category_names ) && count( $category_names ) > 0 ) {
							foreach ( $category_names as $cat ) {
								$item_category             = ( 0 === $cat_count ) ? 'item_category' : 'item_category' . $cat_count;
								$ga_item[ $item_category ] = $cat;
								$cat_count ++;
							}
						}
						unset( $ga_item['id'] );
						unset( $ga_item['name'] );
						unset( $ga_item['category'] );
						unset( $ga_item['variant'] );

					}
				}

				unset( $ga['event_category'] );
				unset( $ga['ecomm_pagetype'] );
				unset( $ga['ecomm_prodid'] );
				unset( $ga['ecomm_totalvalue'] );


			}

			return $ga;
		}


	}

	if ( class_exists( 'WFOCU_Core' ) ) {
		WFOCU_Core::register( 'ecom_tracking', 'WFOCU_Ecomm_Tracking' );
	}
}
