<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFACP_template_layout4' ) ) {
	#[AllowDynamicProperties]
	final class WFACP_template_layout4 extends WFACP_Pre_Built {

		private static $ins = null;
		protected $template_unselected_sections = [ '' ];
		protected $template_slug = 'layout_4';

		/**
		 * Using protected method no one create new instance this class
		 * WFACP_template_layout4 constructor.
		 */
		protected function __construct() {
			parent::__construct();

			$this->template_dir = __DIR__;

			define( 'WFACP_TEMPLATE_MODULE_DIR', $this->template_dir . '/views/template-parts/sections' );

			$this->template_dir = __DIR__;

			$this->css_default_classes();

			//add_action( 'wfacp_assets_styles', array( $this, 'add_styles' ) );
			add_action( 'wfacp_header_print_in_head', array( $this, 'template_specific_css' ), 9 );
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_style' ], 99 );
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_script' ] );

			add_filter( 'wfacp_customizer_layout', [ $this, 'change_oder_on_mobile' ], 11, 2 );

			remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10 );
			remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );

			$this->set_default_layout_setting();

			add_filter( 'wfacp_customizer_layout', [ $this, 'layout_4_customizer_fields' ], 11, 2 );
			add_filter( 'wfacp_order_summary_cols_span', [ $this, 'change_col_span_for_order_summary' ] );
		}

		public function change_col_span_for_order_summary( $colspan_attr1 ) {

			return '';
		}

		public function css_default_classes() {

			$css_classess = [
				'billing_email'      => [
					'class' => 'wfacp-col-full',
				],
				'billing_first_name' => [
					'class' => 'wfacp-col-left-half',
				],
				'billing_last_name'  => [
					'class' => 'wfacp-col-left-half',
				],

				'billing_address_1' => [
					'class' => 'wfacp-col-left-half',
				],
				'billing_city'      => [
					'class' => 'wfacp-col-left-half',
				],
				'billing_postcode'  => [
					'class' => 'wfacp-col-left-third',
				],
				'billing_country'   => [
					'class' => 'wfacp-col-left-third',
				],
				'billing_state'     => [
					'class' => 'wfacp-col-left-third',
				],

				'billing_phone'       => [
					'class' => 'wfacp-col-full',
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
				'billing_company'     => [
					'class' => 'wfacp-col-left-half',
				],

				'shipping_address_1' => [
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

		public function set_default_layout_setting() {
			$selected_template_slug = $this->template_slug;

			if ( ! isset( $selected_template_slug ) ) {
				return;
			}
			$_heading_talign = 'wfacp-text-left';
			if ( is_rtl() ) {
				$_heading_talign = 'wfacp-text-right';
			}
			$this->layout_setting = [
				'wfacp_header'         => [
					$selected_template_slug . '_rbox_border_type'   => 'none',
					$selected_template_slug . '_rbox_border_width'  => 1,
					$selected_template_slug . '_rbox_border_color'  => '#ffffff',
					$selected_template_slug . '_rbox_padding'       => 10,
					$selected_template_slug . '_section_bg_color'   => 'transparent',
					$selected_template_slug . '_content_text_color' => '#3a3a3a',
					$selected_template_slug . '_header_icon_color'  => '#3a3a3a',
				],
				'wfacp_footer'         => [
					$selected_template_slug . '_section_bg_color'   => '#cae6f7',
					$selected_template_slug . '_content_text_color' => '#565e66',
					$selected_template_slug . '_ft_text_fs'         => array(
						'desktop' => 14,
						'tablet'  => 14,
						'mobile'  => 14,
					),
				],
				'wfacp_product'        => [
					$selected_template_slug . '_section_bg_color'   => '#f5f5f5',
					$selected_template_slug . '_heading_text_color' => '#3a3a3a',
					$selected_template_slug . '_content_text_color' => '#3a3a3a',
				],
				'wfacp_gbadge'         => [

					$selected_template_slug . '_badge_max_width' => 114,
				],
				'wfacp_benefits_0'     => [
					$selected_template_slug . '_heading_talign'     => $_heading_talign,
					$selected_template_slug . '_list_icon'          => 'wfacp-add',
					$selected_template_slug . '_rbox_border_type'   => 'none',
					$selected_template_slug . '_rbox_border_width'  => 1,
					$selected_template_slug . '_rbox_border_color'  => '#000000',
					$selected_template_slug . '_rbox_padding'       => 15,
					$selected_template_slug . '_section_bg_color'   => 'transparent',
					$selected_template_slug . '_heading_text_color' => '#3a3a3a',
					$selected_template_slug . '_content_text_color' => '#3a3a3a',
					$selected_template_slug . '_icon_color'         => '#1e97f4',
				],
				'wfacp_testimonials_0' => [
					$selected_template_slug . '_heading_talign'     => $_heading_talign,
					$selected_template_slug . '_rbox_border_type'   => 'none',
					$selected_template_slug . '_rbox_border_width'  => 1,
					$selected_template_slug . '_rbox_border_color'  => '#ffffff',
					$selected_template_slug . '_rbox_padding'       => 15,
					$selected_template_slug . '_section_bg_color'   => '#ffffff',
					$selected_template_slug . '_sec_heading_color'  => '#3a3a3a',
					$selected_template_slug . '_heading_text_color' => '#3a3a3a',
					$selected_template_slug . '_content_text_color' => '#3a3a3a',
				],
				'wfacp_promises_0'     => [
					$selected_template_slug . '_heading_talign' => $_heading_talign,

					$selected_template_slug . '_rbox_border_color' => '#dedede',

					$selected_template_slug . '_section_bg_color'   => 'transparent',
					$selected_template_slug . '_content_text_color' => '#565e66',
				],
				'wfacp_assurance_0'    => [
					$selected_template_slug . '_heading_talign'     => $_heading_talign,
					$selected_template_slug . '_rbox_border_type'   => 'none',
					$selected_template_slug . '_rbox_border_width'  => 1,
					$selected_template_slug . '_rbox_border_color'  => '#000000',
					$selected_template_slug . '_rbox_padding'       => 15,
					$selected_template_slug . '_section_bg_color'   => 'transparent',
					$selected_template_slug . '_sec_heading_color'  => '#3a3a3a',
					$selected_template_slug . '_content_text_color' => '#3a3a3a',
				],
				'wfacp_customer_0'     => [
					$selected_template_slug . '_heading_talign'     => $_heading_talign,
					$selected_template_slug . '_rbox_border_type'   => 'none',
					$selected_template_slug . '_rbox_border_width'  => 1,
					$selected_template_slug . '_rbox_border_color'  => '#000000',
					$selected_template_slug . '_rbox_padding'       => 10,
					$selected_template_slug . '_section_bg_color'   => 'transparent',
					$selected_template_slug . '_sec_heading_color'  => '#3a3a3a',
					$selected_template_slug . '_heading_text_color' => '#3a3a3a',
					$selected_template_slug . '_content_text_color' => '#565e66',
					$selected_template_slug . '_icon_text_color'    => '#9e9f9f',
				],
				'wfacp_style'          => [
					$selected_template_slug . '_body_background_color'    => '#daf0fd',
					$selected_template_slug . '_sidebar_background_color' => 'transparent',
				],
				'wfacp_form'           => [
					$selected_template_slug . '_field_style_fs'                   => [
						'desktop' => 14,
						'tablet'  => 14,
						'mobile'  => 14,
					],
					$selected_template_slug . '_btn_order-place_width'            => '100%',
					$selected_template_slug . '_btn_order-place_bg_color'         => '#24ae4e',
					$selected_template_slug . '_btn_order-place_text_color'       => '#ffffff',
					$selected_template_slug . '_btn_order-place_bg_hover_color'   => '#7aa631',
					$selected_template_slug . '_btn_order-place_text_hover_color' => '#ffffff',
					$selected_template_slug . '_btn_order-place_fs'               => [
						'desktop' => 28,
						'tablet'  => 28,
						'mobile'  => 20,
					],

					$selected_template_slug . '_btn_order-place_top_bottom_padding' => '12',
					'payment_methods_heading'                                       => __( 'Payment', 'woocommerce' ),
					$selected_template_slug . '_btn_order-place_left_right_padding' => '12',
					$selected_template_slug . '_btn_order-place_border_radius'      => '0',

					$selected_template_slug . '_field_border_width' => 2,
					$selected_template_slug . '_field_border_color' => '#ebebeb',
					$selected_template_slug . '_field_style_color'  => '#67717a',

					$selected_template_slug . '_btn_back_text_color'       => '#337ab7',
					$selected_template_slug . '_btn_back_text_hover_color' => '#1963a2',

					$selected_template_slug . '_additional_bg_color'   => 'transparent',
					$selected_template_slug . '_additional_text_color' => '#888888',

				],
				'wfacp_html_widget_3'  => [
					$selected_template_slug . '_section_bg_color'  => '#fff',
					$selected_template_slug . '_rbox_border_width' => "0",
				],
			];
		}

		public static function get_instance() {
			if ( is_null( self::$ins ) ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		public function layout_4_customizer_fields( $panel, $key ) {

			$selected_template_slug       = $this->get_template_slug();
			$get_exluded_sidebar_sections = $this->get_excluded_sidebar_sections();
			$get_exluded_sidebar_default  = [];

			foreach ( $get_exluded_sidebar_sections as $sec_key => $sec_val ) {
				$choices_key = str_replace( 'wfacp_', '', $sec_val );
				$pos         = strpos( $choices_key, '_' );

				if ( array_key_exists( $sec_val, $this->wfacp_html_fields ) && isset( $this->wfacp_html_fields[ $sec_val ] ) ) {
					$choices_key = $this->wfacp_html_fields[ $sec_val ];

					$unset_layout_order = array_search( $sec_val, $get_exluded_sidebar_sections );
					unset( $get_exluded_sidebar_sections[ $unset_layout_order ] );
					$get_exluded_sidebar_sections = array_values( $get_exluded_sidebar_sections );


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
					'default'     => $get_exluded_sidebar_sections,
					'choices'     => $get_exluded_sidebar_default,
					'priority'    => 51,
				];
			}

			return $panel;

		}

		public function get_excluded_sidebar_sections() {
			$this->exluded_sidebar_sections = array( 'wfacp_testimonials_0', 'wfacp_html_widget_3' );

			return $this->exluded_sidebar_sections;
		}

		public function change_oder_on_mobile( $panel_details, $section_key ) {

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

		public function customizer_layout_order( $panel_details, $section_key ) {
			$selected_template_slug = $this->template_slug;

			$fields_data = $panel_details['sections']['section']['fields'];


			$get_exluded_sidebar_sections = $this->get_excluded_sidebar_sections();


			$default_sidebar_layout_order_choices = $fields_data[ $selected_template_slug . '_sidebar_layout_order' ]['choices'];
			$default_sidebar_layout_order         = $fields_data[ $selected_template_slug . '_sidebar_layout_order' ]['default'];

			if ( is_array( $get_exluded_sidebar_sections ) && count( $get_exluded_sidebar_sections ) > 0 ) {
				$j = 0;

				foreach ( $get_exluded_sidebar_sections as $key => $value ) {

					$unset_layout_order = array_search( $value, $default_sidebar_layout_order );
					unset( $default_sidebar_layout_order_choices[ $value ] );
					unset( $default_sidebar_layout_order[ $unset_layout_order ] );
					$default_sidebar_layout_order = array_values( $default_sidebar_layout_order );

					$j ++;

				}
			}

			$panel_details['sections']['section']['fields'][ $selected_template_slug . '_sidebar_layout_order' ]['choices'] = $default_sidebar_layout_order_choices;
			$panel_details['sections']['section']['fields'][ $selected_template_slug . '_sidebar_layout_order' ]['default'] = $default_sidebar_layout_order;

			return $panel_details;
		}

		public function change_default_setting( $panel_details, $panel_key ) {

			$selected_template_slug = $this->get_template_slug();
			$fields_data            = $panel_details['sections']['section']['fields'];
			foreach ( $fields_data as $key => $value ) {
				if ( isset( $this->layout_setting[ $panel_key ][ $key ] ) ) {

					$panel_details['sections']['section']['fields'][ $key ]['default'] = $this->layout_setting[ $panel_key ][ $key ];
				}
			}
			if ( $panel_key == 'wfacp_form' ) {

				unset( $panel_details['sections']['section']['fields'][ $selected_template_slug . '_field_style_position' ] );
			} elseif ( $panel_key == 'wfacp_product' ) {
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_section_height' ]['default'] = 240;
			}

			return $panel_details;
		}

		public function add_styles( $styles ) {

			$styles['layout4-bts6'] = array(
				'path'      => plugin_dir_url( WFACP_PLUGIN_FILE ) . 'templates/layout_4/views/css/style.css',
				'version'   => WFACP_VERSION,
				'in_footer' => false,
				'supports'  => array(
					'customizer',
					'customizer-preview',
					'offer',
					'offer-page',
				),
			);
			$styles['layout4-bts5'] = array(
				'path'      => plugin_dir_url( WFACP_PLUGIN_FILE ) . 'templates/layout_4/views/css/responsive.css',
				'version'   => WFACP_VERSION,
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

		public function template_specific_css() {
			//        include $this->template_dir . '/css.php';
		}

		public function enqueue_style() {

			if ( is_rtl() ) {
				wp_enqueue_style( 'layout4-style', $this->url . 'css/style-rtl.css', array(), WFACP_VERSION, false );
				wp_enqueue_style( 'wfacp-common-rtl-style', plugin_dir_url( WFACP_PLUGIN_FILE ) . 'assets/css/wfacp-common-style-rtl.css', false, WFACP_VERSION_DEV );

			} else {
				wp_enqueue_style( 'layout4-style', $this->url . 'css/style.css', array(), WFACP_VERSION, false );
			}

		}


	}

	return WFACP_template_layout4::get_instance();
}