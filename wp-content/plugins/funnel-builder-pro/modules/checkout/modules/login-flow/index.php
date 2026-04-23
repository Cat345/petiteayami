<?php

namespace FunnelKit\Checkout\Modules\Login_Flow;

/**
 * Class Main
 *
 * This class manages the Login Flow Module.
 * Implemented as a singleton to ensure only one instance exists at a time.
 */
if ( ! class_exists( '\FunnelKit\Checkout\Modules\Login_Flow\Main' ) ) {
	class Main {
		private static $instance;
		private $smart_login = array();

		/**
		 * Private constructor to prevent direct instantiation.
		 */
		private function __construct() {
			$this->init_hooks();
			$this->ajax();
			// Process login on wp_loaded like WooCommerce does
			add_action( 'wp_loaded', array( __CLASS__, 'process_login' ), 20 );
		}

		/**
		 * Get the singleton instance.
		 *
		 * @return Main
		 */
		public static function get_instance() {
			if ( self::$instance === null ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Include necessary files.
		 */
		public function ajax() {

			add_action( 'wp_ajax_nopriv_funnelkit_search_customer', array( __CLASS__, 'handle_search_customer_request' ) );
			// Login is now handled via wp_loaded hook (process_login method) - no AJAX
			add_action( 'wp_ajax_nopriv_funnelkit_reset_password', array( __CLASS__, 'handle_reset_password_request' ) );
		}

		/**
		 * Initialize WordPress hooks for the plugin.
		 */
		public function init_hooks() {
			add_action( 'wfacp_template_load', array( $this, 'load_files' ) );
			// Attach the redirect method to the 'woocommerce_customer_reset_password' hook
			add_action( 'woocommerce_customer_reset_password', array( $this, 'redirect_after_reset_password' ) );
			add_filter( 'wfacp_template_localize_data', array( $this, 'add_localize_data' ) );
		}

		public function load_files() {

			$page_settings = \WFACP_Common::get_page_settings( \WFACP_Common::get_id() );

			$this->smart_login['display_smart_login']                   = isset( $page_settings['display_smart_login'] ) ? trim( $page_settings['display_smart_login'] ) : 'false';
			$this->smart_login['display_prompt_returning_user']         = isset( $page_settings['display_prompt_returning_user'] ) ? trim( $page_settings['display_prompt_returning_user'] ) : 'false';
			$this->smart_login['display_prompt_returning_user_message'] = isset( $page_settings['display_prompt_returning_user_message'] ) ? trim( $page_settings['display_prompt_returning_user_message'] ) : '';

			if ( wc_string_to_bool( $this->smart_login['display_smart_login'] ) === true || true === wc_string_to_bool( $this->smart_login['display_prompt_returning_user'] ) ) {

				// Hook into various WordPress actions and filters.
				add_action( 'woocommerce_after_checkout_form', array( $this, 'display_modal_with_forms' ) );
				add_action( 'wfacp_smart_login_modal_popup', array( $this, 'display_modal_with_forms' ) );
				add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_scripts' ), 102 );
				/* Prompt Returning Users for login  */

				add_action( 'wfacp_internal_css', array( $this, 'display_prompt_returning_user_css' ) );
			}
		}

		/**
		 * Displays a modal with login and reset password forms.
		 *
		 * @return void
		 */
		public function display_modal_with_forms() {
			if ( is_user_logged_in() ) {
				return;
			}
			\WFACP_Common::get_template( 'login-flow/form-login-modal.php' );
		}

		public function add_localize_data( $data ) {

			return array_merge(
				array(
					'nonce'                         => wp_create_nonce( 'flf-nonce' ),
					'loginActiontext'               => sprintf( __( 'Hey there! It seems you have a %s account. Please Login', 'woofunnels-aero-checkout' ), get_bloginfo( 'name' ) ),
					'loginActionButtonLabel'        => __( 'Login', 'woofunnels-aero-checkout' ),
					'loginActionLoggingText'        => __( 'Logging in...', 'woofunnels-aero-checkout' ),
					'resetPasswordButtonLabel'      => __( 'Reset password', 'woofunnels-aero-checkout' ),
					'resetPasswordResettingText'    => __( 'Resetting...', 'woofunnels-aero-checkout' ),
					'loginRequiredMessage'          => __( 'Please log in to continue with checkout', 'woofunnels-aero-checkout' ),
					'user_login_icon'               => WFACP_PLUGIN_URL . '/assets/img/wfacp-user-existing.svg',
					'display_smart_login'           => isset( $this->smart_login['display_smart_login'] ) ? $this->smart_login['display_smart_login'] : '',
					'display_prompt_returning_user' => isset( $this->smart_login['display_prompt_returning_user'] ) ? $this->smart_login['display_prompt_returning_user'] : '',
					'enable_guest_checkout'         => get_option( 'woocommerce_enable_guest_checkout', 'yes' ),
				),
				$data
			);
		}

		/**
		 * Enqueue public-facing scripts.
		 */
		public function enqueue_public_scripts() {
			if ( is_user_logged_in() ) {
				return;
			}
			wp_enqueue_style( 'funnelkit-login-flow', WFACP_PLUGIN_URL . '/assets/css/module/login-flow.css', array(), WFACP_VERSION );
		}

		/**
		 * Redirects the user to the WooCommerce checkout page after a successful password reset.
		 *
		 * Hooks into 'woocommerce_customer_reset_password' action. It checks for a specific
		 * cookie ('funnelkit_ajax_pwd_reset_flag') that indicates whether the user has reset
		 * their password via an AJAX-powered form. If the cookie is present and set to 'true',
		 * the user is redirected to the checkout page. The cookie is then unset to prevent
		 * repeated redirections.ß
		 *
		 * @param \WP_User $customer The customer object.
		 */
		public function redirect_after_reset_password( $customer ) {

			if ( 0 === $customer->ID ) {
				return;
			}

			$meta = get_user_meta( $customer->ID, '_funnelkit_user_forget_password', true );
			if ( empty( $meta ) ) {
				return;
			}

			$source = $meta['source'];
			if ( $meta['is_global_checkout'] ) {
				$source = wc_get_checkout_url();
			}
			$source = wp_validate_redirect( $source, wc_get_checkout_url() );
			delete_user_meta( $customer->ID, '_funnelkit_user_forget_password' );
			// Perform a safe redirect to the checkout page
			wp_safe_redirect( $source );
			exit;
		}


		/**
		 * Search for a customer by email and return a response.
		 */
		public static function handle_search_customer_request() {

			if ( is_null( WC()->session ) ) {
				wp_send_json_error( array( 'message' => __( 'Session not available', 'woofunnels-aero-checkout' ) ) );
			}

			$rate_limit    = apply_filters( 'wfacp_login_email_rate_limit', 5 );
			$email_attempt = WC()->session->get( '_wfacp_email_check_attempt', 0 );
			if ( $email_attempt >= $rate_limit ) {
				wp_send_json_error(
					array(
						'message'    => '',
						'rate_limit' => 'yes',
					)
				);
			}

			++$email_attempt;
			WC()->session->set( '_wfacp_email_check_attempt', $email_attempt );
			// Check if required POST parameters are set.
			if ( ! isset( $_POST['nonce'], $_POST['email'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Missing required parameters', 'woofunnels-aero-checkout' ) ) );
			}

			// Validate nonce.
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'flf-nonce' ) ) {
				wp_send_json_error( array( 'message' => __( 'Unauthorized request', 'woofunnels-aero-checkout' ) ) );
			}

			// Retrieve and sanitize email.
			$email = sanitize_email( wp_unslash( $_POST['email'] ) );

			// Validate email.
			if ( ! is_email( $email ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid email address', 'woofunnels-aero-checkout' ) ) );
			}

			// Retrieve customer by email.
			$user          = get_user_by( 'email', $email );
			$data          = array();
			$page_id       = isset( $_POST['page_id'] ) ? absint( bwf_clean( wp_unslash( $_POST['page_id'] ) ) ) : 0;
			$page_settings = \WFACP_Common::get_page_settings( $page_id );

			$label = isset( $page_settings['display_prompt_returning_user_message'] ) ? trim( $page_settings['display_prompt_returning_user_message'] ) : '';

			// Check if user has the 'customer' role.
			if ( $user && ! empty( $label ) ) {

				$label    = str_replace( '{{site_title}}', get_bloginfo( 'name' ), $label );
				$email_id = $user->data->user_email;

				$loginActionButtonLabel = __( 'Login', 'woocommerce' );

				$filed_html  = '<p class="form-row wfacp-col-full wfacp-search-wrap" >';
				$filed_html .= '<span id="funnelkitLoginAction"><span>' . esc_html( $label ) . '</span>';

				if ( true === self::wc_enable_checkout_login_reminder() ) {
					$filed_html .= '<button type="button" id="funnelkitLoginModalToggler">' . $loginActionButtonLabel . '</button>';
				}
				$filed_html        .= '</span></p>';
				$data['success']    = true;
				$data['html']       = $filed_html;
				$data['email_id']   = $email_id;
				$data['rate_limit'] = ( $email_attempt >= $rate_limit ) ? 'yes' : 'no';
				wp_send_json_success( $data );
			}
			$failed_data = array(
				'html'       => '',
				'success'    => false,
				'rate_limit' => ( $email_attempt >= $rate_limit ) ? 'yes' : 'no',
				'message'    => __( 'No customer account found', 'woofunnels-aero-checkout' ),
			);

			// Optional: If user is not a customer, you might want to send a different message.
			wp_send_json_error( $failed_data );
		}

		/**
		 * Process login form submission via PHP (like WooCommerce does).
		 * Hooks into wp_loaded to handle form POST before template loads.
		 */
		public static function process_login() {
			// Check if this is a FunnelKit login form submission
			$nonce_value = isset( $_POST['funnelkit-login-nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['funnelkit-login-nonce'] ) ) : ( isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '' );

			if ( ! isset( $_POST['funnelkit_login'], $_POST['username'], $_POST['password'] ) || ! wp_verify_nonce( $nonce_value, 'funnelkit-login' ) ) {
				return;
			}

			try {
				$creds = array(
					'user_login'    => trim( wp_unslash( $_POST['username'] ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					'user_password' => wp_unslash( $_POST['password'] ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					'remember'      => isset( $_POST['rememberme'] ),
				);

				$validation_error = new \WP_Error();
				$validation_error = apply_filters( 'funnelkit_process_login_errors', $validation_error, $creds['user_login'], $creds['user_password'] );

				if ( $validation_error->get_error_code() ) {
					throw new \Exception( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> ' . $validation_error->get_error_message() );
				}

				if ( empty( $creds['user_login'] ) ) {
					throw new \Exception( '<strong>' . __( 'Error:', 'woocommerce' ) . '</strong> ' . __( 'Username is required.', 'woocommerce' ) );
				}

				// On multisite, ensure user exists on current site
				if ( is_multisite() ) {
					$user_data = get_user_by( is_email( $creds['user_login'] ) ? 'email' : 'login', $creds['user_login'] );

					if ( $user_data && ! is_user_member_of_blog( $user_data->ID, get_current_blog_id() ) ) {
						add_user_to_blog( get_current_blog_id(), $user_data->ID, 'customer' );
					}
				}

				// Perform the login
				$user = wp_signon( apply_filters( 'funnelkit_login_credentials', $creds ), is_ssl() );

				if ( is_wp_error( $user ) ) {
					throw new \Exception( $user->get_error_message() );
				}

				// Determine redirect URL
				if ( ! empty( $_POST['redirect'] ) ) {
					$redirect = wp_unslash( $_POST['redirect'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				} elseif ( wc_get_raw_referer() ) {
					$redirect = wc_get_raw_referer();
				} else {
					$redirect = wc_get_checkout_url();
				}

				$redirect = remove_query_arg( array( 'wc_error', 'password-reset' ), $redirect );

				$redirect = wp_validate_redirect( apply_filters( 'funnelkit_login_redirect', $redirect, $user ), wc_get_checkout_url() );
				wp_safe_redirect( $redirect );
				exit;

			} catch ( \Exception $e ) {
				wc_add_notice( apply_filters( 'funnelkit_login_errors', $e->getMessage() ), 'error' );
				do_action( 'funnelkit_login_failed' );
			}
		}

		/**
		 * Handles AJAX request for password reset.
		 *
		 * Processes the password reset request initiated via AJAX.
		 * Checks for the user's existence and sends a reset password email if the user exists.
		 * Returns a JSON response with a success or failure status.
		 *
		 * @since 1.0.0
		 */
		public static function handle_reset_password_request() {
			// Verify the nonce to ensure the request is legitimate.
			$nonce_value   = bwf_clean( wp_unslash( wc_get_var( $_REQUEST['funnelkit-lost-password-nonce'], bwf_clean( wp_unslash( wc_get_var( $_REQUEST['_wpnonce'], '' ) ) ) ) ) ); // @codingStandardsIgnoreLine.
			$source_raw    = bwf_clean( wp_unslash( wc_get_var( $_REQUEST['wfacp_source'], '' ) ) ); // @codingStandardsIgnoreLine.
			$source        = wp_validate_redirect( $source_raw, wc_get_checkout_url() );
			$wfacp_post_id = absint( bwf_clean( wp_unslash( wc_get_var( $_REQUEST['_wfacp_post_id'], '' ) ) ) ); // @codingStandardsIgnoreLine.
			// If nonce verification fails, send a JSON response with an error message.
			if ( ! wp_verify_nonce( $nonce_value, 'funnelkit-login' ) ) {
				wp_send_json_error( array( 'message' => 'Nonce verification failed, please try again.' ) );
			}

			if ( empty( $_POST['user_login'] ) ) {
				wp_send_json_error( array( 'message' => 'username and email empty' ) );

				return;
			}
			// Sanitize the user login email address input from the form.
			$user_email = sanitize_text_field( wp_unslash( $_POST['user_login'] ?? '' ) );
			if ( is_email( $user_email ) ) {
				// Retrieve the user by their email address.
				$user = get_user_by( 'email', $user_email );
			} else {
				// Retrieve the user by their email address.
				$user = get_user_by( 'login', $user_email );
			}

			// If no user is found, send a JSON response with an error message.
			if ( false === $user || is_null( $user ) || ! $user instanceof \WP_User ) {
				wp_send_json_error(
					array(
						'message' => __( 'Invalid username or email.', 'woocommerce' ),
						'key'     => WC()->session->get_customer_id(),
					)
				);
			}

			// Attempt to send the password reset email to the user.
			$success = \WC_Shortcode_My_Account::retrieve_password();

			// If the password reset email was sent successfully, send a JSON success message.
			if ( $success ) {
				$session_key = '';
				if ( ! is_null( WC()->session ) && WC()->session->get_customer_id() ) {
					$session_key = WC()->session->get_customer_id();
					update_user_meta( $user->ID, '_woocommerce_load_saved_cart_after_login', true );
					update_user_meta( $user->ID, '_woocommerce_persistent_cart_' . get_current_blog_id(), array( 'cart' => WC()->cart->get_cart_for_session() ) );
				}

				// Default redirect URL
				update_user_meta(
					$user->ID,
					'_funnelkit_user_forget_password',
					array(
						'source'             => $source,
						'checkout_id'        => $wfacp_post_id,
						'session_key'        => $session_key,
						'is_global_checkout' => ( $wfacp_post_id == \WFACP_Common::get_checkout_page_id() ) || class_exists( '\WFFN_Common' ) && ( \WFFN_Common::get_store_checkout_id() == $wfacp_post_id ),
					)
				);
				// Save current page either is store checkout or dedicated checkout

				wp_send_json_success(
					array(
						'message' => __( 'A password reset email has been sent to the email address on file for your account, but may take several minutes to show up in your inbox. Please wait at least 10 minutes before attempting another reset.', 'woocommerce' ) . "<button type='button' class='wfacp-quickv-close'>" . __( 'Close', 'woocommerce' ) . '</button>',
					)
				);
			} else {
				// If there was an error in sending the reset email, send a JSON error message.
				wp_send_json_error( array( 'message' => 'There was an error sending the reset link. Please try again later.' ) );
			}
		}

		public static function wc_enable_checkout_login_reminder() {
			if ( is_user_logged_in() || 'no' === get_option( 'woocommerce_enable_checkout_login_reminder' ) ) {
				return false;
			}

			return true;
		}


		public function display_prompt_returning_user_css() {

			$icon = WFACP_PLUGIN_URL . '/assets/img/wfacp-user-existing.svg';
			?>

		<style>
			body #wfacp-sec-wrapper #funnelkitLoginAction > span {
				background: url('<?php echo esc_url( $icon ); ?>') no-repeat center left;

			}

		</style>
			<?php
		}
	}

	Main::get_instance();
}
