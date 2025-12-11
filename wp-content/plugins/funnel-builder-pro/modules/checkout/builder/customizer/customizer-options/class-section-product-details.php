<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFACP_SectionProductDetails' ) ) {
	#[AllowDynamicProperties]
	class WFACP_SectionProductDetails {

		public static $customizer_key_prefix = 'wfacp_';
		public static $_instance = null;

		private $template_common;

		/**
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

		public function productDetails_settings() {

			$selected_template_slug = $this->template_common->get_template_slug();

			/** PANEL: Product Details  Setting */
			$productDetails_panel = array();
			$hash_key             = '';

			$productDetails_panel['wfacp_product'] = array(
				'panel'    => 'no',
				'data'     => array(
					'priority'    => 20,
					'title'       => 'Product',
					'description' => '',
				),
				'sections' => array(
					'section' => array(
						'data'   => array(
							'title'    => 'Product',
							'priority' => 20,
						),
						'fields' => [
							'ct_enable_product_section'                         => array(
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Product', 'woocommerce' ) . '</div>',
								'priority' => 20,
							),
							$selected_template_slug . '_enable_product_section' => [
								'type'        => 'checkbox',
								'label'       => __( 'Enable Product Section', 'woofunnels-aero-checkout' ),
								'description' => '',
								'default'     => true,
								'priority'    => 20,
							],
							/* Product Details Setting */
							'ct_summary'                                        => array(
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Summary', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 20,
							),
							'title'                                             => array(
								'type'          => 'text',
								'label'         => __( 'Title', 'woofunnels-aero-checkout' ),
								'default'       => esc_attr__( 'Your Awesome Product', 'woofunnels-aero-checkout' ),
								'transport'     => 'postMessage',
								'priority'      => 20,
								'wfacp_partial' => [
									'elem' => '.wfacp_product h1',
								],
							),
							$selected_template_slug . '_title_fs'               => array(
								'type'        => 'wfacp-responsive-font',
								'label'       => __( 'Title Font Size', 'woofunnels-aero-checkout' ),
								'default'     => array(
									'desktop' => 26,
									'tablet'  => 24,
									'mobile'  => 24,
								),
								'input_attrs' => array(
									'step' => 1,
									'min'  => 12,
									'max'  => 48,
								),
								'units'       => array(
									'px' => 'px',
									'em' => 'em',
								),

								'priority'        => 20,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal'   => true,
										'responsive' => true,
										'type'       => 'css',
										'prop'       => [ 'font-size' ],
										'elem'       => '.wfacp_product .wfacp_heading_text',
									],
								],
							),
							'desc'                                              => array(
								'type'        => 'textarea',
								'label'       => __( 'Description', 'woofunnels-aero-checkout' ),
								'default'     => __( "Use this section to highlight your product's USP. Mention the most important benefit. E.g: This awesome product has 100% organic hand-picked ingredients that heal and protect your skin.", 'woofunnels-aero-checkout' ),
								'description' => '',
								'priority'    => 20,
								'transport'   => 'postMessage',

								'wfacp_transport' => array(
									array(
										'type' => 'html',
										'elem' => '.wfacp_product .wfacp-customize-text',
									),
									[
										'type' => 'add_remove_class',
										'elem' => '.wfacp_product .wfacp-customize-text',
									],
								),

							),
							$selected_template_slug . '_desc_fs'                => array(
								'type'        => 'wfacp-responsive-font',
								'label'       => __( 'Description Font Size', 'woofunnels-aero-checkout' ),
								'default'     => array(
									'desktop' => 20,
									'tablet'  => 18,
									'mobile'  => 15,
								),
								'input_attrs' => array(
									'step' => 1,
									'min'  => 12,
									'max'  => 32,
								),
								'units'       => array(
									'px' => 'px',
									'em' => 'em',
								),

								'priority'        => 20,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal'   => true,
										'responsive' => true,
										'type'       => 'css',
										'prop'       => [ 'font-size' ],
										'elem'       => '.wfacp_product p',
									],
									[
										'internal'   => true,
										'responsive' => true,
										'type'       => 'css',
										'prop'       => [ 'font-size' ],
										'elem'       => '.wfacp_product .wfacp-customize-text',
									],
								],
							),

							$selected_template_slug . '_section_height' => array(
								'type'            => 'slider',
								'label'           => esc_attr__( 'Section Height', 'woofunnels-aero-checkout' ),
								'default'         => 261,
								'choices'         => array(
									'min'  => '1',
									'max'  => '1000',
									'step' => '1',
								),
								'priority'        => 20,
								'transport'       => 'postMessage',
								'wfacp_transport' => [
									[
										'internal' => true,
										'type'     => 'css',
										'suffix'   => 'px',
										'prop'     => [ 'min-height' ],
										'elem'     => '.wfacp-about-product.wfacp_product',
									],
								],
							),

							'ct_product_section'                              => array(
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Image', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 20,
							),
							$selected_template_slug . '_enable_product_image' => [
								'type'        => 'checkbox',
								'label'       => __( 'Show Product Image', 'woofunnels-aero-checkout' ),
								'description' => '',
								'default'     => true,
								'priority'    => 20,
							],
							'product_image'                                   => array(
								'type'            => 'image',
								'label'           => esc_attr__( 'Product Image', 'woofunnels-aero-checkout' ),
								'default'         => $this->template_common->img_path . 'product_default_icon.jpg',
								'priority'        => 20,
								'description'     => '',
								'active_callback' => [
									[
										'setting'  => 'wfacp_product_section_' . $selected_template_slug . '_enable_product_image',
										'operator' => '=',
										'value'    => true,
									],
								],

							),
							/* Product Details Color Setting */
							'ct_colors'                                       => [
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 230,
							],
							$selected_template_slug . '_section_bg_color'     => [
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
										'elem'     => '.wfacp_product',
									],
								],

							],
							$selected_template_slug . '_heading_text_color'   => [
								'type'            => 'color',
								'label'           => esc_attr__( 'Heading Color', 'woofunnels-aero-checkout' ),
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
										'prop'     => [ 'color' ],
										'elem'     => '.wfacp_product' . ' .wfacp_heading_text',
									],
								],

							],
							$selected_template_slug . '_content_text_color'   => [
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
										'elem'     => '.wfacp_product' . ' p',
									],
									[
										'internal' => true,
										'type'     => 'css',
										'prop'     => [ 'color' ],
										'elem'     => '.wfacp_product .wfacp-customize-text',
									],

								],
							],

						],
					),
				),
			);
			$productDetails_panel['wfacp_product'] = apply_filters( 'wfacp_layout_default_setting', $productDetails_panel['wfacp_product'], 'wfacp_product' );

			return $productDetails_panel;
		}
	}
}