<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFACP_Sectionlayout' ) ) {
	#[AllowDynamicProperties]
	class WFACP_Sectionlayout {

		public static $customizer_key_prefix = 'wfacp_';
		public static $_instance = null;
		/**
		 * @var WFACP_Template_Common
		 */
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

		public function layout_settings() {

			$selected_template_slug = $this->template_common->get_template_slug();

			/** PANEL: Layout Setting */
			$layout_panel = [];

			$layout_panel['wfacp_layout'] = [
				'panel'    => 'no',
				'data'     => [
					'priority'    => 10,
					'title'       => __( 'Widget Visibility', 'woofunnels-aero-checkout' ),
					'description' => '',
				],
				'sections' => [
					'section' => [
						'data'   => [
							'title'    => __( 'Widget Visibility', 'woofunnels-aero-checkout' ),
							'priority' => 10,
						],
						'fields' => [
							'ct_components'                                         => [
								'type'     => 'custom',
								'default'  => '<div class="options-title-divider">' . esc_html__( 'Sections', 'woofunnels-aero-checkout' ) . '</div>',
								'priority' => 40,
							],
							$selected_template_slug . '_sidebar_layout_order'       => [
								'type'        => 'sortable',
								'label'       => __( 'Elements Order & Visibility for Desktop View Sidebar', 'woofunnels-aero-checkout' ),
								'description' => __( '<b>Tip:</b> Drag and Drop Sections to modify its position. <br>Click on Eye icon to turn ON/OFF visibility of the section.', 'woofunnels-aero-checkout' ),
								'default'     => [
									'wfacp_benefits_0',
									'wfacp_testimonials_0',
									'wfacp_promises_0',
									'wfacp_assurance_0',
									'wfacp_customer_0',
									'wfacp_html_widget_3',
								],
								'choices'     => [
									'wfacp_benefits_0'     => esc_attr__( 'Benefits', 'woofunnels-aero-checkout' ),
									'wfacp_testimonials_0' => esc_attr__( 'Testimonials', 'woofunnels-aero-checkout' ),
									'wfacp_promises_0'     => esc_attr__( 'Promises', 'woofunnels-aero-checkout' ),
									'wfacp_assurance_0'    => esc_attr__( 'Assurance', 'woofunnels-aero-checkout' ),
									'wfacp_customer_0'     => esc_attr__( 'Customer Support', 'woofunnels-aero-checkout' ),
									'wfacp_html_widget_1'  => esc_attr__( 'Custom HTML Sidebar-1', 'woofunnels-aero-checkout' ),
									'wfacp_html_widget_2'  => esc_attr__( 'Custom HTML Sidebar-2', 'woofunnels-aero-checkout' ),
									'wfacp_html_widget_3'  => esc_attr__( 'Custom HTML Sidebar-3', 'woofunnels-aero-checkout' ),
								],
								'priority'    => 50,
							],
							$selected_template_slug . '_mobile_sections_page_order' => [
								'type'        => 'sortable',
								'label'       => __( 'Elements Order & Visibility for Mobile View', 'woofunnels-aero-checkout' ),
								'description' => __( '<i><b>Tip:</b> You can rearrange widgets or control visibility on mobile. Shrinking the browser and checking preview may not lead to real results. Check on mobile decide to see the actual preview.</i>', 'woofunnels-aero-checkout' ),
								'default'     => [
									'wfacp_form',
									'wfacp_product',
									'wfacp_benefits_0',
									'wfacp_testimonials_0',
									'wfacp_promises_0',
									'wfacp_assurance_0',
									'wfacp_customer_0',
								],
								'choices'     => [
									'wfacp_form'           => esc_attr__( 'Form', 'woofunnels-aero-checkout' ),
									'wfacp_product'        => esc_attr__( 'Product', 'woocommerce' ),
									'wfacp_benefits_0'     => esc_attr__( 'Benefits', 'woofunnels-aero-checkout' ),
									'wfacp_testimonials_0' => esc_attr__( 'Testimonials', 'woofunnels-aero-checkout' ),
									'wfacp_promises_0'     => esc_attr__( 'Promises', 'woofunnels-aero-checkout' ),
									'wfacp_assurance_0'    => esc_attr__( 'Assurance', 'woofunnels-aero-checkout' ),
									'wfacp_customer_0'     => esc_attr__( 'Customer Support', 'woofunnels-aero-checkout' ),
									'wfacp_html_widget_1'  => esc_attr__( 'Custom HTML Sidebar-1', 'woofunnels-aero-checkout' ),
									'wfacp_html_widget_2'  => esc_attr__( 'Custom HTML Sidebar-2', 'woofunnels-aero-checkout' ),
									'wfacp_html_widget_3'  => esc_attr__( 'Custom HTML Below Form', 'woofunnels-aero-checkout' ),
								],
								'priority'    => 55,
							],
							'hidden_feilds'                                         => [
								'type'     => 'hidden',
								'label'    => '',
								'default'  => 1,
								'priority' => 55,
							],
							'customizer_data'                                       => [
								'type'            => 'sortable',
								'label'           => __( 'All Customizer Data', 'woofunnels-aero-checkout' ),
								'description'     => __( '<b>Tip:</b> Drag and Drop Sections to modify its position. <br>Click on Eye icon to turn ON/OFF visibility of the section.', 'woofunnels-aero-checkout' ),
								'default'         => [
									'wfacp_form',
									'wfacp_product',
									'wfacp_benefits_0',
									'wfacp_testimonials_0',
									'wfacp_promises_0',
									'wfacp_assurance_0',
									'wfacp_customer_0',
									'wfacp_html_widget_1',
									'wfacp_html_widget_2',
									'wfacp_html_widget_3',
								],
								'choices'         => [
									'wfacp_form'           => esc_attr__( 'Form', 'woofunnels-aero-checkout' ),
									'wfacp_product'        => esc_attr__( 'Product', 'woofunnels-aero-checkout' ),
									'wfacp_benefits_0'     => esc_attr__( 'Benefits', 'woofunnels-aero-checkout' ),
									'wfacp_testimonials_0' => esc_attr__( 'Testimonials', 'woofunnels-aero-checkout' ),
									'wfacp_promises_0'     => esc_attr__( 'Promises', 'woofunnels-aero-checkout' ),
									'wfacp_assurance_0'    => esc_attr__( 'Assurance', 'woofunnels-aero-checkout' ),
									'wfacp_customer_0'     => esc_attr__( 'Customer Support', 'woofunnels-aero-checkout' ),
									'wfacp_html_widget_1'  => esc_attr__( 'Custom HTML Sidebar-1', 'woofunnels-aero-checkout' ),
									'wfacp_html_widget_2'  => esc_attr__( 'Custom HTML Sidebar-2', 'woofunnels-aero-checkout' ),
									'wfacp_html_widget_3'  => esc_attr__( 'Custom HTML Sidebar-3', 'woofunnels-aero-checkout' ),
								],
								'priority'        => 55,
								'active_callback' => [
									[
										'setting'  => 'wfacp_layout_section_hidden_feilds',
										'operator' => '!=',
										'value'    => '1',
									],
								],
							],
						],
					],
				],
			];

			$layout_panel['wfacp_layout'] = apply_filters( 'wfacp_customizer_layout', $layout_panel['wfacp_layout'], 'wfacp_layout' );

			return $layout_panel;

		}


	}
}