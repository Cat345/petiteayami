<?php
if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
if ( ! class_exists( 'WFACP_template_layout1' ) ) {
	#[AllowDynamicProperties]
	final class WFACP_template_layout1 extends WFACP_Pre_Built {

		private static $ins = null;


		protected $layout_setting = [];
		protected $template_slug = 'layout_1';


		protected function __construct() {
			parent::__construct();
			$this->template_dir = __DIR__;
			define( 'WFACP_TEMPLATE_MODULE_DIR', $this->template_dir . '/views/template-parts/sections' );

			$this->css_default_classes();

			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_style' ] );
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_script' ] );
			remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10 );
			remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );

			add_filter( 'wfacp_customizer_layout', [ $this, 'change_layout_order_setting' ], 11, 2 );

			add_filter( 'wfacp_layout_default_setting', [ $this, 'change_default_setting_layout_1' ], 10, 2 );
			//	add_filter( 'wfacp_forms_field', [ $this, 'change_form_setting' ], 11, 2 );
			$this->set_default_layout_setting();

			add_filter( 'wfacp_customizer_layout', [ $this, 'layout_1_customizer_fields' ], 11, 2 );
			add_filter( 'wfacp_order_summary_cols_span', [ $this, 'change_col_span_for_order_summary' ] );

			add_action( 'wfacp_below_form', function () {


				$this->excluded_other_widget();

				if ( is_array( $this->excluded_other_widget() ) && count( $this->excluded_other_widget() ) > 0 ) {
					foreach ( $this->excluded_other_widget() as $key => $value ) {
						if ( array_key_exists( $value, $this->wfacp_html_fields ) ) {
							if ( isset( $this->customizer_fields_data[ $value ] ) ) {
								$data = $this->customizer_fields_data[ $value ];
								$this->get_module( $data, false, 'wfacp_html_widget', 'wfacp_html_widget_3' );
							}

						}

					}
				}


			} );
		}

		public function change_col_span_for_order_summary( $colspan_attr1 ) {

			return '';
		}

		public function css_default_classes() {
			$css_classess      = [
				'billing_email'      => [
					'class' => 'wfacp-col-full',
				],
				'billing_first_name' => [
					'class' => 'wfacp-col-left-half',
				],
				'billing_last_name'  => [
					'class' => 'wfacp-col-left-half',
				],
				'billing_address_1'  => [
					'class' => 'wfacp-col-left-half',
				],
				'billing_address_2'  => [
					'class' => 'wfacp-col-left-half',
				],
				'billing_city'       => [
					'class' => 'wfacp-col-left-half',
				],
				'billing_postcode'   => [
					'class' => 'wfacp-col-left-third',
				],
				'billing_country'    => [
					'class' => 'wfacp-col-left-third',
				],
				'billing_state'      => [
					'class' => 'wfacp-col-left-third',
				],
				'billing_phone'      => [
					'class' => 'wfacp-col-full',
				],
				'billing_company'    => [
					'class' => 'wfacp-col-left-half',
				],

				'shipping_email'      => [
					'class' => 'wfacp-col-full',
				],
				'shipping_first_name' => [
					'class' => 'wfacp-col-left-half',
				],
				'shipping_last_name'  => [
					'class' => 'wfacp-col-left-half',
				],
				'shipping_company'    => [
					'class' => 'wfacp-col-left-half',
				],

				'shipping_address_1' => [
					'class' => 'wfacp-col-left-half',
				],
				'shipping_address_2' => [
					'class' => 'wfacp-col-left-half',
				],
				'shipping_city'      => [
					'class' => 'wfacp-col-left-half',
				],
				'shipping_postcode'  => [
					'class' => 'wfacp-col-left-third',
				],
				'shipping_country'   => [
					'class' => 'wfacp-col-left-third',
				],
				'shipping_state'     => [
					'class' => 'wfacp-col-left-third',
				],

				'shipping_phone' => [
					'class' => 'wfacp-col-full',
				],
				'order_comments' => [
					'class' => 'wfacp-col-full',
				],
			];
			$this->css_classes = apply_filters( 'wfacp_default_form_classes', $css_classess );
		}

		public function get_excluded_sidebar_sections() {
			$this->excluded_sidebar_sections = [
				'wfacp_promises_0',
				'wfacp_html_widget_3'
			];

			return $this->excluded_sidebar_sections;
		}

		public function set_default_layout_setting() {
			$selected_template_slug = $this->template_slug;

			if ( ! isset( $selected_template_slug ) ) {
				return;
			}


			$this->layout_setting = [
				'wfacp_header'         => [
					$selected_template_slug . '_rbox_border_type'   => 'none',
					$selected_template_slug . '_rbox_border_width'  => '1',
					$selected_template_slug . '_rbox_border_color'  => '#ffffff',
					$selected_template_slug . '_rbox_padding'       => 10,
					$selected_template_slug . '_section_bg_color'   => '#d2dee4',
					$selected_template_slug . '_content_text_color' => '#565e66',
				],
				'wfacp_footer'         => [
					$selected_template_slug . '_section_bg_color'   => '#d2dee4',
					$selected_template_slug . '_content_text_color' => '#565e66',
					$selected_template_slug . '_ft_text_fs'         => array(
						'desktop' => 15,
						'tablet'  => 15,
						'mobile'  => 15,
					),
				],
				'wfacp_gbadge'         => [
					$selected_template_slug . '_badge_margin_top' => 53,
					$selected_template_slug . '_badge_max_width'  => 160,

				],
				'wfacp_product'        => [
					$selected_template_slug . '_section_bg_color'   => '#f7f7f7',
					$selected_template_slug . '_heading_text_color' => '#DAA751',
					$selected_template_slug . '_content_text_color' => '#565e66',

				],
				'wfacp_benefits_0'     => [
					$selected_template_slug . '_rbox_border_type'   => 'none',
					$selected_template_slug . '_rbox_border_width'  => '1',
					$selected_template_slug . '_rbox_border_color'  => '#000000',
					$selected_template_slug . '_rbox_padding'       => 10,
					$selected_template_slug . '_section_bg_color'   => 'transparent',
					$selected_template_slug . '_heading_text_color' => '#8a9a5f',
					$selected_template_slug . '_content_text_color' => '#8a9a5f',
					$selected_template_slug . '_icon_color'         => '#8a9a5f',

				],
				'wfacp_testimonials_0' => [
					$selected_template_slug . '_rbox_border_type'   => 'none',
					$selected_template_slug . '_rbox_border_width'  => '1',
					$selected_template_slug . '_rbox_border_color'  => '#000000',
					$selected_template_slug . '_rbox_padding'       => 10,
					$selected_template_slug . '_section_bg_color'   => 'transparent',
					$selected_template_slug . '_sec_heading_color'  => '#414349',
					$selected_template_slug . '_heading_text_color' => '#414349',
					$selected_template_slug . '_content_text_color' => '#656565',

				],
				'wfacp_promises_0'     => [

					$selected_template_slug . '_section_bg_color' => 'transparent',

				],
				'wfacp_assurance_0'    => [
					$selected_template_slug . '_rbox_border_type'   => 'none',
					$selected_template_slug . '_rbox_border_width'  => '1',
					$selected_template_slug . '_rbox_border_color'  => '#000000',
					$selected_template_slug . '_rbox_padding'       => 10,
					$selected_template_slug . '_section_bg_color'   => 'transparent',
					$selected_template_slug . '_sec_heading_color'  => '#414349',
					$selected_template_slug . '_content_text_color' => '#656565',

				],
				'wfacp_customer_0'     => [
					$selected_template_slug . '_rbox_border_type'   => 'none',
					$selected_template_slug . '_rbox_border_width'  => '1',
					$selected_template_slug . '_rbox_border_color'  => '#000000',
					$selected_template_slug . '_rbox_padding'       => 10,
					$selected_template_slug . '_section_bg_color'   => 'transparent',
					$selected_template_slug . '_sec_heading_color'  => '#414349',
					$selected_template_slug . '_heading_text_color' => '#000000',
					$selected_template_slug . '_content_text_color' => '#000000',

				],
				'wfacp_style'          => [
					$selected_template_slug . '_body_background_color'    => '#f2f2f2',
					$selected_template_slug . '_sidebar_background_color' => '#F8FFE2',
				],
				'wfacp_form'           => [
					$selected_template_slug . '_rbox_padding' => 0,

					$selected_template_slug . '_btn_order-place_width'              => '100%',
					$selected_template_slug . '_btn_order-place_bg_color'           => '#24ae4e',
					$selected_template_slug . '_btn_order-place_text_color'         => '#ffffff',
					$selected_template_slug . '_btn_order-place_bg_hover_color'     => '#7aa631',
					$selected_template_slug . '_btn_order-place_text_hover_color'   => '#ffffff',
					$selected_template_slug . '_btn_order-place_fs'                 => [
						'desktop' => 30,
						'tablet'  => 25,
						'mobile'  => 20,
					],
					$selected_template_slug . '_btn_order-place_top_bottom_padding' => '12',
					$selected_template_slug . '_btn_order-place_left_right_padding' => '44',
					$selected_template_slug . '_btn_order-place_border_radius'      => '5',
					'payment_methods_heading'                                       => __( 'Payment', 'woocommerce' ),
					$selected_template_slug . '_btn_back_text_color'                => '#337ab7',
					$selected_template_slug . '_btn_back_text_hover_color'          => '#1963a2',
					$selected_template_slug . '_additional_bg_color'                => 'transparent',
					$selected_template_slug . '_additional_text_color'              => '#888888',
				],


			];
		}

		public static function get_instance() {
			if ( is_null( self::$ins ) ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		/**
		 * Using protected method no one create new instance this class
		 * WFACP_template_layout1 constructor.
		 */


		public function layout_1_customizer_fields( $panel, $key ) {

			$selected_template_slug       = $this->get_template_slug();
			$get_exluded_sidebar_sections = $this->get_excluded_sidebar_sections();
			$get_exluded_sidebar_default  = [];

			foreach ( $get_exluded_sidebar_sections as $sec_key => $sec_val ) {
				$choices_key = str_replace( 'wfacp_', '', $sec_val );
				$pos         = strpos( $choices_key, '_' );

				if ( array_key_exists( $sec_val, $this->wfacp_html_fields ) && isset( $this->wfacp_html_fields[ $sec_val ] ) ) {
					$choices_key = $this->wfacp_html_fields[ $sec_val ];

					$unset_layout_order = array_search( $sec_val, $get_exluded_sidebar_sections );


					$get_exluded_sidebar_sections1 = array_values( $get_exluded_sidebar_sections );
					unset( $get_exluded_sidebar_sections1[ $unset_layout_order ] );
					$get_exluded_sidebar_sections1 = array_values( $get_exluded_sidebar_sections1 );


				} elseif ( false !== $pos ) {
					$choices_key = substr( $choices_key, 0, $pos );
				}

				$get_exluded_sidebar_default[ $sec_val ] = ucwords( $choices_key );
				unset( $choices_key );
			}


			if ( $key == 'wfacp_layout' ) {


				$panel['sections']['section']['fields'][ $selected_template_slug . '_other_layout_widget' ] = [
					'type'        => 'sortable',
					'label'       => __( 'Elements Order & Visibility for Desktop Other Widgets', 'woofunnels-aero-checkout' ),
					'description' => __( '<b>Tip:</b> Drag and Drop Sections to modify its position. <br>Click on Eye icon to turn ON/OFF visibility of the section.', 'woofunnels-aero-checkout' ),
					'default'     => $get_exluded_sidebar_sections1,
					'choices'     => $get_exluded_sidebar_default,
					'priority'    => 51,
				];


				$arr_default = $panel['sections']['section']['fields'][ $selected_template_slug . '_sidebar_layout_order' ]['default'];
				$arr_choices = $panel['sections']['section']['fields'][ $selected_template_slug . '_sidebar_layout_order' ]['choices'];

				$finalDefault = [];
				$finalChoice  = [];
				foreach ( $arr_default as $key1 => $value1 ) {

					if ( in_array( $value1, $get_exluded_sidebar_sections ) ) {
						continue;
					}
					$finalDefault[] = $value1;

				}
				foreach ( $arr_choices as $key2 => $value2 ) {

					if ( array_key_exists( $key2, $get_exluded_sidebar_default ) ) {
						continue;
					}
					$finalChoice[ $key2 ] = $value2;

				}

				$panel['sections']['section']['fields'][ $selected_template_slug . '_sidebar_layout_order' ]['default'] = $finalDefault;
				$panel['sections']['section']['fields'][ $selected_template_slug . '_sidebar_layout_order' ]['choices'] = $finalChoice;


			}

			return $panel;

		}

		public function change_layout_order_setting( $panel_details, $section_key ) {

			$selected_template_slug = $this->get_template_slug();

			$_sidebar_default_layout_order   = $panel_details['sections']['section']['fields'][ $selected_template_slug . '_sidebar_layout_order' ]['default'];
			$_sidebar_default_layout_choices = $panel_details['sections']['section']['fields'][ $selected_template_slug . '_sidebar_layout_order' ]['choices'];

			$unset_default_value  = '';
			$final_default_layout = [];
			foreach ( $_sidebar_default_layout_order as $key => $value ) {
				if ( strpos( $value, 'wfacp_promises_' ) !== false ) {

					$unset_default_value               = $value;
					$this->excluded_sidebar_sections[] = $unset_default_value;
					continue;
				}
				$final_default_layout[] = $value;
			}

			$this->excluded_layout_sections_sidebar[] = $unset_default_value;

			unset( $_sidebar_default_layout_choices[ $unset_default_value ] );

			$panel_details['sections']['section']['fields'][ $selected_template_slug . '_sidebar_layout_order' ]['default'] = $final_default_layout;
			$panel_details['sections']['section']['fields'][ $selected_template_slug . '_sidebar_layout_order' ]['choices'] = $_sidebar_default_layout_choices;

			return $panel_details;

		}

		public function change_default_setting_layout_1( $panel_details, $panel_key ) {

			$selected_template_slug = $this->get_template_slug();
			if ( $panel_key == 'wfacp_gbadge' ) {
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_badge_icon' ]['wfacp_partial'] = [
					'elem' => '.wfacp_gbadge_icon .wfacp_product_image_sec',
				];
			}

			return $panel_details;
		}

		public function change_form_setting( $field, $key ) {

			$formData = [];

			if ( isset( $this->customizer_fields_data['wfacp_form'] ) ) {
				$formData = $this->customizer_fields_data['wfacp_form'];
			}

			if ( is_array( $formData ) && count( $formData ) <= 0 ) {

				return $field;
			}

			if ( isset( $formData['form_data']['field_style_position'] ) && $formData['form_data']['field_style_position'] == 'wfacp-label-post-inside' ) {
				if ( $field['type'] !== 'checkbox' ) {
					unset( $field['label'] );
				}
			} elseif ( $formData['form_data']['field_style_position'] == 'wfacp-label-post-outside' ) {
				//          unset( $field['placeholder'] );
			}

			return $field;

		}


		public function customizer_layout_order( $panel_details, $section_key ) {

			$selected_template_slug                                                                                               = $this->get_template_slug();
			$panel_details['sections']['section']['fields'][ $selected_template_slug . '_mobile_sections_page_order' ]['default'] = [
				'wfacp_product',
				'wfacp_form',
				'wfacp_benefits_0',
				'wfacp_testimonials_0',
				'wfacp_assurance_0',
				'wfacp_promises_0',
				'wfacp_customer_0',

			];

			return $panel_details;

		}

		public function enqueue_style() {
			parent::enqueue_script();


			if ( is_rtl() ) {
				wp_enqueue_style( 'layout9-style', $this->url . 'css/style-rtl.css', array(), WFACP_VERSION, false );
				wp_enqueue_style( 'wfacp-common-rtl-style', plugin_dir_url( WFACP_PLUGIN_FILE ) . 'assets/css/wfacp-common-style-rtl.css', false, WFACP_VERSION_DEV );

			} else {
				wp_enqueue_style( 'layout1-style', $this->url . 'css/style.css', array(), WFACP_VERSION, false );
			}


		}


	}

	return WFACP_template_layout1::get_instance();
}