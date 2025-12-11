<?php

namespace FunnelKit\Checkout\Modules\Login_Flow;

/**
 * Class Main
 *
 * This class manages the Login Flow Module.
 * Implemented as a singleton to ensure only one instance exists at a time.
 */
if ( ! class_exists( '\FunnelKit\Checkout\Modules\Login_Flow\Main' ) ){
class Main {
	private static $instance;
	private $smart_login = [];

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {
		$this->init_hooks();
		$this->ajax();

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
		add_action( 'wp_ajax_nopriv_funnelkit_user_login', array( __CLASS__, 'handle_user_login_request' ) );
		add_action( 'wp_ajax_nopriv_funnelkit_reset_password', array( __CLASS__, 'handle_reset_password_request' ) );
	}

	/**
	 * Initialize WordPress hooks for the plugin.
	 */
	public function init_hooks() {
		add_action( 'wfacp_template_load', [ $this, 'load_files' ] );
		// Attach the redirect method to the 'woocommerce_customer_reset_password' hook
		add_action( 'woocommerce_customer_reset_password', array( $this, 'redirect_after_reset_password' ) );
		add_filter( 'wfacp_template_localize_data', [ $this, 'add_localize_data' ] );
	}

	public function load_files() {


		$page_settings = \WFACP_Common::get_page_settings( \WFACP_Common::get_id() );


		$this->smart_login['display_smart_login']                   = isset( $page_settings['display_smart_login'] ) ? trim( $page_settings['display_smart_login'] ) : "false";
		$this->smart_login['display_prompt_returning_user']         = isset( $page_settings['display_prompt_returning_user'] ) ? trim( $page_settings['display_prompt_returning_user'] ) : "false";
		$this->smart_login['display_prompt_returning_user_message'] = isset( $page_settings['display_prompt_returning_user_message'] ) ? trim( $page_settings['display_prompt_returning_user_message'] ) : "";


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




		return array_merge( array(
			'nonce'                         => wp_create_nonce( 'flf-nonce' ),
			'loginActiontext'               => sprintf( __( 'Hey there! It seems you have a %s account.', 'login-flow' ), get_bloginfo( 'name' ) ),
			'loginActionButtonLabel'        => __( 'Login', 'woofunnel-aero-checkout' ),
			'loginActionLoggingText'        => __( 'Logging in...', 'woofunnels-aero-checkout' ),
			'resetPasswordButtonLabel'      => __( 'Reset password', 'woofunnels-aero-checkout' ),
			'resetPasswordResettingText'    => __( 'Resetting...', 'woofunnels-aero-checkout' ),
			'user_login_icon'               => WFACP_PLUGIN_URL . '/assets/img/wfacp-user-existing.svg',
			'display_smart_login'           => isset( $this->smart_login['display_smart_login'] ) ? $this->smart_login['display_smart_login'] : '',
			'display_prompt_returning_user' => isset( $this->smart_login['display_prompt_returning_user'] ) ? $this->smart_login['display_prompt_returning_user'] : '',
		), $data );
	}

	/**
	 * Enqueue public-facing scripts.
	 */
	public function enqueue_public_scripts() {
		if ( is_user_logged_in() ) {
			return;
		}
		wp_enqueue_style( 'funnelkit-login-flow', WFACP_PLUGIN_URL . '/assets/css/module/login-flow.css' );
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
		delete_user_meta( $customer->ID, '_funnelkit_user_forget_password' );
		// Perform a redirect to the checkout page
		wp_redirect( $source );
		exit;
	}


	/**
	 * Search for a customer by email and return a response.
	 */
	public static function handle_search_customer_request() {

		$rate_limit    = apply_filters( 'wfacp_login_email_rate_limit', 5 );
		$email_attempt = WC()->session->get( '_wfacp_email_check_attempt', 0 );
		if ( $email_attempt >= $rate_limit ) {
			wp_send_json_error( array( 'message' => '', 'rate_limit' => 'yes' ) );
		}

		$email_attempt ++;
		WC()->session->set( '_wfacp_email_check_attempt', $email_attempt );
		// Check if required POST parameters are set.
		if ( ! isset( $_POST['nonce'], $_POST['email'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing required parameters', 'woofunnels-aero-checkout' ) ) );
		}

		// Validate nonce.
		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'flf-nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized request', 'woofunnels-aero-checkout' ) ) );
		}

		// Retrieve and sanitize email.
		$email = sanitize_email( $_POST['email'] );

		// Validate email.
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid email address', 'woofunnels-aero-checkout' ) ) );
		}

		// Retrieve customer by email.
		$user          = get_user_by( 'email', $email );
		$data          = [];
		$page_id       = isset( $_POST['page_id'] ) ? wc_clean( $_POST['page_id'] ) : 0;
		$page_settings = \WFACP_Common::get_page_settings( $page_id );

		$label = isset( $page_settings['display_prompt_returning_user_message'] ) ? trim( $page_settings['display_prompt_returning_user_message'] ) : "";


		// Check if user has the 'customer' role.
		if ( $user && ! empty( $label ) ) {

			$label    = str_replace( '{{site_title}}', get_bloginfo( 'name' ), $label );
			$email_id = $user->data->user_email;

			$loginActionButtonLabel = __( 'Login', 'woocommerce' );


			$filed_html = '<p class="form-row wfacp-col-full wfacp-search-wrap" >';
			$filed_html .= '<span id="funnelkitLoginAction"><span>' . $label . '</span>';

			if ( true === self::wc_enable_checkout_login_reminder() ) {
				$filed_html .= '<button type="button" id="funnelkitLoginModalToggler">' . $loginActionButtonLabel . '</button>';
			}
			$filed_html         .= '</span></p>';
			$data['success']    = true;
			$data['html']       = $filed_html;
			$data['email_id']   = $email_id;
			$data['rate_limit'] =  ( $email_attempt >= $rate_limit ) ? 'yes' : 'no';
			wp_send_json_success( $data );
		}
		$failed_data = [
			'html'       => '',
			'success'    => false,
			'rate_limit' => ( $email_attempt >= $rate_limit ) ? 'yes' : 'no',
			'message'    => __( 'No customer account found', 'woofunnel-aero-checkout' ),
		];

		// Optional: If user is not a customer, you might want to send a different message.
		wp_send_json_error( $failed_data );
	}

	/**
	 * Handle user login and return a response.
	 */
	public static function handle_user_login_request() {
		// Check if required POST parameters are set.

		if ( ! isset( $_POST['funnelkit-login-nonce'], $_POST['username'], $_POST['password'] ) ) {
			wp_send_json_error( array( 'message' => 'Missing required parameters' ) );
		}

		// Verify the nonce to ensure the request is legitimate.
		$nonce_value = wc_clean( wp_unslash( wc_get_var( $_REQUEST['funnelkit-login-nonce'], wc_clean( wp_unslash( wc_get_var( $_REQUEST['_wpnonce'], '' ) ) ) ) ) ); // @codingStandardsIgnoreLine.
		// If nonce verification fails, send a JSON response with an error message.
		if ( ! wp_verify_nonce( $nonce_value, 'funnelkit-login' ) ) {
			wp_send_json_error( array( 'message' => 'Nonce verification failed, please try again.' ) );
		}

		// Retrieve and sanitize credentials.
		$credentials = array(
			'user_login'    => sanitize_text_field( $_POST['username'] ),
			'user_password' => sanitize_text_field( $_POST['password'] ),
			'remember'      => isset( $_POST['rememberme'] ),
		);

		// Attempt sign on.
		$user = wp_signon( $credentials, false );

		// Check for errors.
		if ( ! is_wp_error( $user ) ) {
			wp_set_current_user( $user->ID );
			wp_send_json_success( array( 'message' => 'Login successful' ) );
		} else {
			wp_send_json_error( array(
				'message' => 'Login failed',
				'error'   => $user->get_error_message(),
			) );
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


		$nonce_value   = wc_clean( wp_unslash( wc_get_var( $_REQUEST['funnelkit-lost-password-nonce'], wc_clean( wp_unslash( wc_get_var( $_REQUEST['_wpnonce'], '' ) ) ) ) ) ); // @codingStandardsIgnoreLine.
		$source        = wc_clean( wp_unslash( wc_get_var( $_REQUEST["wfacp_source"], '' ) ) ); // @codingStandardsIgnoreLine.
		$wfacp_post_id = wc_clean( wp_unslash( wc_get_var( $_REQUEST["_wfacp_post_id"], '' ) ) ); // @codingStandardsIgnoreLine.
		// If nonce verification fails, send a JSON response with an error message.
		if ( ! wp_verify_nonce( $nonce_value, 'funnelkit-login' ) ) {
			wp_send_json_error( array( 'message' => 'Nonce verification failed, please try again.' ) );
		}

		if ( empty( $_POST['user_login'] ) ) {
			wp_send_json_error( array( 'message' => 'username and email empty' ) );

			return;
		}
		// Sanitize the user login email address input from the form.
		$user_email = sanitize_text_field( $_POST['user_login'] ?? '' );
		if ( is_email( $user_email ) ) {
			// Retrieve the user by their email address.
			$user = get_user_by( 'email', $user_email );
		} else {
			// Retrieve the user by their email address.
			$user = get_user_by( 'login', $user_email );
		}


		// If no user is found, send a JSON response with an error message.
		if ( false === $user || is_null( $user ) || ! $user instanceof \WP_User ) {
			wp_send_json_error( [
				'message' => __( 'Invalid username or email.', 'woocommerce' ),
				'key'     => WC()->session->get_customer_id()
			] );
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
			update_user_meta( $user->ID, '_funnelkit_user_forget_password', [
				'source'             => $source,
				'checkout_id'        => $wfacp_post_id,
				'session_key'        => $session_key,
				'is_global_checkout' => ( $wfacp_post_id == \WFACP_Common::get_checkout_page_id() ) || class_exists( '\WFFN_Common' ) && ( \WFFN_Common::get_store_checkout_id() == $wfacp_post_id )
			] );
			//Save current page either is store checkout or dedicated checkout

			wp_send_json_success( [
				'message' => __( 'A password reset email has been sent to the email address on file for your account, but may take several minutes to show up in your inbox. Please wait at least 10 minutes before attempting another reset.', 'woocommerce' ) . "<button type='button' class='wfacp-quickv-close'>" . __( 'Close', 'woocommerce' ) . "</button>"
			] );
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
                background: url('<?php echo esc_url($icon); ?>') no-repeat center left;

            }

        </style>
		<?php


	}

}

Main::get_instance();
}
