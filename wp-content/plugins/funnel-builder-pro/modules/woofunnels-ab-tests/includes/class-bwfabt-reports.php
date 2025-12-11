<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'BWFABT_Reports' ) ) {
	/**
	 * Handles the operations and usage of reports in woofunnels ab testings
	 * Class BWFABT_Reports
	 */
	#[AllowDynamicProperties]
	class BWFABT_Reports {

		/**
		 * @var null
		 */
		public static $ins = null;

		/**
		 * @var BWFABT_Reports[]
		 */
		public $reports = array();

		/**
		 * Report classes prefix
		 * @var string
		 */
		public $class_prefix = 'BWFABT_AB_Report_';

		/**
		 * BWFABT_Reports constructor.
		 * @throws Exception
		 */
		public function __construct() {
			add_action( 'wp_loaded', array( $this, 'get_registered_report_objects' ), 5 );
		}

		/**
		 * @return BWFABT_Reports|null
		 * @throws Exception
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		/**
		 * Return a single registered report by providing it report_type like (upstroke, aerocheckout etc.)
		 *
		 * @param $report_type
		 *
		 * @return bool|BWFABT_Reports
		 */
		public function get_integration( $report_type ) {

			$get_supported_reports = $this->get_supported_reports();
			if ( is_array( $get_supported_reports ) && count( $get_supported_reports ) > 0 && array_key_exists( $report_type, $get_supported_reports ) ) {
				return $this->get_integration_object( $get_supported_reports[ $report_type ] );
			}

			return false;
		}

		/**
		 * @return mixed|void
		 */
		public function get_supported_reports() {
			return apply_filters( 'bwfabt_get_supported_reports', array() );
		}

		/**
		 * @param $report_class
		 *
		 * @return BWFABT_Reports
		 */
		public function get_integration_object( $report_class ) {
			if ( isset( $this->reports[ $report_class ] ) ) {
				return $this->reports[ $report_class ];
			}

			$this->reports[ $report_class ] = call_user_func( array( $report_class, 'get_instance' ) );

			return $this->reports[ $report_class ];
		}


		/**
		 * @return mixed
		 */
		public function get_registered_report_objects() {

			$available_reports = $this->get_supported_reports();
			if ( false === is_array( $available_reports ) ) {
				return $available_reports;
			}
			$supported_reports      = array_keys( $available_reports );
			$registered_reports_obj = array();
			foreach ( $supported_reports as $report_type ) {

				$registered_reports_obj[ $report_type ] = $this->get_integration( $report_type );
			}

			return $registered_reports_obj;
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
		 * Get a single report object by passing report_type(like upstroke, aerocheckout etc)
		 *
		 * @param $report_type
		 *
		 * @return BWFABT_Reports
		 */
		public function get_single_report( $report_type ) {
			$controller_class = $this->get_report_class_name_by_type( $report_type );

			return $this->get_integration_object( $controller_class );
		}

		/**
		 * Get report class name by passing report_type like (upstroke, aerorcheckout etc)
		 *
		 * @param $controller_type
		 */
		public function get_report_class_name_by_type( $report_type ) {
			$reports = $this->get_supported_reports();

			return isset( $reports[ $report_type ] ) ? $reports[ $report_type ] : null;
		}
	}

	BWFABT_Core()->reports = BWFABT_Reports::get_instance();
}