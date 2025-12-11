<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'BWFABT_Controllers' ) ) {
	/**
	 * Handles the operations and usage of controllers in woofunnels ab testings
	 * Class BWFABT_Controllers
	 */
	#[AllowDynamicProperties]
	class BWFABT_Controllers {

		/**
		 * @var null
		 */
		public static $ins = null;

		/**
		 * @var BWFABT_Controllers[]
		 */
		public $controllers = array();

		/**
		 * Controller classes prefix
		 * @var string
		 */
		public $class_prefix = 'BWFABT_Controller_';

		/**
		 * BWFABT_Controllers constructor.
		 * @throws Exception
		 */
		public function __construct() {

			add_action( 'wp_loaded', array( $this, 'get_registered_controller_objects' ), 5 );


		}

		/**
		 * @return BWFABT_Controllers|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		/**
		 * Return a single registered controlls by providing it controller_type like (upstroke, aerocheckout etc.)
		 *
		 * @param $controller_type
		 *
		 * @return bool|BWFABT_Controller
		 */
		public function get_integration( $controller_type ) {

			$get_supported_controllers = $this->get_supported_controllers();
			if ( is_array( $get_supported_controllers ) && count( $get_supported_controllers ) > 0 && array_key_exists( $controller_type, $get_supported_controllers ) ) {
				return $this->get_integration_object( $get_supported_controllers[ $controller_type ] );
			}

			return false;
		}

		/**
		 * @return mixed|void
		 */
		public function get_supported_controllers() {
			return apply_filters( 'bwfabt_get_supported_controllers', array() );
		}

		/**
		 * @param $controller_class
		 *
		 * @return BWFABT_Controllers
		 */
		public function get_integration_object( $controller_class ) {
			if ( isset( $this->controllers[ $controller_class ] ) ) {
				return $this->controllers[ $controller_class ];
			}

			$this->controllers[ $controller_class ] = call_user_func( array( $controller_class, 'get_instance' ) );

			return $this->controllers[ $controller_class ];
		}


		/**
		 * @return mixed
		 */
		public function get_registered_controller_objects() {

			$available_controllers = $this->get_supported_controllers();
			if ( false === is_array( $available_controllers ) ) {
				return $available_controllers;
			}
			$supported_controllers      = array_keys( $available_controllers );
			$registered_controllers_obj = array();
			foreach ( $supported_controllers as $controller_type ) {

				$registered_controllers_obj[ $controller_type ] = $this->get_integration( $controller_type );
			}

			return $registered_controllers_obj;
		}

		/**
		 * Slugify the class name and remove underscores and convert it to filename
		 * Helper function for the auto-loading
		 *
		 * @param $class_name
		 *
		 * @return mixed|string
		 */
		public function slugify_classname( $class_name ) {
			$classname = $this->custom_sanitize_title( $class_name );
			$classname = str_replace( '_', '-', $classname );

			return $classname;
		}

		/**
		 * Custom sanitize title method to avoid conflicts with WordPress hooks on sanitize_title
		 * 
		 * @param string $title The title to sanitize
		 * @return string The sanitized title
		 */
		private function custom_sanitize_title( $title ) {
			$title = remove_accents( $title );
			$title = sanitize_title_with_dashes( $title );
			
			return $title;
		}

		/**
		 * Get a single controller object by passing controller_type(like upstroke, aerocheckout etc)
		 *
		 * @param controller_type
		 *
		 * @return BWFABT_AB_Controllers
		 */
		public function get_single_controller( $controller_type ) {
			$controller_class = $this->get_controller_class_name_by_type( $controller_type );

			return $this->get_integration_object( $controller_class );
		}

		/**
		 * Get controller class name by passing controller controller_type like (upstroke, aerorcheckout etc)
		 *
		 * @param $controller_type
		 */
		public function get_controller_class_name_by_type( $controller_type ) {
			$controllers = $this->get_supported_controllers();

			return isset( $controllers[ $controller_type ] ) ? $controllers[ $controller_type ] : null;
		}

		/**
		 * @param $control_id
		 * @param $data
		 *
		 * Return step data on update actions for manage tags in funnel step list
		 *
		 * @return mixed
		 */
		public function maybe_get_step_list_data( $control_id, $data ) {

			if ( 0 === absint( $control_id ) ) {
				return $data;
			}

			if ( ! function_exists( 'wffn_rest_api_helpers' ) ) {
				return $data;
			}

			$step_data = wffn_rest_api_helpers()->get_step_post( $control_id, true );

			if ( ! is_array( $step_data ) ) {
				return $data;
			}

			$data['step_data'] = is_array( $step_data ) && isset( $step_data['step_data'] ) ? $step_data['step_data'] : false;
			$data['step_list'] = is_array( $step_data ) && isset( $step_data['step_list'] ) ? $step_data['step_list'] : false;

			return $data;
		}


	}

	BWFABT_Core()->controllers = BWFABT_Controllers::get_instance();
}