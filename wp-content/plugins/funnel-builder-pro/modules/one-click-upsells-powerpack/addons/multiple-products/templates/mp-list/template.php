<?php
if ( ! class_exists( 'WFOCU_Template_MP_List' ) ) {
	class WFOCU_Template_MP_List extends WFOCU_Customizer_Common {

		private static $ins = null;
		public $template_slug = 'mp-list';
		protected $section_fields = array();
		protected $offer_data = null;
		protected $offer_products_meta = null;
		protected $change_set = array();
		protected $sections = array( 'wfocu_section' );
		protected $template_dir = __DIR__;
		protected $offer_id = 0;
		public $web_google_fonts = [
			'default'   => 'Default',
			'Open Sans' => 'Open Sans',
		];

		public function __construct() {
			parent::__construct();
			add_action( 'wfocu_assets_styles', array( $this, 'add_styles' ) );
			add_action( 'init', array( $this, 'get_customizer_data' ), 28 );
			add_filter( 'wfocu_view_body_classes', array( $this, 'add_body_classes' ) );
			add_action( 'header_print_in_head', array( $this, 'template_specific_css' ), 9 );
			add_filter( 'wfocu_get_template_part_path', array( $this, 'maybe_set_template_path' ), 10, 2 );
			if ( ! isset( $this->no_img_path ) ) {
				$this->no_img_path = $this->img_path;
			}
		}

		public static function get_instance() {
			if ( is_null( self::$ins ) ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		public function add_styles( $styles ) {

			$styles['mp-list-css']              = array(
				'path'      => plugin_dir_url( WFOCU_MP_PLUGIN_FILE ) . 'templates/mp-list/css/style.css',
				'version'   => WFOCU_VERSION,
				'in_footer' => false,
				'supports'  => array(
					'customizer',
					'customizer-preview',
					'offer',
					'offer-page',
				),
			);
			$styles['mp-list-urgency-bar-css']  = array(
				'path'      => plugin_dir_url( WFOCU_MP_PLUGIN_FILE ) . 'templates/mp-list/css/urgency-bar.css',
				'version'   => WFOCU_VERSION,
				'in_footer' => false,
				'supports'  => array(
					'customizer',
					'customizer-preview',
					'offer',
					'offer-page',
				),
			);
			$styles['mp-list-product-grid-css'] = array(
				'path'      => plugin_dir_url( WFOCU_MP_PLUGIN_FILE ) . 'templates/mp-list/css/product-grid.css',
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
			include __DIR__ . '/css.php';
		}

		public function get_customizer_data() {
			$offer_data = $this->data;

			$this->web_google_fonts = function_exists( 'bwf_get_fonts_list' ) ? bwf_get_fonts_list( 'name_key' ) : [];

			if ( is_null( $offer_data ) ) {
				return;
			}
			if ( count( get_object_vars( $offer_data->products ) ) > 0 ) {

				$layout_panel = $header_panel = $heading_panel = $buy_block_panel = $feature_panel = $review_panel = $guarantee_panel = $urgency_bar_panel = $other_panel = $footer_panel = $style_panel = $css_panel = $product_panel = array();

				$merge_tags_description = '<a href="javascript:void(0)"  onclick="wfocu_show_tb(\'WooFunnels Shortcodes\', \'wfocu_shortcode_help_box\');" >' . __( 'Click here to learn about merge tags available for this area.', 'woofunnels-upstroke-power-pack' ) . '</a>';

				/** PANEL: LAYOUT */
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
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Layout', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 10,
								),
								'style'            => array(
									'type'     => 'radio-buttonset',
									'label'    => esc_attr__( 'Mode', 'woofunnels-upstroke-power-pack' ),
									'default'  => 'wfocu-fullwidth',
									'choices'  => array(
										'wfocu-fullwidth' => esc_attr__( 'Fullwidth', 'woofunnels-upstroke-power-pack' ),
										'wfocu-boxed'     => esc_attr__( 'Boxed', 'woofunnels-upstroke-power-pack' ),
									),
									'priority' => 20,
								),
								'site_boxed_width' => array(
									'type'            => 'slider',
									'label'           => __( 'Site Boxed Width (px)', 'woofunnels-upstroke-power-pack' ),
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
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Sections', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 40,
								),
								'order'            => array(
									'type'        => 'sortable',
									'label'       => __( 'Order & Visibility', 'woofunnels-upstroke-power-pack' ),
									'description' => __( 'Drag and Drop Sections to modify its position. <br>Click on Eye icon to turn ON/OFF visibility of the section.', 'woofunnels-upstroke-power-pack' ),
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
										'header'              => esc_attr__( 'Header', 'woofunnels-upstroke-power-pack' ),
										'header_progress_bar' => esc_attr__( 'Progress Bar', 'woofunnels-upstroke-power-pack' ),
										'heading'             => esc_attr__( 'Pattern Interrupt', 'woofunnels-upstroke-power-pack' ),
										'products'            => esc_attr__( 'Product', 'woofunnels-upstroke-power-pack' ),
										'reviews'             => esc_attr__( 'Reviews', 'woofunnels-upstroke-power-pack' ),
										'features'            => esc_attr__( 'Features', 'woofunnels-upstroke-power-pack' ),
										'guarantee'           => esc_attr__( 'Guarantee', 'woofunnels-upstroke-power-pack' ),
										'urgency_bar'         => esc_attr__( 'Urgency Bar', 'woofunnels-upstroke-power-pack' ),
										'footer'              => esc_attr__( 'Footer', 'woofunnels-upstroke-power-pack' ),
									),
									'priority'    => 50,
								),
							),
						),
					),
				);

				$this->customizer_data[] = $layout_panel;
				/** PANEL: HEADER */
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
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Logo', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 10,
								),
								'logo'            => array(
									'type'          => 'image',
									'label'         => __( 'Logo Image', 'woofunnels-upstroke-power-pack' ),
									'priority'      => 20,
									'transport'     => 'postMessage',
									'wfocu_partial' => array(
										'elem'                => '.wfocu-page-header-section .wfocu-page-logo',
										'container_inclusive' => false,
									),
								),
								'logo_width'      => array(
									'type'            => 'slider',
									'label'           => __( 'Max Width', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Align', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Page Title', 'woofunnels-upstroke-power-pack' ),
									'default'         => esc_attr__( 'Special Offer', 'woofunnels-upstroke-power-pack' ),
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
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 60,
								),
								'bgcolor'         => array(
									'type'            => 'color',
									'label'           => __( 'Background Color', 'woofunnels-upstroke-power-pack' ),
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
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Layout', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 10,
								),
								'style'           => array(
									'type'      => 'radio-image-full',
									'label'     => __( 'Style', 'woofunnels-upstroke-power-pack' ),
									'default'   => 'style1',
									'choices'   => array(
										'style1' => array(
											'label' => __( 'Style 1', 'woofunnels-upstroke-power-pack' ),
											'path'  => $this->img_path . 'progress_1.svg',
										),
										'style2' => array(
											'label' => __( 'Style 2', 'woofunnels-upstroke-power-pack' ),
											'path'  => $this->img_path . 'progress_2.svg',
										),
									),
									'priority'  => 20,
									'transport' => 'auto',
								),
								'ct_progress'     => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Steps', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 30,
								),
								'step1t'          => array(
									'type'            => 'text',
									'label'           => __( 'Step 1 Text', 'woofunnels-upstroke-power-pack' ),
									'default'         => esc_attr__( 'Order Submitted', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Step 2 Text', 'woofunnels-upstroke-power-pack' ),
									'default'         => esc_attr__( 'Special Offer', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Step 3 Text', 'woofunnels-upstroke-power-pack' ),
									'default'         => esc_attr__( 'Order Receipt', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Step Text', 'woofunnels-upstroke-power-pack' ),
									'default'         => esc_attr__( 'Step 2 of 3: Customize your order', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Step Font Size', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Progress Percentage', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Progress Bar Text', 'woofunnels-upstroke-power-pack' ),
									'default'         => esc_attr__( '{{percentage}} Complete', 'woofunnels-upstroke-power-pack' ),
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
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 110,
								),
								'step_tcolor'     => array(
									'type'            => 'color',
									'label'           => __( 'Step Text Color', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Step Base Color', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Step Active Color', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Step Border Color', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Background Color', 'woofunnels-upstroke-power-pack' ),
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
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Content', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 10,
								),
								'head'           => array(
									'type'          => 'textarea',
									'label'         => __( 'Headline', 'woofunnels-upstroke-power-pack' ),
									'description'   => $merge_tags_description,
									'default'       => __( "Wait <span class=\"wfocu-highlight\">{{customer_first_name}}</span>! We've got a special offer for you that complements your purchase." ),
									'transport'     => 'postMessage',
									'wfocu_partial' => array(
										'elem' => '.wfocu-top-headings .wfocu-top-heading',
									),
									'priority'      => 20,
								),
								'head_fs'        => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Headline Font Size', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Sub Headline', 'woofunnels-upstroke-power-pack' ),
									'description'     => $merge_tags_description,
									'default'         => esc_attr__( 'Check these out!', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Sub Headline Font Size', 'woofunnels-upstroke-power-pack' ),
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
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 60,
								),
								'head_color'     => array(
									'type'            => 'color',
									'label'           => __( 'Headline Color', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Sub Headline Color', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Background Color', 'woofunnels-upstroke-power-pack' ),
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
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Layout', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 10,
								),
								'style'               => array(
									'type'     => 'radio-image-full',
									'label'    => esc_attr__( 'Style', 'woofunnels-upstroke-power-pack' ),
									'default'  => 'style1',
									'choices'  => array(
										'style1' => array(
											'label' => __( 'Style 1', 'woofunnels-upstroke-power-pack' ),
											'path'  => $this->img_path . 'buy_block_1.svg',
										),
									),
									'priority' => 20,
								),
								'ct_buy_block'        => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Buy Block', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 30,
								),
								'accept_btn_text1'    => array(
									'type'            => 'textarea',
									'description'     => $merge_tags_description,
									'label'           => esc_attr__( 'Accept Button Text', 'woofunnels-upstroke-power-pack' ),
									'default'         => esc_attr__( 'Yes, Add To My Order', 'woofunnels-upstroke-power-pack' ),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'html',
											'elem' => '.wfocu-buy-block-style1 .wfocu-accept-button .wfocu-text',
										),

									),
									'wfocu_partial'   => array(
										'elem' => '.wfocu-buy-block-style1 .wfocu-accept-button .wfocu-text',
									),
									'priority'        => 40,
								),
								'accept_btn_text1_fs' => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Accept Button Text Font Size', 'woofunnels-upstroke-power-pack' ),
									'default'         => array(
										'desktop' => 21,
										'tablet'  => 19,
										'mobile'  => 17,
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

									),
									'priority'        => 50,
								),
								'accept_btn_text2'    => array(
									'type'            => 'textarea',
									'description'     => $merge_tags_description,
									'label'           => esc_attr__( 'Accept Button Sub Text', 'woofunnels-upstroke-power-pack' ),
									'default'         => esc_attr__( 'We will ship it out in same package.', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Accept Button Sub Text Font Size', 'woofunnels-upstroke-power-pack' ),
									'default'         => array(
										'desktop' => 15,
										'tablet'  => 14,
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

								/** Style-2 Fields */

								'click_trigger_text'    => array(
									'type'            => 'textarea',
									'description'     => $merge_tags_description,
									'label'           => esc_attr__( 'Text Below Button', 'woofunnels-upstroke-power-pack' ),
									'default'         => __( 'We will ship it out in same package.', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Text Below Button Font Size', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => esc_attr__( 'Decline Offer Link Text', 'woofunnels-upstroke-power-pack' ),
									'default'         => esc_attr__( 'No thanks, I don’t want to take advantage of this one-time offer >', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Decline Offer Link Text Font Size', 'woofunnels-upstroke-power-pack' ),
									'default'         => array(
										'desktop' => 14,
										'tablet'  => 14,
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
									'label'           => esc_attr__( 'Dispay Decline Offer Link  As A Button', 'woofunnels-upstroke-power-pack' ),
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
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Payment Icons', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 150,
								),
								'display_payment_icon'  => array(
									'type'        => 'checkbox',
									'label'       => esc_attr__( 'Display Payment Icons', 'woofunnels-upstroke-power-pack' ),
									'description' => esc_attr__( 'Manage settings from Other->Payment Icons Section.', 'woofunnels-upstroke-power-pack' ),
									'default'     => true,
									'priority'    => 160,
								),
								'ct_advanced'           => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Advanced', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 170,
								),
								'btn_type'              => array(
									'type'            => 'radio-buttonset',
									'label'           => esc_attr__( 'Button Style', 'woofunnels-upstroke-power-pack' ),
									'default'         => 'wfocu-btn-full',
									'choices'         => array(
										'wfocu-btn-full'     => __( 'Full', 'woofunnels-upstroke-power-pack' ),
										'wfocu-btn-flexible' => __( 'Flexible', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => esc_attr__( 'Button Width (%)', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => esc_attr__( 'Button Top/Bottom Padding', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => esc_attr__( 'Button Left/Right Padding', 'woofunnels-upstroke-power-pack' ),
									'default'         => 25,
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
									'label'           => esc_attr__( 'Button Radius', 'woofunnels-upstroke-power-pack' ),
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
									'label'       => esc_attr__( 'Enable Accept Button Icon', 'woofunnels-upstroke-power-pack' ),
									'description' => '',
									'default'     => false,
									'priority'    => 230,
								),
								'accept_btn_icon'       => array(
									'type'            => 'radio-icon',
									'label'           => esc_attr__( 'Accept Button Icon', 'woofunnels-upstroke-power-pack' ),
									'default'         => 'dashicons-cart',
									'transport'       => 'refresh',
									'choices'         => array(
										'dashicons-cart'             => __( '<span class="dashicons dashicons-cart"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-yes'              => __( '<span class="dashicons dashicons-yes"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-arrow-up'         => __( '<span class="dashicons dashicons-arrow-up"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-arrow-down'       => __( '<span class="dashicons dashicons-arrow-down"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-arrow-right'      => __( '<span class="dashicons dashicons-arrow-right"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-arrow-left'       => __( '<span class="dashicons dashicons-arrow-left"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-arrow-up-alt'     => __( '<span class="dashicons 
									dashicons-arrow-up-alt"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-arrow-down-alt'   => __( '<span class="dashicons dashicons-arrow-down-alt"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-arrow-right-alt'  => __( '<span class="dashicons dashicons-arrow-right-alt"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-arrow-left-alt'   => __( '<span class="dashicons dashicons-arrow-left-alt"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-arrow-up-alt2'    => __( '<span class="dashicons 
									dashicons-arrow-up-alt2"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-arrow-down-alt2'  => __( '<span class="dashicons dashicons-arrow-down-alt2"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-arrow-right-alt2' => __( '<span class="dashicons dashicons-arrow-right-alt2"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-arrow-left-alt2'  => __( '<span class="dashicons dashicons-arrow-left-alt2"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-heart'            => __( '<span class="dashicons dashicons-heart"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-star-filled'      => __( '<span class="dashicons dashicons-star-filled"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-plus-alt'         => __( '<span class="dashicons dashicons-plus-alt"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-awards'           => __( '<span class="dashicons dashicons-awards"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-shield'           => __( '<span class="dashicons dashicons-shield"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-shield-alt'       => __( '<span class="dashicons dashicons-shield-alt"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-thumbs-up'        => __( '<span class="dashicons dashicons-thumbs-up"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-thumbs-down'      => __( '<span class="dashicons dashicons-thumbs-down"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-smiley'           => __( '<span class="dashicons dashicons-smiley"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-tickets-alt'      => __( '<span class="dashicons dashicons-tickets-alt"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-tag'              => __( '<span class="dashicons dashicons-tag"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-cloud'            => __( '<span class="dashicons dashicons-cloud"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-controls-forward' => __( '<span class="dashicons dashicons-controls-forward"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-controls-back'    => __( '<span class="dashicons dashicons-controls-back"></span>', 'woofunnels-upstroke-power-pack' ),

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

								'btn_effect' => array(
									'type'            => 'select',
									'label'           => esc_attr__( 'Button Hover Effect', 'woofunnels-upstroke-power-pack' ),
									'default'         => 'no-effect',
									'choices'         => array(
										'none'                        => __( 'No effect', 'woofunnels-upstroke-power-pack' ),
										'wfocu-btn-pulse-grow'        => __( 'Pulse Grow', 'woofunnels-upstroke-power-pack' ),
										'wfocu-btn-bounce-in'         => __( 'Bounce In', 'woofunnels-upstroke-power-pack' ),
										'wfocu-btn-wobble-horizontal' => __( 'Wobble', 'woofunnels-upstroke-power-pack' ),
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'   => 'class',
											'remove' => array( 'wfocu-btn-pulse-grow', 'wfocu-btn-bounce-in', 'wfocu-btn-wobble-horizontal' ),
											'elem'   => '.wfocu-buy-block-style1 .wfocu-accept-button',
										),

									),
									'priority'        => 270,
								),

								'ct_accept_btn' => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Accept Button Colors', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 280,
								),

								'ct_accept_btn_state'            => array(
									'type'      => 'radio-buttonset',
									'label'     => '',
									'default'   => 'normal',
									'choices'   => array(
										'normal' => __( 'Normal', 'woofunnels-upstroke-power-pack' ),
										'hover'  => __( 'Hover', 'woofunnels-upstroke-power-pack' ),
									),
									'transport' => 'postMessage',
									'priority'  => 290,
								),
								'accept_btn_bg_color'            => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Background Color', 'woofunnels-upstroke-power-pack' ),
									'default'         => '#70dc1d',
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'     => 'css',
											'prop'     => array( 'background-color' ),
											'elem'     => '.wfocu-buy-block-style1 .wfocu-accept-button',
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
									'label'           => esc_attr__( 'Text Color', 'woofunnels-upstroke-power-pack' ),
									'default'         => '#ffffff',
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'     => 'css',
											'prop'     => array( 'color' ),
											'elem'     => '.wfocu-buy-block-style1 .wfocu-accept-button',
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
									'label'           => esc_attr__( 'Bottom Shadow Color', 'woofunnels-upstroke-power-pack' ),
									'default'         => '#00a300',
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'   => 'css',
											'prop'   => array( 'box-shadow' ),
											'prefix' => '0px 4px 0px ',
											'elem'   => '.wfocu-buy-block-style1 .wfocu-accept-button',
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
									'label'           => esc_attr__( 'Background Color', 'woofunnels-upstroke-power-pack' ),
									'default'         => '#89e047',
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'  => 'css',
											'hover' => true,
											'prop'  => array( 'background-color' ),
											'elem'  => '.wfocu-buy-block-style1 .wfocu-accept-button',
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
									'label'           => esc_attr__( 'Text Color', 'woofunnels-upstroke-power-pack' ),
									'default'         => '#ffffff',
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'  => 'css',
											'prop'  => array( 'color' ),
											'elem'  => '.wfocu-buy-block-style1 .wfocu-accept-button',
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
									'label'           => esc_attr__( 'Text Below Button Color', 'woofunnels-upstroke-power-pack' ),
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

								'ct_skip_offer'                 => array(
									'type'            => 'custom',
									'default'         => '<div class="options-title-divider">' . esc_html__( 'Decline Offer Colors', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority'        => 430,
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_buy_block_buy_block_style',
											'value'    => 'style1',
											'operator' => '==',
										),
									),
								),
								'ct_skip_offer_state'           => array(
									'type'            => 'radio-buttonset',
									'label'           => '',
									'default'         => 'normal',
									'choices'         => array(
										'normal' => __( 'Normal', 'woofunnels-upstroke-power-pack' ),
										'hover'  => __( 'Hover', 'woofunnels-upstroke-power-pack' ),
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
								'skip_offer_btn_bg_color'       => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Button Background Color', 'woofunnels-upstroke-power-pack' ),
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
								'skip_offer_text_color'         => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Text Color', 'woofunnels-upstroke-power-pack' ),
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
								'skip_offer_btn_bg_color_hover' => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Button Background Color', 'woofunnels-upstroke-power-pack' ),
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
								'skip_offer_text_color_hover'   => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Text Color', 'woofunnels-upstroke-power-pack' ),
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
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Heading', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 10,
								),
								'heading'           => array(
									'type'          => 'textarea',
									'label'         => __( 'Heading', 'woofunnels-upstroke-power-pack' ),
									'description'   => $merge_tags_description,
									'default'       => esc_attr__( 'Add this awesome item to your cart with all its amazing benefits', 'woofunnels-upstroke-power-pack' ),
									'transport'     => 'postMessage',
									'wfocu_partial' => array(
										'elem' => '.wfocu-feature-section .wfocu-section-headings .wfocu-heading',
									),
									'priority'      => 20,
								),
								'sub_heading'       => array(
									'type'            => 'textarea',
									'label'           => __( 'Sub Heading', 'woofunnels-upstroke-power-pack' ),
									'description'     => $merge_tags_description,
									'default'         => esc_attr__( 'Order Now and You’ll Get..', 'woofunnels-upstroke-power-pack' ),
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
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Features', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 40,
								),
								'reasons'           => array(
									'type'      => 'repeater',
									'label'     => esc_attr__( 'Features', 'woofunnels-upstroke-power-pack' ),
									'priority'  => 50,
									'row_label' => array(
										'type'  => 'text',
										'value' => esc_attr__( 'Feature', 'woofunnels-upstroke-power-pack' ),
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
											'label'   => __( 'Feature Text', 'woofunnels-upstroke-power-pack' ),
											'default' => '',
										),
									),
								),
								'ct_desc'           => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Additional Description', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 60,
								),
								'additional_text'   => array(
									'type'            => 'textarea',
									'label'           => __( 'Additional Text', 'woofunnels-upstroke-power-pack' ),
									'description'     => $merge_tags_description,
									'default'         => esc_attr__( 'Handle an objection or answer an important question. Use this text section here to let them know why they should click the button below.', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Align', 'woofunnels-upstroke-power-pack' ),
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
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Buy Block', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 90,
								),
								'display_buy_block' => array(
									'type'        => 'checkbox',
									'label'       => esc_attr__( 'Display Buy Block', 'woofunnels-upstroke-power-pack' ),
									'description' => esc_attr__( 'Enable if you want to display buy block.', 'woofunnels-upstroke-power-pack' ),
									'default'     => true,
									'priority'    => 100,
								),
								'ct_colors'         => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 110,
								),
								'icon_color'        => array(
									'type'     => 'color',
									'label'    => esc_attr__( 'Icon Color', 'woofunnels-upstroke-power-pack' ),
									'default'  => '#70dc1d',
									'choices'  => array(
										'alpha' => true,
									),
									'priority' => 120,
								),
								'bg_color'          => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Background Color', 'woofunnels-upstroke-power-pack' ),
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
									'label'       => esc_attr__( 'Override Global Color Settings', 'woofunnels-upstroke-power-pack' ),
									'description' => esc_attr__( 'Enable if you want to change Heading, Sub Heading, Content color specifically for this section.', 'woofunnels-upstroke-power-pack' ),
									'default'     => false,
									'priority'    => 140,
								),
								'heading_color'     => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Heading Text Color', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => esc_attr__( 'Sub Heading Text Color', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => esc_attr__( 'Content Color', 'woofunnels-upstroke-power-pack' ),
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
						'label'           => esc_attr__( 'Display Product Variation Selection', 'woofunnels-upstroke-power-pack' ),
						'description'     => esc_attr__( 'Enable if you want to display product variation selection form.', 'woofunnels-upstroke-power-pack' ),
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
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Heading', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 10,
								),
								'heading'           => array(
									'type'          => 'textarea',
									'label'         => __( 'Heading', 'woofunnels-upstroke-power-pack' ),
									'description'   => $merge_tags_description,
									'default'       => esc_attr__( 'Trusted and Raved about by 1000s of our customers...', 'woofunnels-upstroke-power-pack' ),
									'transport'     => 'postMessage',
									'wfocu_partial' => array(
										'elem' => '.wfocu-review-section .wfocu-section-headings .wfocu-heading',
									),
									'priority'      => 20,
								),
								'sub_heading'       => array(
									'type'            => 'textarea',
									'label'           => __( 'Sub Heading', 'woofunnels-upstroke-power-pack' ),
									'description'     => $merge_tags_description,
									'default'         => esc_attr__( 'You\'ll love it too...', 'woofunnels-upstroke-power-pack' ),
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
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Review Box', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 40,
								),
								'rtype'             => array(
									'type'     => 'radio-buttonset',
									'label'    => __( 'Product Reviews', 'woofunnels-upstroke-power-pack' ),
									'default'  => 'manual',
									'choices'  => array(
										'manual'    => 'Manual',
										'automatic' => 'Automatic',

									),
									'priority' => 50,
								),
								'rthreshold'        => array(
									'type'            => 'slider',
									'label'           => __( 'Show Reviews With Ratings', 'woofunnels-upstroke-power-pack' ),
									'description'     => __( 'Greater than or equal to', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'No. Of Reviews To Show ', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => esc_attr__( 'Reviews', 'woofunnels-upstroke-power-pack' ),
									'priority'        => 80,
									'row_label'       => array(
										'type'  => 'text',
										'value' => esc_attr__( 'Review', 'woofunnels-upstroke-power-pack' ),
									),
									'default'         => array(
										array(
											'heading' => esc_attr__( 'Best Solution For A Major Problem', 'woofunnels-upstroke-power-pack' ),
											'message' => 'Duis ullamcorper ipsum et mi lacinia, et laoreet nibh aliquet. Proin fermentum, libero in imperdiet scelerisque, est lorem consectetur quam, sit amet semper velit tortor id eros. Praesent enim tortor, auctor sed bibendum ut, scelerisque ut magna. Duis ullamcorper ipsum et mi ',
											'name'    => 'Tamigachi ',
											'date'    => '2018-10-03',
											'rating'  => '5',
											'image'   => $this->no_img_path . 'no_image.jpg',
										),
										array(
											'heading' => esc_attr__( 'Best Solution For A Major Problem2', 'woofunnels-upstroke-power-pack' ),
											'message' => 'Duis ullamcorper ipsum et mi lacinia, et laoreet nibh aliquet. Proin fermentum, libero in imperdiet scelerisque, est lorem consectetur quam, sit amet semper velit tortor id eros. Praesent enim tortor, auctor sed bibendum ut,',
											'name'    => 'Tamigachi ',
											'date'    => '2018-01-25',
											'rating'  => '4',
											'image'   => $this->no_img_path . 'no_image.jpg',
										),
										array(
											'heading' => esc_attr__( 'Best Solution For A Major Problem3', 'woofunnels-upstroke-power-pack' ),
											'message' => 'Duis ullamcorper ipsum et mi lacinia, et laoreet nibh aliquet. Proin fermentum, libero in imperdiet scelerisque, est lorem consectetur quam, sit amet semper velit tortor id eros. Praesent enim tortor, auctor sed bibendum ut, scelerisque ut magna. Duis ullamcorper ipsum et mi ',
											'name'    => 'Tamigachi ',
											'date'    => '2017-12-23',
											'rating'  => '4',
											'image'   => $this->no_img_path . 'no_image.jpg',
										),
										array(
											'heading' => esc_attr__( 'Best Solution For A Major Problem4', 'woofunnels-upstroke-power-pack' ),
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
											'label' => __( 'Heading', 'woofunnels-upstroke-power-pack' ),
										),
										'message' => array(
											'type'  => 'textarea',
											'label' => __( 'Testimonial', 'woofunnels-upstroke-power-pack' ),
										),
										'name'    => array(
											'type'  => 'text',
											'label' => __( 'Name', 'woofunnels-upstroke-power-pack' ),
										),
										'date'    => array(
											'type'        => 'date',
											'label'       => esc_attr__( 'Date', 'woofunnels-upstroke-power-pack' ),
											'description' => esc_attr__( 'Date Format', 'woofunnels-upstroke-power-pack' ),
										),
										'rating'  => array(
											'type'    => 'radio',
											'label'   => __( 'Rating', 'woofunnels-upstroke-power-pack' ),
											'default' => '5',
											'choices' => array(
												'1' => esc_attr__( '1', 'woofunnels-upstroke-power-pack' ),
												'2' => esc_attr__( '2', 'woofunnels-upstroke-power-pack' ),
												'3' => esc_attr__( '3', 'woofunnels-upstroke-power-pack' ),
												'4' => esc_attr__( '4', 'woofunnels-upstroke-power-pack' ),
												'5' => esc_attr__( '5', 'woofunnels-upstroke-power-pack' ),
											),
										),
										'image'   => array(
											'type'  => 'image',
											'label' => esc_attr__( 'Image', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Review Title Font Size', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Review Meta Font Size', 'woofunnels-upstroke-power-pack' ),
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
									'label'       => __( 'Display Star Ratings', 'woofunnels-upstroke-power-pack' ),
									'description' => __( 'Display Ratings inside Review Box', 'woofunnels-upstroke-power-pack' ),
									'default'     => true,
									'priority'    => 110,
								),
								'display_image'     => array(
									'type'        => 'checkbox',
									'label'       => __( 'Display Image', 'woofunnels-upstroke-power-pack' ),
									'description' => __( 'Display image inside Review Box', 'woofunnels-upstroke-power-pack' ),
									'default'     => true,
									'priority'    => 120,
								),
								'display_auth_date' => array(
									'type'        => 'checkbox',
									'label'       => __( 'Display Author With Date', 'woofunnels-upstroke-power-pack' ),
									'description' => __( 'Display author with date inside Review Box', 'woofunnels-upstroke-power-pack' ),
									'default'     => true,
									'priority'    => 130,
								),
								'ct_desc'           => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Additional Description', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 140,
								),
								'additional_text'   => array(
									'type'            => 'textarea',
									'label'           => __( 'Additional Text', 'woofunnels-upstroke-power-pack' ),
									'description'     => $merge_tags_description,
									'default'         => esc_attr__( 'Use this section to tell them what other users are saying….', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Align', 'woofunnels-upstroke-power-pack' ),
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
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Buy Block', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 170,
								),
								'display_buy_block' => array(
									'type'        => 'checkbox',
									'label'       => esc_attr__( 'Display Buy Block', 'woofunnels-upstroke-power-pack' ),
									'description' => esc_attr__( 'Enable if you want to display buy block.', 'woofunnels-upstroke-power-pack' ),
									'default'     => true,
									'priority'    => 180,
								),

								'ct_advanced'        => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Advanced', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 190,
								),
								'rbox_border_type'   => array(
									'type'            => 'select',
									'label'           => esc_attr__( 'Review Box Border Type', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => esc_attr__( 'Review Box Border Width', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => esc_attr__( 'Review Box Border Color', 'woofunnels-upstroke-power-pack' ),
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
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 230,
								),
								'rbox_heading_color' => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Review Box Title Color', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => esc_attr__( 'Review Box Meta Color', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => esc_attr__( 'Background Color', 'woofunnels-upstroke-power-pack' ),
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
									'label'       => esc_attr__( 'Override Global Colors', 'woofunnels-upstroke-power-pack' ),
									'description' => esc_attr__( 'Enable if you want to change Heading, Sub Heading, Content color specifically for this section.', 'woofunnels-upstroke-power-pack' ),
									'default'     => false,
									'priority'    => 270,
								),
								'heading_color'      => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Heading Text Color', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => esc_attr__( 'Sub Heading Text Color', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => esc_attr__( 'Content Color', 'woofunnels-upstroke-power-pack' ),
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
						'label'           => esc_attr__( 'Display Product Variation Selection', 'woofunnels-upstroke-power-pack' ),
						'description'     => esc_attr__( 'Enable if you want to display product variation selection form.', 'woofunnels-upstroke-power-pack' ),
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
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Heading', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 10,
								),
								'heading'            => array(
									'type'          => 'textarea',
									'label'         => __( 'Heading', 'woofunnels-upstroke-power-pack' ),
									'description'   => $merge_tags_description,
									'default'       => esc_attr__( 'We Standby Our Product', 'woofunnels-upstroke-power-pack' ),
									'transport'     => 'postMessage',
									'wfocu_partial' => array(
										'elem' => '.wfocu-guarantee-section .wfocu-section-headings .wfocu-heading',
									),
									'priority'      => 20,
								),
								'sub_heading'        => array(
									'type'            => 'textarea',
									'label'           => __( 'Sub Heading', 'woofunnels-upstroke-power-pack' ),
									'default'         => esc_attr__( 'Got a guarantee or a solid return/refund policy? Highlight them all here to arrest your buyer\'s last-minute objections. Reinforce the trust.', 'woofunnels-upstroke-power-pack' ),
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
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Guarantee Box', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 40,
								),
								'icon_text'          => array(
									'type'      => 'repeater',
									'label'     => esc_attr__( 'Guarantee', 'woofunnels-upstroke-power-pack' ),
									'row_label' => array(
										'type'  => 'text',
										'value' => esc_attr__( 'Guarantee', 'woofunnels-upstroke-power-pack' ),
									),
									'priority'  => 50,
									'default'   => array(
										array(
											'heading' => esc_attr__( '100% Secure Checkout', 'woofunnels-upstroke-power-pack' ),
											'message' => '100% Lorem ipsum dolor sit amet, consectetur adipiscing elit. Duis posuere dolor vitae ex maximus dictum. Aenean laoreet congue diam.',
											'builtin' => 'icon_1',
											'image'   => '',
										),
										array(
											'heading' => esc_attr__( 'Free Shipping', 'woofunnels-upstroke-power-pack' ),
											'message' => 'Free Lorem ipsum dolor sit amet, consectetur adipiscing elit. Duis posuere dolor vitae ex maximus dictum. Aenean laoreet congue diam.',
											'builtin' => 'icon_2',
											'image'   => '',
										),
										array(
											'heading' => esc_attr__( 'Refund Guarantee', 'woofunnels-upstroke-power-pack' ),
											'message' => 'Refund Lorem ipsum dolor sit amet, consectetur adipiscing elit. Duis posuere dolor vitae ex maximus dictum. Aenean laoreet congue diam.',
											'builtin' => 'icon_3',
											'image'   => '',
										),
										array(
											'heading' => esc_attr__( 'Complete Satisfaction', 'woofunnels-upstroke-power-pack' ),
											'message' => 'Complete Lorem ipsum dolor sit amet, consectetur adipiscing elit. Duis posuere dolor vitae ex maximus dictum. Aenean laoreet congue diam.',
											'builtin' => 'icon_4',
											'image'   => '',
										),
									),
									'fields'    => array(
										'heading' => array(
											'type'  => 'text',
											'label' => __( 'Heading', 'woofunnels-upstroke-power-pack' ),
										),
										'message' => array(
											'type'  => 'textarea',
											'label' => __( 'Text', 'woofunnels-upstroke-power-pack' ),
										),
										'builtin' => array(
											'type'    => 'radio-image',
											'label'   => esc_attr__( 'Built-in Icon', 'woofunnels-upstroke-power-pack' ),
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
											'label'       => esc_attr__( 'Custom Icon', 'woofunnels-upstroke-power-pack' ),
											'default'     => '',
											'description' => esc_attr__( 'Custom will override built-in selected icon.', 'woofunnels-upstroke-power-pack' ),
										),

									),
								),
								'gbox_heading_fs'    => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Heading Font Size', 'woofunnels-upstroke-power-pack' ),
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
									'label'       => __( 'Display Icon', 'woofunnels-upstroke-power-pack' ),
									'description' => __( 'Display icon inside Guarantee Box', 'woofunnels-upstroke-power-pack' ),
									'default'     => true,
									'priority'    => 70,
								),
								'ct_desc'            => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Additional Description', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 80,
								),
								'additional_text'    => array(
									'type'            => 'textarea',
									'label'           => __( 'Additional Text', 'woofunnels-upstroke-power-pack' ),
									'description'     => $merge_tags_description,
									'default'         => esc_attr__( 'Got a guarantee or a solid return/refun policy? Highlight them all here to arrest your buyer\'s last-minute objections. Reinforce the trust.', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Align', 'woofunnels-upstroke-power-pack' ),
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
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Buy Block', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 110,
								),
								'display_buy_block'  => array(
									'type'        => 'checkbox',
									'label'       => esc_attr__( 'Display Buy Block', 'woofunnels-upstroke-power-pack' ),
									'description' => esc_attr__( 'Enable if you want to display buy block.', 'woofunnels-upstroke-power-pack' ),
									'default'     => true,
									'priority'    => 120,
								),
								'ct_colors'          => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 130,
								),
								'gbox_heading_color' => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Guarantee Box Heading Color', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => esc_attr__( 'Background Color', 'woofunnels-upstroke-power-pack' ),
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
									'label'       => esc_attr__( 'Override Global Color Settings', 'woofunnels-upstroke-power-pack' ),
									'description' => esc_attr__( 'Enable if you want to change Heading, Sub Heading, Content color specifically for this section.', 'woofunnels-upstroke-power-pack' ),
									'default'     => false,
									'priority'    => 160,
								),
								'heading_color'      => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Heading Text Color', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => esc_attr__( 'Sub Heading Text Color', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => esc_attr__( 'Content Color', 'woofunnels-upstroke-power-pack' ),
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
						'label'           => esc_attr__( 'Display Product Variation Selection', 'woofunnels-upstroke-power-pack' ),
						'description'     => esc_attr__( 'Enable if you want to display product variation selection form.', 'woofunnels-upstroke-power-pack' ),
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
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Layout', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 10,
								),
								'position'  => array(
									'type'     => 'radio-image-text',
									'label'    => esc_attr__( 'Position', 'woofunnels-upstroke-power-pack' ),
									'default'  => 'sticky_header',
									'choices'  => array(
										'inline'        => array(
											'label' => __( 'Inline', 'woofunnels-upstroke-power-pack' ),
											'path'  => $this->img_path . 'sticky_middle.svg',
										),
										'sticky_header' => array(
											'label' => __( 'Sticky Header', 'woofunnels-upstroke-power-pack' ),
											'path'  => $this->img_path . 'sticky_top.svg',
										),
										'sticky_footer' => array(
											'label' => __( 'Sticky Footer', 'woofunnels-upstroke-power-pack' ),
											'path'  => $this->img_path . 'sticky_bottom.svg',
										),
									),
									'priority' => 20,
								),

								'ct_content'      => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Content', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 30,
								),
								'heading'         => array(
									'type'          => 'textarea',
									'description'   => $merge_tags_description,
									'label'         => __( 'Text', 'woofunnels-upstroke-power-pack' ),
									'default'       => esc_attr__( 'Act Fast: Grab this one-time exclusive offer before time runs out. This offer is not available elsewhere on the site.', 'woofunnels-upstroke-power-pack' ),
									'transport'     => 'postMessage',
									'wfocu_partial' => array(
										'elem' => '.wfocu-urgency-bar .wfocu-h3',
									),
									'priority'      => 40,
								),
								'heading_fs'      => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Text Font Size', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Height', 'woofunnels-upstroke-power-pack' ),
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
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Countdown Timer', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 70,
								),
								'show_timer'      => array(
									'type'        => 'checkbox',
									'label'       => esc_attr__( 'Display Countdown timer', 'woofunnels-upstroke-power-pack' ),
									'description' => esc_attr__( 'Manage settings from Other->Countdown Timer Section.', 'woofunnels-upstroke-power-pack' ),
									'default'     => true,
									'priority'    => 80,
								),
								'timer_align'     => array(
									'type'            => 'radio-buttonset',
									'label'           => __( 'Position', 'woofunnels-upstroke-power-pack' ),
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
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Advanced', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 100,
								),
								'reveal_bar_secs' => array(
									'type'        => 'text',
									'label'       => esc_attr__( 'Show Urgency Bar', 'woofunnels-upstroke-power-pack' ),
									'description' => esc_attr__( 'After x Seconds.', 'woofunnels-upstroke-power-pack' ),
									'default'     => '2',
									'transport'   => 'postMessage',
									'priority'    => 110,
								),
								'display_on'      => array(
									'type'     => 'multicheck',
									'label'    => __( 'Display On', 'woofunnels-upstroke-power-pack' ),
									'default'  => array( 'desktop', 'mobile' ),
									'choices'  => array(
										'desktop' => esc_attr__( 'Desktop', 'woofunnels-upstroke-power-pack' ),
										'mobile'  => esc_attr__( 'Mobile', 'woofunnels-upstroke-power-pack' ),
									),
									'priority' => 120,
								),
								'ct_colors'       => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 130,
								),
								'heading_color'   => array(
									'type'            => 'color',
									'label'           => __( 'Text Color', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => esc_attr__( 'Bar Shadow Color', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => esc_attr__( 'Background Color', 'woofunnels-upstroke-power-pack' ),
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
				$other_panel['wfocu_other'] = array(
					'panel'    => 'yes',
					'data'     => array(
						'priority'    => 100,
						'title'       => 'Other',
						'description' => '',
					),
					'sections' => array(
						'ctimer'    => array(
							'data'   => array(
								'title'    => 'Countdown Timer',
								'priority' => 10,
							),
							'fields' => array(
								'ct_layout'   => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Layout', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 10,
								),
								'skin'        => array(
									'type'    => 'radio-image-text',
									'label'   => esc_attr__( 'Skin', 'woofunnels-upstroke-power-pack' ),
									'default' => 'style1',
									'choices' => array(
										'style1' => array(
											'label' => __( 'Style 1', 'woofunnels-upstroke-power-pack' ),
											'path'  => $this->img_path . 'countdown_1.svg',
										),
										'style2' => array(
											'label' => __( 'Style 2', 'woofunnels-upstroke-power-pack' ),
											'path'  => $this->img_path . 'countdown_2.svg',
										),
									),

									'priority' => 20,
								),
								'ct_timer'    => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Countdown Timer', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 30,
								),
								'timer_hours' => array(
									'type'     => 'number',
									'label'    => __( 'Hours', 'woofunnels-upstroke-power-pack' ),
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
									'label'    => __( 'Minutes', 'woofunnels-upstroke-power-pack' ),
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
									'label'       => esc_attr__( 'Hide Hours', 'woofunnels-upstroke-power-pack' ),
									'description' => esc_attr__( 'Hide Hour\'s block if less than an hour left' ),
									'default'     => false,
									'priority'    => 60,
								),
								'zero_action'      => array(
									'type'     => 'radio',
									'label'    => esc_attr__( 'Action if Timer Hits Zero', 'woofunnels-upstroke-power-pack' ),
									'default'  => 'stay_on_page',
									'choices'  => array(
										'stay_on_page'     => 'Stay on this page',
										'redirect_to_next' => 'Redirect to next offer',

									),
									'priority' => 70,
								),
								'show_labels'      => array(
									'type'        => 'checkbox',
									'label'       => esc_attr__( 'Show Labels', 'woofunnels-upstroke-power-pack' ),
									'description' => esc_attr__( 'Enable if you want to display lables.', 'woofunnels-upstroke-power-pack' ),
									'default'     => true,
									'priority'    => 80,
								),
								'label_hrs'        => array(
									'type'            => 'text',
									'label'           => esc_attr__( 'Hours', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => esc_attr__( 'Minutes', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => esc_attr__( 'Seconds', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => esc_attr__( 'Text Above Timer', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Text Above Timer Font Size', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Timer Digit Font Size', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Timer Label Font Size', 'woofunnels-upstroke-power-pack' ),
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
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 160,
								),
								'timer_text_color' => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Text Above Timer Color', 'woofunnels-upstroke-power-pack' ),
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
									'label'    => esc_attr__( 'Timer Digit Color', 'woofunnels-upstroke-power-pack' ),
									'default'  => '#ffffff',
									'choices'  => array(
										'alpha' => true,
									),
									'priority' => 180,
								),
								'label_color'      => array(
									'type'     => 'color',
									'label'    => esc_attr__( 'Timer Label Color', 'woofunnels-upstroke-power-pack' ),
									'default'  => '#ffffff',
									'choices'  => array(
										'alpha' => true,
									),
									'priority' => 190,

								),
								'timer_bg_color'   => array(
									'type'     => 'color',
									'label'    => esc_attr__( 'Timer Background Color', 'woofunnels-upstroke-power-pack' ),
									'default'  => '#ce3362',
									'choices'  => array(
										'alpha' => true,
									),
									'priority' => 200,
								),
							),
						),
						'picons'    => array(
							'data'   => array(
								'title'    => 'Payment Icons',
								'priority' => 20,
							),
							'fields' => array(
								'ct_payment_icons' => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Icons', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 10,
								),
								'order'            => array(
									'type'     => 'sortable',
									'label'    => __( 'Order', 'woofunnels-upstroke-power-pack' ),
									'default'  => array(
										'americanexpress',
										'discover',
										'mastercard',
										'paypal',
										'visa',
									),
									'choices'  => array(
										'amazon'          => esc_attr__( 'Amazon', 'woofunnels-upstroke-power-pack' ),
										'americanexpress' => esc_attr__( 'American Express', 'woofunnels-upstroke-power-pack' ),
										'authorize'       => esc_attr__( 'Authorize', 'woofunnels-upstroke-power-pack' ),
										'briantree'       => esc_attr__( 'Briantree', 'woofunnels-upstroke-power-pack' ),
										'cirrus'          => esc_attr__( 'Cirrus', 'woofunnels-upstroke-power-pack' ),
										'dinnersclub'     => esc_attr__( 'Dinners Club', 'woofunnels-upstroke-power-pack' ),
										'directdebit'     => esc_attr__( 'Direct Debit', 'woofunnels-upstroke-power-pack' ),
										'discover'        => esc_attr__( 'Discover', 'woofunnels-upstroke-power-pack' ),
										'ebay'            => esc_attr__( 'Ebay', 'woofunnels-upstroke-power-pack' ),
										'jcb'             => esc_attr__( 'Jcb', 'woofunnels-upstroke-power-pack' ),
										'maestro'         => esc_attr__( 'Maestro', 'woofunnels-upstroke-power-pack' ),
										'mastercard'      => esc_attr__( 'Master Card', 'woofunnels-upstroke-power-pack' ),
										'paypal'          => esc_attr__( 'PayPal', 'woofunnels-upstroke-power-pack' ),
										'solo'            => esc_attr__( 'Solo', 'woofunnels-upstroke-power-pack' ),
										'stripe'          => esc_attr__( 'Stripe', 'woofunnels-upstroke-power-pack' ),
										'switch'          => esc_attr__( 'Switch', 'woofunnels-upstroke-power-pack' ),
										'visa'            => esc_attr__( 'Visa', 'woofunnels-upstroke-power-pack' ),
										'visaelectron'    => esc_attr__( 'Visa Electron', 'woofunnels-upstroke-power-pack' ),
									),
									'priority' => 20,
								),
								'custom'           => array(
									'type'        => 'image',
									'label'       => __( 'Custom Icon', 'woofunnels-upstroke-power-pack' ),
									'default'     => '',
									'description' => esc_attr__( 'Image size must not be greater than 60px.', 'woofunnels-upstroke-power-pack' ),
									'priority'    => 30,
								),
								'ct_colors'        => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 40,
								),
								'color'            => array(
									'type'            => 'radio-buttonset',
									'label'           => __( 'Icon Color', 'woofunnels-upstroke-power-pack' ),
									'default'         => 'wfocu-original-color',
									'choices'         => array(
										'wfocu-original-color'  => esc_attr__( 'Original', 'woofunnels-upstroke-power-pack' ),
										'wfocu-greyscale-color' => esc_attr__( 'Greyscale', 'woofunnels-upstroke-power-pack' ),

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
						'hl_pblock' => array(
							'data'   => array(
								'title'    => 'Highlighted Product',
								'priority' => 30,
							),
							'fields' => array(
								'ct_badge' => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Badge', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 10,
								),

								'badge_text'    => array(
									'type'            => 'text',
									'label'           => __( 'Badge Text', 'woofunnels-upstroke-power-pack' ),
									'default'         => 'BEST<br> VALUE',
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'html',
											'elem' => '.wfocu-best-badge span',
										),
									),
									'priority'        => 20,
								),
								'ct_advanced'   => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Advanced', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 30,
								),
								'border_type'   => array(
									'type'            => 'select',
									'label'           => esc_attr__( 'Border Type', 'woofunnels-upstroke-power-pack' ),
									'default'         => 'solid',
									'choices'         => array(
										'none'   => 'None',
										'solid'  => 'Solid',
										'double' => 'Double',
										'dotted' => 'Dotted',
										'dashed' => 'Dashed',
									),
									'transport'       => 'postMessage',
									'priority'        => 40,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'border-style' ),
											'elem' => '.wfocu-mp-wrapper .wfocu-highlight-pblock',
										),
									),
								),
								'border_width'  => array(
									'type'            => 'slider',
									'label'           => esc_attr__( 'Border Width', 'woofunnels-upstroke-power-pack' ),
									'default'         => 5,
									'choices'         => array(
										'min'  => '1',
										'max'  => '12',
										'step' => '1',
									),
									'transport'       => 'postMessage',
									'priority'        => 50,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'border-width' ),
											'elem' => '.wfocu-mp-wrapper .wfocu-highlight-pblock',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_other_hl_pblock_border_type',
											'operator' => '!=',
											'value'    => 'none',
										),
									),
								),
								'ct_accept_btn' => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Button Colors', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 60,
								),

								'ct_accept_btn_state'            => array(
									'type'      => 'radio-buttonset',
									'label'     => '',
									'default'   => 'normal',
									'choices'   => array(
										'normal' => __( 'Normal', 'woofunnels-upstroke-power-pack' ),
										'hover'  => __( 'Hover', 'woofunnels-upstroke-power-pack' ),
									),
									'transport' => 'postMessage',
									'priority'  => 70,
								),
								'accept_btn_bg_color'            => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Background Color', 'woofunnels-upstroke-power-pack' ),
									'default'         => '#70dc1d',
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'     => 'css',
											'prop'     => array( 'background-color' ),
											'elem'     => '.wfocu-highlight-pblock .wfocu-buy-block-style1 .wfocu-accept-button',
											'internal' => true,
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_other_hl_pblock_ct_accept_btn_state',
											'value'    => 'normal',
											'operator' => '==',
										),
									),
									'priority'        => 80,
								),
								'accept_btn_text_color'          => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Text Color', 'woofunnels-upstroke-power-pack' ),
									'default'         => '#ffffff',
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'     => 'css',
											'prop'     => array( 'color' ),
											'elem'     => '.wfocu-highlight-pblock .wfocu-buy-block-style1 .wfocu-accept-button',
											'internal' => true,
										),

									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_other_hl_pblock_ct_accept_btn_state',
											'value'    => 'normal',
											'operator' => '==',
										),
									),
									'priority'        => 90,
								),
								'accept_btn_bottom_shadow_color' => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Bottom Shadow Color', 'woofunnels-upstroke-power-pack' ),
									'default'         => '#00a300',
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'   => 'css',
											'prop'   => array( 'box-shadow' ),
											'prefix' => '0px 4px 0px ',
											'elem'   => '.wfocu-highlight-pblock .wfocu-buy-block-style1 .wfocu-accept-button',
										),

									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_other_hl_pblock_ct_accept_btn_state',
											'value'    => 'normal',
											'operator' => '==',
										),
									),
									'priority'        => 100,
								),
								'accept_btn_bg_color_hover'      => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Background Color', 'woofunnels-upstroke-power-pack' ),
									'default'         => '#89e047',
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'  => 'css',
											'hover' => true,
											'prop'  => array( 'background-color' ),
											'elem'  => '.wfocu-highlight-pblock .wfocu-buy-block-style1 .wfocu-accept-button',
										),

									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_other_hl_pblock_ct_accept_btn_state',
											'value'    => 'hover',
											'operator' => '==',
										),
									),
									'priority'        => 110,
								),
								'accept_btn_text_color_hover'    => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Text Color', 'woofunnels-upstroke-power-pack' ),
									'default'         => '#ffffff',
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'  => 'css',
											'prop'  => array( 'color' ),
											'elem'  => '.wfocu-highlight-pblock .wfocu-buy-block-style1 .wfocu-accept-button',
											'hover' => true,
										),

									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_other_hl_pblock_ct_accept_btn_state',
											'value'    => 'hover',
											'operator' => '==',
										),
									),
									'priority'        => 120,
								),

								'ct_colors'      => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 130,
								),
								'badge_tcolor'   => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Badge Text Color', 'woofunnels-upstroke-power-pack' ),
									'default'         => '#ffffff',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-highlight-pblock .wfocu-best-badge span',
										),
									),
									'priority'        => 140,
								),
								'badge_bg_color' => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Badge Background Color', 'woofunnels-upstroke-power-pack' ),
									'default'         => '#6fdc1e',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'   => 'css',
											'prop'   => array( 'border-top-color' ),
											'elem'   => '.wfocu-highlight-pblock .wfocu-best-badge',
											'pseudo' => 'before',
										),
									),
									'priority'        => 150,

								),
								'border_color'   => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Border Color', 'woofunnels-upstroke-power-pack' ),
									'default'         => '#ff9919',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'priority'        => 160,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'border-color' ),
											'elem' => '.wfocu-mp-wrapper .wfocu-highlight-pblock',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_other_hl_pblock_border_type',
											'operator' => '!=',
											'value'    => 'none',
										),
									),
								),
							),
						),

					),
				);

				$this->customizer_data[] = $other_panel;
				/** PANEL: FOOTER */
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
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Content', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 10,
								),
								'f_text'           => array(
									'type'          => 'textarea',
									'description'   => $merge_tags_description,
									'label'         => __( 'Text', 'woofunnels-upstroke-power-pack' ),
									'default'       => __( 'Secure checkout - 100% protected & safe.', 'woofunnels-upstroke-power-pack' ),
									'transport'     => 'postMessage',
									'wfocu_partial' => array(
										'elem' => '.wfocu-page-footer-section .wfocu-footer-text',
									),
									'priority'      => 20,
								),
								'f_text_fs'        => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Text Font Size', 'woofunnels-upstroke-power-pack' ),
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
									'label'     => esc_attr__( 'Bottom Links', 'woofunnels-upstroke-power-pack' ),
									'row_label' => array(
										'type'  => 'text',
										'value' => esc_attr__( 'Footer Link', 'woofunnels-upstroke-power-pack' ),
									),
									'default'   => array(
										array(
											'name' => esc_attr__( 'Disclaimer', 'woofunnels-upstroke-power-pack' ),
											'link' => '#',
										),
										array(
											'name' => esc_attr__( 'Terms & Conditions', 'woofunnels-upstroke-power-pack' ),
											'link' => '#',
										),
										array(
											'name' => esc_attr__( 'Privacy Policy', 'woofunnels-upstroke-power-pack' ),
											'link' => '#',
										),
									),
									'fields'    => array(
										'name' => array(
											'type'  => 'text',
											'label' => __( 'Name', 'woofunnels-upstroke-power-pack' ),
										),
										'link' => array(
											'type'  => 'text',
											'label' => __( 'Link', 'woofunnels-upstroke-power-pack' ),
										),
									),
									'priority'  => 40,
								),
								'f_links_fs'       => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Bottom Links Font Size', 'woofunnels-upstroke-power-pack' ),
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
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Payment Icons', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 60,
								),
								'f_payment_icons'  => array(
									'type'        => 'checkbox',
									'label'       => esc_attr__( 'Display Payment Icons', 'woofunnels-upstroke-power-pack' ),
									'description' => esc_attr__( 'Manage settings from Other->Payment Icons Section.', 'woofunnels-upstroke-power-pack' ),
									'default'     => true,
									'transport'   => 'refresh',
									'priority'    => 70,
								),
								'ct_colors'        => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 80,
								),
								'f_text_color'     => array(
									'type'            => 'color',
									'label'           => __( 'Text Color', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Bottom Links Color', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => esc_attr__( 'Background Color', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => esc_attr__( 'Site Background Color ', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => esc_attr__( 'Heading Text Color', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => esc_attr__( 'Sub Heading Text Color', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => esc_attr__( 'Content Color', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Highlight Color', 'woofunnels-upstroke-power-pack' ),
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
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Font Size', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 10,
								),
								'heading_fs'     => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Heading', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Sub Heading', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Content', 'woofunnels-upstroke-power-pack' ),
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
									'label'           => __( 'Font Family', 'woofunnels-upstroke-power-pack' ),
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
									'label'    => __( 'Custom CSS', 'woofunnels-upstroke-power-pack' ),
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
				$product_panel['wfocu_product'] = array(
					'data'     => array(
						'priority'    => 40,
						'title'       => 'Product',
						'description' => '',
					),
					'sections' => array(
						'settings' => array(
							'data'   => array(
								'title'    => 'Settings',
								'priority' => 140,
							),
							'fields' => array(
								'ct_headings'       => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Heading', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 10,
								),
								'heading'           => array(
									'type'        => 'textarea',
									'label'       => __( 'Heading', 'woofunnels-upstroke-power-pack' ),
									'description' => $merge_tags_description,
									'default'     => __( 'Pick one of these offers and pocket instant savings of up to <span class="wfocu-highlight">XX% off</span>', 'woofunnels-upstroke-power-pack' ),

									'transport'     => 'postMessage',
									'wfocu_partial' => array(
										'elem' => '.wfocu-product-section .wfocu-section-headings .wfocu-heading',
									),
									'priority'      => 20,
								),
								'sub_heading'       => array(
									'type'            => 'textarea',
									'label'           => __( 'Sub Heading', 'woofunnels-upstroke-power-pack' ),
									'description'     => $merge_tags_description,
									'default'         => esc_attr__( 'This offer is time sensitive and only available for next few minutes...', 'woofunnels-upstroke-power-pack' ),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'html',
											'elem' => '.wfocu-product-section .wfocu-section-headings .wfocu-sub-heading',
										),
									),
									'priority'        => 30,
								),
								'top_desc_text'     => array(
									'type'            => 'textarea',
									'label'           => __( 'Text', 'woofunnels-upstroke-power-pack' ),
									'description'     => $merge_tags_description,
									'default'         => '',
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'   => 'html',
											'elem'   => '.wfocu-product-section .wfocu-top-content-area',
											'prefix' => '<p>',
											'suffix' => '</p>',
										),
									),
									'priority'        => 40,
								),
								'top_desc_talign'   => array(
									'type'            => 'radio-buttonset',
									'label'           => __( 'Align', 'woofunnels-upstroke-power-pack' ),
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
											'elem'   => '.wfocu-product-section .wfocu-top-content-area',
											'remove' => array( 'wfocu-text-left', 'wfocu-text-center', 'wfocu-text-right' ),
										),
									),
									'priority'        => 50,
								),
								'ct_advanced'       => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Advanced', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 60,
								),
								'show_border'       => array(
									'type'        => 'checkbox',
									'label'       => esc_attr__( 'Enable Border', 'woofunnels-upstroke-power-pack' ),
									'description' => esc_attr__( 'Enable if you want to wrap Product setcion with border', 'woofunnels-upstroke-power-pack' ),
									'default'     => true,
									'priority'    => 70,
								),
								'border_type'       => array(
									'type'            => 'select',
									'label'           => esc_attr__( 'Border Type', 'woofunnels-upstroke-power-pack' ),
									'default'         => 'solid',
									'choices'         => array(
										'solid'  => 'Solid',
										'double' => 'Double',
										'dotted' => 'Dotted',
										'dashed' => 'Dashed',
									),
									'transport'       => 'postMessage',
									'priority'        => 80,
									'wfocu_transport' => array(
										array(
											'type'     => 'css',
											'prop'     => array( 'border-style' ),
											'elem'     => '.wfocu-mp-wrapper .wfocu-pblock-border:not(.wfocu-highlight-pblock)',
											'internal' => true,
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_product_settings_show_border',
											'operator' => '==',
											'value'    => true,
										),
									),
								),
								'border_width'      => array(
									'type'            => 'slider',
									'label'           => esc_attr__( 'Border Width', 'woofunnels-upstroke-power-pack' ),
									'default'         => 2,
									'choices'         => array(
										'min'  => '1',
										'max'  => '12',
										'step' => '1',
									),
									'transport'       => 'postMessage',
									'priority'        => 90,
									'wfocu_transport' => array(
										array(
											'type'     => 'css',
											'prop'     => array( 'border-width' ),
											'elem'     => '.wfocu-mp-wrapper .wfocu-pblock-border:not(.wfocu-highlight-pblock) ',
											'internal' => true,
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_product_settings_show_border',
											'operator' => '==',
											'value'    => true,
										),
									),

								),
								'ct_colors'         => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 100,
								),
								'border_color'      => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Border Color', 'woofunnels-upstroke-power-pack' ),
									'default'         => '#eeeded',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'priority'        => 110,
									'wfocu_transport' => array(
										array(
											'type'     => 'css',
											'prop'     => array( 'border-color' ),
											'internal' => true,
											'elem'     => '.wfocu-mp-wrapper .wfocu-pblock-border:not(.wfocu-highlight-pblock)',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_product_settings_show_border',
											'operator' => '==',
											'value'    => true,
										),
									),
								),
								'bg_color'          => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Background Color', 'woofunnels-upstroke-power-pack' ),
									'default'         => '#ffffff',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'priority'        => 120,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'background-color' ),
											'elem' => '.wfocu-mp-product-section',
										),
									),
								),
								'override_global'   => array(
									'type'        => 'checkbox',
									'label'       => esc_attr__( 'Override Global Colors', 'woofunnels-upstroke-power-pack' ),
									'description' => esc_attr__( 'Enable if you want to change Heading, Sub Heading, Content color specifically for this section.', 'woofunnels-upstroke-power-pack' ),
									'default'     => false,
									'priority'    => 130,
								),
								'heading_color'     => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Heading Text Color', 'woofunnels-upstroke-power-pack' ),
									'default'         => '#414349',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-product-section .wfocu-section-headings .wfocu-heading',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_product_settings_override_global',
											'operator' => '==',
											'value'    => true,
										),
									),
									'priority'        => 140,
								),
								'sub_heading_color' => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Sub Heading Text Color', 'woofunnels-upstroke-power-pack' ),
									'default'         => '#414349',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-product-section .wfocu-section-headings .wfocu-sub-heading',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_product_settings_override_global',
											'operator' => '==',
											'value'    => true,
										),
									),
									'priority'        => 150,
								),
								'content_color'     => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Content Color', 'woofunnels-upstroke-power-pack' ),
									'default'         => '#414349',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-product-section .wfocu-top-content-area p',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-product-section .wfocu-top-content-area ul li',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-product-section .wfocu-top-content-area ol li',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-product-section .wfocu-content-area p',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-product-section .wfocu-content-area ul li',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-product-section .wfocu-content-area  ol li',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_product_settings_override_global',
											'operator' => '==',
											'value'    => true,
										),
									),
									'priority'        => 160,
								),

							),
						),
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

						$prod_img = 0;
						if ( ! empty( $main_img ) ) {
							$prod_img = (int) $main_img;

						}
						if ( empty( $main_img ) && is_array( $gallery_img ) && count( $gallery_img ) > 0 ) {
							$prod_img = (int) $gallery_img[0];
						}

						/*@todo If no main Img is set and No Gallery Image is set then  fetch varaitions image*/
						/**
						 * Variation images to be bunch with the other gallery images
						 */

						$product_panel['wfocu_product']['sections'][ 'product_' . $hash_key ] = array(
							'data'   => array(
								'title'    => $title,
								'priority' => $priority * 10,
							),
							'fields' => array(
								'ct_gallery'          => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Gallery', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 10,
								),
								'image'               => array(
									'type'          => 'image',
									'label'         => esc_attr__( 'Product Image', 'woofunnels-upstroke-power-pack' ),
									'description'   => esc_attr__( 'This will override default product image.', 'woofunnels-upstroke-power-pack' ),
									'default'       => $prod_img,
									'choices'       => array(
										'save_as' => 'id',
									),
									'settings'      => 'image_setting_id',
									'transport'     => 'postMessage',
									'priority'      => 20,
									'wfocu_partial' => array(
										'elem'                => '.wfocu-pkey-' . $hash_key . ' .wfocu-pblock-img',
										'container_inclusive' => false,
									),
								),
								'ct_summary'          => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Product', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 30,
								),
								'heading'             => array(
									'type'            => 'text',
									'label'           => __( 'Heading', 'woofunnels-upstroke-power-pack' ),
									'default'         => 'BUY 2 BOTTLES + 1 FREE',
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'html',
											'elem' => '.wfocu-pkey-' . $hash_key . ' .wfocu-pblock-heading',
										),
									),
									'priority'        => 40,

								),
								'heading_fs'          => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Heading Font Size', 'woofunnels-upstroke-power-pack' ),
									'default'         => array(
										'desktop' => 19,
										'tablet'  => 19,
										'mobile'  => 18,
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
											'elem'       => '.wfocu-pkey-' . $hash_key . ' .wfocu-pblock-heading',
										),
									),
									'priority'        => 50,
								),
								'sub_heading'         => array(
									'type'            => 'text',
									'label'           => __( 'Sub Heading', 'woofunnels-upstroke-power-pack' ),
									'default'         => 'Subheading Here for this product',
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'html',
											'elem' => '.wfocu-pkey-' . $hash_key . ' .wfocu-pblock-sub-heading',
										),
									),
									'priority'        => 60,
								),
								'sub_heading_fs'      => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Sub Heading Font Size', 'woofunnels-upstroke-power-pack' ),
									'default'         => array(
										'desktop' => 15,
										'tablet'  => 15,
										'mobile'  => 15,
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
											'elem'       => '.wfocu-pkey-' . $hash_key . ' .wfocu-pblock-sub-heading',
										),
									),
									'priority'        => 70,
								),
								'title'               => array(
									'type'            => 'text',
									'label'           => __( 'Product Title', 'woofunnels-upstroke-power-pack' ),
									'default'         => $title,
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type' => 'html',
											'elem' => '.wfocu-pkey-' . $hash_key . ' .wfocu-product-title',
										),
									),
									'priority'        => 80,

								),
								'title_fs'            => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Product Title Font Size', 'woofunnels-upstroke-power-pack' ),
									'default'         => array(
										'desktop' => 20,
										'tablet'  => 20,
										'mobile'  => 18,
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
									'priority'        => 90,
								),
								'reg_price_fs'        => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Regular Price Font Size', 'woofunnels-upstroke-power-pack' ),
									'default'         => array(
										'desktop' => 21,
										'tablet'  => 21,
										'mobile'  => 21,
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
									'priority'        => 100,
								),
								'sale_price_fs'       => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Sale Price Font Size', 'woofunnels-upstroke-power-pack' ),
									'default'         => array(
										'desktop' => 24,
										'tablet'  => 24,
										'mobile'  => 24,
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
									'priority'        => 110,
								),
								'text_below_price'    => array(
									'type'            => 'textarea',
									'label'           => __( 'Text Below Price', 'woofunnels-upstroke-power-pack' ),
									'description'     => $merge_tags_description,
									'default'         => '{{product_save_percentage key="' . $hash_key . '"}} OFF',
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'   => 'html',
											'elem'   => '.wfocu-pkey-' . $hash_key . ' .wfocu-text-below-price',
											'prefix' => '<p>',
											'suffix' => '</p>',
										),
									),
									'priority'        => 120,

								),
								'text_below_price_fs' => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Text Below Price Font Size', 'woofunnels-upstroke-power-pack' ),
									'default'         => array(
										'desktop' => 28,
										'tablet'  => 26,
										'mobile'  => 25,
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
											'elem'       => '.wfocu-pkey-' . $hash_key . ' .wfocu-text-below-price',
										),
										array(
											'internal'   => true,
											'type'       => 'css',
											'responsive' => true,
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-pkey-' . $hash_key . ' .wfocu-text-below-price p',
										),
									),
									'priority'        => 130,
								),
								'desc_type'           => array(
									'type'      => 'radio-buttonset',
									'label'     => esc_attr__( 'Product Description Style', 'woofunnels-upstroke-power-pack' ),
									'default'   => 'wfocu-desc-text',
									'choices'   => array(
										'wfocu-desc-text' => __( 'Text', 'woofunnels-upstroke-power-pack' ),
										'wfocu-desc-list' => __( 'List', 'woofunnels-upstroke-power-pack' ),
									),
									'transport' => 'refresh',

									'priority' => 140,
								),
								'desc'                => array(
									'type'            => 'textarea',
									'label'           => __( 'Product Description', 'woofunnels-upstroke-power-pack' ),
									'default'         => $short_desc,
									'description'     => $merge_tags_description,
									'transport'       => 'postMessage',
									'priority'        => 150,
									'wfocu_transport' => array(
										array(
											'type'       => 'html',
											'line_break' => 0,
											'elem'       => '.wfocu-pkey-' . $hash_key . ' .wfocu-product-short-description',
											'prefix'     => '<p>',
											'suffix'     => '</p>',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_product_product_' . $hash_key . '_desc_type',
											'value'    => 'wfocu-desc-text',
											'operator' => '==',
										),
									),
								),

								'desc_list'              => array(
									'type'            => 'repeater',
									'label'           => esc_attr__( 'Description List', 'woofunnels-upstroke-power-pack' ),
									'description'     => $merge_tags_description,
									'row_label'       => array(
										'type'  => 'text',
										'value' => esc_attr__( 'Check List', 'woofunnels-upstroke-power-pack' ),
									),
									'priority'        => 160,
									'default'         => array(
										array(
											'message' => 'Free standard shipping',
										),
										array(
											'message' => '30 days money-back guarantee',
										),
										array(
											'message' => '1 month supply for only $25.76 per bottle',
										),
										array(
											'message' => 'Claim saving of 14%',
										),
									),
									'fields'          => array(
										'message' => array(
											'type'  => 'text',
											'label' => __( 'Text', 'woofunnels-upstroke-power-pack' ),
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_product_product_' . $hash_key . '_desc_type',
											'value'    => 'wfocu-desc-list',
											'operator' => '==',
										),
									),
								),
								'desc_list_icon'         => array(
									'type'            => 'radio-icon',
									'label'           => esc_attr__( 'List Icon', 'woofunnels-upstroke-power-pack' ),
									'default'         => 'dashicons-yes',
									'transport'       => 'refresh',
									'choices'         => array(
										'dashicons-cart'             => __( '<span class="dashicons dashicons-cart"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-yes'              => __( '<span class="dashicons dashicons-yes"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-arrow-up'         => __( '<span class="dashicons dashicons-arrow-up"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-arrow-down'       => __( '<span class="dashicons dashicons-arrow-down"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-arrow-right'      => __( '<span class="dashicons dashicons-arrow-right"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-arrow-left'       => __( '<span class="dashicons dashicons-arrow-left"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-arrow-up-alt'     => __( '<span class="dashicons 
									dashicons-arrow-up-alt"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-arrow-down-alt'   => __( '<span class="dashicons dashicons-arrow-down-alt"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-arrow-right-alt'  => __( '<span class="dashicons dashicons-arrow-right-alt"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-arrow-left-alt'   => __( '<span class="dashicons dashicons-arrow-left-alt"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-arrow-up-alt2'    => __( '<span class="dashicons 
									dashicons-arrow-up-alt2"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-arrow-down-alt2'  => __( '<span class="dashicons dashicons-arrow-down-alt2"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-arrow-right-alt2' => __( '<span class="dashicons dashicons-arrow-right-alt2"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-arrow-left-alt2'  => __( '<span class="dashicons dashicons-arrow-left-alt2"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-heart'            => __( '<span class="dashicons dashicons-heart"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-star-filled'      => __( '<span class="dashicons dashicons-star-filled"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-plus-alt'         => __( '<span class="dashicons dashicons-plus-alt"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-awards'           => __( '<span class="dashicons dashicons-awards"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-shield'           => __( '<span class="dashicons dashicons-shield"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-shield-alt'       => __( '<span class="dashicons dashicons-shield-alt"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-thumbs-up'        => __( '<span class="dashicons dashicons-thumbs-up"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-thumbs-down'      => __( '<span class="dashicons dashicons-thumbs-down"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-smiley'           => __( '<span class="dashicons dashicons-smiley"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-tickets-alt'      => __( '<span class="dashicons dashicons-tickets-alt"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-tag'              => __( '<span class="dashicons dashicons-tag"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-cloud'            => __( '<span class="dashicons dashicons-cloud"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-controls-forward' => __( '<span class="dashicons dashicons-controls-forward"></span>', 'woofunnels-upstroke-power-pack' ),
										'dashicons-controls-back'    => __( '<span class="dashicons dashicons-controls-back"></span>', 'woofunnels-upstroke-power-pack' ),

									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_product_product_' . $hash_key . '_desc_type',
											'value'    => 'wfocu-desc-list',
											'operator' => '==',
										),
									),
									'priority'        => 170,
								),
								'desc_icon_size'         => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Description List Icon Size', 'woofunnels-upstroke-power-pack' ),
									'default'         => array(
										'desktop' => 22,
										'tablet'  => 22,
										'mobile'  => 22,
									),
									'input_attrs'     => array(
										'step' => 1,
										'min'  => 12,
										'max'  => 80,
									),
									'units'           => array(
										'px' => 'px',
									),
									'transport'       => 'postMessage',
									'wfocu_transport' => array(
										array(
											'type'       => 'css',
											'responsive' => true,
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-pkey-' . $hash_key . ' .wfocu-pblock-check-list .wfocu-licon',

										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_product_product_' . $hash_key . '_desc_type',
											'value'    => 'wfocu-desc-list',
											'operator' => '==',
										),
									),
									'priority'        => 180,
								),
								'desc_fs'                => array(
									'type'            => 'wfocu-responsive-font',
									'label'           => __( 'Description Font Size', 'woofunnels-upstroke-power-pack' ),
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
										array(
											'internal'   => true,
											'type'       => 'css',
											'responsive' => true,
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-pkey-' . $hash_key . ' .wfocu-pblock-check-list',
										),
										array(
											'internal'   => true,
											'type'       => 'css',
											'responsive' => true,
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-pkey-' . $hash_key . ' .wfocu-pblock-check-list p',
										),
										array(
											'internal'   => true,
											'type'       => 'css',
											'responsive' => true,
											'prop'       => array( 'font-size' ),
											'elem'       => '.wfocu-pkey-' . $hash_key . ' .wfocu-pblock-check-list li',
										),
										array(
											'type'       => 'css',
											'responsive' => true,
											'prop'       => array( 'line-height' ),
											'elem'       => '.wfocu-pkey-' . $hash_key . ' .wfocu-pblock-check-list .wfocu-licon',
											'callback'   => 'adjust_desc_icon_line_height',
										),
									),
									'priority'        => 190,
								),
								'ct_advanced'            => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Advanced', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 200,
								),
								'highlight'              => array(
									'type'        => 'checkbox',
									'label'       => esc_attr__( 'Highlight This Product', 'woofunnels-upstroke-power-pack' ),
									'description' => esc_attr__( 'Enable if you want to highlight this product. Manage settings from Other->Highlight Product Section.', 'woofunnels-upstroke-power-pack' ),

									'default'  => false,
									'priority' => 210,
								),
								'ct_colors'              => array(
									'type'     => 'custom',
									'default'  => '<div class="options-title-divider">' . esc_html__( 'Colors', 'woofunnels-upstroke-power-pack' ) . '</div>',
									'priority' => 220,
								),
								'heading_color'          => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Heading Color', 'woofunnels-upstroke-power-pack' ),
									'default'         => '#414349',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'priority'        => 230,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-pkey-' . $hash_key . ' .wfocu-pblock-heading',
										),
									),
								),
								'sub_heading_color'      => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Sub Heading Color', 'woofunnels-upstroke-power-pack' ),
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
											'elem' => '.wfocu-pkey-' . $hash_key . ' .wfocu-pblock-sub-heading',
										),
									),
								),
								'title_color'            => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Product Title Color', 'woofunnels-upstroke-power-pack' ),
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
											'elem' => '.wfocu-pkey-' . $hash_key . ' .wfocu-product-title',
										),
									),
								),
								'reg_price_color'        => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Regular Price Color', 'woofunnels-upstroke-power-pack' ),
									'default'         => '#8d8e92',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'priority'        => 260,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-pkey-' . $hash_key . ' .wfocu-regular-price',
										),
									),
								),
								'sale_price_color'       => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Sale Price Color', 'woofunnels-upstroke-power-pack' ),
									'default'         => '#414349',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'priority'        => 270,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-pkey-' . $hash_key . ' .wfocu-sale-price',
										),
									),
								),
								'text_below_price_color' => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Text below Price Color', 'woofunnels-upstroke-power-pack' ),
									'default'         => '#ff0000',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'priority'        => 280,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-pkey-' . $hash_key . ' .wfocu-text-below-price',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-pkey-' . $hash_key . ' .wfocu-text-below-price p',
										),
									),
								),
								'bg_color'               => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Background Color', 'woofunnels-upstroke-power-pack' ),
									'default'         => '#ffffff',
									'choices'         => array(
										'alpha' => true,
									),
									'transport'       => 'postMessage',
									'priority'        => 290,
									'wfocu_transport' => array(
										array(
											'type' => 'css',
											'prop' => array( 'background-color' ),
											'elem' => '.wfocu-pkey-' . $hash_key . ' .wfocu-pblock-inner',
										),
									),
								),
								'override_global'        => array(
									'type'        => 'checkbox',
									'label'       => esc_attr__( 'Override Global Colors', 'woofunnels-upstroke-power-pack' ),
									'description' => esc_attr__( 'Enable if you want to change Content color specifically for this section.', 'woofunnels-upstroke-power-pack' ),
									'default'     => false,
									'priority'    => 300,
								),
								'content_color'          => array(
									'type'            => 'color',
									'label'           => esc_attr__( 'Content Color', 'woofunnels-upstroke-power-pack' ),
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
											'elem' => '.wfocu-pkey-' . $hash_key . ' .wfocu-pblock-check-list ul li',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-pkey-' . $hash_key . '  .wfocu-product-attr-wrapper',
										),
										array(
											'type' => 'css',
											'prop' => array( 'color' ),
											'elem' => '.wfocu-pkey-' . $hash_key . '  .wfocu-prod-qty-wrapper',
										),
									),
									'active_callback' => array(
										array(
											'setting'  => 'wfocu_product_product_' . $hash_key . '_override_global',
											'operator' => '==',
											'value'    => true,
										),
									),
									'priority'        => 310,
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

		public function maybe_set_template_path( $existing, $slug ) {

			if ( '/product-grids/style1' === $slug ) {
				return plugin_dir_path( WFOCU_MP_PLUGIN_FILE ) . 'templates/mp-list/views' . $slug . '.php';
			}

			if ( '/product-layout/style-grid' === $slug ) {

				return plugin_dir_path( WFOCU_MP_PLUGIN_FILE ) . 'templates/mp-list/views' . $slug . '.php';
			}

			return $existing;
		}


		/**
		 * Hooked over 'wfocu_is_show_quantity_selector'
		 * Turn Off Quantity Selector on conditional basis in the rending lifecycle.
		 *
		 * @param $is_show
		 *
		 * @return bool
		 */
		public function maybe_turn_off_qty_selector( $is_show ) {

			if ( 1 === did_action( 'wfocu_front_mp_products_start' ) && 0 === did_action( 'wfocu_front_mp_products_end' ) ) {
				return $is_show;
			} else {
				return false;
			}
		}


		/**
		 * Hooked over 'wfocu_buy_btn_classes'
		 * Turn Off Quantity Selector on conditional basis in the rending lifecycle.
		 *
		 * @param $is_show
		 *
		 * @return bool
		 */
		public function maybe_add_class_to_move_to_select( $classes ) {

			if ( 1 === did_action( 'wfocu_front_mp_products_start' ) && 0 === did_action( 'wfocu_front_mp_products_end' ) ) {

				return $classes;
			} else {

				/**
				 * When Buy Buttons are not in the product sections, do not let the add the products
				 * Remove the class on which the js runs
				 *
				 */
				$array_search = array_search( 'wfocu_upsell', $classes, true );
				unset( $classes[ $array_search ] );

				/**
				 * Add custom class to attach the handler that will scroll to the product section
				 */
				array_push( $classes, 'wfocu_mp_scrollto' );

				return $classes;
			}
		}


		public function maybe_render_js() {
			?>
            <script>

                (
                    function ($) {
                        "use strict";

                        $('.wfocu_mp_scrollto').on('click', function () {
                            const variationSelector = $('div.wfocu-product-section').eq(0);
                            const body = $("html, body");
                            body.stop().animate({scrollTop: variationSelector.offset().top - 100}, 1000, 'swing', function () {

                            });
                        });

                        $(document).on('wfocu_variation_selected', function (event, key, variationID) {


                            if ($(".wfocu-pkey-" + key + " .wfocu-pblock-img").length > 0) {

                                var getImagesSet = $(".wfocu-pkey-" + key + " .wfocu-pblock-img").attr('data-gallery');
                                var getImagesIDSet = $('.wfocu_variation_selector_wrap[data-key="' + key + '"]').attr('data-images');
                                getImagesIDSet = JSON.parse(getImagesIDSet);
                                getImagesSet = JSON.parse(getImagesSet);

                                if (getImagesIDSet.hasOwnProperty(variationID)) {
                                    if (true !== window.wfocuLoaded && true === wfocuCommons.applyFilters('wfocuShowFirstImageOnload', false)) {
                                        return;
                                    }
                                    var mediaID = getImagesIDSet[variationID];
                                    $(".wfocu-pkey-" + key + " .wfocu-pblock-img img").attr('src', getImagesSet[mediaID]);
                                }
                            }
                        });
                    }
                )(jQuery);
            </script>
			<?php
		}

		public function load_hooks() {
			add_filter( 'wfocu_is_show_quantity_selector', array( $this, 'maybe_turn_off_qty_selector' ) );
			add_filter( 'wfocu_buy_btn_classes', array( $this, 'maybe_add_class_to_move_to_select' ) );
			add_action( 'footer_before_print_scripts', array( $this, 'maybe_render_js' ), 999 );
			add_action( 'footer_before_print_scripts', array( $this, 'maybe_render_js' ), 999 );

		}

		public function remove_qty_attribute_selectors_from_buy_block( $value ) {
			$value = false;

			return $value;
		}
	}

	return WFOCU_Template_MP_List::get_instance();
}