<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFACP_SectionFooter' ) ) {
	#[AllowDynamicProperties]
	class WFACP_SectionFooter {

		public static $customizer_key_prefix = 'wfacp_';
		public static $_instance = null;

		private $template_common;

		/**
		 * WFACP_SectionCustomerCare constructor.
		 *
		 * @param null|WFACP_Template_Common $template_common
		 */
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

		public function footer_settings() {

			$selected_template_slug = $this->template_common->get_template_slug();
			/** PANEL: Footer Setting */
			$footer_panel = array();

			$refundPolicy         = __( 'Refund policy', 'woofunnels-aero-checkout' );
			$privacyPolicy        = __( 'Privacy policy' ,'woocommerce');
			$terms_condition      = __( 'Terms of service', 'woofunnels-aero-checkout' );
			$copy_right           = __( 'Copyright © 2021 WooFunnels - All Rights Reserved', 'woofunnels-aero-checkout' );
			$default_footer_value = '<a href="#">' . $refundPolicy . '</a><a href="#">' . $privacyPolicy . '</a><a href="#">' . $terms_condition . '</a><br>' . $copy_right;

			$footer_panel['wfacp_footer'] = array(
				'panel'    => 'no',
				'data'     => array(
					'priority'    => 75,
					'title'       => __( 'Footer', 'woofunnels-aero-checkout' ),
					'description' => '',
				),
				'sections' => array(
					'section' => array(
						'data' => array(
							'title'    => __( 'Footer', 'woofunnels-aero-checkout' ),
							'priority' => 75,
						),

						'fields' => [

							'ft_ct_content'                                 => array(
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Content', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 20,
							),
							'ft_text'                                       => array(
								'type'          => 'editor',
								'label'         => __( 'Text', 'woofunnels-aero-checkout' ),
								'default'       => $default_footer_value,
								'transport'     => 'postMessage',
								'wfacp_partial' => array(
									'elem'                => '.wfacp-footer .wfacp-footer-text',
									'container_inclusive' => true,
								),
								'priority'      => 20,
							),
							$selected_template_slug . '_ft_text_fs'         => array(
								'type'            => 'wfacp-responsive-font',
								'label'           => __( 'Text Font Size', 'woofunnels-aero-checkout' ),
								'default'         => array(
									'desktop' => 16,
									'tablet'  => 16,
									'mobile'  => 15,
								),
								'input_attrs'     => array(
									'step' => 1,
									'min'  => 12,
									'max'  => 32,
								),
								'units'           => array(
									'px' => 'px',
									'em' => 'em',
								),
								'transport'       => 'postMessage',
								'wfacp_transport' => array(
									array(
										'internal'   => true,
										'responsive' => true,
										'type'       => 'css',
										'prop'       => array( 'font-size' ),
										'elem'       => '.wfacp_footer .wfacp-footer-text',
									),
									array(
										'internal'   => true,
										'responsive' => true,
										'type'       => 'css',
										'prop'       => array( 'font-size' ),
										'elem'       => '.wfacp_footer .wfacp-footer-text p',
									),
								),
								'priority'        => 20,
							),

							/* Footer Color Setting */
							'ct_colors'                                     => [
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 230,
							],
							$selected_template_slug . '_section_bg_color'   => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Background Color', 'woofunnels-aero-checkout' ),
								'default'         => '#414349',
								'choices'         => [
									'alpha' => true,
								],
								'priority'        => 250,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'background-color' ],
										'elem'     => '.wfacp_footer',
									],
								],

							],
							$selected_template_slug . '_content_text_color' => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Content Color', 'woofunnels-aero-checkout' ),
								'default'         => '#414349',
								'choices'         => [
									'alpha' => true,
								],
								'priority'        => 260,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => '.wfacp_footer p',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => '.wfacp_footer a',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => '.wfacp_footer ul li',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => '.wfacp_footer .wfacp-footer-text',
									],

								],
							],

						],
					),
				),
			);

			$footer_panel['wfacp_footer'] = apply_filters( 'wfacp_layout_default_setting', $footer_panel['wfacp_footer'], 'wfacp_footer' );

			return $footer_panel;

		}


	}
}