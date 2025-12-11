<?php
if ( ! defined( 'WFACP_TEMPLATE_DIR' ) ) {
	return '';
}
if ( ! class_exists( 'WFACP_template_layout2' ) ) {
	#[AllowDynamicProperties]
	final class WFACP_template_layout2 extends WFACP_Pre_Built {

		private static $ins = null;
		protected $layout_setting = [];
		protected $template_slug = 'layout_2';

		protected function __construct() {
			parent::__construct();

			$this->template_dir = __DIR__;

			define( 'WFACP_TEMPLATE_MODULE_DIR', $this->template_dir . '/views/template-parts/sections' );
			$this->css_default_classes();

			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_style' ] );
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_script' ] );
			remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10 );
			remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );

			add_filter( 'wfacp_style_default_setting', [ $this, 'temp_change_default_setting' ], 12, 2 );
			add_filter( 'wfacp_layout_default_setting', [ $this, 'temp_default_setting' ], 10, 2 );
			add_filter( 'wfacp_customizer_layout', [ $this, 'name_change_for_customizer_field' ], 11, 2 );
			add_filter( 'wfacp_order_summary_cols_span', [ $this, 'change_col_span_for_order_summary' ] );
			$this->set_default_layout_setting();

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

				'billing_phone'   => [
					'class' => 'wfacp-col-full',
				],
				'billing_company' => [
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

		public function name_change_for_customizer_field( $panel, $key ) {

			if ( $key == 'wfacp_layout' ) {
				$selected_template_slug                                                                                                              = $this->get_template_slug();
				$panel['sections']['section']['fields'][ $selected_template_slug . '_mobile_sections_page_order' ]['choices']['wfacp_html_widget_3'] = 'Custom HTML Sidebar-3';
			}

			return $panel;

		}

		public function temp_default_setting( $field, $key ) {

			if ( $key == 'wfacp_html_widget_3' ) {
				if ( isset( $field['data']['title'] ) ) {
					$field['data']['title'] = __( 'Custom HTML Sidebar-3', 'woofunnels-aero-checkout' );
				}
				if ( isset( $field['sections']['section']['data']['title'] ) ) {
					$field['sections']['section']['data']['title'] = __( 'Custom HTML Sidebar-3', 'woofunnels-aero-checkout' );
				}

			}


			return $field;

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
					$selected_template_slug . '_rbox_border_width'  => '1',
					$selected_template_slug . '_rbox_border_color'  => '#ffffff',
					$selected_template_slug . '_rbox_padding'       => 10,
					$selected_template_slug . '_section_bg_color'   => '#ffffff',
					$selected_template_slug . '_content_text_color' => '#565e66',
				],
				'wfacp_footer'         => [
					$selected_template_slug . '_section_bg_color'   => '#ffffff',
					$selected_template_slug . '_content_text_color' => '#707070',
					$selected_template_slug . '_ft_text_fs'         => array(
						'desktop' => 12,
						'tablet'  => 12,
						'mobile'  => 12,
					),
				],
				'wfacp_product'        => [
					$selected_template_slug . '_title_fs'           => array(
						'desktop' => 24,
						'tablet'  => 22,
						'mobile'  => 20,
					),
					$selected_template_slug . '_desc_fs'            => array(
						'desktop' => 14,
						'tablet'  => 14,
						'mobile'  => 14,
					),
					$selected_template_slug . '_section_bg_color'   => '#ffffff',
					$selected_template_slug . '_heading_text_color' => '#565e66',
					$selected_template_slug . '_content_text_color' => '#7b8893',
				],
				'wfacp_gbadge'         => [

					$selected_template_slug . '_badge_max_width'  => 128,
					$selected_template_slug . '_badge_margin_top' => - 30,
				],
				'wfacp_benefits_0'     => [
					'heading'                                        => __( 'WHY BUY FROM US', 'woofunnels-aero-checkout' ),
					$selected_template_slug . '_heading_fs'          => array(
						'desktop' => 20,
						'tablet'  => 20,
						'mobile'  => 18,
					),
					$selected_template_slug . '_heading_talign'      => $_heading_talign,
					$selected_template_slug . '_heading_font_weight' => 'wfacp-normal',
					$selected_template_slug . '_rbox_border_type'    => 'none',
					$selected_template_slug . '_rbox_border_width'   => '1',
					$selected_template_slug . '_rbox_border_color'   => '#000000',
					$selected_template_slug . '_rbox_padding'        => 10,
					$selected_template_slug . '_section_bg_color'    => 'transparent',
					$selected_template_slug . '_sec_heading_color'   => '#565e66',
					$selected_template_slug . '_heading_text_color'  => '#565e66',
					$selected_template_slug . '_content_text_color'  => '#565e66',
					$selected_template_slug . '_icon_color'          => '#1d96f3',
				],
				'wfacp_testimonials_0' => [
					'heading'                                        => __( "WHAT THEY'RE SAYING", 'woofunnels-aero-checkout' ),
					$selected_template_slug . '_heading_fs'          => array(
						'desktop' => 20,
						'tablet'  => 20,
						'mobile'  => 18,
					),
					$selected_template_slug . '_heading_talign'      => $_heading_talign,
					$selected_template_slug . '_heading_font_weight' => 'wfacp-normal',
					$selected_template_slug . '_rbox_border_type'    => 'none',
					$selected_template_slug . '_rbox_border_width'   => '1',
					$selected_template_slug . '_rbox_border_color'   => '#000000',
					$selected_template_slug . '_rbox_padding'        => 10,
					$selected_template_slug . '_section_bg_color'    => 'transparent',
					$selected_template_slug . '_sec_heading_color'   => '#565e66',
					$selected_template_slug . '_heading_text_color'  => '#565e66',
					$selected_template_slug . '_content_text_color'  => '#656565',
				],
				'wfacp_promises_0'     => [

					$selected_template_slug . '_rbox_border_color' => '#dedede',

					$selected_template_slug . '_section_bg_color'   => '#41434900',
					$selected_template_slug . '_content_text_color' => '#999999',

				],
				'wfacp_assurance_0'    => [
					'mwidget_listw'                                  => [
						[
							'mwidget_image'   => $this->img_path . 'product_default_icon.jpg',
							'mwidget_heading' => __( '30 DAYS REFUND POLICY', 'woofunnels-aero-checkout' ),
							'mwidget_content' => esc_attr__( 'You have to take enough risks in life, this shouldn’t be one of them. Try this out for 30 days on me and if you aren’t happy just send me an email and I’ll refund your entire purchase – no questions asked.', 'woofunnels-aero-checkout' ),
						],
						[
							'mwidget_image'   => $this->img_path . 'product_default_icon.jpg',
							'mwidget_heading' => __( 'PRIVACY', 'woofunnels-aero-checkout' ),
							'mwidget_content' => esc_attr__( 'We will not share or trade online information that you provide us (including e-mail addresses).', 'woofunnels-aero-checkout' ),
						],
					],
					$selected_template_slug . '_heading_fs'          => array(
						'desktop' => 20,
						'tablet'  => 20,
						'mobile'  => 18,
					),
					$selected_template_slug . '_heading_talign'      => $_heading_talign,
					$selected_template_slug . '_heading_font_weight' => 'wfacp-normal',
					$selected_template_slug . '_enable_divider'      => false,
					$selected_template_slug . '_rbox_border_type'    => 'none',
					$selected_template_slug . '_rbox_border_width'   => '1',
					$selected_template_slug . '_rbox_border_color'   => '#000000',
					$selected_template_slug . '_rbox_padding'        => 10,
					$selected_template_slug . '_section_bg_color'    => 'transparent',
					$selected_template_slug . '_heading_text_color'  => '#565e66',
					$selected_template_slug . '_content_text_color'  => '#656565',
				],
				'wfacp_customer_0'     => [
					'heading'                                        => __( 'CUSTOMER SUPPORT', 'woofunnels-aero-checkout' ),
					$selected_template_slug . '_heading_fs'          => array(
						'desktop' => 20,
						'tablet'  => 20,
						'mobile'  => 18,
					),
					$selected_template_slug . '_heading_talign'      => $_heading_talign,
					$selected_template_slug . '_heading_font_weight' => 'wfacp-normal',
					'sub_heading'                                    => __( 'Our Award-Winning Customer Support Is Here For You', 'woofunnels-aero-checkout' ),
					$selected_template_slug . '_sub_heading_talign'  => $_heading_talign,
					$selected_template_slug . '_rbox_border_type'    => 'solid',
					$selected_template_slug . '_rbox_border_width'   => '1',
					$selected_template_slug . '_rbox_border_color'   => '#dedede',
					$selected_template_slug . '_rbox_padding'        => 20,
					$selected_template_slug . '_section_bg_color'    => 'transparent',
					$selected_template_slug . '_sec_heading_color'   => '#565e66',
					$selected_template_slug . '_heading_text_color'  => '#565e66',
					$selected_template_slug . '_content_text_color'  => '#565e66',
					$selected_template_slug . '_icon_text_color'     => '#9e9f9f',
				],
				'wfacp_style'          => [
					$selected_template_slug . '_body_background_color'    => '#ecf1f5',
					$selected_template_slug . '_sidebar_background_color' => '#F8FFE2',
				],
				'wfacp_form'           => [
					$selected_template_slug . '_heading_fs'            => array(
						'desktop' => 14,
						'tablet'  => 14,
						'mobile'  => 14,
					),
					$selected_template_slug . '_sub_heading_fs'        => array(
						'desktop' => 12,
						'tablet'  => 12,
						'mobile'  => 12,
					),
					$selected_template_slug . '_field_style_fs'        => array(
						'desktop' => 14,
						'tablet'  => 14,
						'mobile'  => 14,
					),
					$selected_template_slug . '_sec_heading_color'     => '#565e66',
					$selected_template_slug . '_sec_sub_heading_color' => '#565e66',

					$selected_template_slug . '_btn_order-place_width'              => '100%',
					$selected_template_slug . '_btn_order-place_bg_color'           => '#24ae4e',
					$selected_template_slug . '_btn_order-place_text_color'         => '#ffffff',
					$selected_template_slug . '_btn_order-place_bg_hover_color'     => '#7aa631',
					$selected_template_slug . '_btn_order-place_text_hover_color'   => '#ffffff',
					$selected_template_slug . '_btn_order-place_fs'                 => [
						'desktop' => 24,
						'tablet'  => 24,
						'mobile'  => 20,
					],
					$selected_template_slug . '_btn_order-place_top_bottom_padding' => '12',
					$selected_template_slug . '_btn_order-place_left_right_padding' => '44',
					$selected_template_slug . '_btn_order-place_border_radius'      => '3',

					$selected_template_slug . '_field_border_color'        => '#c9d3dc',
					$selected_template_slug . '_field_style_color'         => '#67717a',
					$selected_template_slug . '_btn_back_text_color'       => '#337ab7',
					$selected_template_slug . '_btn_back_text_hover_color' => '#1963a2',

					$selected_template_slug . '_additional_bg_color'   => '#ecf1f5',
					$selected_template_slug . '_additional_text_color' => '#565e66',
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


		public function temp_change_default_setting( $panel_details, $panel_key ) {

			$selected_template_slug = $this->template_slug;
			if ( ( is_array( $panel_key ) && count( $panel_key ) > 0 && array_key_exists( 'wfacp_style', $panel_key ) ) ) {

				if ( in_array( 'colors', $panel_key['wfacp_style'] ) ) {
					foreach ( $panel_key['wfacp_style'] as $key => $value ) {

						unset( $panel_details['sections'][ $value ]['fields'][ $selected_template_slug . '_sidebar_background_color' ] );
					}
				}
			}

			if ( array_key_exists( 'colors', $panel_details['sections'] ) ) {
				$selected_template_slug = $this->template_slug;

				$layout_key = $selected_template_slug . '_body_background_color';

				$panel_details['sections']['colors']['fields'][ $layout_key ]['wfacp_transport'][] = [

					'internal' => true,
					'type'     => 'css',
					'prop'     => [ 'background' ],
					'elem'     => 'body .wfacp-panel-wrapper',

				];

			}

			return $panel_details;
		}

		public function customizer_layout_order( $panel_details, $section_key ) {
			$selected_template_slug = $this->get_template_slug();

			$layout_key                 = $selected_template_slug . '_sidebar_layout_order';
			$mobile_sections_page_order = $selected_template_slug . '_mobile_sections_page_order';

			unset( $panel_details['sections']['section']['fields'][ $layout_key ] );
			$panel_details['sections']['section']['fields'][ $mobile_sections_page_order ]['label']   = 'Elements Order & Visibility';
			$panel_details['sections']['section']['fields'][ $mobile_sections_page_order ]['default'] = [
				'wfacp_product',
				'wfacp_benefits_0',
				'wfacp_testimonials_0',
				'wfacp_assurance_0',
				'wfacp_form',
				'wfacp_customer_0',
				'wfacp_promises_0',
			];

			return $panel_details;
		}

		public function change_default_setting( $panel_details, $panel_key ) {

			$selected_template_slug = $this->template_slug;
			$fields_data            = $panel_details['sections']['section']['fields'];
			foreach ( $fields_data as $key => $value ) {
				if ( isset( $this->layout_setting[ $panel_key ][ $key ] ) ) {

					$panel_details['sections']['section']['fields'][ $key ]['default'] = $this->layout_setting[ $panel_key ][ $key ];
				}
			}

			if ( $panel_key == 'wfacp_form' ) {
				unset( $panel_details['sections']['section']['fields'][ $selected_template_slug . '_field_style_position' ] );
			} elseif ( $panel_key == 'wfacp_product' ) {
				unset( $panel_details['sections']['section']['fields'][ $selected_template_slug . '_section_height' ] );
			}

			return $panel_details;
		}

		public function enqueue_style() {
			parent::enqueue_script();

			if ( is_rtl() ) {
				wp_enqueue_style( 'layout2-style', $this->url . 'css/style-rtl.css', array(), WFACP_VERSION, false );
				wp_enqueue_style( 'wfacp-common-rtl-style', plugin_dir_url( WFACP_PLUGIN_FILE ) . 'assets/css/wfacp-common-style-rtl.css', false, WFACP_VERSION_DEV );

			} else {
				wp_enqueue_style( 'layout2-style', $this->url . 'css/style.css', array(), WFACP_VERSION, false );
			}

		}

		public function enqueue_script() {
			parent::enqueue_script();
		}


		public function wfacp_get_support() {
			return $this->template_dir . '/views/template-parts/customer-support.php';
		}

		public function wfacp_get_promise() {
			return $this->template_dir . '/views/template-parts/permission-icon.php';
		}

	}

	return WFACP_template_layout2::get_instance();
}