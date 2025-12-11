<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'WFFN_Optin_Action_Create_WP_User' ) ) {
	/**
	 * This class will create a wp user on optin form submitted if needed.
	 * Class WFFN_Optin_Action_Create_WP_User
	 */
	#[AllowDynamicProperties]
	class WFFN_Optin_Action_Create_WP_User extends WFFN_Optin_Action {

		private static $slug = 'create_wp_user';
		private static $ins = null;
		private $auto_login = false;
		public $priority = 20;

		/**
		 * WFFN_Optin_Action_Create_WP_User constructor.
		 */
		public function __construct() {
			parent::__construct();
		}

		/**
		 * @return WFFN_Optin_Action_Create_WP_User|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		/**
		 * @return bool
		 */
		public function should_register() {
			return parent::should_register();
		}

		/**
		 * @return string
		 */
		public static function get_slug() {
			return self::$slug;
		}


		/**
		 * @param $posted_data
		 * @param $fields_settings
		 *
		 * @return array|bool|mixed
		 */
		public function handle_action( $posted_data, $fields_settings, $optin_action_settings ) { //phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter

			return $posted_data;
		}

		public function maybe_insert_user( $posted_data, $role = 'subscriber' ) {


			/**
			 * Bail out if user is entered same mail ID as login
			 */
			$user_id = get_current_user_id();

			if ( $user_id > 0 ) {
				WFFN_Core()->logger->log( 'Skipping creating user, already logged in.' ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$posted_data['user_id'] = $user_id;
				BWF_Optin_Tags::get_instance()->maybe_set_optin( $posted_data['opid'] );
				WFOPP_Core()->autologin->set_user_id( $user_id );

				return $posted_data;
			}

			$this->setup_optin_data( $posted_data );
			$optin_email = $this->get_optin_data( WFFN_Optin_Form_Field_Email::get_slug() );
			$first_name  = $this->get_optin_data( WFFN_Optin_Form_Field_First_Name::get_slug() );
			$last_name   = $this->get_optin_data( WFFN_Optin_Form_Field_Last_Name::get_slug() );

			$user_id = get_user_by( 'email', $optin_email );
			if ( ! empty( $user_id ) ) {
				WFOPP_Core()->autologin->set_user_id( $user_id->ID );
				$posted_data['user_id'] = $user_id->ID;

				return $posted_data;
			}


			/** Creating wp user **/
			$password = wp_generate_password();
			$creds    = apply_filters( 'wffn_optin_user_pre_create', array(
				'user_login' => $optin_email,
				'user_pass'  => $password,
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'user_email' => $optin_email,
				'role'       => $role,
			), $posted_data );

			$user_id = wp_insert_user( $creds );

			if ( is_wp_error( $user_id ) ) {
				WFFN_Core()->logger->log( 'Error in creating user: ' . print_r( $user_id->get_error_message(), true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

				return $posted_data;
			}

			WFFN_Core()->logger->log( "A wp user is created with user_id: $user_id and creds: " . print_r( $creds, true ) ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

			do_action( 'wffn_optin_user_created', $user_id, $creds, $posted_data );

			$posted_data['user_id'] = $user_id;
			BWF_Optin_Tags::get_instance()->maybe_set_optin( $posted_data['opid'] );
			WFOPP_Core()->autologin->set_user_id( $user_id );

			return $posted_data;
		}


	}

	if ( class_exists( 'WFOPP_Core' ) ) {
		WFOPP_Core()->optin_actions->register( WFFN_Optin_Action_Create_WP_User::get_instance() );
	}
}