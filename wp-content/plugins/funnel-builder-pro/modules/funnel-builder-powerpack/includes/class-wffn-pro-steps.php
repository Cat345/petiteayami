<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'WFFN_Pro_Steps' ) ) {
	/**
	 * Handles the operations and usage of steps in funnel
	 * Class WFFN_Pro_Steps
	 */
	class WFFN_Pro_Steps {

		/**
		 * @var null
		 */
		public static $ins = null;

		/**
		 * @var array $steps
		 */
		public $steps = array();

		/**
		 * Step classes prefix
		 * @var string
		 */
		public $class_prefix = 'WFFN_Pro_Step_';

		/**
		 * WFFN_Pro_Steps constructor.
		 */
		public function __construct() {
			add_action( 'wffn_pro_loaded', array( $this, 'load_steps' ) );
		}

		/**
		 * @return WFFN_Pro_Steps|null
		 * @throws Exception
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		/**
		 * @return WFFN_Pro_Steps[]
		 */
		public function get_supported_steps() {
			return $this->steps;
		}

		/**
		 * @param $step_class
		 *
		 * @return bool|mixed
		 */
		public function get_integration_object( $step_class ) {

			if ( isset( $this->steps[ $step_class ] ) ) {
				return $this->steps[ $step_class ];
			}

			return false;
		}

		/**
		 * @param $step WFFN_Step
		 *
		 * @throws Exception
		 */
		public function register( $step ) {
			if ( ! is_subclass_of( $step, 'WFFN_Pro_Step' ) ) {
				throw new Exception( 'Must be a subclass of WFFN_Pro_Step' );
			}
			if ( empty( $step->slug ) ) {
				throw new Exception( 'The type must be set' );
			}
			if ( isset( $this->steps[ $step->slug ] ) ) {
				throw new Exception( 'Step type already registered: ' . $step->slug );
			}

			if ( false === $step->should_register() ) {
				return;
			}
			$this->steps[ $step->slug ] = $step;
		}

		/**
		 * Includes steps files
		 *
		 */
		public function load_steps() {
			// load all the trigger files automatically
			foreach ( glob( plugin_dir_path( WFFN_PRO_PLUGIN_FILE ) . 'steps/*/class-*.php' ) as $steps_file_name ) {
				require_once( $steps_file_name ); //phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			}
		}
	}

	if ( class_exists( 'WFFN_Pro_Core' ) ) {
		WFFN_Pro_Core::register( 'steps', 'WFFN_Pro_Steps' );
	}
}
