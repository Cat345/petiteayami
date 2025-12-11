<?php
defined( 'ABSPATH' ) || exit; //Exit if accessed directly
if ( ! class_exists( 'WFFN_Optin_Form_Field_HTML' ) ) {
	/**
	 * Class WFFN_Optin_Form_Field_HTML
	 */
	#[AllowDynamicProperties]
	class WFFN_Optin_Form_Field_HTML extends WFFN_Optin_Form_Field {

		private static $ins = null;
		public static $slug = 'wfop_wysiwyg';
		public $is_custom_field = true;

		public $index = 50;

		/**
		 * WFFN_Optin_Form_Field_HTML constructor.
		 */
		public function __construct() {
			parent::__construct();
		}

		/**
		 * @return WFFN_Optin_Form_Field_HTML|null
		 */
		public static function get_instance() {
			if ( null === self::$ins ) {
				self::$ins = new self;
			}

			return self::$ins;
		}

		/**
		 * @return string
		 */
		public static function get_slug() {
			return self::$slug;
		}

		/**
		 * Return title of this form field
		 */
		public function get_title() {
			return __( 'HTML', 'funnel-builder-powerpack' );
		}

		/**
		 * @param $field_data
		 *
		 * @return string|void
		 */
		public function get_field_output( $field_data ) {
			$field_data = wp_parse_args( $field_data, $this->get_field_format() );
			$value      = $this->get_default_value( $field_data );
			echo htmlspecialchars_decode( $value );
		}

		/**
		 * @return array
		 */
		public function get_field_format() {
			return array(
				'width'       => 'wffn-sm-100',
				'type'        => $this::get_slug(),
				'label'       => __( 'HTML', 'funnel-builder-powerpack' ),
				'placeholder' => '',
				'required'    => true,
				'default'     => '',
			);
		}

	}

	if ( class_exists( 'WFOPP_Core' ) ) {
		WFOPP_Core()->form_fields->register( WFFN_Optin_Form_Field_HTML::get_instance() );
	}
}
