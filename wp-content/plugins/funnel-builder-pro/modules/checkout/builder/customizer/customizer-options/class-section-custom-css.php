<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WFACP_SectionCustomCss' ) ) {
	#[AllowDynamicProperties]
	class WFACP_SectionCustomCss {

		public static $customizer_key_prefix = 'wfacp_';
		public static $_instance = null;
		/**
		 * @var $template_common  WFACP_Template_Common
		 */
		public $template_common;

		protected function __construct( $template_common = null ) {
			if ( ! is_null( $template_common ) ) {
				$this->template_common = $template_common;
			}
		}

		public static function get_instance( $template_common ) {
			if ( self::$_instance == null ) {
				self::$_instance = new self( $template_common );
			}

			return self::$_instance;
		}

		public function custom_css_settings() {

			$selected_template_slug = $this->template_common->get_template_slug();
			/** PANEL: Custom Setting */
			$css_panel = array();

			$css_panel['wfacp_custom_css'] = array(
				'panel'    => 'no',
				'data'     => array(
					'priority'    => 85,
					'title'       => __( 'Custom CSS', 'woofunnels-aero-checkout' ),
					'description' => '',
				),
				'sections' => array(
					'section' => array(
						'data'   => array(
							'title'    => __( 'Custom CSS', 'woofunnels-aero-checkout' ),
							'priority' => 85,
						),
						'fields' => array(
							$selected_template_slug . '_code' => array(
								'type'     => 'code',
								'label'    => __( 'Custom CSS', 'woofunnels-aero-checkout' ),
								'choices'  => array(
									'language' => 'css',
								),
								'priority' => 10,
							),
						),
					),
				),
			);

			return $css_panel;
		}
	}
}