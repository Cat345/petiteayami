<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WFACP_Compatibility_With_Magic_Login_Pro' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Compatibility_With_Magic_Login_Pro {

		public function __construct() {
			add_action( 'wfacp_after_checkout_page_found', array( $this, 'action' ) );
		}

		public function action() {
			if ( ! $this->is_enable() ) {
				return;
			}

			$settings = \MagicLogin\Utils\get_settings();
			$position = isset( $settings['woo_position'] ) ? $settings['woo_position'] : 'before';

			if ( false !== stripos( $position, 'before' ) ) {
				add_action( 'woocommerce_before_checkout_form', array( $this, 'add_form' ), 9 );
			} elseif ( false !== stripos( $position, 'after' ) ) {
				add_action( 'woocommerce_before_checkout_form', array( $this, 'set_enqueue_flag' ), 8 );
				add_action( 'woocommerce_login_form_end', array( $this, 'add_form' ), 10 );
			} else {
				add_action( 'woocommerce_before_checkout_form', array( $this, 'add_form' ), 9 );
			}

			add_action( 'wfacp_internal_css', array( $this, 'add_css' ), 9 );
		}

		public function is_enable() {
			return class_exists( 'MagicLogin\Integrations\WooCommerce' ) && function_exists( '\MagicLogin\Utils\get_settings' );
		}

		public function add_form() {
			if ( ! $this->is_enable() ) {
				return;
			}

			$hook     = current_action();
			$settings = \MagicLogin\Utils\get_settings();

			if ( false === stripos( $hook, $settings['woo_position'] ) ) {
				$this->render_form_manually();
				return;
			}

			MagicLogin\Integrations\WooCommerce::maybe_add_magic_login_to_woocommerce_login_form( 'global/form-login.php', '' );
		}

		private function render_form_manually() {
			$settings = \MagicLogin\Utils\get_settings();
			if ( ! $settings['enable_woo_integration'] || did_action( 'magic_login_before_woocommerce_login_form' ) ) {
				return;
			}

			$hide_form         = apply_filters( 'magic_login_hide_on_woocommerce_login_form', true );
			$shortcode_content = apply_filters( 'magic_login_shortcode_content_on_woocommerce_login_form', '[magic_login_form submit_class="woocommerce-button"]' );
			?>

			<div id="magic-login-woo-wrapper" class="magic-login-woocommerce_login_form_end" <?php echo ( $hide_form ) ? ' style="display:none;"' : ''; ?>>
				<span class="magic-login-woo-or-separator"></span>
				<?php
				do_action( 'magic_login_before_woocommerce_login_form' );
				echo do_shortcode( $shortcode_content );
				do_action( 'magic_login_after_woocommerce_login_form' );
				?>
			</div>
			<?php
		}

		public function set_enqueue_flag() {
			if ( ! $this->is_enable() ) {
				return;
			}

			$settings = \MagicLogin\Utils\get_settings();
			if ( ! $settings['enable_woo_integration'] ) {
				return;
			}

			try {
				$reflection = new \ReflectionClass( 'MagicLogin\Integrations\WooCommerce' );
				$property   = $reflection->getProperty( 'enqueue_scripts' );
				$property->setAccessible( true );
				$property->setValue( null, true );
			} catch ( \ReflectionException $e ) {
				return;
			}
		}

		public function add_css() {
			?>
			<style>
				#wfacp-sec-wrapper #magic-login-shortcode #user_login {
					padding: 10px 12px;
					margin-bottom: 10px;
				}

				#wfacp-sec-wrapper #magic-login-woo-wrapper {
					margin: 15px 0;
				}

				#wfacp-sec-wrapper #magic-login-woo-wrapper .magic-login-content input[type="text"],
				#wfacp-sec-wrapper #magic-login-woo-wrapper .magic-login-content input[type="email"] {
					width: 100%;
					padding: 10px 12px;
					margin-bottom: 10px;
					border: 1px solid #d1d1d1;
					border-radius: 3px;
					font-size: 14px;
					background: #fff;
					box-shadow: none;
				}

				#wfacp-sec-wrapper #magic-login-woo-wrapper .magic-login-content button,
				#wfacp-sec-wrapper #magic-login-woo-wrapper .magic-login-content input[type="submit"] {
					background: #444;
					color: #fff;
					border: none;
					padding: 12px 20px;
					border-radius: 3px;
					font-size: 14px;
					width: 100%;
					cursor: pointer;
					transition: all 0.2s ease;
				}

				#wfacp-sec-wrapper #magic-login-woo-wrapper .magic-login-content button:hover,
				#wfacp-sec-wrapper #magic-login-woo-wrapper .magic-login-content input[type="submit"]:hover {
					background: #222;
				}
			</style>
			<?php
		}
	}

	WFACP_Plugin_Compatibilities::register( new WFACP_Compatibility_With_Magic_Login_Pro(), 'magic-login-pro' );
}
