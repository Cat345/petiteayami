<?php
if ( ! class_exists( 'WFOCU_Admin_Notices' ) ) {
	class WFOCU_Admin_Notices {

		private static $ins = null;
		public $admin_path;
		public $admin_url;
		public $memory = null;
		public $should_show_shortcodes = null;

		public function __construct() {

			$this->admin_path = WFOCU_PLUGIN_DIR . '/admin';
			$this->admin_url  = WFOCU_PLUGIN_URL . '/admin';
			if ( WFOCU_Core()->admin->is_upstroke_page() ) {

				add_action( 'admin_notices', array( $this, 'maybe_show_notice_for_no_gateways' ) );
			}
			add_action( 'admin_notices', array( $this, 'maybe_show_notice_for_paypal_missing_creds' ) );
			add_action( 'admin_notices', array( $this, 'maybe_show_notice_on_memory_usage_and_php_version' ) );
			if ( ( true === WFOCU_Common::plugin_active_check( 'pixelyoursite-pro/pixelyoursite-pro.php' ) || true === WFOCU_Common::plugin_active_check( 'pixelyoursite/facebook-pixel-master.php' ) ) && '' === get_option( 'wfocu_notice_pys_dismissed', '' ) ) {
			    add_action( 'admin_notices', array( $this, 'maybe_show_notice_on_pixel_your_site_pro' ) );
            }
			if ( isset( $_GET['_wpnonce'] ) && isset( $_GET['nid'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'wfocu_dismissed_notice' ) ) {

				add_action( 'admin_init', array( $this, 'maybe_dismiss_notice' ) );
			}
			add_action( 'admin_init', array( $this, 'maybe_show_notice_on_google_enhanced_pixel_plugin' ) );
			add_action( 'admin_init', array( $this, 'maybe_show_notice_on_fb_wooocommerce_plugin' ) );
		}

		public function maybe_show_notice_for_no_gateways() {


				$get_gateway_list = WFOCU_Core()->gateways->get_gateways_list();
				if ( empty( $get_gateway_list ) ) {
					$this->no_gateway_notice();
				}


		}

		public function maybe_show_notice_for_paypal_missing_creds() {
			$get_enabled_gateways = WFOCU_Core()->data->get_option( 'gateways' );

			$get_paypal_settings = get_option( 'woocommerce_paypal_settings', [] );

			if ( isset( $get_paypal_settings['enabled'] ) && 'yes' === $get_paypal_settings['enabled'] && is_array( $get_enabled_gateways ) && ( in_array( 'paypal', $get_enabled_gateways ) ) ) {

				if ( isset( $get_paypal_settings['enabled'] ) && 'no' === $get_paypal_settings['_should_load'] ) {
					return;
				}
				$get_integration = WFOCU_Core()->gateways->get_integration( 'paypal' );
				if ( false === $get_integration->has_api_credentials_set() ) {
					$this->paypal_creds_missing_notice();
				}
			}
		}

		public function maybe_show_notice_on_memory_usage_and_php_version() {

			$this->memory = $this->get_system_memory();
			/**
			 * Show notice as memory needs to be greater or equal to 256 mb
			 */
			if ( 268430000 > $this->memory ) {
				?>


                <div class="wfocu-notice bwf-notice notice notice-error">
                    <p><?php _e( 'FunnelKit Notice: PHP memory is running low. It is recommended to set PHP Memory to at least 256MB. <a target="_blank" href="https://wordpress.org/support/article/editing-wp-config-php/#increasing-memory-allocated-to-php">Learn how to increase php memory limit</a>', 'woofunnels-upstroke-one-click-upsell' ); ?>
                    </p>

                </div>
				<?php
			}
		}


		public function paypal_creds_missing_notice() {
			?>
            <div class="wfocu-notice bwf-notice notice notice-error">
                <p><?php _e( 'FunnelKit Notice: One Click Upsells won\'t trigger on PayPal Standard. Please add API Credentials (Username,Password and Signature) in gateway settings.', 'woofunnels-upstroke-one-click-upsell' ); ?> </p>
                <p>
                    <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=paypal' ); ?>" class="button"><?php _e( 'Update PayPal settings', 'woofunnels-upstroke-one-click-upsell' ); ?></a>
                </p>
            </div>
			<?php
		}

		public function no_gateway_notice() {
			?>
            <div class="wfocu-notice bwf-notice notice notice-error">
                <p><?php _e( 'FunnelKit Notice: No gateway(s) enabled in One Click Upsells Settings. ', 'woofunnels-upstroke-one-click-upsell' ); ?>
                    <a target="_blank" href="https://funnelkit.com/docs/one-click-upsell/supported-payment-methods/">Learn more about compatibility with gateways</a></p>
                <p><a href="<?php echo $this->get_settings_link( '/upstroke/wfocu_gateways' ); ?>" class="button"><?php _e( 'Update Settings', 'woofunnels-upstroke-one-click-upsell' ); ?></a></p>
            </div>
			<?php
		}

		public function get_settings_link( $path = '' ) {
			if ( class_exists( 'WFFN_Step' ) ) {
				return admin_url( 'admin.php?page=bwf&path=/settings' . $path );
			}

			return admin_url( 'admin.php?page=upstroke&tab=settings' );
		}

		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		public function get_system_memory() {
			$memory = wc_let_to_num( WP_MEMORY_LIMIT );
			if ( function_exists( 'memory_get_usage' ) ) {
				$system_memory = wc_let_to_num( @ini_get( 'memory_limit' ) );

				$memory = max( $memory, $system_memory );
			}

			return $memory;
		}

		public function maybe_show_notice_on_pixel_your_site_pro() {
			$admin_settings = BWF_Admin_General_Settings::get_instance();
			$has_pixel_config = false;

			// Facebook Pixel
			$fb_pixel_key = $admin_settings->get_option('fb_pixel_key');
			$is_fb_purchase = $admin_settings->get_option('is_fb_purchase_event');
			if (!empty($fb_pixel_key) && is_array($is_fb_purchase) && in_array('yes', $is_fb_purchase)) {
				$has_pixel_config = true;
			}

			// Google Analytics
			$ga_key = $admin_settings->get_option('ga_key');
			$is_ga_purchase = $admin_settings->get_option('is_ga_purchase_event');
			if (!empty($ga_key) && is_array($is_ga_purchase) && in_array('yes', $is_ga_purchase)) {
				$has_pixel_config = true;
			}

			// Google Ads
			$gad_key = $admin_settings->get_option('gad_key');
			$is_gad_purchase = $admin_settings->get_option('is_gad_purchase_event');
			if (!empty($gad_key) && is_array($is_gad_purchase) && in_array('yes', $is_gad_purchase)) {
				$has_pixel_config = true;
			}

			// Pinterest
			$pint_key = $admin_settings->get_option('pint_key');
			$is_pint_purchase = $admin_settings->get_option('is_pint_purchase_event');
			if (!empty($pint_key) && is_array($is_pint_purchase) && in_array('yes', $is_pint_purchase)) {
				$has_pixel_config = true;
			}

			// TikTok
			$tiktok_pixel = $admin_settings->get_option('tiktok_pixel');
			$is_tiktok_purchase = $admin_settings->get_option('is_tiktok_purchase_event');
			if (!empty($tiktok_pixel) && is_array($is_tiktok_purchase) && in_array('yes', $is_tiktok_purchase)) {
				$has_pixel_config = true;
			}

			// Snapchat
			$snapchat_pixel = $admin_settings->get_option('snapchat_pixel');
			$is_snapchat_purchase = $admin_settings->get_option('is_snapchat_purchase_event');
			if (!empty($snapchat_pixel) && is_array($is_snapchat_purchase) && in_array('yes', $is_snapchat_purchase)) {
				$has_pixel_config = true;
			}

			if ($has_pixel_config) {
				$this->pys_notice();
			}
		}

		public function pys_notice() {
			?>
            <div class="wfocu-notice bwf-notice notice notice-error">
                <p><?php _e( 'FunnelKit Notice: PixelYourSite is activated. To avoid duplication of purchase events, <strong>disable the Purchase Event </strong> from PixelYourSite and enable it from Settings.', 'woofunnels-upstroke-one-click-upsell' ); ?>
                    <a target="_blank" href="https://funnelkit.com/docs/funnel-builder/global-settings/facebook-conversion-api/"><?php _e( 'Learn more about setting up Facebook pixel tracking.', 'woofunnels-upstroke-one-click-upsell' ); ?></a>
                </p>
                <p>
                    <a href="<?php echo $this->get_settings_link( '/woofunnels_general_settings' ); ?>" class="button"><?php _e( 'Update Settings', 'woofunnels-upstroke-one-click-upsell' ); ?></a>
                    <a style="padding-left: 10px;" href="<?php echo wp_nonce_url( admin_url( 'index.php?nid=pys' ), 'wfocu_dismissed_notice' ); ?>"><?php _e( 'I\'ve already done this', 'woofunnels-upstroke-one-click-upsell' ); ?></a>
                </p>
            </div>
			<?php
		}

		public function maybe_show_notice_on_google_enhanced_pixel_plugin() {
			if ( ( true === WFOCU_Common::plugin_active_check( 'enhanced-e-commerce-for-woocommerce-store/enhanced-ecommerce-google-analytics.php' ) && '' === get_option( 'wfocu_notice_enhancedga_dismissed', '' ) ) ) {
				add_action( 'admin_notices', array( $this, 'enhanced_ga_notice' ) );
			}
		}

		public function enhanced_ga_notice() {
			?>
            <div class="wfocu-notice bwf-notice notice notice-error">
                <p><?php _e( 'FunnelKit Notice:  Enhanced E-commerce for Woocommerce store by Tatvic is activated. To avoid duplication of purchase events, <strong>disable the Purchase Event</strong> from Enhanced E-commerce for Woocommerce store. ', 'woofunnels-upstroke-one-click-upsell' ); ?>
                    <a target="_blank" href="https://funnelkit.com/docs/one-click-upsell/compatibilities/enhanced-ecommerce-google-analytics-plugin/"><?php _e( 'Learn more about disabling purchase event.', 'woofunnels-upstroke-one-click-upsell' ); ?></a>
                </p>

                <p>
                    <a href="<?php echo $this->get_settings_link( '/woofunnels_general_settings' ); ?>" class="button"><?php _e( 'Update Settings', 'woofunnels-upstroke-one-click-upsell' ); ?></a>
                    <a style="padding-left: 10px;" href="<?php echo wp_nonce_url( admin_url( 'index.php?nid=enhancedga' ), 'wfocu_dismissed_notice' ); ?>"><?php _e( 'I\'ve already done this', 'woofunnels-upstroke-one-click-upsell' ); ?></a>
                </p>
            </div>
			<?php
		}

		public function maybe_show_notice_on_fb_wooocommerce_plugin() {


			if ( ( true === WFOCU_Common::plugin_active_check( 'facebook-for-woocommerce/facebook-for-woocommerce.php' ) && '' === get_option( 'wfocu_notice_fbwoo_dismissed', '' ) ) ) {
				add_action( 'admin_notices', array( $this, 'fbwooo_notice' ) );

			}
		}

		/**
		 * @todo replace the link with the valid fbwoo link for buildwoofunnels
		 */
		public function fbwooo_notice() {
			?>
            <div class="wfocu-notice bwf-notice notice notice-error">
                <p><?php _e( 'FunnelKit Notice: Facebook for WooCommerce is activated. To avoid duplication of purchase events, <strong>disable the Purchase Event</strong> from Facebook for WooCommerce store.', 'woofunnels-upstroke-one-click-upsell' ); ?>
                    <a target="_blank" href="https://funnelkit.com/docs/one-click-upsell/compatibilities/facebook-for-woocommerce/"><?php _e( 'Learn more about disabling purchase event.', 'woofunnels-upstroke-one-click-upsell' ); ?></a>

                </p>

                <p>
                    <a href="<?php echo $this->get_settings_link( '/woofunnels_general_settings' ); ?>" class="button"><?php _e( 'Update Settings', 'woofunnels-upstroke-one-click-upsell' ); ?></a>
                    <a style="padding-left: 10px;" href="<?php echo wp_nonce_url( admin_url( 'index.php?nid=fbwoo' ), 'wfocu_dismissed_notice' ); ?>"><?php _e( 'I\'ve already done this', 'woofunnels-upstroke-one-click-upsell' ); ?></a>
                </p>
            </div>
			<?php
		}

		public function maybe_dismiss_notice() {
				update_option( 'wfocu_notice_' . $_GET['nid'] . '_dismissed', 'yes' );

		}

	}

	if ( class_exists( 'WFOCU_Core' ) ) {
		WFOCU_Core::register( 'admin_notices', 'WFOCU_Admin_Notices' );
	}
}