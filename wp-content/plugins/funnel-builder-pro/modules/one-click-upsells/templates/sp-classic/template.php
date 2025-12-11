<?php
if ( ! class_exists( 'WFOCU_Template_Sp_Classic' ) ) {

	class WFOCU_Template_Sp_Classic extends WFOCU_Customizer_Common {


		private static $ins = null;
		public $template_slug = 'sp-classic';
		protected $section_fields = array();
		protected $offer_data = null;
		protected $offer_products_meta = null;
		protected $change_set = array();
		protected $sections = array( 'wfocu_section' );
		protected $template_dir = __DIR__;
		protected $offer_id = 0;

		public function __construct() {
			parent::__construct();
			add_filter( 'wfocu_assets_styles', array( $this, 'add_styles' ) );
			add_action( 'init', array( $this, 'get_customizer_data' ), 28 );
			add_filter( 'wfocu_view_body_classes', array( $this, 'add_body_classes' ) );
			add_action( 'wfocu_header_print_in_head', array( $this, 'template_specific_css' ), 9 );
		}

		public static function get_instance() {
			if ( is_null( self::$ins ) ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		public function add_styles( $styles ) {

			$styles['classic-css']             = array(
				'path'      => WFOCU_PLUGIN_URL . '/templates/sp-classic/css/style.css',
				'version'   => WFOCU_VERSION,
				'in_footer' => false,
				'supports'  => array(
					'customizer',
					'customizer-preview',
					'offer',
					'offer-page',
				),
			);
			$styles['classic-urgency-bar-css'] = array(
				'path'      => WFOCU_PLUGIN_URL . '/templates/sp-classic/css/urgency-bar.css',
				'version'   => WFOCU_VERSION,
				'in_footer' => false,
				'supports'  => array(
					'customizer',
					'customizer-preview',
					'offer',
					'offer-page',
				),
			);

			return $styles;
		}

		public function get_sections() {
			return $this->sections;
		}

		public function get_offer_id() {
			return $this->offer_id;
		}

		public function set_offer_id( $offer_id = false ) {
			if ( false !== $offer_id ) {
				$this->offer_id = $offer_id;
			}
		}

		public function get_offer_data() {
			return $this->offer_data;
		}

		public function set_offer_data( $offer = false ) {
			$this->offer_data = $offer;
		}

		public function add_body_classes( $classes ) {
			$page_layout = WFOCU_Common::get_option( 'wfocu_' . $this->template_slug . '_layout_layout_style' );
			array_push( $classes, $page_layout );

			return $classes;
		}

		public function template_specific_css() {
			include $this->template_dir . '/css.php'; //phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
		}

		public function get_customizer_data() {
			$offer_data = $this->data;

			$this->web_google_fonts = bwf_get_fonts_list( 'name_key' );

			if ( is_null( $offer_data ) ) {
				return;
			}
			if ( count( get_object_vars( $offer_data->products ) ) > 0 ) {

				$merge_tags_description = '<a href="javascript:void(0)"  onclick="wfocu_show_tb(\'WooFunnels Shortcodes\', \'wfocu_shortcode_help_box\');" >' . __( 'Click here to learn about merge tags available for this area.', 'woofunnels-upstroke-one-click-upsell' ) . '</a>';

				/** PANEL: LAYOUT */
				$layout_panel                                                = [];
				$layout_panel[ 'wfocu_' . $this->template_slug . '_layout' ] = array(
					'panel'    => 'no',
					'data'     => array(
						'priority'    => 10,
						'title'       => 'Layout',
						'description' => '',
					),
					'sections' => array(
						'layout' => array(
							'data'   => array(
								'title'    => 'Layout & Sections',
								'priority' => 10,
							),
							'fields' => array(
								'ct_layout'        => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Layout', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 10,
								),
								'style'            => array(
									'type'     => 'radio-buttonset',
									'label'    => esc_attr__( 'Mode', 'woofunnels-upstroke-one-click-upsell' ),
									'default'  => 'wfocu-fullwidth',
									'choices'  => array(
										'wfocu-fullwidth' => esc_attr__( 'Fullwidth', 'woofunnels-upstroke-one-click-upsell' ),
										'wfocu-boxed'     => esc_attr__( 'Boxed', 'woofunnels-upstroke-one-click-upsell' ),
									),
									'priority' => 20,
								),
								'site_boxed_width' => array(
									'type'            => 'slider',
									'label'           => __( 'Site Boxed Width (px)', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 1024,
									'choices'         => array(
										'min'  => '900',
										'max'  => '1200',
										'step' => '1',
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'max-width' ),
											'elem' => 'body.wfocu-boxed',
										),
										array(
											'type' => 'css',
											'prop' => array( 'max-width' ),
											'elem' => 'body.wfocu-boxed .wfocu-urgency-bar ',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_' . $this->template_slug . '_layout_layout_style',
											'value'    => 'wfocu-boxed',
											'operator' => '==',
										),
									),
									'priority'        => 30,
								),
								'ct_components'    => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Sections', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 40,
								),
								'order'            => array(
									'type'        => 'sortable',
									'label'       => __( 'Order & Visibility', 'woofunnels-upstroke-one-click-upsell' ),
									'description' => __( 'Drag and Drop Sections to modify its position. <br>Click on Eye icon to turn ON/OFF visibility of the section.', 'woofunnels-upstroke-one-click-upsell' ),
									'default'     => array(
										'header',
										'header_progress_bar',
										'heading',
										'products',
										'reviews',
										'features',
										'guarantee',
										'urgency_bar',
										'footer',
									),
									'choices'     => array(
										'header'              => esc_attr__( 'Header', 'woofunnels-upstroke-one-click-upsell' ),
										'header_progress_bar' => esc_attr__( 'Progress Bar', 'woofunnels-upstroke-one-click-upsell' ),
										'heading'             => esc_attr__( 'Pattern Interrupt', 'woofunnels-upstroke-one-click-upsell' ),
										'products'            => esc_attr__( 'Product', 'woofunnels-upstroke-one-click-upsell' ),
										'reviews'             => esc_attr__( 'Reviews', 'woofunnels-upstroke-one-click-upsell' ),
										'features'            => esc_attr__( 'Features', 'woofunnels-upstroke-one-click-upsell' ),
										'guarantee'           => esc_attr__( 'Guarantee', 'woofunnels-upstroke-one-click-upsell' ),
										'urgency_bar'         => esc_attr__( 'Urgency Bar', 'woofunnels-upstroke-one-click-upsell' ),
										'footer'              => esc_attr__( 'Footer', 'woofunnels-upstroke-one-click-upsell' ),
									),
									'priority'    => 50,
								),
							),
						),
					),
				);

				$this->customizer_data[] = $layout_panel;
				/** PANEL: HEADER */
				$header_panel                 = [];
				$header_panel['wfocu_header'] = array(
					'panel'    => 'yes',
					'data'     => array(
						'priority'    => 20,
						'title'       => 'Header',
						'description' => '',
					),
					'sections' => array(
						'top'          => array(
							'data'   => array(
								'title'    => 'Logo',
								'priority' => 10,
							),
							'fields' => array(
								'ct_logo'         => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Logo', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 10,
								),
								'logo'            => array(
									'type'          => 'image',
									'label'         => __( 'Logo Image', 'woofunnels-upstroke-one-click-upsell' ),
									'priority'      => 20,
									'transport'     => 'postMessage',
									'wfocu_partial' => array(
										'elem'                => '.wfocu-page-header-section .wfocu-page-logo',
										'container_inclusive' => false,
									),
								),
								'logo_width'      => array(
									'type'            => 'slider',
									'label'           => __( 'Max Width', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 180,
									'choices'         => array(
										'min'  => '32',
										'max'  => '216',
										'step' => '2',
									),
									'transport'       => 'postMessage',
									'priority'        => 30,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'max-width' ),
											'elem' => '.wfocu-page-header-section .wfocu-page-logo img',
										),
									),
								),
								'logo_align'      => array(
									'type'            => 'radio-buttonset',
									'label'           => __( 'Align', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 'wfocu-text-center',
									'choices'         => array(
										'wfocu-text-left'   => 'Left',
										'wfocu-text-center' => 'Center',
									),
									'priority'        => 40,
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'   => 'class',
											'elem'   => '.wfocu-page-header-inner .wfocu-page-logo',
											'remove' => array( 'wfocu-text-center', 'wfocu-text-left' ),
										),
									),
								),
								'page_meta_title' => array(
									'type'            => 'text',
									'label'           => __( 'Page Title', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => esc_attr__( 'Special Offer', 'woofunnels-upstroke-one-click-upsell' ),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'html',
											'elem' => 'title',
										),
									),
									'priority'        => 50,
								),
								'ct_colors'       => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 60,
								),
								'bgcolor'         => array(
									'type'            => 'color',
									'label'           => __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#ffffff',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'priority'        => 70,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'background-color' ),
											'elem' => '.wfocu-page-header-section',
										),
									),
								),
							),
						),
						'progress_bar' => array(
							'data'   => array(
								'title'    => 'Progress Bar',
								'priority' => 20,
							),
							'fields' => array(
								'ct_layout'       => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Layout', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 10,
								),
								'style'           => array(
									'type'      => 'radio-image-full',
									'label'     => __( 'Style', 'woofunnels-upstroke-one-click-upsell' ),
									'default'   => 'style1',
									'choices'   => array(
										'style1' => array(
											'label' => __( 'Style 1', 'woofunnels-upstroke-one-click-upsell' ),
											'path'  => $this->img_path . 'progress_1.svg',
										),
										'style2' => array(
											'label' => __( 'Style 2', 'woofunnels-upstroke-one-click-upsell' ),
											'path'  => $this->img_path . 'progress_2.svg',
										),
									),
									'priority'  => 20,
									'transport' => 'auto',
								),
								'ct_progress'     => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Steps', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 30,
								),
								'step1t'          => array(
									'type'            => 'text',
									'label'           => __( 'Step 1 Text', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => esc_attr__( 'Order Submitted', 'woofunnels-upstroke-one-click-upsell' ),
									'transport'       => 'postMessage',
									'priority'        => 40,
									'wfocu_transport' => array(
										array(
											'type' => 'html',
											'elem' => '.wfocu-progressbar .step1',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_header_progress_bar_style',
											'operator' => '!=',
											'value'    => 'style2',
										),
									),
									'wfocu_partial'   => array(
										'elem' => '.wfocu-progressbar .step1',
									),
								),
								'step2t'          => array(
									'type'            => 'text',
									'label'           => __( 'Step 2 Text', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => esc_attr__( 'Special Offer', 'woofunnels-upstroke-one-click-upsell' ),
									'transport'       => 'postMessage',
									'priority'        => 50,
									'wfocu_transport' => array(
										array(
											'type' => 'html',
											'elem' => '.wfocu-progressbar .step2',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_header_progress_bar_style',
											'operator' => '!=',
											'value'    => 'style2',
										),
									),
								),
								'step3t'          => array(
									'type'            => 'text',
									'label'           => __( 'Step 3 Text', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => esc_attr__( 'Order Receipt', 'woofunnels-upstroke-one-click-upsell' ),
									'transport'       => 'postMessage',
									'priority'        => 60,
									'wfocu_transport' => array(
										array(
											'type' => 'html',
											'elem' => '.wfocu-progressbar .step3',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_header_progress_bar_style',
											'operator' => '!=',
											'value'    => 'style2',
										),
									),
								),
								'percent_stept1t' => array(
									'type'            => 'text',
									'label'           => __( 'Step Text', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => esc_attr__( 'Step 2 of 3: Customize your order', 'woofunnels-upstroke-one-click-upsell' ),
									'transport'       => 'postMessage',
									'priority'        => 70,
									'wfocu_transport' => array(
										array(
											'type' => 'html',
											'elem' => '.wfocu-progressbar .wfocu-current-step-text',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_header_progress_bar_style',
											'operator' => '==',
											'value'    => 'style2',
										),
									),
									'wfocu_partial'   => array(
										'elem' => '.wfocu-progressbar .wfocu-current-step-text',
									),
								),
								'step_fs'         => array(
									'type'            => 'slider',
									'label'           => __( 'Step Font Size', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 18,
									'choices'         => array(
										'min'  => '12',
										'max'  => '32',
										'step' => '1',
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'font-size' ),
											'elem' => '.wfocu-progressbar-style1 .wfocu-progressbar .wfocu-pstep',
										),
										array(
											'type' => 'css',
											'prop' => array( 'font-size' ),
											'elem' => '.wfocu-progressbar-style2 .wfocu-current-step-text',
										),
									),
									'priority'        => 80,

								),
								'percent_val'     => array(
									'type'            => 'slider',
									'label'           => __( 'Progress Percentage', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 57,
									'choices'         => array(
										'min'  => '1',
										'max'  => '100',
										'step' => '1',
									),
									'transport'       => 'postMessage',
									'priority'        => 90,
									'wfocu_transport' => array(
										array(
											'type'     => 'css',
											'prop'     => array( 'width' ),
											'suffix'   => '%',
											'elem'     => '.wfocu-progressbar .wfocu-progress-meter .wfocu-progress-scale',
											'callback' => 'modify_progress_percent_val',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_header_progress_bar_style',
											'operator' => '==',
											'value'    => 'style2',
										),
									),
								),
								'percentage_text' => array(
									'type'            => 'text',
									'label'           => __( 'Progress Bar Text', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => esc_attr__( '{{percentage}} Complete', 'woofunnels-upstroke-one-click-upsell' ),
									'transport'       => 'postMessage',
									'priority'        => 100,
									'wfocu_transport' => array(
										array(
											'type'     => 'html',
											'elem'     => '.wfocu-progressbar .wfocu-progress-meter .wfocu-progress-scale',
											'callback' => 'modify_progress_bar_text',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_header_progress_bar_style',
											'operator' => '==',
											'value'    => 'style2',
										),
									),
								),
								'ct_colors'       => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 110,
								),
								'step_tcolor'     => array(
									'type'            => 'color',
									'label'           => __( 'Step Text Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#494949',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-progressbar-style1 .wfocu-progressbar .wfocu-pstep',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-progressbar-style2 .wfocu-current-step-text',
										),
									),
									'priority'        => 120,
								),
								'base_color'      => array(
									'type'            => 'color',
									'label'           => __( 'Step Base Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#d8d8d8',
									'transport'       => 'postMessage',
									'choices'         => array(
										'alpha' => true,
									),
									'priority'        => 130,
									'wfocu_transport' => array(
										array(
											'type'   => 'css',
											'prop'   => array( 'background-color' ),
											'elem'   => 'body .wfocu-progressbar-style1 .wfocu-pstep',
											'pseudo' => 'before',
										),
										array(
											'type'   => 'css',
											'prop'   => array( 'background-color' ),
											'elem'   => 'body .wfocu-progressbar-style1 .wfocu-pstep',
											'pseudo' => 'after',
										),
										array(
											'type' => 'css',
											'prop' => array( 'background-color' ),
											'elem' => '.wfocu-progressbar-style2  .wfocu-progress-meter',
										),
									),
								),
								'progress_color'  => array(
									'type'            => 'color',
									'label'           => __( 'Step Active Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#53b803',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'   => 'css',
											'prop'   => array( 'background-color' ),
											'elem'   => 'body .wfocu-progressbar-style1 .wfocu-pstep.wfocu-completed',
											'pseudo' => 'after',
										),
										array(
											'type'   => 'css',
											'prop'   => array( 'background-color' ),
											'elem'   => 'body .wfocu-progressbar-style1 .wfocu-pstep.wfocu-completed + .wfocu-pstep',
											'pseudo' => 'before',
										),
										array(
											'type'   => 'css',
											'prop'   => array( 'background-color' ),
											'elem'   => 'body .wfocu-progressbar-style1 .wfocu-pstep.wfocu-active',
											'pseudo' => 'after',
										),
										array(
											'type' => 'css',
											'prop' => array( 'background-color' ),
											'elem' => '.wfocu-progressbar-style2 .wfocu-progress-meter .wfocu-progress-scale',
										),
									),
									'priority'        => 140,
								),
								'border_color'    => array(
									'type'            => 'color',
									'label'           => __( 'Step Border Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#dedede',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'border-color' ),
											'elem' => '.wfocu-progressbar-style2 .wfocu-progress-meter',
										),
									),
									'priority'        => 150,
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_header_progress_bar_style',
											'operator' => '==',
											'value'    => 'style2',
										),
									),
								),
								'bgcolor'         => array(
									'type'            => 'color',
									'label'           => __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#ffffff',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'priority'        => 160,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'background-color' ),
											'elem' => '.wfocu-progressbar-section',
										),
									),
								),
							),
						),
					),
				);

				$this->customizer_data[] = $header_panel;

				/** PANEL: PATTERN INTERRUPT */
				$heading_panel                  = [];
				$heading_panel['wfocu_heading'] = array(
					'panel'    => 'no',
					'data'     => array(
						'priority'    => 30,
						'title'       => 'Pattern Interrupt',
						'description' => '',
					),
					'sections' => array(
						'heading' => array(
							'data'   => array(
								'title'    => 'Pattern Interrupt',
								'priority' => 30,
							),
							'fields' => array(
								'ct_content'     => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Content', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 10,
								),
								'head'           => array(
									'type'          => 'textarea',
									'label'         => __( 'Headline', 'woofunnels-upstroke-one-click-upsell' ),
									'description'   => $merge_tags_description,
									'default'       => __( "Wait <span class='wfocu-highlight'>{{customer_first_name}}</span>! Here's an exclusive offer to complement your order!" ),
									'transport'     => 'postMessage',
									'wfocu_partial' => array(
										'elem' => '.wfocu-top-headings .wfocu-top-heading',
									),
									'priority'      => 20,
								),
								'head_fs'        => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Headline Font Size', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => array(
										'desktop' => 32,
										'tablet'  => 28,
										'mobile'  => 24,
									),
									'input_attrs'     => array(
										'step' => 1,
										'min'  => 12,
										'max'  => 60,
									),
									'units'           => array(
										'px' => 'px',
										'em' => 'em',
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'internal'   => true,
											'type'       => 'css',
											'responsive' => true,
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-top-headings .wfocu-top-heading',
										),
									),
									'priority'        => 30,

								),
								'sub_head'       => array(
									'type'            => 'textarea',
									'label'           => __( 'Sub Headline', 'woofunnels-upstroke-one-click-upsell' ),
									'description'     => $merge_tags_description,
									'default'         => esc_attr__( 'Add this to your order and get 25% discount. We will ship it with other items.', 'woofunnels-upstroke-one-click-upsell' ),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'html',
											'elem' => '.wfocu-top-headings .wfocu-top-sub-heading',
										),
									),
									'priority'        => 40,
								),
								'sub_head_fs'    => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Sub Headline Font Size', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => array(
										'desktop' => 28,
										'tablet'  => 24,
										'mobile'  => 20,
									),
									'input_attrs'     => array(
										'step' => 1,
										'min'  => 12,
										'max'  => 48,
									),
									'units'           => array(
										'px' => 'px',
										'em' => 'em',
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'internal'   => true,
											'type'       => 'css',
											'responsive' => true,
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-top-headings .wfocu-top-sub-heading',
										),
									),
									'priority'        => 50,
								),
								'ct_colors'      => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 60,
								),
								'head_color'     => array(
									'type'            => 'color',
									'label'           => __( 'Headline Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#414349',
									'transport'       => 'postMessage',
									'choices'         => array(
										'alpha' => true,
									),
									'priority'        => 70,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-top-headings .wfocu-top-heading',
										),
									),
								),
								'sub_head_color' => array(
									'type'            => 'color',
									'label'           => __( 'Sub Headline Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#414349',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-top-headings .wfocu-top-sub-heading',
										),
									),
									'priority'        => 80,

								),
								'bgcolor'        => array(
									'type'            => 'color',
									'label'           => __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#efefef',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'priority'        => 90,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'background-color' ),
											'elem' => '.wfocu-header-section',
										),
									),
								),
							),
						),
					),
				);

				$this->customizer_data[] = $heading_panel;

				/** PANEL: BUY BLOCK BLOCK */
				$buy_block_panel                    = [];
				$buy_block_panel['wfocu_buy_block'] = array(
					'panel'    => 'no',
					'data'     => array(
						'priority'    => 50,
						'title'       => 'Global Buy Block',
						'description' => '',
					),
					'sections' => array(
						'buy_block' => array(
							'data'   => array(
								'title'    => 'Buy Block',
								'priority' => 50,
							),
							'fields' => array(
								'ct_layout'           => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Layout', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 10,
								),
								'style'               => array(
									'type'     => 'radio-image-full',
									'label'    => esc_attr__( 'Style', 'woofunnels-upstroke-one-click-upsell' ),
									'default'  => 'style1',
									'choices'  => array(
										'style1' => array(
											'label' => __( 'Style 1', 'woofunnels-upstroke-one-click-upsell' ),
											'path'  => $this->img_path . 'buy_block_1.svg',
										),
										'style2' => array(
											'label' => __( 'Style 2', 'woofunnels-upstroke-one-click-upsell' ),
											'path'  => $this->img_path . 'buy_block_2.svg',
										),
									),
									'priority' => 20,
								),
								'ct_buy_block'        => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Buy Block', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 30,
								),
								'accept_btn_text1'    => array(
									'type'            => 'textarea',
									'description'     => $merge_tags_description,
									'label'           => esc_attr__( 'Accept Button Text', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => esc_attr__( 'Yes, Add This To My Order', 'woofunnels-upstroke-one-click-upsell' ),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'html',
											'elem' => '.wfocu-buy-block-style1 .wfocu-accept-button .wfocu-text',
										),
										array(
											'type' => 'html',
											'elem' => '.wfocu-buy-block-style2 .wfocu-accept-button .wfocu-text',
										),
									),
									'wfocu_partial'   => array(
										'elem' => '.wfocu-buy-block-style1 .wfocu-accept-button .wfocu-text',
									),
									'priority'        => 40,
								),
								'accept_btn_text1_fs' => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Accept Button Text Font Size', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => array(
										'desktop' => 25,
										'tablet'  => 25,
										'mobile'  => 16,
									),
									'input_attrs'     => array(
										'step' => 1,
										'min'  => 12,
										'max'  => 48,
									),
									'units'           => array(
										'px' => 'px',
										'em' => 'em',
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'internal'   => true,
											'responsive' => true,
											'type'       => 'css',
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-buy-block-style1 .wfocu-accept-button .wfocu-text',
										),
										array(
											'internal'   => true,
											'responsive' => true,
											'type'       => 'css',
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-buy-block-style1 .wfocu-accept-button .wfocu-icon',
										),
										array(
											'internal'   => true,
											'responsive' => true,
											'type'       => 'css',
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-buy-block-style2 .wfocu-accept-button .wfocu-text',
										),
										array(
											'internal'   => true,
											'responsive' => true,
											'type'       => 'css',
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-buy-block-style2 .wfocu-accept-button .wfocu-icon',
										),
									),
									'priority'        => 50,
								),
								'accept_btn_text2'    => array(
									'type'            => 'textarea',
									'description'     => $merge_tags_description,
									'label'           => esc_attr__( 'Accept Button Sub Text', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => esc_attr__( 'We will ship it out in same package.', 'woofunnels-upstroke-one-click-upsell' ),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'html',
											'elem' => '.wfocu-buy-block-style1 .wfocu-accept-button .wfocu-btn-sub',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_style',
											'value'    => 'style1',
											'operator' => '==',
										),
									),
									'priority'        => 60,
								),
								'accept_btn_text2_fs' => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Accept Button Sub Text Font Size', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => array(
										'desktop' => 16,
										'tablet'  => 16,
										'mobile'  => 13,
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
									'wfocu_transport' => array(
										array(
											'internal'   => true,
											'responsive' => true,
											'type'       => 'css',
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-buy-block-style1 .wfocu-accept-button .wfocu-btn-sub',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_style',
											'value'    => 'style1',
											'operator' => '==',
										),
									),
									'priority'        => 70,
								),

								/** style 2 fields */
								'skip_btn_text'       => array(
									'type'            => 'textarea',
									'description'     => $merge_tags_description,
									'label'           => esc_attr__( 'Decline Button Text', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => esc_attr__( 'No Thanks, I don\'t want this' ),
									'transport'       => 'postMessage',
									'wfocu_partial'   => array(
										'elem' => '.wfocu-buy-block-style2 .wfocu-skip-button .wfocu-text',
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_style',
											'value'    => 'style2',
											'operator' => '==',
										),
									),
									'priority'        => 80,
								),
								'skip_btn_text_fs'    => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Decline Button Text Font Size', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => array(
										'desktop' => 25,
										'tablet'  => 25,
										'mobile'  => 22,
									),
									'input_attrs'     => array(
										'step' => 1,
										'min'  => 12,
										'max'  => 48,
									),
									'units'           => array(
										'px' => 'px',
										'em' => 'em',
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'internal'   => true,
											'responsive' => true,
											'type'       => 'css',
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-buy-block-style2 .wfocu-skip-button .wfocu-text',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_style',
											'value'    => 'style2',
											'operator' => '==',
										),
									),
									'priority'        => 90,
								),
								/** style 2 fields */

								'click_trigger_text'    => array(
									'type'            => 'textarea',
									'description'     => $merge_tags_description,
									'label'           => esc_attr__( 'Text Below Button', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => __( 'We will ship it out in same package.', 'woofunnels-upstroke-one-click-upsell' ),
									'transport'       => 'postMessage',
									'priority'        => 100,
									'wfocu_transport' => array(
										array(
											'type' => 'html',
											'elem' => '.wfocu-buy-block .wfocu-click-trigger-text ',
										),
									),
								),
								'click_trigger_text_fs' => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Text Below Button Font Size', 'woofunnels-upstroke-one-click-upsell' ),
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
									'wfocu_transport' => array(
										array(
											'internal'   => true,
											'responsive' => true,
											'type'       => 'css',
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-buy-block .wfocu-click-trigger-text ',
										),
									),
									'priority'        => 110,
								),
								'skip_offer_text'       => array(
									'type'            => 'textarea',
									'description'     => $merge_tags_description,
									'label'           => esc_attr__( 'Decline Offer Link Text', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => esc_attr__( 'No thanks, I don’t want to take advantage of this one-time offer >', 'woofunnels-upstroke-one-click-upsell' ),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'html',
											'elem' => '.wfocu-buy-block-style1 .wfocu-skip-offer-link',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_style',
											'value'    => 'style1',
											'operator' => '==',
										),
									),
									'priority'        => 120,
								),
								'skip_offer_text_fs'    => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Decline Offer Link Text Font Size', 'woofunnels-upstroke-one-click-upsell' ),
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
									'wfocu_transport' => array(
										array(
											'internal'   => true,
											'responsive' => true,
											'type'       => 'css',
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-buy-block-style1 .wfocu-skip-offer-link',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_style',
											'value'    => 'style1',
											'operator' => '==',
										),
									),
									'priority'        => 130,
								),
								'skip_offer_btn_style'  => array(
									'type'            => 'checkbox',
									'label'           => esc_attr__( 'Dispay Decline Offer Link  As A Button', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => false,
									'priority'        => 140,
									'wfocu_transport' => array(
										array(
											'type'   => 'class',
											'remove' => array( 'wfocu-skip-offer-btn' ),
											'elem'   => '.wfocu-buy-block-style1 .wfocu-skip-offer-link',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_style',
											'value'    => 'style1',
											'operator' => '==',
										),
									),
								),
								'ct_payment_icons'      => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Payment Icons', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 150,
								),
								'display_payment_icon'  => array(
									'type'        => 'checkbox',
									'label'       => esc_attr__( 'Display Payment Icons', 'woofunnels-upstroke-one-click-upsell' ),
									'description' => esc_attr__( 'Manage settings from Other->Payment Icons Section.', 'woofunnels-upstroke-one-click-upsell' ),
									'default'     => true,
									'priority'    => 160,
								),
								'ct_advanced'           => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Advanced', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 170,
								),
								'btn_type'              => array(
									'type'            => 'radio-buttonset',
									'label'           => esc_attr__( 'Button Style', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 'wfocu-btn-full',
									'choices'         => array(
										'wfocu-btn-full'     => __( 'Full', 'woofunnels-upstroke-one-click-upsell' ),
										'wfocu-btn-flexible' => __( 'Flexible', 'woofunnels-upstroke-one-click-upsell' ),
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'   => 'class',
											'remove' => array( 'wfocu-btn-full', 'wfocu-btn-flexible' ),
											'elem'   => '.wfocu-buy-block  .wfocu-button',
										),
									),
									'priority'        => 180,
								),
								'btn_width'             => array(
									'type'            => 'slider',
									'label'           => esc_attr__( 'Button Width (%)', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 100,
									'choices'         => array(
										'min'  => 10,
										'max'  => 100,
										'step' => 5,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'   => 'css',
											'prop'   => array( 'width' ),
											'suffix' => '%',
											'elem'   => '.wfocu-buy-block-style1  .wfocu-button',
										),
										array(
											'type'   => 'css',
											'prop'   => array( 'width' ),
											'suffix' => '%',
											'elem'   => '.wfocu-buy-block-style2  .wfocu-button',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_btn_type',
											'value'    => 'wfocu-btn-flexible',
											'operator' => '==',
										),
									),
									'priority'        => 190,
								),
								'btn_vertical_gap'      => array(
									'type'            => 'number',
									'label'           => esc_attr__( 'Button Top/Bottom Padding', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 15,
									'choices'         => array(
										'min'  => 1,
										'max'  => 200,
										'step' => 1,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'padding-top' ),
											'elem' => '.wfocu-buy-block .wfocu-button ',
										),
										array(
											'type' => 'css',
											'prop' => array( 'padding-bottom' ),
											'elem' => '.wfocu-buy-block .wfocu-button ',
										),
									),
									'priority'        => 200,
								),
								'btn_horizontal_gap'    => array(
									'type'            => 'number',
									'label'           => esc_attr__( 'Button Left/Right Padding', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 30,
									'choices'         => array(
										'min'  => 1,
										'max'  => 200,
										'step' => 1,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'padding-left' ),
											'elem' => '.wfocu-buy-block .wfocu-button ',
										),
										array(
											'type' => 'css',
											'prop' => array( 'padding-right' ),
											'elem' => '.wfocu-buy-block .wfocu-button ',
										),
									),
									'priority'        => 210,
								),
								'btn_radius'            => array(
									'type'            => 'number',
									'label'           => esc_attr__( 'Button Radius', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 3,
									'choices'         => array(
										'min'  => 0,
										'max'  => 200,
										'step' => 1,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'border-radius', '-moz-border-radius', '-webkit-border-radius', '-ms-border-radius', '-o-border-radius' ),
											'elem' => '.wfocu-buy-block .wfocu-button ',
										),
									),
									'priority'        => 220,
								),
								'show_accept_btn_icon'  => array(
									'type'        => 'checkbox',
									'label'       => esc_attr__( 'Enable Accept Button Icon', 'woofunnels-upstroke-one-click-upsell' ),
									'description' => esc_attr__( '', 'woofunnels-upstroke-one-click-upsell' ),
									'default'     => false,
									'priority'    => 230,
								),
								'accept_btn_icon'       => array(
									'type'            => 'radio-icon',
									'label'           => esc_attr__( 'Accept Button Icon', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 'dashicons-cart',
									'transport'       => 'refresh',
									'choices'         => array(
										'dashicons-cart'             => '<span class="dashicons dashicons-cart"></span>',
										'dashicons-yes'              => '<span class="dashicons dashicons-yes"></span>',
										'dashicons-arrow-up'         => '<span class="dashicons dashicons-arrow-up"></span>',
										'dashicons-arrow-down'       => '<span class="dashicons dashicons-arrow-down"></span>',
										'dashicons-arrow-right'      => '<span class="dashicons dashicons-arrow-right"></span>',
										'dashicons-arrow-left'       => '<span class="dashicons dashicons-arrow-left"></span>',
										'dashicons-arrow-up-alt'     => '<span class="dashicons dashicons-arrow-up-alt"></span>',
										'dashicons-arrow-down-alt'   => '<span class="dashicons dashicons-arrow-down-alt"></span>',
										'dashicons-arrow-right-alt'  => '<span class="dashicons dashicons-arrow-right-alt"></span>',
										'dashicons-arrow-left-alt'   => '<span class="dashicons dashicons-arrow-left-alt"></span>',
										'dashicons-arrow-up-alt2'    => '<span class="dashicons dashicons-arrow-up-alt2"></span>',
										'dashicons-arrow-down-alt2'  => '<span class="dashicons dashicons-arrow-down-alt2"></span>',
										'dashicons-arrow-right-alt2' => '<span class="dashicons dashicons-arrow-right-alt2"></span>',
										'dashicons-arrow-left-alt2'  => '<span class="dashicons dashicons-arrow-left-alt2"></span>',
										'dashicons-heart'            => '<span class="dashicons dashicons-heart"></span>',
										'dashicons-star-filled'      => '<span class="dashicons dashicons-star-filled"></span>',
										'dashicons-plus-alt'         => '<span class="dashicons dashicons-plus-alt"></span>',
										'dashicons-awards'           => '<span class="dashicons dashicons-awards"></span>',
										'dashicons-shield'           => '<span class="dashicons dashicons-shield"></span>',
										'dashicons-shield-alt'       => '<span class="dashicons dashicons-shield-alt"></span>',
										'dashicons-thumbs-up'        => '<span class="dashicons dashicons-thumbs-up"></span>',
										'dashicons-thumbs-down'      => '<span class="dashicons dashicons-thumbs-down"></span>',
										'dashicons-smiley'           => '<span class="dashicons dashicons-smiley"></span>',
										'dashicons-tickets-alt'      => '<span class="dashicons dashicons-tickets-alt"></span>',
										'dashicons-tag'              => '<span class="dashicons dashicons-tag"></span>',
										'dashicons-cloud'            => '<span class="dashicons dashicons-cloud"></span>',
										'dashicons-controls-forward' => '<span class="dashicons dashicons-controls-forward"></span>',
										'dashicons-controls-back'    => '<span class="dashicons dashicons-controls-back"></span>',
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_show_accept_btn_icon',
											'value'    => true,
											'operator' => '==',
										),
									),
									'priority'        => 240,
								),
								'show_skip_btn_icon'    => array(
									'type'            => 'checkbox',
									'label'           => esc_attr__( 'Enable Decline Button Icon', 'woofunnels-upstroke-one-click-upsell' ),
									'description'     => '',
									'default'         => false,
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_style',
											'value'    => 'style2',
											'operator' => '==',
										),
									),
									'priority'        => 250,
								),
								'skip_btn_icon'         => array(
									'type'            => 'radio-icon',
									'label'           => esc_attr__( 'Decline Button Icon', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 'dashicons-cart',
									'choices'         => array(
										'dashicons-cart'             => __( '<span class="dashicons dashicons-cart"></span>', 'woofunnels-upstroke-one-click-upsell' ),
										'dashicons-yes'              => __( '<span class="dashicons dashicons-yes"></span>', 'woofunnels-upstroke-one-click-upsell' ),
										'dashicons-arrow-up'         => __( '<span class="dashicons dashicons-arrow-up"></span>', 'woofunnels-upstroke-one-click-upsell' ),
										'dashicons-arrow-down'       => __( '<span class="dashicons dashicons-arrow-down"></span>', 'woofunnels-upstroke-one-click-upsell' ),
										'dashicons-arrow-right'      => __( '<span class="dashicons dashicons-arrow-right"></span>', 'woofunnels-upstroke-one-click-upsell' ),
										'dashicons-arrow-left'       => __( '<span class="dashicons dashicons-arrow-left"></span>', 'woofunnels-upstroke-one-click-upsell' ),
										'dashicons-arrow-up-alt'     => __( '<span class="dashicons 
dashicons-arrow-up-alt"></span>', 'woofunnels-upstroke-one-click-upsell' ),
										'dashicons-arrow-down-alt'   => __( '<span class="dashicons dashicons-arrow-down-alt"></span>', 'woofunnels-upstroke-one-click-upsell' ),
										'dashicons-arrow-right-alt'  => __( '<span class="dashicons dashicons-arrow-right-alt"></span>', 'woofunnels-upstroke-one-click-upsell' ),
										'dashicons-arrow-left-alt'   => __( '<span class="dashicons dashicons-arrow-left-alt"></span>', 'woofunnels-upstroke-one-click-upsell' ),
										'dashicons-arrow-up-alt2'    => __( '<span class="dashicons 
dashicons-arrow-up-alt2"></span>', 'woofunnels-upstroke-one-click-upsell' ),
										'dashicons-arrow-down-alt2'  => __( '<span class="dashicons dashicons-arrow-down-alt2"></span>', 'woofunnels-upstroke-one-click-upsell' ),
										'dashicons-arrow-right-alt2' => __( '<span class="dashicons dashicons-arrow-right-alt2"></span>', 'woofunnels-upstroke-one-click-upsell' ),
										'dashicons-arrow-left-alt2'  => __( '<span class="dashicons dashicons-arrow-left-alt2"></span>', 'woofunnels-upstroke-one-click-upsell' ),
										'dashicons-heart'            => __( '<span class="dashicons dashicons-heart"></span>', 'woofunnels-upstroke-one-click-upsell' ),
										'dashicons-star-filled'      => __( '<span class="dashicons dashicons-star-filled"></span>', 'woofunnels-upstroke-one-click-upsell' ),
										'dashicons-plus-alt'         => __( '<span class="dashicons dashicons-plus-alt"></span>', 'woofunnels-upstroke-one-click-upsell' ),
										'dashicons-awards'           => __( '<span class="dashicons dashicons-awards"></span>', 'woofunnels-upstroke-one-click-upsell' ),
										'dashicons-shield'           => __( '<span class="dashicons dashicons-shield"></span>', 'woofunnels-upstroke-one-click-upsell' ),
										'dashicons-shield-alt'       => __( '<span class="dashicons dashicons-shield-alt"></span>', 'woofunnels-upstroke-one-click-upsell' ),
										'dashicons-thumbs-up'        => __( '<span class="dashicons dashicons-thumbs-up"></span>', 'woofunnels-upstroke-one-click-upsell' ),
										'dashicons-thumbs-down'      => __( '<span class="dashicons dashicons-thumbs-down"></span>', 'woofunnels-upstroke-one-click-upsell' ),
										'dashicons-smiley'           => __( '<span class="dashicons dashicons-smiley"></span>', 'woofunnels-upstroke-one-click-upsell' ),
										'dashicons-tickets-alt'      => __( '<span class="dashicons dashicons-tickets-alt"></span>', 'woofunnels-upstroke-one-click-upsell' ),
										'dashicons-tag'              => __( '<span class="dashicons dashicons-tag"></span>', 'woofunnels-upstroke-one-click-upsell' ),
										'dashicons-cloud'            => __( '<span class="dashicons dashicons-cloud"></span>', 'woofunnels-upstroke-one-click-upsell' ),
										'dashicons-controls-forward' => __( '<span class="dashicons dashicons-controls-forward"></span>', 'woofunnels-upstroke-one-click-upsell' ),
										'dashicons-controls-back'    => __( '<span class="dashicons dashicons-controls-back"></span>', 'woofunnels-upstroke-one-click-upsell' ),

									),
									'transport'       => 'refresh',
									'priority'        => 260,
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_style',
											'value'    => 'style2',
											'operator' => '==',
										),
										array(
											'setting'  => 'wfocu_buy_block_buy_block_show_skip_btn_icon',
											'value'    => true,
											'operator' => '==',
										),
									),
								),
								'btn_effect'            => array(
									'type'            => 'select',
									'label'           => esc_attr__( 'Button Hover Effect', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 'no-effect',
									'choices'         => array(
										'none'                        => __( 'No effect', 'woofunnels-upstroke-one-click-upsell' ),
										'wfocu-btn-pulse-grow'        => __( 'Pulse Grow', 'woofunnels-upstroke-one-click-upsell' ),
										'wfocu-btn-bounce-in'         => __( 'Bounce In', 'woofunnels-upstroke-one-click-upsell' ),
										'wfocu-btn-wobble-horizontal' => __( 'Wobble', 'woofunnels-upstroke-one-click-upsell' ),
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'   => 'class',
											'remove' => array( 'wfocu-btn-pulse-grow', 'wfocu-btn-bounce-in', 'wfocu-btn-wobble-horizontal' ),
											'elem'   => '.wfocu-buy-block-style1 .wfocu-accept-button',
										),
										array(
											'type'   => 'class',
											'remove' => array( 'wfocu-btn-pulse-grow', 'wfocu-btn-bounce-in', 'wfocu-btn-wobble-horizontal' ),
											'elem'   => '.wfocu-buy-block-style2 .wfocu-accept-button',
										),
										array(
											'type'   => 'class',
											'remove' => array( 'wfocu-btn-pulse-grow', 'wfocu-btn-bounce-in', 'wfocu-btn-wobble-horizontal' ),
											'elem'   => '.wfocu-buy-block-style2 .wfocu-skip-button',
										),
									),
									'priority'        => 270,
								),

								'ct_accept_btn' => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Accept Button Colors', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 280,
								),

								'ct_accept_btn_state'            => array(
									'type'      => 'radio-buttonset',
									'label'     => '',
									'default'   => 'normal',
									'choices'   => array(
										'normal' => __( 'Normal', 'woofunnels-upstroke-one-click-upsell' ),
										'hover'  => __( 'Hover', 'woofunnels-upstroke-one-click-upsell' ),
									),
									'transport' => 'postMessage',
									'priority'  => 290,
								),
								'accept_btn_bg_color'            => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#70dc1d',
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'     => 'css',
											'prop'     => array( 'background-color' ),
											'elem'     => '.wfocu-buy-block-style1 .wfocu-accept-button',
											'internal' => true,
										),
										array(
											'type'     => 'css',
											'prop'     => array( 'background-color' ),
											'elem'     => '.wfocu-buy-block-style2 .wfocu-accept-button',
											'internal' => true,
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_ct_accept_btn_state',
											'value'    => 'normal',
											'operator' => '==',
										),
									),
									'priority'        => 300,
								),
								'accept_btn_text_color'          => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#ffffff',
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'     => 'css',
											'prop'     => array( 'color' ),
											'elem'     => '.wfocu-buy-block-style1 .wfocu-accept-button',
											'internal' => true,
										),
										array(
											'type'     => 'css',
											'prop'     => array( 'color' ),
											'elem'     => '.wfocu-buy-block-style2 .wfocu-accept-button',
											'internal' => true,
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_ct_accept_btn_state',
											'value'    => 'normal',
											'operator' => '==',
										),
									),
									'priority'        => 310,
								),
								'accept_btn_bottom_shadow_color' => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Bottom Shadow Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#00a300',
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'   => 'css',
											'prop'   => array( 'box-shadow' ),
											'prefix' => '0px 4px 0px ',
											'elem'   => '.wfocu-buy-block-style1 .wfocu-accept-button',
										),
										array(
											'type'   => 'css',
											'prop'   => array( 'box-shadow' ),
											'prefix' => '0px 4px 0px ',
											'elem'   => '.wfocu-buy-block-style2 .wfocu-accept-button',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_ct_accept_btn_state',
											'value'    => 'normal',
											'operator' => '==',
										),
									),
									'priority'        => 320,
								),
								'accept_btn_bg_color_hover'      => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#89e047',
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'  => 'css',
											'hover' => true,
											'prop'  => array( 'background-color' ),
											'elem'  => '.wfocu-buy-block-style1 .wfocu-accept-button',
										),
										array(
											'type'  => 'css',
											'hover' => true,
											'prop'  => array( 'background-color' ),
											'elem'  => '.wfocu-buy-block-style2 .wfocu-accept-button',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_ct_accept_btn_state',
											'value'    => 'hover',
											'operator' => '==',
										),
									),
									'priority'        => 330,
								),
								'accept_btn_text_color_hover'    => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#ffffff',
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'  => 'css',
											'prop'  => array( 'color' ),
											'elem'  => '.wfocu-buy-block-style1 .wfocu-accept-button',
											'hover' => true,
										),
										array(
											'type'  => 'css',
											'prop'  => array( 'color' ),
											'elem'  => '.wfocu-buy-block-style2 .wfocu-accept-button',
											'hover' => true,
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_ct_accept_btn_state',
											'value'    => 'hover',
											'operator' => '==',
										),
									),
									'priority'        => 340,
								),
								'click_trigger_text_color'       => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Text Below Button Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#414349',
									'transport'       => 'postMessage',
									'priority'        => 350,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-buy-block .wfocu-click-trigger-text ',
										),
									),
								),
								'ct_skip_btn'                    => array(
									'type'            => 'custom',
									'default'         => '<div class="options-title-divider">' . esc_html__( 'Decline Button Colors', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority'        => 360,
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_style',
											'value'    => 'style2',
											'operator' => '==',
										),
									),
								),
								'ct_skip_btn_state'              => array(
									'type'            => 'radio-buttonset',
									'label'           => '',
									'default'         => 'normal',
									'choices'         => array(
										'normal' => __( 'Normal', 'woofunnels-upstroke-one-click-upsell' ),
										'hover'  => __( 'Hover', 'woofunnels-upstroke-one-click-upsell' ),
									),
									'transport'       => 'postMessage',
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_style',
											'value'    => 'style2',
											'operator' => '==',
										),
									),
									'priority'        => 370,
								),
								'skip_btn_bg_color'              => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#d52011',
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'     => 'css',
											'prop'     => array( 'background-color' ),
											'elem'     => '.wfocu-buy-block-style2 .wfocu-skip-button',
											'internal' => true,
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_style',
											'value'    => 'style2',
											'operator' => '==',
										),
										array(
											'setting'  => 'wfocu_buy_block_buy_block_ct_skip_btn_state',
											'value'    => 'normal',
											'operator' => '==',
										),
									),
									'priority'        => 380,
								),
								'skip_btn_text_color'            => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#ffffff',
									'transport'       => 'postMessage',
									'priority'        => 390,
									'wfocu_transport' => array(
										array(
											'type'     => 'css',
											'prop'     => array( 'color' ),
											'elem'     => '.wfocu-buy-block-style2 .wfocu-skip-button',
											'internal' => true,
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_style',
											'value'    => 'style2',
											'operator' => '==',
										),
										array(
											'setting'  => 'wfocu_buy_block_buy_block_ct_skip_btn_state',
											'value'    => 'normal',
											'operator' => '==',
										),
									),

								),
								'skip_btn_bottom_shadow_color'   => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Bottom Shadow Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#890e04',
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'   => 'css',
											'prop'   => array( 'box-shadow' ),
											'prefix' => '0px 4px 0px ',
											'elem'   => '.wfocu-buy-block-style2 .wfocu-skip-button',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_style',
											'value'    => 'style2',
											'operator' => '==',
										),
										array(
											'setting'  => 'wfocu_buy_block_buy_block_ct_skip_btn_state',
											'value'    => 'normal',
											'operator' => '==',
										),
									),
									'priority'        => 400,
								),
								'skip_btn_bg_color_hover'        => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#ab251a',
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'  => 'css',
											'prop'  => array( 'background-color' ),
											'hover' => true,
											'elem'  => '.wfocu-buy-block-style2 .wfocu-skip-button',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_style',
											'value'    => 'style2',
											'operator' => '==',
										),
										array(
											'setting'  => 'wfocu_buy_block_buy_block_ct_skip_btn_state',
											'value'    => 'hover',
											'operator' => '==',
										),
									),
									'priority'        => 410,
								),
								'skip_btn_text_color_hover'      => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#ffffff',
									'transport'       => 'postMessage',
									'priority'        => 420,
									'wfocu_transport' => array(
										array(
											'type'  => 'css',
											'prop'  => array( 'color' ),
											'hover' => true,
											'elem'  => '.wfocu-buy-block-style2 .wfocu-skip-button',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_style',
											'value'    => 'style2',
											'operator' => '==',
										),
										array(
											'setting'  => 'wfocu_buy_block_buy_block_ct_skip_btn_state',
											'value'    => 'hover',
											'operator' => '==',
										),
									),
								),
								'ct_skip_offer'                  => array(
									'type'            => 'custom',
									'default'         => '<div class="options-title-divider">' . esc_html__( 'Decline Offer Colors', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority'        => 430,
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_style',
											'value'    => 'style1',
											'operator' => '==',
										),
									),
								),
								'ct_skip_offer_state'            => array(
									'type'            => 'radio-buttonset',
									'label'           => '',
									'default'         => 'normal',
									'choices'         => array(
										'normal' => __( 'Normal', 'woofunnels-upstroke-one-click-upsell' ),
										'hover'  => __( 'Hover', 'woofunnels-upstroke-one-click-upsell' ),
									),
									'transport'       => 'postMessage',
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_style',
											'value'    => 'style1',
											'operator' => '==',
										),
									),
									'priority'        => 440,
								),
								'skip_offer_btn_bg_color'        => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Button Background Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#dddddd',
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'     => 'css',
											'prop'     => array( 'background-color' ),
											'elem'     => '.wfocu-buy-block-style1 .wfocu-skip-offer-btn',
											'internal' => true,
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_style',
											'value'    => 'style1',
											'operator' => '==',
										),
										array(
											'setting'  => 'wfocu_buy_block_buy_block_skip_offer_btn_style',
											'value'    => true,
											'operator' => '==',
										),
										array(
											'setting'  => 'wfocu_buy_block_buy_block_ct_skip_offer_state',
											'value'    => 'normal',
											'operator' => '==',
										),
									),
									'priority'        => 450,
								),
								'skip_offer_text_color'          => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#777777',
									'transport'       => 'postMessage',
									'priority'        => 460,
									'wfocu_transport' => array(
										array(
											'type'     => 'css',
											'prop'     => array( 'color' ),
											'elem'     => '.wfocu-buy-block-style1 .wfocu-skip-offer-link',
											'internal' => true,
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_style',
											'value'    => 'style1',
											'operator' => '==',
										),
										array(
											'setting'  => 'wfocu_buy_block_buy_block_ct_skip_offer_state',
											'value'    => 'normal',
											'operator' => '==',
										),
									),
								),
								'skip_offer_btn_bg_color_hover'  => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Button Background Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#a3a3a3',
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'  => 'css',
											'prop'  => array( 'background-color' ),
											'hover' => true,
											'elem'  => '.wfocu-buy-block-style1 .wfocu-skip-offer-btn',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_style',
											'value'    => 'style1',
											'operator' => '==',
										),
										array(
											'setting'  => 'wfocu_buy_block_buy_block_skip_offer_btn_style',
											'value'    => true,
											'operator' => '==',
										),
										array(
											'setting'  => 'wfocu_buy_block_buy_block_ct_skip_offer_state',
											'value'    => 'hover',
											'operator' => '==',
										),
									),
									'priority'        => 470,
								),
								'skip_offer_text_color_hover'    => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#9e9e9e',
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'  => 'css',
											'prop'  => array( 'color' ),
											'elem'  => '.wfocu-buy-block-style1 .wfocu-skip-offer-link',
											'hover' => true,
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_style',
											'value'    => 'style1',
											'operator' => '==',
										),
										array(
											'setting'  => 'wfocu_buy_block_buy_block_ct_skip_offer_state',
											'value'    => 'hover',
											'operator' => '==',
										),
									),
									'priority'        => 480,
								),

							),
						),
					),
				);

				$this->customizer_data[] = $buy_block_panel;
				/** PANEL: FEATURES */
				$feature_panel                   = [];
				$feature_panel['wfocu_features'] = array(
					'panel'    => 'no',
					'data'     => array(
						'priority'    => 60,
						'title'       => 'Features',
						'description' => '',
					),
					'sections' => array(
						'reasons' => array(
							'data'   => array(
								'title'    => 'Features',
								'priority' => 60,
							),
							'fields' => array(
								'ct_headings'       => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Heading', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 10,
								),
								'heading'           => array(
									'type'          => 'textarea',
									'label'         => __( 'Heading', 'woofunnels-upstroke-one-click-upsell' ),
									'description'   => $merge_tags_description,
									'default'       => esc_attr__( 'Add this awesome item to your cart with all its amazing benefits', 'woofunnels-upstroke-one-click-upsell' ),
									'transport'     => 'postMessage',
									'wfocu_partial' => array(
										'elem' => '.wfocu-feature-section .wfocu-section-headings .wfocu-heading',
									),
									'priority'      => 20,
								),
								'sub_heading'       => array(
									'type'            => 'textarea',
									'label'           => __( 'Sub Heading', 'woofunnels-upstroke-one-click-upsell' ),
									'description'     => $merge_tags_description,
									'default'         => esc_attr__( 'Order Now and You’ll Get..', 'woofunnels-upstroke-one-click-upsell' ),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'html',
											'elem' => '.wfocu-feature-section .wfocu-section-headings .wfocu-sub-heading',
										),
									),
									'priority'        => 30,
								),
								'ct_features'       => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Features', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 40,
								),
								'reasons'           => array(
									'type'      => 'repeater',
									'label'     => esc_attr__( 'Features', 'woofunnels-upstroke-one-click-upsell' ),
									'priority'  => 50,
									'row_label' => array(
										'type'  => 'text',
										'value' => esc_attr__( 'Feature', 'woofunnels-upstroke-one-click-upsell' ),
									),
									'default'   => array(
										array(
											'message' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
										),
										array(
											'message' => 'Maecenas et nunc lobortis, suscipit massa non, eleifend elit. Aenean bibendum interdum massa, eu viverra ipsum ultrices sed.',
										),
										array(
											'message' => 'Lorem ipsum dolor sit amet',
										),
										array(
											'message' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas et nunc lobortis, suscipit massa non, eleifend elit. Aenean bibendum interdum massa, eu viverra ipsum ultrices sed.',
										),
										array(
											'message' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas et nunc lobortis, suscipit massa non, eleifend elit. Aenean bibendum interdum massa, eu viverra ipsum ultrices sed.',
										),
									),
									'fields'    => array(
										'message' => array(
											'type'    => 'textarea',
											'label'   => __( 'Feature Text', 'woofunnels-upstroke-one-click-upsell' ),
											'default' => '',
										),
									),
								),
								'ct_desc'           => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Additional Description', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 60,
								),
								'additional_text'   => array(
									'type'            => 'textarea',
									'label'           => __( 'Additional Text', 'woofunnels-upstroke-one-click-upsell' ),
									'description'     => $merge_tags_description,
									'default'         => esc_attr__( 'Handle an objection or answer an important question. Use this text section here to let them know why they should click the button below.', 'woofunnels-upstroke-one-click-upsell' ),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'       => 'html',
											'line_break' => 0,
											'elem'       => '.wfocu-feature-section .wfocu-content-area',
										),
									),
									'priority'        => 70,
								),
								'additional_talign' => array(
									'type'            => 'radio-buttonset',
									'label'           => __( 'Align', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 'wfocu-text-center',
									'choices'         => array(
										'wfocu-text-left'   => 'Left',
										'wfocu-text-center' => 'Center',
										'wfocu-text-right'  => 'Right',
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'   => 'class',
											'elem'   => '.wfocu-feature-section .wfocu-content-area',
											'remove' => array( 'wfocu-text-left', 'wfocu-text-center', 'wfocu-text-right' ),
										),
									),
									'priority'        => 80,
								),
								'ct_buy_block'      => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Buy Block', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 90,
								),
								'display_buy_block' => array(
									'type'        => 'checkbox',
									'label'       => esc_attr__( 'Display Buy Block', 'woofunnels-upstroke-one-click-upsell' ),
									'description' => esc_attr__( 'Enable if you want to display buy block.', 'woofunnels-upstroke-one-click-upsell' ),
									'default'     => true,
									'priority'    => 100,
								),
								'ct_colors'         => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 110,
								),
								'icon_color'        => array(
									'type'     => 'color',
									'label'    => esc_attr__( 'Icon Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'  => '#70dc1d',
									'choices'  => array(
										'alpha' => true,
									),
									'priority' => 120,
								),
								'bg_color'          => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#efefef',
									'choices'         => array(
										'alpha' => true,
									),
									'priority'        => 130,
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'background-color' ),
											'elem' => '.wfocu-feature-section',
										),
									),
								),
								'override_global'   => array(
									'type'        => 'checkbox',
									'label'       => esc_attr__( 'Override Global Color Settings', 'woofunnels-upstroke-one-click-upsell' ),
									'description' => esc_attr__( 'Enable if you want to change Heading, Sub Heading, Content color specifically for this section.', 'woofunnels-upstroke-one-click-upsell' ),
									'default'     => false,
									'priority'    => 140,
								),
								'heading_color'     => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Heading Text Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#414349',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-feature-section .wfocu-section-headings .wfocu-heading',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_features_reasons_override_global',
											'operator' => '==',
											'value'    => true,
										),
									),
									'priority'        => 150,
								),
								'sub_heading_color' => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Sub Heading Text Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#414349',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-feature-section .wfocu-section-headings .wfocu-sub-heading',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_features_reasons_override_global',
											'operator' => '==',
											'value'    => true,
										),
									),
									'priority'        => 160,
								),
								'content_color'     => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Content Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#414349',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-feature-section .wfocu-feature-sec-wrap p',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-feature-section .wfocu-feature-sec-wrap ul li',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-feature-section .wfocu-feature-sec-wrap ol li',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-feature-section .wfocu-content-area p',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-feature-section .wfocu-content-area ul li',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-feature-section .wfocu-content-area ol li',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-feature-section .wfocu-product-attr-wrapper',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_features_reasons_override_global',
											'operator' => '==',
											'value'    => true,
										),
									),
									'priority'        => 170,
								),
							),
						),
					),
				);
				if ( true === $this->variation_field ) {
					$feature_panel['wfocu_features']['sections']['reasons']['fields']['display_buy_block_variation'] = array(
						'type'            => 'checkbox',
						'label'           => esc_attr__( 'Display Product Variation Selection', 'woofunnels-upstroke-one-click-upsell' ),
						'description'     => esc_attr__( 'Enable if you want to display product variation selection form.', 'woofunnels-upstroke-one-click-upsell' ),
						'default'         => true,
						'priority'        => 105,
						'active_callback' => array(
							array(
								'setting'  => 'wfocu_features_reasons_display_buy_block',
								'operator' => '==',
								'value'    => true,
							),
						),
					);
				}

				$this->customizer_data[] = $feature_panel;
				/** PANEL: REVIEWS */
				$review_panel                  = [];
				$review_panel['wfocu_reviews'] = array(
					'panel'    => 'no',
					'data'     => array(
						'priority'    => 70,
						'title'       => 'Reviews',
						'description' => '',
					),
					'sections' => array(
						'reviews' => array(
							'data'   => array(
								'title'    => 'Reviews',
								'priority' => 70,
							),
							'fields' => array(
								'ct_headings'       => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Heading', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 10,
								),
								'heading'           => array(
									'type'          => 'textarea',
									'label'         => __( 'Heading', 'woofunnels-upstroke-one-click-upsell' ),
									'description'   => $merge_tags_description,
									'default'       => esc_attr__( 'Trusted and Raved about by 1000s of our customers...', 'woofunnels-upstroke-one-click-upsell' ),
									'transport'     => 'postMessage',
									'wfocu_partial' => array(
										'elem' => '.wfocu-review-section .wfocu-section-headings .wfocu-heading',
									),
									'priority'      => 20,
								),
								'sub_heading'       => array(
									'type'            => 'textarea',
									'label'           => __( 'Sub Heading', 'woofunnels-upstroke-one-click-upsell' ),
									'description'     => $merge_tags_description,
									'default'         => esc_attr__( 'You\'ll love it too...', 'woofunnels-upstroke-one-click-upsell' ),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'html',
											'elem' => '.wfocu-review-section .wfocu-section-headings .wfocu-sub-heading',
										),
									),
									'priority'        => 30,
								),
								'ct_review'         => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Review Box', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 40,
								),
								'rtype'             => array(
									'type'     => 'radio-buttonset',
									'label'    => __( 'Product Reviews', 'woofunnels-upstroke-one-click-upsell' ),
									'default'  => 'manual',
									'choices'  => array(
										'manual'    => 'Manual',
										'automatic' => 'Automatic',

									),
									'priority' => 50,
								),
								'rthreshold'        => array(
									'type'            => 'slider',
									'label'           => __( 'Show Reviews With Ratings', 'woofunnels-upstroke-one-click-upsell' ),
									'description'     => __( 'Greater than or equal to', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 4,
									'choices'         => array(
										'min'  => 1,
										'max'  => 5,
										'step' => 1,
									),
									'priority'        => 60,
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_reviews_reviews_rtype',
											'operator' => '!=',
											'value'    => 'manual',
										),
									),
								),
								'limit'             => array(
									'type'            => 'number',
									'label'           => __( 'No. Of Reviews To Show ', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 4,
									'choices'         => array(
										'min'  => 1,
										'max'  => 50,
										'step' => 1,
									),
									'priority'        => 70,
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_reviews_reviews_rtype',
											'operator' => '!=',
											'value'    => 'manual',
										),
									),
								),
								'testimonial'       => array(
									'type'            => 'repeater',
									'label'           => esc_attr__( 'Reviews', 'woofunnels-upstroke-one-click-upsell' ),
									'priority'        => 80,
									'row_label'       => array(
										'type'  => 'text',
										'value' => esc_attr__( 'Review', 'woofunnels-upstroke-one-click-upsell' ),
									),
									'default'         => array(
										array(
											'heading' => esc_attr__( 'Best Solution For A Major Problem', 'woofunnels-upstroke-one-click-upsell' ),
											'message' => 'Duis ullamcorper ipsum et mi lacinia, et laoreet nibh aliquet. Proin fermentum, libero in imperdiet scelerisque, est lorem consectetur quam, sit amet semper velit tortor id eros. Praesent enim tortor, auctor sed bibendum ut, scelerisque ut magna. Duis ullamcorper ipsum et mi ',
											'name'    => 'Tamigachi ',
											'date'    => '2018-10-03',
											'rating'  => '5',
											'image'   => $this->no_img_path . 'no_image.jpg',
										),
										array(
											'heading' => esc_attr__( 'Best Solution For A Major Problem2', 'woofunnels-upstroke-one-click-upsell' ),
											'message' => 'Duis ullamcorper ipsum et mi lacinia, et laoreet nibh aliquet. Proin fermentum, libero in imperdiet scelerisque, est lorem consectetur quam, sit amet semper velit tortor id eros. Praesent enim tortor, auctor sed bibendum ut,',
											'name'    => 'Tamigachi ',
											'date'    => '2018-01-25',
											'rating'  => '4',
											'image'   => $this->no_img_path . 'no_image.jpg',
										),
										array(
											'heading' => esc_attr__( 'Best Solution For A Major Problem3', 'woofunnels-upstroke-one-click-upsell' ),
											'message' => 'Duis ullamcorper ipsum et mi lacinia, et laoreet nibh aliquet. Proin fermentum, libero in imperdiet scelerisque, est lorem consectetur quam, sit amet semper velit tortor id eros. Praesent enim tortor, auctor sed bibendum ut, scelerisque ut magna. Duis ullamcorper ipsum et mi ',
											'name'    => 'Tamigachi ',
											'date'    => '2017-12-23',
											'rating'  => '4',
											'image'   => $this->no_img_path . 'no_image.jpg',
										),
										array(
											'heading' => esc_attr__( 'Best Solution For A Major Problem4', 'woofunnels-upstroke-one-click-upsell' ),
											'message' => 'Duis ullamcorper ipsum et mi lacinia, et laoreet nibh aliquet. Proin fermentum, libero in imperdiet scelerisque, est lorem consectetur quam, sit amet semper velit tortor id eros. Praesent enim tortor, auctor sed bibendum ut, scelerisque ut magna. Duis ullamcorper ipsum et mi ',
											'name'    => 'Tamigachi ',
											'date'    => '2017-12-28',
											'rating'  => '5',
											'image'   => $this->no_img_path . 'no_image.jpg',
										),
									),
									'fields'          => array(
										'heading' => array(
											'type'  => 'text',
											'label' => __( 'Heading', 'woofunnels-upstroke-one-click-upsell' ),
										),
										'message' => array(
											'type'  => 'textarea',
											'label' => __( 'Testimonial', 'woofunnels-upstroke-one-click-upsell' ),
										),
										'name'    => array(
											'type'  => 'text',
											'label' => __( 'Name', 'woofunnels-upstroke-one-click-upsell' ),
										),
										'date'    => array(
											'type'        => 'date',
											'label'       => esc_attr__( 'Date', 'woofunnels-upstroke-one-click-upsell' ),
											'description' => esc_attr__( 'Date Format', 'woofunnels-upstroke-one-click-upsell' ),
										),
										'rating'  => array(
											'type'    => 'radio',
											'label'   => __( 'Rating', 'woofunnels-upstroke-one-click-upsell' ),
											'default' => '5',
											'choices' => array(
												'1' => esc_attr__( '1', 'woofunnels-upstroke-one-click-upsell' ),
												'2' => esc_attr__( '2', 'woofunnels-upstroke-one-click-upsell' ),
												'3' => esc_attr__( '3', 'woofunnels-upstroke-one-click-upsell' ),
												'4' => esc_attr__( '4', 'woofunnels-upstroke-one-click-upsell' ),
												'5' => esc_attr__( '5', 'woofunnels-upstroke-one-click-upsell' ),
											),
										),
										'image'   => array(
											'type'  => 'image',
											'label' => esc_attr__( 'Image', 'woofunnels-upstroke-one-click-upsell' ),
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_reviews_reviews_rtype',
											'operator' => '==',
											'value'    => 'manual',
										),
									),
								),
								'rbox_heading_fs'   => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Review Title Font Size', 'woofunnels-upstroke-one-click-upsell' ),
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
									'wfocu_transport' => array(
										array(
											'internal'   => true,
											'responsive' => true,
											'type'       => 'css',
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-review-section  .wfocu-review-block .wfocu-review-type',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_reviews_reviews_rtype',
											'operator' => '==',
											'value'    => 'manual',
										),
									),
									'priority'        => 90,
								),
								'rbox_meta_fs'      => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Review Meta Font Size', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => array(
										'desktop' => 12,
										'tablet'  => 12,
										'mobile'  => 12,
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
									'wfocu_transport' => array(
										array(
											'internal'   => true,
											'responsive' => true,
											'type'       => 'css',
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-review-section  .wfocu-review-block .wfocu-review-meta',
										),
									),
									'priority'        => 100,
								),
								'display_rating'    => array(
									'type'        => 'checkbox',
									'label'       => __( 'Display Star Ratings', 'woofunnels-upstroke-one-click-upsell' ),
									'description' => __( 'Display Ratings inside Review Box', 'woofunnels-upstroke-one-click-upsell' ),
									'default'     => true,
									'priority'    => 110,
								),
								'display_image'     => array(
									'type'        => 'checkbox',
									'label'       => __( 'Display Image', 'woofunnels-upstroke-one-click-upsell' ),
									'description' => __( 'Display image inside Review Box', 'woofunnels-upstroke-one-click-upsell' ),
									'default'     => true,
									'priority'    => 120,
								),
								'display_auth_date' => array(
									'type'        => 'checkbox',
									'label'       => __( 'Display Author With Date', 'woofunnels-upstroke-one-click-upsell' ),
									'description' => __( 'Display author with date inside Review Box', 'woofunnels-upstroke-one-click-upsell' ),
									'default'     => true,
									'priority'    => 130,
								),
								'ct_desc'           => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Additional Description', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 140,
								),
								'additional_text'   => array(
									'type'            => 'textarea',
									'label'           => __( 'Additional Text', 'woofunnels-upstroke-one-click-upsell' ),
									'description'     => $merge_tags_description,
									'default'         => esc_attr__( 'Use this section to tell them what other users are saying….', 'woofunnels-upstroke-one-click-upsell' ),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'       => 'html',
											'line_break' => 0,
											'elem'       => '.wfocu-review-section .wfocu-content-area',
										),
									),
									'priority'        => 150,
								),
								'additional_talign' => array(
									'type'            => 'radio-buttonset',
									'label'           => __( 'Align', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 'wfocu-text-center',
									'choices'         => array(
										'wfocu-text-left'   => 'Left',
										'wfocu-text-center' => 'Center',
										'wfocu-text-right'  => 'Right',
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'   => 'class',
											'elem'   => '.wfocu-review-section .wfocu-content-area',
											'remove' => array( 'wfocu-text-left', 'wfocu-text-center', 'wfocu-text-right' ),
										),
									),
									'priority'        => 160,
								),
								'ct_buy_block'      => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Buy Block', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 170,
								),
								'display_buy_block' => array(
									'type'        => 'checkbox',
									'label'       => esc_attr__( 'Display Buy Block', 'woofunnels-upstroke-one-click-upsell' ),
									'description' => esc_attr__( 'Enable if you want to display buy block.', 'woofunnels-upstroke-one-click-upsell' ),
									'default'     => true,
									'priority'    => 180,
								),

								'ct_advanced'        => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Advanced', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 190,
								),
								'rbox_border_type'   => array(
									'type'            => 'select',
									'label'           => esc_attr__( 'Review Box Border Type', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 'solid',
									'choices'         => array(
										'none'   => 'None',
										'solid'  => 'Solid',
										'double' => 'Double',
										'dotted' => 'Dotted',
										'dashed' => 'Dashed',
									),
									'transport'       => 'postMessage',
									'priority'        => 200,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'border-style' ),
											'elem' => '.wfocu-review-section  .wfocu-review-block ',
										),
									),
								),
								'rbox_border_width'  => array(
									'type'            => 'slider',
									'label'           => esc_attr__( 'Review Box Border Width', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 1,
									'choices'         => array(
										'min'  => '1',
										'max'  => '12',
										'step' => '1',
									),
									'transport'       => 'postMessage',
									'priority'        => 210,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'border-width' ),
											'elem' => '.wfocu-review-section  .wfocu-review-block ',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_reviews_reviews_rbox_border_type',
											'operator' => '!=',
											'value'    => 'none',
										),
									),
								),
								'rbox_border_color'  => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Review Box Border Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#e2e2e2',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'priority'        => 220,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'border-color' ),
											'elem' => '.wfocu-review-section  .wfocu-review-block ',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_reviews_reviews_rbox_border_type',
											'operator' => '!=',
											'value'    => 'none',
										),
									),
								),
								'ct_colors'          => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 230,
								),
								'rbox_heading_color' => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Review Box Title Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#414349',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'priority'        => 240,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-review-section  .wfocu-review-block .wfocu-review-type',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_reviews_reviews_rtype',
											'operator' => '==',
											'value'    => 'manual',
										),
									),
								),
								'rbox_meta_color'    => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Review Box Meta Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#414349',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'priority'        => 250,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-review-section  .wfocu-review-block .wfocu-review-meta',
										),
									),
								),
								'bg_color'           => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#ffffff',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'priority'        => 260,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'background-color' ),
											'elem' => '.wfocu-review-section',
										),
									),
								),
								'override_global'    => array(
									'type'        => 'checkbox',
									'label'       => esc_attr__( 'Override Global Colors', 'woofunnels-upstroke-one-click-upsell' ),
									'description' => esc_attr__( 'Enable if you want to change Heading, Sub Heading, Content color specifically for this section.', 'woofunnels-upstroke-one-click-upsell' ),
									'default'     => false,
									'priority'    => 270,
								),
								'heading_color'      => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Heading Text Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#414349',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-review-section .wfocu-section-headings .wfocu-heading',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_reviews_reviews_override_global',
											'operator' => '==',
											'value'    => true,
										),
									),
									'priority'        => 280,
								),
								'sub_heading_color'  => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Sub Heading Text Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#414349',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-review-section .wfocu-section-headings .wfocu-sub-heading',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_reviews_reviews_override_global',
											'operator' => '==',
											'value'    => true,
										),
									),
									'priority'        => 290,
								),
								'content_color'      => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Content Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#414349',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-review-section .wfocu-review-block  .wfocu-review-content p',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-review-section .wfocu-review-block .wfocu-review-content ul li',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-review-section .wfocu-review-block .wfocu-review-content ol li',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-review-section .wfocu-content-area  p',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-review-section .wfocu-content-area  ul li',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-review-section .wfocu-content-area  ol li',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-review-section .wfocu-product-attr-wrapper',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_reviews_reviews_override_global',
											'operator' => '==',
											'value'    => true,
										),
									),
									'priority'        => 300,
								),
							),
						),
					),
				);
				if ( true === $this->variation_field ) {
					$review_panel['wfocu_reviews']['sections']['reviews']['fields']['display_buy_block_variation'] = array(
						'type'            => 'checkbox',
						'label'           => esc_attr__( 'Display Product Variation Selection', 'woofunnels-upstroke-one-click-upsell' ),
						'description'     => esc_attr__( 'Enable if you want to display product variation selection form.', 'woofunnels-upstroke-one-click-upsell' ),
						'default'         => true,
						'priority'        => 185,
						'active_callback' => array(
							array(
								'setting'  => 'wfocu_reviews_reviews_display_buy_block',
								'operator' => '==',
								'value'    => true,
							),
						),
					);
				}

				$this->customizer_data[] = $review_panel;

				/** PANEL: GUARANTEE */
				$guarantee_panel                    = [];
				$guarantee_panel['wfocu_guarantee'] = array(
					'panel'    => 'no',
					'data'     => array(
						'priority'    => 80,
						'title'       => 'Guarantee',
						'description' => '',
					),
					'sections' => array(
						'guarantee' => array(
							'data'   => array(
								'title'    => 'Guarantee',
								'priority' => 80,
							),
							'fields' => array(
								'ct_headings'        => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Heading', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 10,
								),
								'heading'            => array(
									'type'          => 'textarea',
									'label'         => __( 'Heading', 'woofunnels-upstroke-one-click-upsell' ),
									'description'   => $merge_tags_description,
									'default'       => esc_attr__( 'We Standby Our Product', 'woofunnels-upstroke-one-click-upsell' ),
									'transport'     => 'postMessage',
									'wfocu_partial' => array(
										'elem' => '.wfocu-guarantee-section .wfocu-section-headings .wfocu-heading',
									),
									'priority'      => 20,
								),
								'sub_heading'        => array(
									'type'            => 'textarea',
									'label'           => __( 'Sub Heading', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => esc_attr__( 'Got a guarantee or a solid return/refund policy? Highlight them all here to arrest your buyer\'s last-minute objections. Reinforce the trust.', 'woofunnels-upstroke-one-click-upsell' ),
									'transport'       => 'postMessage',
									'description'     => $merge_tags_description,
									'wfocu_transport' => array(
										array(
											'type' => 'html',
											'elem' => '.wfocu-guarantee-section  .wfocu-section-headings .wfocu-sub-heading',
										),
									),
									'priority'        => 30,
								),
								'ct_guarantee'       => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Guarantee Box', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 40,
								),
								'icon_text'          => array(
									'type'      => 'repeater',
									'label'     => esc_attr__( 'Guarantee', 'woofunnels-upstroke-one-click-upsell' ),
									'row_label' => array(
										'type'  => 'text',
										'value' => esc_attr__( 'Guarantee', 'woofunnels-upstroke-one-click-upsell' ),
									),
									'priority'  => 50,
									'default'   => array(
										array(
											'heading' => esc_attr__( '100% Secure Checkout', 'woofunnels-upstroke-one-click-upsell' ),
											'message' => '100% Lorem ipsum dolor sit amet, consectetur adipiscing elit. Duis posuere dolor vitae ex maximus dictum. Aenean laoreet congue diam.',
											'builtin' => 'icon_1',
											'image'   => '',
										),
										array(
											'heading' => esc_attr__( 'Free Shipping', 'woofunnels-upstroke-one-click-upsell' ),
											'message' => 'Free Lorem ipsum dolor sit amet, consectetur adipiscing elit. Duis posuere dolor vitae ex maximus dictum. Aenean laoreet congue diam.',
											'builtin' => 'icon_2',
											'image'   => '',
										),
										array(
											'heading' => esc_attr__( 'Refund Guarantee', 'woofunnels-upstroke-one-click-upsell' ),
											'message' => 'Refund Lorem ipsum dolor sit amet, consectetur adipiscing elit. Duis posuere dolor vitae ex maximus dictum. Aenean laoreet congue diam.',
											'builtin' => 'icon_3',
											'image'   => '',
										),
										array(
											'heading' => esc_attr__( 'Complete Satisfaction', 'woofunnels-upstroke-one-click-upsell' ),
											'message' => 'Complete Lorem ipsum dolor sit amet, consectetur adipiscing elit. Duis posuere dolor vitae ex maximus dictum. Aenean laoreet congue diam.',
											'builtin' => 'icon_4',
											'image'   => '',
										),
									),
									'fields'    => array(
										'heading' => array(
											'type'  => 'text',
											'label' => __( 'Heading', 'woofunnels-upstroke-one-click-upsell' ),
										),
										'message' => array(
											'type'  => 'textarea',
											'label' => __( 'Text', 'woofunnels-upstroke-one-click-upsell' ),
										),
										'builtin' => array(
											'type'    => 'radio-image',
											'label'   => esc_attr__( 'Built-in Icon', 'woofunnels-upstroke-one-click-upsell' ),
											'choices' => array(
												'icon_1'  => $this->img_public_path . 'guarantee/icon_1.png',
												'icon_2'  => $this->img_public_path . 'guarantee/icon_2.png',
												'icon_3'  => $this->img_public_path . 'guarantee/icon_3.png',
												'icon_4'  => $this->img_public_path . 'guarantee/icon_4.png',
												'icon_5'  => $this->img_public_path . 'guarantee/icon_5.png',
												'icon_6'  => $this->img_public_path . 'guarantee/icon_6.png',
												'icon_7'  => $this->img_public_path . 'guarantee/icon_7.png',
												'icon_8'  => $this->img_public_path . 'guarantee/icon_8.png',
												'icon_9'  => $this->img_public_path . 'guarantee/icon_9.png',
												'icon_10' => $this->img_public_path . 'guarantee/icon_10.png',
												'icon_11' => $this->img_public_path . 'guarantee/icon_11.png',
												'icon_12' => $this->img_public_path . 'guarantee/icon_12.png',
												'icon_13' => $this->img_public_path . 'guarantee/icon_13.png',
												'icon_14' => $this->img_public_path . 'guarantee/icon_14.png',
											),
											'default' => 'icon_1',
										),
										'image'   => array(
											'type'        => 'image',
											'label'       => esc_attr__( 'Custom Icon', 'woofunnels-upstroke-one-click-upsell' ),
											'default'     => '',
											'description' => esc_attr__( 'Custom will override built-in selected icon.', 'woofunnels-upstroke-one-click-upsell' ),
										),

									),
								),
								'gbox_heading_fs'    => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Heading Font Size', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => array(
										'desktop' => 18,
										'tablet'  => 18,
										'mobile'  => 17,
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
									'wfocu_transport' => array(
										array(
											'internal'   => true,
											'responsive' => true,
											'type'       => 'css',
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-guarantee-section .wfocu-guarantee-box .wfocu-block-heading',
										),
									),
									'priority'        => 60,
								),
								'display_image'      => array(
									'type'        => 'checkbox',
									'label'       => __( 'Display Icon', 'woofunnels-upstroke-one-click-upsell' ),
									'description' => __( 'Display icon inside Guarantee Box', 'woofunnels-upstroke-one-click-upsell' ),
									'default'     => true,
									'priority'    => 70,
								),
								'ct_desc'            => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Additional Description', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 80,
								),
								'additional_text'    => array(
									'type'            => 'textarea',
									'label'           => __( 'Additional Text', 'woofunnels-upstroke-one-click-upsell' ),
									'description'     => $merge_tags_description,
									'default'         => esc_attr__( 'Got a guarantee or a solid return/refund policy? Highlight them all here to arrest your buyer\'s last-minute objections. Reinforce the trust.', 'woofunnels-upstroke-one-click-upsell' ),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'       => 'html',
											'line_break' => 0,
											'elem'       => '.wfocu-guarantee-section .wfocu-content-area',
										),
									),
									'priority'        => 90,
								),
								'additional_talign'  => array(
									'type'            => 'radio-buttonset',
									'label'           => __( 'Align', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 'wfocu-text-center',
									'choices'         => array(
										'wfocu-text-left'   => 'Left',
										'wfocu-text-center' => 'Center',
										'wfocu-text-right'  => 'Right',
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'   => 'class',
											'elem'   => '.wfocu-guarantee-section .wfocu-content-area',
											'remove' => array( 'wfocu-text-left', 'wfocu-text-center', 'wfocu-text-right' ),
										),
									),
									'priority'        => 100,
								),
								'ct_buy_block'       => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Buy Block', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 110,
								),
								'display_buy_block'  => array(
									'type'        => 'checkbox',
									'label'       => esc_attr__( 'Display Buy Block', 'woofunnels-upstroke-one-click-upsell' ),
									'description' => esc_attr__( 'Enable if you want to display buy block.', 'woofunnels-upstroke-one-click-upsell' ),
									'default'     => true,
									'priority'    => 120,
								),
								'ct_colors'          => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 130,
								),
								'gbox_heading_color' => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Guarantee Box Heading Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#414349',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-guarantee-section .wfocu-guarantee-box .wfocu-block-heading',
										),
									),
									'priority'        => 140,
								),
								'bg_color'           => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#ffffff',
									'choices'         => array(
										'alpha' => true,
									),
									'priority'        => 150,
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'background-color' ),
											'elem' => '.wfocu-guarantee-section',
										),
									),
								),
								'override_global'    => array(
									'type'        => 'checkbox',
									'label'       => esc_attr__( 'Override Global Color Settings', 'woofunnels-upstroke-one-click-upsell' ),
									'description' => esc_attr__( 'Enable if you want to change Heading, Sub Heading, Content color specifically for this section.', 'woofunnels-upstroke-one-click-upsell' ),
									'default'     => false,
									'priority'    => 160,
								),
								'heading_color'      => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Heading Text Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#414349',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-guarantee-section .wfocu-section-headings .wfocu-heading',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_guarantee_guarantee_override_global',
											'operator' => '==',
											'value'    => true,
										),
									),
									'priority'        => 170,
								),
								'sub_heading_color'  => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Sub Heading Text Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#414349',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-guarantee-section .wfocu-section-headings .wfocu-sub-heading',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_guarantee_guarantee_override_global',
											'operator' => '==',
											'value'    => true,
										),
									),
									'priority'        => 180,
								),
								'content_color'      => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Content Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#414349',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-guarantee-section .wfocu-guarantee-box .wfocu-block-text p',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-guarantee-section .wfocu-guarantee-box .wfocu-block-text ul li',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-guarantee-section .wfocu-guarantee-box .wfocu-block-text ol li',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-guarantee-section .wfocu-content-area p',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-guarantee-section .wfocu-content-area ul li',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-guarantee-section .wfocu-content-area ol li',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-guarantee-section .wfocu-product-attr-wrapper',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_guarantee_guarantee_override_global',
											'operator' => '==',
											'value'    => true,
										),
									),
									'priority'        => 190,
								),
							),
						),
					),
				);
				if ( true === $this->variation_field ) {
					$guarantee_panel['wfocu_guarantee']['sections']['guarantee']['fields']['display_buy_block_variation'] = array(
						'type'            => 'checkbox',
						'label'           => esc_attr__( 'Display Product Variation Selection', 'woofunnels-upstroke-one-click-upsell' ),
						'description'     => esc_attr__( 'Enable if you want to display product variation selection form.', 'woofunnels-upstroke-one-click-upsell' ),
						'default'         => true,
						'priority'        => 125,
						'active_callback' => array(
							array(
								'setting'  => 'wfocu_guarantee_guarantee_display_buy_block',
								'operator' => '==',
								'value'    => true,
							),
						),
					);
				}

				$this->customizer_data[] = $guarantee_panel;

				/** PANEL: URGENCY BAR */
				$urgency_bar_panel                      = [];
				$urgency_bar_panel['wfocu_urgency_bar'] = array(
					'panel'    => 'no',
					'data'     => array(
						'priority'    => 90,
						'title'       => 'Urgency Bar',
						'description' => '',
					),
					'sections' => array(
						'urgency_bar' => array(
							'data'   => array(
								'title'    => 'Urgency Bar',
								'priority' => 90,
							),
							'fields' => array(
								'ct_layout' => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Layout', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 10,
								),
								'position'  => array(
									'type'     => 'radio-image-text',
									'label'    => esc_attr__( 'Position', 'woofunnels-upstroke-one-click-upsell' ),
									'default'  => 'sticky_header',
									'choices'  => array(
										'inline'        => array(
											'label' => __( 'Inline', 'woofunnels-upstroke-one-click-upsell' ),
											'path'  => $this->img_path . 'sticky_middle.svg',
										),
										'sticky_header' => array(
											'label' => __( 'Sticky Header', 'woofunnels-upstroke-one-click-upsell' ),
											'path'  => $this->img_path . 'sticky_top.svg',
										),
										'sticky_footer' => array(
											'label' => __( 'Sticky Footer', 'woofunnels-upstroke-one-click-upsell' ),
											'path'  => $this->img_path . 'sticky_bottom.svg',
										),
									),
									'priority' => 20,
								),

								'ct_content'      => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Content', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 30,
								),
								'heading'         => array(
									'type'          => 'textarea',
									'description'   => $merge_tags_description,
									'label'         => __( 'Text', 'woofunnels-upstroke-one-click-upsell' ),
									'default'       => esc_attr__( 'Act Fast: Grab this one-time exclusive offer before time runs out. This offer is not available elsewhere on the site.', 'woofunnels-upstroke-one-click-upsell' ),
									'transport'     => 'postMessage',
									'wfocu_partial' => array(
										'elem' => '.wfocu-urgency-bar .wfocu-h3',
									),
									'priority'      => 40,
								),
								'heading_fs'      => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Text Font Size', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => array(
										'desktop' => 18,
										'tablet'  => 18,
										'mobile'  => 17,
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
									'wfocu_transport' => array(
										array(
											'internal'   => true,
											'responsive' => true,
											'type'       => 'css',
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-urgency-bar .wfocu-content-div',
										),
									),
									'priority'        => 50,
								),
								'height'          => array(
									'type'            => 'slider',
									'label'           => __( 'Height', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 69,
									'choices'         => array(
										'min'  => '50',
										'max'  => '500',
										'step' => '1',
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'height' ),
											'elem' => '.wfocu-urgency-bar .wfocu-urgency-col',
										),
									),
									'priority'        => 60,
								),
								'ct_timer'        => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Countdown Timer', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 70,
								),
								'show_timer'      => array(
									'type'        => 'checkbox',
									'label'       => esc_attr__( 'Display Countdown timer', 'woofunnels-upstroke-one-click-upsell' ),
									'description' => esc_attr__( 'Manage settings from Other->Countdown Timer Section.', 'woofunnels-upstroke-one-click-upsell' ),
									'default'     => true,
									'priority'    => 80,
								),
								'timer_align'     => array(
									'type'            => 'radio-buttonset',
									'label'           => __( 'Position', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 'wfocu-ctimer-left',
									'choices'         => array(
										'wfocu-ctimer-left'  => 'Left',
										'wfocu-ctimer-right' => 'Right',
									),
									'priority'        => 90,
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_urgency_bar_urgency_bar_show_timer',
											'operator' => '==',
											'value'    => true,
										),
									),
								),
								'ct_advanced'     => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Advanced', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 100,
								),
								'reveal_bar_secs' => array(
									'type'        => 'text',
									'label'       => esc_attr__( 'Show Urgency Bar', 'woofunnels-upstroke-one-click-upsell' ),
									'description' => esc_attr__( 'After x Seconds.', 'woofunnels-upstroke-one-click-upsell' ),
									'default'     => '2',
									'transport'   => 'postMessage',
									'priority'    => 110,
								),
								'display_on'      => array(
									'type'     => 'multicheck',
									'label'    => __( 'Display On', 'woofunnels-upstroke-one-click-upsell' ),
									'default'  => array( 'desktop', 'mobile' ),
									'choices'  => array(
										'desktop' => esc_attr__( 'Desktop', 'woofunnels-upstroke-one-click-upsell' ),
										'mobile'  => esc_attr__( 'Mobile', 'woofunnels-upstroke-one-click-upsell' ),
									),
									'priority' => 120,
								),
								'ct_colors'       => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 130,
								),
								'heading_color'   => array(
									'type'            => 'color',
									'label'           => __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#2e2c2c',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-urgency-bar .wfocu-content-div',
										),
									),
									'priority'        => 140,
								),
								'shadow_color'    => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Bar Shadow Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#bfbebe',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'   => 'css',
											'prop'   => array( 'box-shadow', '-moz-box-shadow', '-webkit-box-shadow', '-ms-box-shadow', '-o-box-shadow' ),
											'prefix' => '0px 0px 8px 0px ',
											'elem'   => '.wfocu-urgency-bar',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_urgency_bar_urgency_bar_position',
											'operator' => '!=',
											'value'    => 'inline',
										),

									),
									'priority'        => 150,

								),
								'bg_color'        => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#f9f9f9',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'priority'        => 160,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'background-color' ),
											'elem' => '.wfocu-urgency-bar',
										),
									),
								),
							),
						),
					),
				);

				$this->customizer_data[] = $urgency_bar_panel;

				/** PANEL: Countdown Timer */
				$other_panel                = [];
				$other_panel['wfocu_other'] = array(
					'panel'    => 'yes',
					'data'     => array(
						'priority'    => 100,
						'title'       => 'Other',
						'description' => '',
					),
					'sections' => array(
						'ctimer' => array(
							'data'   => array(
								'title'    => 'Countdown Timer',
								'priority' => 10,
							),
							'fields' => array(
								'ct_layout'   => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Layout', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 10,
								),
								'skin'        => array(
									'type'    => 'radio-image-text',
									'label'   => esc_attr__( 'Skin', 'woofunnels-upstroke-one-click-upsell' ),
									'default' => 'style1',
									'choices' => array(
										'style1' => array(
											'label' => __( 'Style 1', 'woofunnels-upstroke-one-click-upsell' ),
											'path'  => $this->img_path . 'countdown_1.svg',
										),
										'style2' => array(
											'label' => __( 'Style 2', 'woofunnels-upstroke-one-click-upsell' ),
											'path'  => $this->img_path . 'countdown_2.svg',
										),
									),

									'priority' => 20,
								),
								'ct_timer'    => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Countdown Timer', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 30,
								),
								'timer_hours' => array(
									'type'     => 'number',
									'label'    => __( 'Hours', 'woofunnels-upstroke-one-click-upsell' ),
									'default'  => 0,
									'choices'  => array(
										'min'  => 0,
										'max'  => 24,
										'step' => 1,
									),
									'priority' => 40,
								),
								'timer_mins'  => array(
									'type'     => 'number',
									'label'    => __( 'Minutes', 'woofunnels-upstroke-one-click-upsell' ),
									'default'  => 30,
									'choices'  => array(
										'min'  => 0,
										'max'  => 60,
										'step' => 1,
									),
									'priority' => 50,
								),

								'hide_hrs'         => array(
									'type'        => 'checkbox',
									'label'       => esc_attr__( 'Hide Hours', 'woofunnels-upstroke-one-click-upsell' ),
									'description' => esc_attr__( 'Hide Hour\'s block if less than an hour left' ),
									'default'     => false,
									'priority'    => 60,
								),
								'zero_action'      => array(
									'type'     => 'radio',
									'label'    => esc_attr__( 'Action if Timer Hits Zero', 'woofunnels-upstroke-one-click-upsell' ),
									'default'  => 'stay_on_page',
									'choices'  => array(
										'stay_on_page'     => 'Stay on this page',
										'redirect_to_next' => 'Redirect to next offer',

									),
									'priority' => 70,
								),
								'show_labels'      => array(
									'type'        => 'checkbox',
									'label'       => esc_attr__( 'Show Labels', 'woofunnels-upstroke-one-click-upsell' ),
									'description' => esc_attr__( 'Enable if you want to display lables.', 'woofunnels-upstroke-one-click-upsell' ),
									'default'     => true,
									'priority'    => 80,
								),
								'label_hrs'        => array(
									'type'            => 'text',
									'label'           => esc_attr__( 'Hours', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 'Hours',
									'priority'        => 90,
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_other_ctimer_show_labels',
											'value'    => true,
											'operator' => '==',
										),
									),
								),
								'label_mins'       => array(
									'type'            => 'text',
									'label'           => esc_attr__( 'Minutes', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 'Minutes',
									'priority'        => 100,
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_other_ctimer_show_labels',
											'value'    => true,
											'operator' => '==',
										),
									),
								),
								'label_secs'       => array(
									'type'            => 'text',
									'label'           => esc_attr__( 'Seconds', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 'Seconds',
									'priority'        => 110,
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_other_ctimer_show_labels',
											'value'    => true,
											'operator' => '==',
										),
									),
								),
								'text_above_timer' => array(
									'type'            => 'text',
									'label'           => esc_attr__( 'Text Above Timer', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 'Offer Expires In',
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'html',
											'elem' => '.wfocu-countdown-highlight .wfocu-countdown-timer-text',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_other_ctimer_skin',
											'value'    => 'style2',
											'operator' => '==',
										),
									),
									'priority'        => 120,
								),
								'timer_text_fs'    => array(
									'type'            => 'slider',
									'label'           => __( 'Text Above Timer Font Size', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 18,
									'choices'         => array(
										'min'  => '12',
										'max'  => '72',
										'step' => '1',
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'font-size' ),
											'elem' => '.wfocu-countdown-highlight .wfocu-countdown-timer-text',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_other_ctimer_skin',
											'value'    => 'style2',
											'operator' => '==',
										),
									),
									'priority'        => 130,
								),
								'digit_fs'         => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Timer Digit Font Size', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => array(
										'desktop' => 18,
										'tablet'  => 18,
										'mobile'  => 17,
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
									'wfocu_transport' => array(
										array(
											'internal'   => true,
											'responsive' => true,
											'type'       => 'css',
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-countdown-square-ghost .wfocu-square-wrap .wfocu-timer-digit',
										),
										array(
											'internal'   => true,
											'responsive' => true,
											'type'       => 'css',
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-countdown-highlight .wfocu-highlight-wrap .wfocu-timer-digit',
										),
									),
									'priority'        => 140,
								),
								'label_fs'         => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Timer Label Font Size', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => array(
										'desktop' => 13,
										'tablet'  => 13,
										'mobile'  => 13,
									),
									'input_attrs'     => array(
										'step' => 1,
										'min'  => 12,
										'max'  => 20,
									),
									'units'           => array(
										'px' => 'px',
										'em' => 'em',
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'internal'   => true,
											'responsive' => true,
											'type'       => 'css',
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-countdown-square-ghost .wfocu-square-wrap .wfocu-timer-label',
										),
										array(
											'internal'   => true,
											'responsive' => true,
											'type'       => 'css',
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-countdown-highlight .wfocu-highlight-wrap .wfocu-timer-label',
										),
									),
									'priority'        => 150,
								),
								'ct_colors'        => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 160,
								),
								'timer_text_color' => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Text Above Timer Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#ffffff',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-countdown-highlight .wfocu-countdown-timer-text',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_other_ctimer_skin',
											'value'    => 'style2',
											'operator' => '==',
										),
									),
									'priority'        => 170,
								),
								'digit_color'      => array(
									'type'     => 'color',
									'label'    => esc_attr__( 'Timer Digit Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'  => '#ffffff',
									'choices'  => array(
										'alpha' => true,
									),
									'priority' => 180,
								),
								'label_color'      => array(
									'type'     => 'color',
									'label'    => esc_attr__( 'Timer Label Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'  => '#ffffff',
									'choices'  => array(
										'alpha' => true,
									),
									'priority' => 190,

								),
								'timer_bg_color'   => array(
									'type'     => 'color',
									'label'    => esc_attr__( 'Timer Background Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'  => '#ce3362',
									'choices'  => array(
										'alpha' => true,
									),
									'priority' => 200,
								),
							),
						),
						'picons' => array(
							'data'   => array(
								'title'    => 'Payment Icons',
								'priority' => 20,
							),
							'fields' => array(
								'ct_payment_icons' => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Icons', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 10,
								),
								'order'            => array(
									'type'     => 'sortable',
									'label'    => __( 'Order', 'woofunnels-upstroke-one-click-upsell' ),
									'default'  => array(
										'americanexpress',
										'discover',
										'mastercard',
										'paypal',
										'visa',
									),
									'choices'  => array(
										'amazon'          => esc_attr__( 'Amazon', 'woofunnels-upstroke-one-click-upsell' ),
										'americanexpress' => esc_attr__( 'American Express', 'woofunnels-upstroke-one-click-upsell' ),
										'authorize'       => esc_attr__( 'Authorize', 'woofunnels-upstroke-one-click-upsell' ),
										'briantree'       => esc_attr__( 'Briantree', 'woofunnels-upstroke-one-click-upsell' ),
										'cirrus'          => esc_attr__( 'Cirrus', 'woofunnels-upstroke-one-click-upsell' ),
										'dinnersclub'     => esc_attr__( 'Dinners Club', 'woofunnels-upstroke-one-click-upsell' ),
										'directdebit'     => esc_attr__( 'Direct Debit', 'woofunnels-upstroke-one-click-upsell' ),
										'discover'        => esc_attr__( 'Discover', 'woofunnels-upstroke-one-click-upsell' ),
										'ebay'            => esc_attr__( 'Ebay', 'woofunnels-upstroke-one-click-upsell' ),
										'jcb'             => esc_attr__( 'Jcb', 'woofunnels-upstroke-one-click-upsell' ),
										'maestro'         => esc_attr__( 'Maestro', 'woofunnels-upstroke-one-click-upsell' ),
										'mastercard'      => esc_attr__( 'Master Card', 'woofunnels-upstroke-one-click-upsell' ),
										'paypal'          => esc_attr__( 'PayPal', 'woofunnels-upstroke-one-click-upsell' ),
										'solo'            => esc_attr__( 'Solo', 'woofunnels-upstroke-one-click-upsell' ),
										'stripe'          => esc_attr__( 'Stripe', 'woofunnels-upstroke-one-click-upsell' ),
										'switch'          => esc_attr__( 'Switch', 'woofunnels-upstroke-one-click-upsell' ),
										'visa'            => esc_attr__( 'Visa', 'woofunnels-upstroke-one-click-upsell' ),
										'visaelectron'    => esc_attr__( 'Visa Electron', 'woofunnels-upstroke-one-click-upsell' ),
									),
									'priority' => 20,
								),
								'custom'           => array(
									'type'        => 'image',
									'label'       => __( 'Custom Icon', 'woofunnels-upstroke-one-click-upsell' ),
									'default'     => '',
									'description' => esc_attr__( 'Image size must not be greater than 60px.', 'woofunnels-upstroke-one-click-upsell' ),
									'priority'    => 30,
								),
								'ct_colors'        => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 40,
								),
								'color'            => array(
									'type'            => 'radio-buttonset',
									'label'           => __( 'Icon Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 'wfocu-original-color',
									'choices'         => array(
										'wfocu-original-color'  => esc_attr__( 'Original', 'woofunnels-upstroke-one-click-upsell' ),
										'wfocu-greyscale-color' => esc_attr__( 'Greyscale', 'woofunnels-upstroke-one-click-upsell' ),

									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'   => 'class',
											'remove' => array( 'wfocu-original-color', 'wfocu-greyscale-color' ),
											'elem'   => '.wfocu-product-pay-card',
										),
									),

									'priority' => 50,
								),
							),
						),

					),
				);

				$this->customizer_data[] = $other_panel;
				/** PANEL: FOOTER */
				$footer_panel                 = [];
				$footer_panel['wfocu_footer'] = array(
					'panel'    => 'no',
					'data'     => array(
						'priority'    => 120,
						'title'       => 'Footer',
						'description' => '',
					),
					'sections' => array(
						'footer' => array(
							'data'   => array(
								'title'    => 'Footer',
								'priority' => 120,
							),
							'fields' => array(
								'ct_content'       => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Content', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 10,
								),
								'f_text'           => array(
									'type'          => 'textarea',
									'description'   => $merge_tags_description,
									'label'         => __( 'Text', 'woofunnels-upstroke-one-click-upsell' ),
									'default'       => __( 'Secure checkout - 100% protected & safe.', 'woofunnels-upstroke-one-click-upsell' ),
									'transport'     => 'postMessage',
									'wfocu_partial' => array(
										'elem' => '.wfocu-page-footer-section .wfocu-footer-text',
									),
									'priority'      => 20,
								),
								'f_text_fs'        => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Text Font Size', 'woofunnels-upstroke-one-click-upsell' ),
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
									'wfocu_transport' => array(
										array(
											'internal'   => true,
											'responsive' => true,
											'type'       => 'css',
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-page-footer-section .wfocu-footer-text',
										),
									),
									'priority'        => 30,
								),
								'f_links'          => array(
									'type'      => 'repeater',
									'label'     => esc_attr__( 'Bottom Links', 'woofunnels-upstroke-one-click-upsell' ),
									'row_label' => array(
										'type'  => 'text',
										'value' => esc_attr__( 'Footer Link', 'woofunnels-upstroke-one-click-upsell' ),
									),
									'default'   => array(
										array(
											'name' => esc_attr__( 'Disclaimer', 'woofunnels-upstroke-one-click-upsell' ),
											'link' => '#',
										),
										array(
											'name' => esc_attr__( 'Terms & Conditions', 'woofunnels-upstroke-one-click-upsell' ),
											'link' => '#',
										),
										array(
											'name' => esc_attr__( 'Privacy Policy', 'woofunnels-upstroke-one-click-upsell' ),
											'link' => '#',
										),
									),
									'fields'    => array(
										'name' => array(
											'type'  => 'text',
											'label' => __( 'Name', 'woofunnels-upstroke-one-click-upsell' ),
										),
										'link' => array(
											'type'  => 'text',
											'label' => __( 'Link', 'woofunnels-upstroke-one-click-upsell' ),
										),
									),
									'priority'  => 40,
								),
								'f_links_fs'       => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Bottom Links Font Size', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => array(
										'desktop' => 12,
										'tablet'  => 12,
										'mobile'  => 12,
									),
									'input_attrs'     => array(
										'step' => 1,
										'min'  => 10,
										'max'  => 24,
									),
									'units'           => array(
										'px' => 'px',
										'em' => 'em',
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'internal'   => true,
											'responsive' => true,
											'type'       => 'css',
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-page-footer-section .wfocu-footer-links a',
										),
									),
									'priority'        => 50,
								),
								'ct_payment_icons' => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Payment Icons', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 60,
								),
								'f_payment_icons'  => array(
									'type'        => 'checkbox',
									'label'       => esc_attr__( 'Display Payment Icons', 'woofunnels-upstroke-one-click-upsell' ),
									'description' => esc_attr__( 'Manage settings from Other->Payment Icons Section.', 'woofunnels-upstroke-one-click-upsell' ),
									'default'     => true,
									'transport'   => 'refresh',
									'priority'    => 70,
								),
								'ct_colors'        => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 80,
								),
								'f_text_color'     => array(
									'type'            => 'color',
									'label'           => __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#22334f',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-page-footer-section .wfocu-footer-text',
										),
									),
									'priority'        => 90,
								),
								'f_links_color'    => array(
									'type'            => 'color',
									'label'           => __( 'Bottom Links Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#a5a5a5',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-page-footer-section .wfocu-footer-links a',
										),
									),
									'priority'        => 100,
								),
								'bg_color'         => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#ffffff',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'priority'        => 110,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'background-color' ),
											'elem' => '.wfocu-page-footer-section',
										),
									),
								),
							),
						),
					),
				);

				$this->customizer_data[] = $footer_panel;
				/** PANEL: STYLE */
				$style_panel                = [];
				$style_panel['wfocu_style'] = array(
					'data'     => array(
						'priority'    => 130,
						'title'       => 'Style',
						'description' => '',
					),
					'sections' => array(
						'colors'     => array(
							'data'   => array(
								'title'    => 'Colors',
								'priority' => 10,
							),
							'fields' => array(
								'site_bg_color'     => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Site Background Color ', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#ffffff',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'background-color' ),
											'elem' => 'html',
										),
									),
									'priority'        => 10,
								),
								'heading_color'     => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Heading Text Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#414349',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'     => 'css',
											'prop'     => array( 'color' ),
											'elem'     => '.wfocu-section-headings .wfocu-heading',
											'internal' => true,
										),
									),
									'priority'        => 20,
								),
								'sub_heading_color' => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Sub Heading Text Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#414349',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'     => 'css',
											'prop'     => array( 'color' ),
											'elem'     => '.wfocu-section-headings .wfocu-sub-heading',
											'internal' => true,
										),
									),
									'priority'        => 30,
								),
								'content_color'     => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Content Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#414349',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'     => 'css',
											'prop'     => array( 'color' ),
											'elem'     => 'body',
											'internal' => true,
										),
										array(
											'type'     => 'css',
											'prop'     => array( 'color' ),
											'elem'     => 'body p',
											'internal' => true,
										),
										array(
											'type'     => 'css',
											'prop'     => array( 'color' ),
											'elem'     => 'body ul li',
											'internal' => true,
										),
										array(
											'type'     => 'css',
											'prop'     => array( 'color' ),
											'elem'     => 'body ol li',
											'internal' => true,
										),
									),
									'priority'        => 40,
								),
								'highlight_color'   => array(
									'type'            => 'color',
									'label'           => __( 'Highlight Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#ff0000',
									'transport'       => 'postMessage',
									'choices'         => array(
										'alpha' => true,
									),
									'priority'        => 50,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => 'body .wfocu-highlight',
										),
									),
								),
							),
						),
						'typography' => array(
							'data'   => array(
								'title'    => 'Typography',
								'priority' => 20,
							),
							'fields' => array(
								'ct_font_size'   => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Font Size', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 10,
								),
								'heading_fs'     => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Heading', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => array(
										'desktop' => 28,
										'tablet'  => 26,
										'mobile'  => 24,
									),
									'input_attrs'     => array(
										'step' => 1,
										'min'  => 12,
										'max'  => 48,
									),
									'units'           => array(
										'px' => 'px',
										'em' => 'em',
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'internal'   => true,
											'responsive' => true,
											'type'       => 'css',
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-section-headings .wfocu-heading',
										),
									),
									'priority'        => 20,
								),
								'sub_heading_fs' => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Sub Heading', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => array(
										'desktop' => 18,
										'tablet'  => 18,
										'mobile'  => 16,
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
									'wfocu_transport' => array(
										array(
											'internal'   => true,
											'responsive' => true,
											'type'       => 'css',
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-section-headings .wfocu-sub-heading',
										),
									),
									'priority'        => 30,
								),
								'content_fs'     => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Content', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => array(
										'desktop' => 14,
										'tablet'  => 14,
										'mobile'  => 14,
									),
									'input_attrs'     => array(
										'step' => 1,
										'min'  => 12,
										'max'  => 24,
									),
									'units'           => array(
										'px' => 'px',
										'em' => 'em',
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'internal'   => true,
											'responsive' => true,
											'type'       => 'css',
											'prop'       => array( 'font-size' ),
											'elem'       => 'body',
										),
										array(
											'internal'   => true,
											'responsive' => true,
											'type'       => 'css',
											'prop'       => array( 'font-size' ),
											'elem'       => 'body p',
										),
										array(
											'internal'   => true,
											'responsive' => true,
											'type'       => 'css',
											'prop'       => array( 'font-size' ),
											'elem'       => 'body ul li',
										),
										array(
											'internal'   => true,
											'responsive' => true,
											'type'       => 'css',
											'prop'       => array( 'font-size' ),
											'elem'       => 'body ol li',
										),
									),
									'priority'        => 40,
								),
								'font_family_fs' => array(
									'type'            => 'select',
									'label'           => __( 'Font Family', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 'default',
									'choices'         => $this->web_google_fonts,
									'wfocu_transport' => array(),
									'priority'        => 50,
								),
							),
						),
					),
				);

				$this->customizer_data[] = $style_panel;

				/** PANEL: LAYOUT */
				$css_panel                     = [];
				$css_panel['wfocu_custom_css'] = array(
					'panel'    => 'no',
					'data'     => array(
						'priority'    => 140,
						'title'       => 'Custom CSS',
						'description' => '',
					),
					'sections' => array(
						'css' => array(
							'data'   => array(
								'title'    => 'Custom CSS',
								'priority' => 140,
							),
							'fields' => array(
								'code' => array(
									'type'     => 'code',
									'label'    => __( 'Custom CSS', 'woofunnels-upstroke-one-click-upsell' ),
									'choices'  => array(
										'language' => 'css',
									),
									'priority' => 10,
								),
							),
						),
					),
				);

				$this->customizer_data[] = $css_panel;

				/** PANEL: PRODUCT */
				$product_panel                  = [];
				$product_panel['wfocu_product'] = array(
					'data' => array(
						'priority'    => 40,
						'title'       => 'Product',
						'description' => '',
					),
				);

				$priority = 1;

				foreach ( $offer_data->products as $hash_key => $product ) {
					if ( isset( $product->id ) && $product->id > 0 ) {

						$product_obj = wc_get_product( $product->id );
						$title       = $product->name;
						$short_desc  = $product_obj->get_short_description();
						$main_img    = $product_obj->get_image_id();
						$gallery_img = $product_obj->get_gallery_image_ids();

						$gallery      = array();
						$images_taken = array();
						if ( ! empty( $main_img ) ) {
							$gallery[]['gallery'] = (int) $main_img;
							$images_taken[]       = (int) $main_img;
						}

						if ( is_array( $gallery_img ) && count( $gallery_img ) > 0 ) {
							foreach ( $gallery_img as $gallerys ) {
								$gallery[]['gallery'] = (int) $gallerys;
								$images_taken[]       = (int) $gallerys;
							}
						}

						/**
						 * Variation images to be bunch with the other gallery images
						 */
						if ( isset( $product->variations_data ) && isset( $product->variations_data['images'] ) ) {
							foreach ( $product->variations_data['images'] as $id ) {
								if ( false === in_array( $id, $images_taken ) ) { //phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
									$gallery[]['gallery'] = (int) $id;
								}
							}
						}

						$product_panel['wfocu_product']['sections'][ 'product_' . $hash_key ] = array(
							'data'   => array(
								'title'    => $title,
								'priority' => $priority * 10,
							),
							'fields' => array(
								'ct_layout'          => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Layout', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 10,
								),
								'layout'             => array(
									'type'     => 'radio-image-text',
									'label'    => __( 'Style', 'woofunnels-upstroke-one-click-upsell' ),
									'default'  => 'style1',
									'choices'  => array(
										'style1' => array(
											'label' => __( 'Style 1', 'woofunnels-upstroke-one-click-upsell' ),
											'path'  => $this->img_path . 'product_style_1.svg',
										),
										'style2' => array(
											'label' => __( 'Style 2', 'woofunnels-upstroke-one-click-upsell' ),
											'path'  => $this->img_path . 'product_style_2.svg',
										),
										'style3' => array(
											'label' => __( 'Style 3', 'woofunnels-upstroke-one-click-upsell' ),
											'path'  => $this->img_path . 'product_style_3.svg',
										),
										'style4' => array(
											'label' => __( 'Style 4', 'woofunnels-upstroke-one-click-upsell' ),
											'path'  => $this->img_path . 'product_style_4.svg',
										),
										'style5' => array(
											'label' => __( 'Style 5', 'woofunnels-upstroke-one-click-upsell' ),
											'path'  => $this->img_path . 'product_style_5.svg',
										),
										'style6' => array(
											'label' => __( 'Style 6', 'woofunnels-upstroke-one-click-upsell' ),
											'path'  => $this->img_path . 'product_style_6.svg',
										),
										'style7' => array(
											'label' => __( 'Style 7', 'woofunnels-upstroke-one-click-upsell' ),
											'path'  => $this->img_path . 'product_style_7.svg',
										),
										'style8' => array(
											'label' => __( 'Style 8', 'woofunnels-upstroke-one-click-upsell' ),
											'path'  => $this->img_path . 'product_style_8.svg',
										),
									),
									'priority' => 20,
								),
								'images_width'       => array(
									'type'            => 'select',
									'label'           => esc_attr__( 'Product Image Width', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '5',
									'choices'         => array(
										'3' => esc_attr__( '3/12 (25%)', 'woofunnels-upstroke-one-click-upsell' ),
										'4' => esc_attr__( '4/12 (33%)', 'woofunnels-upstroke-one-click-upsell' ),
										'5' => esc_attr__( '5/12', 'woofunnels-upstroke-one-click-upsell' ),
										'6' => esc_attr__( '6/12 (50%)', 'woofunnels-upstroke-one-click-upsell' ),
									),
									'transport'       => 'refresh',
									'priority'        => 25,
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_product_product_' . $hash_key . '_layout',
											'operator' => 'in',
											'value'    => array( 'style1', 'style2', 'style3', 'style4', 'style7', 'style8' ),
										),
									),
								),
								'ct_gallery'         => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Gallery', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 30,
								),
								'images'             => array(
									'type'        => 'repeater',
									'label'       => esc_attr__( 'Product Images', 'woofunnels-upstroke-one-click-upsell' ),
									'description' => esc_attr__( 'This will override default product gallery.', 'woofunnels-upstroke-one-click-upsell' ),
									'row_label'   => array(
										'type'  => 'text',
										'value' => esc_attr__( 'Image', 'woofunnels-upstroke-one-click-upsell' ),
									),
									'default'     => $gallery,
									'fields'      => array(
										'gallery' => array(
											'type'  => 'image',
											'label' => esc_attr__( 'Image', 'woofunnels-upstroke-one-click-upsell' ),
										),
									),
									'transport'   => 'postMessage',
									'priority'    => 40,
								),
								'ct_summary'         => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Product Summary', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 50,
								),
								'title'              => array(
									'type'          => 'text',
									'label'         => __( 'Product Title', 'woofunnels-upstroke-one-click-upsell' ),
									'default'       => $title,
									'transport'     => 'postMessage',
									'priority'      => 60,
									'wfocu_partial' => array(
										'elem' => '.wfocu-pkey-' . $hash_key . ' .wfocu-product-title',
									),
								),
								'title_fs'           => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Product Title Font Size', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => array(
										'desktop' => 26,
										'tablet'  => 24,
										'mobile'  => 20,
									),
									'input_attrs'     => array(
										'step' => 1,
										'min'  => 12,
										'max'  => 48,
									),
									'units'           => array(
										'px' => 'px',
										'em' => 'em',
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'internal'   => true,
											'type'       => 'css',
											'responsive' => true,
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-pkey-' . $hash_key . ' .wfocu-product-title',
										),
									),
									'priority'        => 70,
								),
								'display_rating'     => array(
									'type'     => 'checkbox',
									'label'    => esc_attr__( 'Display Rating', 'woofunnels-upstroke-one-click-upsell' ),
									'default'  => true,
									'priority' => 80,
								),
								'reg_price_fs'       => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Regular Price Font Size', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => array(
										'desktop' => 32,
										'tablet'  => 28,
										'mobile'  => 24,
									),
									'input_attrs'     => array(
										'step' => 1,
										'min'  => 12,
										'max'  => 60,
									),
									'units'           => array(
										'px' => 'px',
										'em' => 'em',
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'internal'   => true,
											'type'       => 'css',
											'responsive' => true,
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-pkey-' . $hash_key . ' .wfocu-regular-price',
										),
									),
									'priority'        => 90,
								),
								'sale_price_fs'      => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Sale Price Font Size', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => array(
										'desktop' => 52,
										'tablet'  => 46,
										'mobile'  => 40,
									),
									'input_attrs'     => array(
										'step' => 1,
										'min'  => 12,
										'max'  => 70,
									),
									'units'           => array(
										'px' => 'px',
										'em' => 'em',
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'internal'   => true,
											'type'       => 'css',
											'responsive' => true,
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-pkey-' . $hash_key . ' .wfocu-sale-price',
										),
									),
									'priority'        => 100,
								),
								'desc'               => array(
									'type'            => 'textarea',
									'label'           => __( 'Product Description', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => $short_desc,
									'description'     => $merge_tags_description,
									'transport'       => 'postMessage',
									'priority'        => 110,
									'wfocu_transport' => array(
										array(
											'type'       => 'html',
											'line_break' => 0,
											'elem'       => '.wfocu-pkey-' . $hash_key . ' .wfocu-product-short-description',
										),
									),
								),
								'desc_fs'            => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Product Description Font Size', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => array(
										'desktop' => 15,
										'tablet'  => 15,
										'mobile'  => 14,
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
									'wfocu_transport' => array(
										array(
											'internal'   => true,
											'type'       => 'css',
											'responsive' => true,
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-pkey-' . $hash_key . ' .wfocu-product-short-description',
										),
										array(
											'internal'   => true,
											'type'       => 'css',
											'responsive' => true,
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-pkey-' . $hash_key . ' .wfocu-product-short-description p',
										),
										array(
											'internal'   => true,
											'type'       => 'css',
											'responsive' => true,
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-pkey-' . $hash_key . ' .wfocu-product-short-description li',
										),
									),
									'priority'        => 120,
								),
								'ct_tab'             => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Tabs', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 130,
								),
								'display_tabs'       => array(
									'type'        => 'checkbox',
									'label'       => esc_attr__( 'Display Product Tabs', 'woofunnels-upstroke-one-click-upsell' ),
									'description' => esc_attr__( 'Enable if you want to display product tabs.', 'woofunnels-upstroke-one-click-upsell' ),
									'default'     => true,
									'priority'    => 140,
								),
								'tab_mode'           => array(
									'type'            => 'radio-buttonset',
									'label'           => __( 'Mode', 'woofunnels-upstroke-one-click-upsell' ),
									'description'     => esc_attr__( 'Choose `Default` option to show Product native Tabs OR Custom to add manually.', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 'default',
									'choices'         => array(
										'default' => 'Default',
										'custom'  => 'Custom',
									),
									'priority'        => 150,
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_product_product_' . $hash_key . '_display_tabs',
											'operator' => '==',
											'value'    => true,
										),
									),
								),
								'tabs'               => array(
									'type'            => 'repeater',
									'label'           => esc_attr__( 'Tabs', 'woofunnels-upstroke-one-click-upsell' ),
									'description'     => esc_attr__( 'This will override default product tabs.', 'woofunnels-upstroke-one-click-upsell' ),
									'row_label'       => array(
										'type'  => 'text',
										'value' => esc_attr__( 'Tab', 'woofunnels-upstroke-one-click-upsell' ),
									),
									'fields'          => array(
										'title' => array(
											'type'     => 'text',
											'label'    => __( 'Title', 'woofunnels-upstroke-one-click-upsell' ),
											'priority' => 10,
										),
										'desc'  => array(
											'type'        => 'textarea',
											'label'       => __( 'Description', 'woofunnels-upstroke-one-click-upsell' ),
											'description' => $merge_tags_description,
											'priority'    => 20,
										),
									),
									'transport'       => 'postMessage',
									'priority'        => 160,
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_product_product_' . $hash_key . '_display_tabs',
											'operator' => '==',
											'value'    => true,
										),
										array(
											'setting'  => 'wfocu_product_product_' . $hash_key . '_tab_mode',
											'operator' => '==',
											'value'    => 'custom',
										),
									),
								),
								'tab_align'          => array(
									'type'            => 'radio-buttonset',
									'label'           => __( 'Align', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 'wfocu-tab-center',
									'choices'         => array(
										'wfocu-tab-left'   => 'Left',
										'wfocu-tab-center' => 'Center',
										'wfocu-tab-right'  => 'Right',
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'   => 'class',
											'elem'   => '.wfocu-pkey-' . $hash_key . ' .wfocu-product-tabs-view-horizontal .wfocu-product-tabs-wrapper',
											'remove' => array( 'wfocu-tab-left', 'wfocu-tab-center', 'wfocu-tab-right' ),
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_product_product_' . $hash_key . '_display_tabs',
											'operator' => '==',
											'value'    => true,
										),
									),
									'priority'        => 170,
								),
								'tab_title_fs'       => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Tab Title Font Size', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => array(
										'desktop' => 15,
										'tablet'  => 15,
										'mobile'  => 14,
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
									'wfocu_transport' => array(
										array(
											'internal'   => true,
											'type'       => 'css',
											'responsive' => true,
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-pkey-' . $hash_key . ' .wfocu-product-widget-tabs .wfocu-tab-title',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_product_product_' . $hash_key . '_display_tabs',
											'operator' => '==',
											'value'    => true,
										),
									),
									'priority'        => 180,
								),
								'ct_advanced'        => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Advanced', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 190,
								),
								'show_border'        => array(
									'type'        => 'checkbox',
									'label'       => esc_attr__( 'Enable Border', 'woofunnels-upstroke-one-click-upsell' ),
									'description' => esc_attr__( 'Enable if you want to wrap Product setcion with border', 'woofunnels-upstroke-one-click-upsell' ),
									'default'     => true,
									'priority'    => 200,
								),
								'border_type'        => array(
									'type'            => 'select',
									'label'           => esc_attr__( 'Border Type', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 'solid',
									'choices'         => array(
										'solid'  => 'Solid',
										'double' => 'Double',
										'dotted' => 'Dotted',
										'dashed' => 'Dashed',
									),
									'transport'       => 'postMessage',
									'priority'        => 210,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'border-style' ),
											'elem' => '.wfocu-pkey-' . $hash_key . '   .wfocu-product-border',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_product_product_' . $hash_key . '_show_border',
											'operator' => '==',
											'value'    => true,
										),
									),
								),
								'border_width'       => array(
									'type'            => 'slider',
									'label'           => esc_attr__( 'Border Width', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => 2,
									'choices'         => array(
										'min'  => '1',
										'max'  => '12',
										'step' => '1',
									),
									'transport'       => 'postMessage',
									'priority'        => 220,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'border-width' ),
											'elem' => '.wfocu-pkey-' . $hash_key . '   .wfocu-product-border',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_product_product_' . $hash_key . '_show_border',
											'operator' => '==',
											'value'    => true,
										),
									),
								),
								'ct_colors'          => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-upstroke-one-click-upsell' ) . '</div>',
									'priority' => 230,
								),
								'title_color'        => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Product Title Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#414349',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'priority'        => 240,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-pkey-' . $hash_key . ' .wfocu-product-title',
										),
									),
								),
								'reg_price_color'    => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Regular Price Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#8d8e92',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'priority'        => 250,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-pkey-' . $hash_key . ' .wfocu-regular-price',
										),
									),
								),
								'sale_price_color'   => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Sale Price Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#414349',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'priority'        => 260,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-pkey-' . $hash_key . ' .wfocu-sale-price',
										),
									),
								),
								'default_tab_tcolor' => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Default Tab Text Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#b2b2b2',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'priority'        => 270,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-pkey-' . $hash_key . ' .wfocu-product-widget-tabs .wfocu-tab-title',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_product_product_' . $hash_key . '_display_tabs',
											'operator' => '==',
											'value'    => true,
										),
									),
								),
								'active_tab_tcolor'  => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Active Tab Text Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#414349',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'priority'        => 280,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-pkey-' . $hash_key . ' .wfocu-product-widget-tabs .wfocu-tab-title.wfocu-active',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_product_product_' . $hash_key . '_display_tabs',
											'operator' => '==',
											'value'    => true,
										),
									),
								),
								'active_tab_color'   => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Active Tab Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#70dc1d',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'priority'        => 290,
									'wfocu_transport' => array(
										array(
											'type'   => 'css',
											'prop'   => array( 'background-color' ),
											'elem'   => '.wfocu-pkey-' . $hash_key . '  .wfocu-product-tabs-view-horizontal .wfocu-tabs-style-line .wfocu-tab-title',
											'pseudo' => 'after',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_product_product_' . $hash_key . '_display_tabs',
											'operator' => '==',
											'value'    => true,
										),
									),
								),
								'border_color'       => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Border Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#eeeded',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'priority'        => 300,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'border-color' ),
											'elem' => '.wfocu-pkey-' . $hash_key . '   .wfocu-product-border',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_product_product_' . $hash_key . '_show_border',
											'operator' => '==',
											'value'    => true,
										),
									),
								),
								'bg_color'           => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#ffffff',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'priority'        => 310,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'background-color' ),
											'elem' => '.wfocu-pkey-' . $hash_key,
										),
									),
								),
								'override_global'    => array(
									'type'        => 'checkbox',
									'label'       => esc_attr__( 'Override Global Colors', 'woofunnels-upstroke-one-click-upsell' ),
									'description' => esc_attr__( 'Enable if you want to change Content color specifically for this section.', 'woofunnels-upstroke-one-click-upsell' ),
									'default'     => false,
									'priority'    => 320,
								),
								'content_color'      => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Content Color', 'woofunnels-upstroke-one-click-upsell' ),
									'default'         => '#414349',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-pkey-' . $hash_key . '  .wfocu-product-short-description p',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-pkey-' . $hash_key . '  .wfocu-product-short-description ul li',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-pkey-' . $hash_key . '  .wfocu-product-short-description ol li',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-pkey-' . $hash_key . '  .wfocu-tab-content  p',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-pkey-' . $hash_key . ' .wfocu-tab-content  ul li',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-pkey-' . $hash_key . '  .wfocu-tab-content  ol li',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-pkey-' . $hash_key . '  .wfocu-product-attr-wrapper',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_product_product_' . $hash_key . '_override_global',
											'operator' => '==',
											'value'    => true,
										),
									),
									'priority'        => 330,
								),
							),
						);

						$priority ++;

					}
				}
				$this->customizer_data[] = $product_panel;
				$this->customizer_data   = apply_filters( 'wfocu_customizer_fieldset', $this->customizer_data );
				/** Set default values against all customizer keys */
				WFOCU_Common::set_customizer_fields_default_vals( $this->customizer_data );
			}
		}

		public function get_group() {
			return 'customizer';
		}

	}

	return WFOCU_Template_Sp_Classic::get_instance();
}