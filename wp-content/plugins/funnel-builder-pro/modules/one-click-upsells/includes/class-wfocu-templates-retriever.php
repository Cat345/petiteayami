<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'WFOCU_Templates_Retriever' ) ) {
	/**
	 * Class to retrieve templates json file
	 * Class WFOCU_Templates_Retriever
	 */
	class WFOCU_Templates_Retriever {

		/** @var null */
		private static $ins = null;

		/** @var array $supported page builders */
		protected $supported_builders = array();

		/**
		 * WFOCU_Templates_Retriever constructor.
		 */
		public function __construct() {

			$this->supported_builders = array( 'elementor', 'divi', 'beaver', 'oxy', 'gutenberg' );
		}

		/**
		 * @return WFOCU_Templates_Retriever|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		/**
		 * Creating and retrieving  templates json
		 */
		public function wfocu_process_builder_templates() {
			$group = filter_input( INPUT_GET, 'get_templates', FILTER_UNSAFE_RAW );

			if ( ! empty( $group ) && in_array( $group, $this->supported_builders, true ) ) {
				$this->get_detailed_template( $group );
			}

			$get_single_template = filter_input( INPUT_GET, 'get_single_template', FILTER_UNSAFE_RAW );
			$group               = filter_input( INPUT_GET, 'group', FILTER_UNSAFE_RAW );
			if ( ! empty( $get_single_template ) && ! empty( $group ) ) {
				$this->get_single_template_json( $get_single_template, $group );
			}
		}

		/**
		 * Retrieving main json file for all template details
		 *
		 * @param $template
		 *
		 * @return array|mixed|object|null
		 */
		public function get_detailed_template( $group ) {

			$group_templates = array();
			if ( empty( $group ) || ! in_array( $group, $this->supported_builders, true ) ) {
				return $group_templates;
			}

			$templates = WooFunnels_Dashboard::get_all_templates();

			return isset( $templates['upsell'][ $group ] ) ? $templates['upsell'][ $group ] : [];

		}


		/**
		 * Retrieving single template json
		 *
		 * @param $get_template
		 * @param $group
		 *
		 * @return array|bool|string|WP_Error
		 */
		public function get_single_template_json( $get_template, $group ) {
			$template_json = WFOCU_Remote_Template_Importer::get_instance()->get_remote_template( $get_template, $group );

			return $template_json;
		}
	}

	if ( class_exists( 'WFOCU_Core' ) ) {
		WFOCU_Core::register( 'template_retriever', 'WFOCU_Templates_Retriever' );
	}
}