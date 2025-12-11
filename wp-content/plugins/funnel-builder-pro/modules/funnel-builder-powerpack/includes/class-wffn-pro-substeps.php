<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'WFFN_Pro_Substeps' ) ) {
	/**
	 * Handles the operations and usage of substeps in funnel
	 * Class WFFN_Pro_Substeps
	 */
	#[AllowDynamicProperties]
	class WFFN_Pro_Substeps {

		/**
		 * @var null
		 */
		public static $ins = null;

		/**
		 * @var WFFN_Pro_Substeps[]
		 */
		public $substeps = array();

		/**
		 * WFFN_Pro_Substeps constructor.
		 */
		public function __construct() {
			add_action( 'wffn_pro_loaded', array( $this, 'load_substeps' ) );
		}

		/**
		 * @return WFFN_Pro_Substeps|null
		 * @throws Exception
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		/**
		 * @return WFFN_Pro_Substeps[]
		 */
		public function get_supported_substeps() {
			return $this->substeps;
		}

		/**
		 * @param $substep_class
		 *
		 * @return bool|WFFN_Pro_Substeps
		 */
		public function get_integration_object( $substep_class ) {

			if ( isset( $this->substeps[ $substep_class ] ) ) {
				return $this->substeps[ $substep_class ];
			}

			return false;
		}

		/**
		 * @param $substep
		 *
		 * @throws Exception
		 */
		public function register( $substep ) {
			if ( ! is_subclass_of( $substep, 'WFFN_Pro_Substep' ) ) {
				throw new Exception( 'Must be a subclass of WFFN_Pro_Substep' );
			}
			if ( empty( $substep->slug ) ) {
				throw new Exception( 'The type must be set' );
			}
			if ( isset( $this->steps[ $substep->slug ] ) ) {
				throw new Exception( 'Step type already registered: ' . $substep->slug );
			}

			if ( false === $substep->should_register() ) {
				return;
			}
			$this->substeps[ $substep->slug ] = $substep;
		}

		/**
		 * Loading substeps files
		 */
		public function load_substeps() {
			// load all the trigger files automatically
			foreach ( glob( plugin_dir_path( WFFN_PRO_PLUGIN_FILE ) . 'substeps/*/class-*.php' ) as $substep_file_name ) {
				require_once( $substep_file_name ); //phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			}
		}
	}

	if ( class_exists( 'WFFN_Pro_Core' ) ) {
		WFFN_Pro_Core::register( 'substeps', 'WFFN_Pro_Substeps' );
	}
}