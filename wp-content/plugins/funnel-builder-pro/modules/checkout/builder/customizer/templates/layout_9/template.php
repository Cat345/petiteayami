<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'WFACP_template_layout9' ) ) {
	#[AllowDynamicProperties]
	final class WFACP_template_layout9 extends WFACP_Pre_Built {

		private static $ins = null;
		protected $template_unselected_sections = [ '' ];
		protected $template_slug = 'layout_9';
		protected $template_header_layout = 'top_bottom';
		private $default_layout9_data = null;

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

			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_style' ], 999 );

			add_action( 'wfacp_before_sidebar_content', array( $this, 'add_order_summary_to_sidebar' ), 11 );
			add_action( 'wfacp_before_sidebar_content_section', array( $this, 'add_order_summary_to_sidebar_section' ), 11 );
			remove_action( 'woocommerce_before_checkout_form', array( $this, 'woocommerce_checkout_login_form' ), 10 );
			remove_action( 'woocommerce_before_checkout_form', array( $this, 'woocommerce_checkout_coupon_form' ), 10 );
			add_filter( 'wfacp_before_order_summary_html', array( $this, 'woocommerce_order_summary_label_change' ), 10, 1 );

			add_filter( 'wfacp_layout_default_setting', [ $this, 'layout_9_change_default_setting' ], 10, 2 );


			add_filter( 'wfacp_customizer_fieldset', [ $this, 'layout_9_change_default_sections' ], 10, 2 );
			add_filter( 'woocommerce_cart_item_thumbnail', [ $this, 'check_cart_product_thumbnail_image' ] );

			remove_action( 'woocommerce_before_checkout_form', [ $this, 'add_form_steps' ], 11 );

			add_filter( 'wfacp_cart_show_product_thumbnail', [ $this, 'check_setting_show_img_order_summary' ], 10, 2 );

			add_filter( 'wfacp_layout_9_sidebar_hide_coupon', [ $this, 'check_layout_9_sidebar_hide_coupon' ], 10 );
			add_filter( 'wfacp_mini_cart_hide_coupon', [ $this, 'hide_coupon_on_mobile_mini_cart' ], 10 );

			add_filter( 'wfacp_shipping_col_span', [ $this, 'add_cols_span_in_shipping' ] );

			add_filter( 'wfacp_order_summary_cols_span', [ $this, 'change_col_span_for_order_summary' ] );


			add_filter( 'wfacp_show_form_coupon', [ $this, 'hide_default_form_coupon' ], 10 );
			add_filter( 'wfacp_html_fields_order_summary', '__return_false' );
			add_action( 'process_wfacp_html', [ $this, 'layout_order_summary' ], 11, 4 );

			$this->set_default_layout_setting();


			add_filter( 'wfacp_customizer_layout', [ $this, 'layout_9_customizer_fields' ], 11, 2 );

			add_action( 'wfacp_outside_header', [ $this, 'add_outside_header' ] );
			add_filter( 'wfacp_style_default_setting', [ $this, 'layout_9_change_customizer_style' ], 10, 2 );

			add_filter( 'wfacp_unset_account_username_field', [ $this, 'enable_username_field_in_checkout_page' ] );

			add_action( 'woocommerce_before_checkout_form', [ $this, 'add_blank_html_for_coupon_error' ] );

			add_filter( 'wfacp_native_checkout_cart', '__return_true' );

			$version = WFACP_Common::get_checkout_page_version();

			if ( version_compare( $version, '2.0.7', '<' ) ) {
				add_filter( 'wfacp_layout_default_setting', [ $this, 'change_default_customizer_setting' ], 20, 2 );
				add_filter( 'wfacp_customizer_layout', [ $this, 'get_default_layout9' ], 20, 2 );
				add_filter( 'wfacp_style_default_setting', [ $this, 'get_default_layout9' ], 20, 2 );
			}
		}

		public function get_default_layout9( $panel_details, $panel_key ) {
			$selected_template_slug = $this->template_slug;

			$register_template = $this->get_selected_register_template();
			$selectedSlug      = $register_template['slug'];

			$file = WFACP_PLUGIN_DIR . '/importer/checkout-settings/' . $selectedSlug . '.php';

			if ( file_exists( $file ) && is_null( $this->default_layout9_data ) ) {
				$data = include $file;
				if ( is_array( $data ) && isset( $data['default_customizer_value'] ) ) {
					$this->default_layout9_data = $data['default_customizer_value'];
				}
			}

			$panel_details = $this->import_default_customizer_data( $panel_details, $this->default_layout9_data, $panel_key, $selected_template_slug );

			return $panel_details;
		}

		public function change_default_customizer_setting( $panel_details, $panel_key ) {
			$selected_template_type = $this->template_type;
			if ( $selected_template_type != 'pre_built' ) {
				return $panel_details;
			}
			$selected_template_slug = $this->template_slug;

			if ( $panel_key == 'wfacp_form' ) {
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_btn_order-place_fs' ]['default'] = [
					'desktop'      => '25',
					'tablet'       => '14',
					'mobile'       => '20',
					'desktop-unit' => 'px',
					'tablet-unit'  => 'px',
					'mobile-unit'  => 'px',
				];
			}

			$panel_details = $this->get_default_layout9( $panel_details, $panel_key );

			return $panel_details;
		}

		public function import_default_customizer_data( $panel_details, $data, $panel_key, $selected_template_slug ) {
			if ( ! is_array( $panel_key ) ) {
				if ( isset( $data[ $panel_key ] ) ) {
					$cust_keys = $panel_key . '_section_';
					foreach ( $data[ $panel_key ] as $customizer_key => $customizer_value ) {
						$main_key   = str_replace( $cust_keys, '', $customizer_key );
						$normal_key = $main_key;

						if ( isset( $panel_details['sections']['section']['fields'][ $normal_key ] ) ) {

							$defaultData = $data[ $panel_key ][ $customizer_key ];

							if ( false !== strpos( $normal_key, '_step_text' ) ) {
								$stepcount = $this->get_step_count();
								$key       = 'wfacp_form_section_breadcrumb_' . $stepcount . '_step_text';
								if ( $customizer_key == $key ) {
									$defaultData = __( 'Order Complete', 'woofunnels-aero-checkout' );
								}
							}
							$panel_details['sections']['section']['fields'][ $normal_key ]['default'] = $defaultData;
						} else if ( false !== strpos( $normal_key, '_form_fields_' ) ) {
							$main_key1 = str_replace( 'wfacp_form_form_fields_', '', $main_key );
							if ( isset( $panel_details['sections']['form_fields']['fields'][ $main_key1 ]['default'] ) ) {
								$panel_details['sections']['form_fields']['fields'][ $main_key1 ]['default'] = $data[ $panel_key ][ $customizer_key ];
							}
						}
					}
				}
			} elseif ( isset( $data['wfacp_style'] ) ) {
				foreach ( $panel_key['wfacp_style'] as $color_key => $color_val ) {
					if ( isset( $data['wfacp_style'][ $color_val ] ) && is_array( $data['wfacp_style'][ $color_val ] ) && count( $data['wfacp_style'][ $color_val ] ) > 0 ) {
						$style_key    = 'wfacp_style_' . $color_val . "_";
						$colors_style = $data['wfacp_style'][ $color_val ];
						foreach ( $colors_style as $keys1 => $value1 ) {
							$main_key  = str_replace( $style_key, '', $keys1 );
							$final_key = $style_key . $main_key;
							if ( isset( $panel_details['sections'][ $color_val ]['fields'][ $main_key ] ) ) {
								$panel_details['sections'][ $color_val ]['fields'][ $main_key ]['default'] = $colors_style[ $final_key ];
							}
						}
					}
				}
			}

			return $panel_details;
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
				'address'            => [
					'class' => 'wfacp-col-left-half',
				],
				'billing_company'    => [
					'class' => 'wfacp-col-full',
				],
				'billing_address_1'  => [
					'class' => 'wfacp-col-left-half',
				],
				'billing_address_2'  => [
					'class' => 'wfacp-col-left-half',
				],

				'billing_country'  => [
					'class' => 'wfacp-col-left-third',
				],
				'billing_city'     => [
					'class' => 'wfacp-col-left-half',
				],
				'billing_postcode' => [
					'class' => 'wfacp-col-left-third',
				],

				'billing_state' => [
					'class' => 'wfacp-col-left-third',
				],
				'billing_phone' => [
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
					'class' => 'wfacp-col-full',
				],
				'shipping_address_1'  => [
					'class' => 'wfacp-col-left-half',
				],
				'shipping_address_2'  => [
					'class' => 'wfacp-col-left-half',
				],

				'shipping_country'  => [
					'class' => 'wfacp-col-left-third',
				],
				'shipping_city'     => [
					'class' => 'wfacp-col-left-half',
				],
				'shipping_postcode' => [
					'class' => 'wfacp-col-left-third',
				],
				'shipping_state'    => [
					'class' => 'wfacp-col-left-third',
				],
				'shipping_phone'    => [
					'class' => 'wfacp-col-full',
				],
				'order_comments'    => [
					'class' => 'wfacp-col-full',
				],
			];
			$this->css_classes = apply_filters( 'wfacp_default_form_classes', $css_classess );
		}

		public function set_default_layout_setting() {
			$selected_template_slug = $this->template_slug;

			$_section_bg_color   = '#000000';
			$_header_icon_color  = '#ffffff';
			$_content_text_color = '#ffffff';
			$_rbox_padding       = 0;
			$_rbox_border_color  = 'none';

			if ( ! WFACP_Common::page_is_old_version( '1.9.3' ) ) {

				$_section_bg_color   = 'transparent';
				$_header_icon_color  = '#666666';
				$_content_text_color = '#666666';

			}

			$btn_width       = 'initial';
			$_heading_talign = 'wfacp-text-left';
			if ( is_rtl() ) {
				$_heading_talign = 'wfacp-text-right';
			}

			$this->layout_setting = [
				'wfacp_header'                => [
					$selected_template_slug . '_rbox_border_type'   => $_rbox_border_color,
					$selected_template_slug . '_rbox_border_color'  => '#e1e1e1',
					$selected_template_slug . '_rbox_padding'       => $_rbox_padding,
					$selected_template_slug . '_section_bg_color'   => $_section_bg_color,
					$selected_template_slug . '_header_icon_color'  => $_header_icon_color,
					$selected_template_slug . '_content_text_color' => $_content_text_color,
				],
				'wfacp_footer'                => [
					$selected_template_slug . '_section_bg_color'   => '#ffffff',
					$selected_template_slug . '_content_text_color' => '#666666',
					$selected_template_slug . '_ft_text_fs'         => [
						'desktop' => 12,
						'tablet'  => 12,
						'mobile'  => 12,
					],
				],
				'wfacp_product'               => [
					$selected_template_slug . '_title_fs'           => [
						'desktop' => 18,
						'tablet'  => 18,
						'mobile'  => 18,
					],
					$selected_template_slug . '_desc_fs'            => [
						'desktop' => 14,
						'tablet'  => 14,
						'mobile'  => 14,
					],
					$selected_template_slug . '_section_bg_color'   => 'transparent',
					$selected_template_slug . '_heading_text_color' => '#333333',
					$selected_template_slug . '_content_text_color' => '#666666',
				],
				'wfacp_style'                 => [
					$selected_template_slug . '_body_background_color'    => '#ffffff',
					$selected_template_slug . '_sidebar_background_color' => '#f7f7f7',
				],
				'wfacp_gbadge'                => [
					$selected_template_slug . '_badge_max_width' => 115,
				],
				'wfacp_benefits_0'            => [
					'heading'                                              => __( 'WHY BUY FROM US', 'woofunnels-aero-checkout' ),
					$selected_template_slug . '_heading_fs'                => [
						'desktop' => 18,
						'tablet'  => 18,
						'mobile'  => 18,
					],
					$selected_template_slug . '_heading_talign'            => $_heading_talign,
					$selected_template_slug . '_heading_font_weight'       => 'wfacp-normal',
					$selected_template_slug . '_rbox_border_type'          => 'none',
					$selected_template_slug . '_rbox_border_width'         => '1',
					$selected_template_slug . '_rbox_border_color'         => '#d2d2d2',
					$selected_template_slug . '_rbox_padding'              => 20,
					$selected_template_slug . '_show_list_description'     => false,
					$selected_template_slug . '_display_list_bold_heading' => false,
					$selected_template_slug . '_section_bg_color'          => 'transparent',
					$selected_template_slug . '_heading_text_color'        => '#666666',
					$selected_template_slug . '_sec_heading_color'         => '#333333',
					$selected_template_slug . '_content_text_color'        => '#666666',
					$selected_template_slug . '_icon_color'                => '#53aef5',

				],
				'wfacp_testimonials_0'        => [
					'heading'                                        => __( "WHAT THEY'RE SAYING", 'woofunnels-aero-checkout' ),
					$selected_template_slug . '_heading_talign'      => $_heading_talign,
					$selected_template_slug . '_heading_font_weight' => 'wfacp-normal',
					$selected_template_slug . '_heading_fs'          => [
						'desktop' => 18,
						'tablet'  => 18,
						'mobile'  => 18,
					],
					$selected_template_slug . '_rbox_border_type'    => 'none',
					$selected_template_slug . '_rbox_border_width'   => '1',
					$selected_template_slug . '_rbox_border_color'   => '#d2d2d2',
					$selected_template_slug . '_rbox_padding'        => 20,
					$selected_template_slug . '_section_bg_color'    => 'transparent',
					$selected_template_slug . '_sec_heading_color'   => '#333333',
					$selected_template_slug . '_heading_text_color'  => '#737373',
					$selected_template_slug . '_content_text_color'  => '#737373',
					$selected_template_slug . '_hide_image'          => true,

				],
				'wfacp_promises_0'            => [
					$selected_template_slug . '_heading_font_weight' => 'wfacp-normal',
					$selected_template_slug . '_rbox_border_color'   => '#f0f0f0',
					$selected_template_slug . '_section_bg_color'    => '#41434900',
					$selected_template_slug . '_content_text_color'  => '#737373',
				],
				'wfacp_assurance_0'           => [
					$selected_template_slug . '_heading_font_weight' => 'wfacp-normal',
					$selected_template_slug . '_heading_fs'          => [
						'desktop' => 18,
						'tablet'  => 18,
						'mobile'  => 18,
					],
					$selected_template_slug . '_heading_talign'      => $_heading_talign,
					$selected_template_slug . '_desc_fs'             => [
						'desktop' => 14,
						'tablet'  => 14,
						'mobile'  => 14,
					],
					$selected_template_slug . '_rbox_border_type'    => 'none',
					$selected_template_slug . '_rbox_border_width'   => '1',
					$selected_template_slug . '_rbox_border_color'   => '#d2d2d2',
					$selected_template_slug . '_rbox_padding'        => 20,
					$selected_template_slug . '_section_bg_color'    => 'transparent',
					$selected_template_slug . '_sec_heading_color'   => '#333333',
					$selected_template_slug . '_heading_text_color'  => '#333333',
					$selected_template_slug . '_content_text_color'  => '#656565',
					'mwidget_listw'                                  => [
						[
							'mwidget_heading' => __( '30 DAYS REFUND POLICY', 'woofunnels-aero-checkout' ),
							'mwidget_content' => esc_attr__( 'You have to take enough risks in life, this shouldn’t be one of them. Try this out for 30 days on me and if you aren’t happy just send me an email and I’ll refund your entire purchase – no questions asked.', 'woofunnels-aero-checkout' ),
							'mwidget_image'   => $this->img_path . 'product_default_icon.jpg',
						],
					],
				],
				'wfacp_customer_0'            => [
					'heading'                                        => __( 'CUSTOMER SUPPORT', 'woofunnels-aero-checkout' ),
					$selected_template_slug . '_heading_font_weight' => 'wfacp-normal',
					$selected_template_slug . '_heading_fs'          => array(
						'desktop' => 20,
						'tablet'  => 20,
						'mobile'  => 18,
					),
					$selected_template_slug . '_heading_talign'      => $_heading_talign,
					'sub_heading'                                    => __( 'Our Award-Winning Customer Support Is Here For You', 'woofunnels-aero-checkout' ),
					$selected_template_slug . '_sub_heading_talign'  => $_heading_talign,
					$selected_template_slug . '_rbox_border_type'    => 'solid',
					$selected_template_slug . '_rbox_border_width'   => '1',
					$selected_template_slug . '_rbox_border_color'   => '#f0f0f0',
					$selected_template_slug . '_rbox_padding'        => 20,
					$selected_template_slug . '_section_bg_color'    => 'transparent',
					$selected_template_slug . '_sec_heading_color'   => '#333333',
					$selected_template_slug . '_heading_text_color'  => '#737373',
					$selected_template_slug . '_content_text_color'  => '#737373',
					$selected_template_slug . '_icon_text_color'     => '#565e66',
				],
				'wfacp_form'                  => [
					$selected_template_slug . '_rbox_padding'                       => 0,
					$selected_template_slug . '_btn_order-place_width'              => '100%',
					$selected_template_slug . '_heading_fs'                         => array(
						'desktop' => 20,
						'tablet'  => 20,
						'mobile'  => 20,
					),
					$selected_template_slug . '_heading_font_weight'                => 'wfacp-normal',
					$selected_template_slug . '_sub_heading_fs'                     => array(
						'desktop' => 14,
						'tablet'  => 14,
						'mobile'  => 14,
					),
					$selected_template_slug . '_sec_heading_color'                  => '#333333',
					$selected_template_slug . '_sec_sub_heading_color'              => '#737373',
					$selected_template_slug . '_field_style_fs'                     => [
						'desktop' => 13,
						'tablet'  => 13,
						'mobile'  => 13,
					],
					$selected_template_slug . '_btn_order-place_width'              => $btn_width,
					$selected_template_slug . '_btn_order-place_talign'             => 'center',
					$selected_template_slug . '_btn_order-place_btn_font_weight'    => 'bold',
					$selected_template_slug . '_btn_order-place_bg_color'           => '#24ae4e',
					$selected_template_slug . '_btn_order-place_text_color'         => '#ffffff',
					$selected_template_slug . '_btn_order-place_bg_hover_color'     => '#7aa631',
					$selected_template_slug . '_btn_order-place_text_hover_color'   => '#ffffff',
					$selected_template_slug . '_btn_order-place_fs'                 => [
						'desktop' => 30,
						'tablet'  => 14,
						'mobile'  => 20,
					],
					$selected_template_slug . '_btn_order-place_top_bottom_padding' => 25,
					$selected_template_slug . '_btn_order-place_left_right_padding' => 130,
					$selected_template_slug . '_btn_order-place_border_radius'      => '4',
					$selected_template_slug . '_btn_order-place_btn_text'           => __( 'Place order', 'woocommerce' ),
					$selected_template_slug . '_field_style_color'                  => '#777777',
					$selected_template_slug . '_field_border_layout'                => 'solid',
					$selected_template_slug . '_field_border_width'                 => '1',
					$selected_template_slug . '_field_border_color'                 => '#bfbfbf',
					$selected_template_slug . '_validation_color'                   => '#d50000',
					$selected_template_slug . '_additional_bg_color'                => '#f7f7f7',
					$selected_template_slug . '_additional_text_color'              => '#737373',
				],
				'wfacp_form_product_switcher' => [
					'section_heading'                                      => __( 'WHAT\'S INCLUDED IN YOUR PLAN?', 'woofunnels-aero-checkout' ),
					$selected_template_slug . '_product_switcher_bg_color' => '#f7f7f7',

				],
				'wfacp_html_widget_1'         => [
					$selected_template_slug . '_rbox_border_type' => 'none',
				],
				'wfacp_html_widget_2'         => [
					$selected_template_slug . '_rbox_border_type' => 'none',
				],

			];


			/* For new Checkout page Default value */
			if ( true === $this->get_wfacp_version() || 1 === $this->get_wfacp_version() ) {
				$this->layout_setting['wfacp_form'][ $selected_template_slug . '_btn_back_text_hover_color' ] = "#d50000";

			}


		}

		public static function get_instance() {
			if ( is_null( self::$ins ) ) {
				self::$ins = new self();
			}

			return self::$ins;
		}

		public function enable_username_field_in_checkout_page( $bool_val ) {
			return false;
		}

		public function layout_9_change_customizer_style( $style_panel, $panel_keys ) {
			$selected_template_slug = $this->get_template_slug();

			foreach ( $panel_keys['wfacp_style'] as $key => $value_1 ) {
				if ( $value_1 == 'colors' ) {
					$style_panel['sections'][ $value_1 ]['fields'][ $selected_template_slug . '_sidebar_background_color' ]['wfacp_transport'] = [
						[
							'internal' => true,
							'type'     => 'css',
							'prop'     => [ 'background' ],
							'elem'     => 'body',
						],
						[
							'internal' => true,
							'type'     => 'css',
							'prop'     => [ 'background' ],
							'elem'     => '.wfacp-right-panel',
						],
						[
							'internal' => true,
							'type'     => 'css',
							'prop'     => [ 'background' ],
							'elem'     => 'body.wfacp_cls_layout_9 .wfacp-panel-wrapper.wfacp_outside_header:before',
						],
					];
				}
			}

			return $style_panel;

		}

		public function add_outside_header() {
			$header_layout_is = $this->get_temaplete_header_layout();

			$headers = $this->customizer_fields_data[ $this->customizer_keys['header'] ];

			$rbox_border_type = '';
			if ( isset( $headers['advance_setting']['rbox_border_type'] ) && $headers['advance_setting']['rbox_border_type'] != '' ) {
				$rbox_border_type = $headers['advance_setting']['rbox_border_type'];
			}

			if ( isset( $header_layout_is ) && $header_layout_is == 'outside_header' ) {

				?>
                <div class="outside_header_wrap">

                    <div class="wfacp-header wfacp_header <?php echo $rbox_border_type . ' ' . $header_layout_is; ?>">
                        <div class="outside_head_sec">
							<?php

							$register_template = $this->get_selected_register_template();
							$selectedSlug      = $register_template['slug'];


							if ( isset( $headers['header_data']['logo'] ) && $headers['header_data']['logo'] != '' ) {

								$logo_link        = '#';
								$logo_link_target = '_self';
								$logo_link_class  = '';

								if ( isset( $headers['header_data']['logo_link_target'] ) && $headers['header_data']['logo_link_target'] == 1 ) {
									$logo_link_target = '_blank';
								}

								if ( ( isset( $headers['header_data']['logo_link'] ) && $headers['header_data']['logo_link'] != '#' ) && ! empty( $headers['header_data']['logo_link'] ) ) {
									$logo_link = $headers['header_data']['logo_link'];
								} else {
									$logo_link       = 'javascript:void(0)';
									$logo_link_class = 'wfacp_no_link';
								}

								$src = $headers['header_data']['logo'];

								if ( ( $selectedSlug == 'royale-step-2' || $selectedSlug == 'royale-step-3' ) && false !== strpos( $src, 'https://woofunnels.s3.amazonaws.com/templates/checkout/customizer/logo_white_aero.png' ) ) {
									$src = $this->img_path . 'woo_checkout_logo.png';
								}


								?>
                                <a class="wfacp_logo_wrap <?php echo $logo_link_class; ?>" href="<?php echo $logo_link; ?>" target="<?php echo $logo_link_target; ?>">
                                    <img class="wfacp-logo" src="<?php echo $src; ?>" alt="<?php the_title() ?>">
                                </a>
								<?php
							}
							?>

                            <div class="wfacp-help-text wfacp-pd-20">
                                <div class="wfacp-header-nav clearfix">
                                    <ul>
										<?php

										$classBlank = 'wfacp_display_none';
										if ( $headers['header_data']['header_text'] != '' ) {
											$classBlank = '';
										}

										?>
                                        <li class="wfacp_header_list_sup <?php echo $classBlank; ?>">
                                            <span class="wfacp-hd-list-sup"><?php echo $headers['header_data']['header_text']; ?></span>
                                        </li>


										<?php
										$hide_sec = 'wfacp_display_none';
										if ( isset( $headers['header_data']['helpdesk_text'] ) && $headers['header_data']['helpdesk_text'] != '' ) {
											$hide_sec = '';
										}
										$helpdesk_link_target = '_self';
										if ( isset( $headers['header_data']['helpdesk_link_target'] ) && $headers['header_data']['helpdesk_link_target'] == 1 ) {
											$helpdesk_link_target = '_blank';
										}

										?>

                                        <li class="wfacp_header_list_help <?php echo $hide_sec; ?>">
                                            <a href="<?php echo $headers['header_data']['helpdesk_url']; ?>" target="<?php echo $helpdesk_link_target; ?>">
                                                <span class="wfacp-hd-list-help"><?php echo $headers['header_data']['helpdesk_text']; ?></span></a>
                                        </li>

										<?php

										$hide_sec = 'wfacp_display_none';
										if ( isset( $headers['header_data']['email'] ) && $headers['header_data']['email'] != '' ) {
											$hide_sec = '';
										}

										$email = $headers['header_data']['email'];

										?>


                                        <li class="wfacp_header_email <?php echo $hide_sec; ?>"><a href="mailto:<?php echo $email; ?>">
                                                <span class="wfacp-hd-list-email"><?php echo $email; ?></span></a>
                                        </li>

										<?php

										$hide_sec = 'wfacp_display_none';
										if ( isset( $headers['header_data']['phone'] ) && $headers['header_data']['phone'] != '' ) {
											$hide_sec = '';
										}

										$phone = $headers['header_data']['phone'];

										$tel_number = '';
										if ( isset( $headers['header_data']['tel_number'] ) && ! empty( $headers['header_data']['tel_number'] ) ) {
											$tel_number = $headers['header_data']['tel_number'];
										}

										?>

                                        <li class="wfacp_header_ph <?php echo $hide_sec; ?>">
                                            <a href="tel:<?php echo $tel_number; ?>"><span class="wfacp-hd-list-phn"><?php echo $phone; ?></span></a>
                                        </li>


                                    </ul>
                                </div>
                            </div>

                        </div>
                    </div>

                </div>


				<?php
				add_action( 'woocommerce_before_checkout_form', [ $this, 'add_form_steps' ], 11, 2 );


				if ( $this->device_mb_tab == 'tablet' ) {
					$this->get_mobile_mini_cart( 'mobile_Collapsible' );
				}

			}
		}

		public function get_temaplete_header_layout() {

			return $this->template_header_layout;
		}

		public function layout_9_customizer_fields( $panel, $key ) {

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
			$this->excluded_sidebar_sections = [
				'wfacp_promises_0',
				'wfacp_customer_0',
				'wfacp_html_widget_3'
			];

			return $this->excluded_sidebar_sections;
		}

		public function layout_order_summary( $field, $key, $args, $value ) {

			if ( 'order_summary' === $key ) {
				WC()->session->set( 'wfacp_order_summary_' . WFACP_Common::get_id(), $args );
				include __DIR__ . '/views/template-parts/main-order-summary.php';
			}
		}

		public function check_cart_product_thumbnail_image( $product_image ) {

			if ( $product_image == '' ) {
				$string = wc_placeholder_img();

				return $string;
			}

			return $product_image;

		}

		public function set_temaplete_header_layout( $header_layout ) {

			if ( $header_layout != '' ) {

				$this->template_header_layout = $header_layout;

				return $header_layout;
			}

			return $this->template_header_layout;
		}


		public function layout_9_change_default_sections( $data, $this_data ) {
			foreach ( $data as $key => $value ) {
				unset( $data[ $key ]['wfacp_gbadge'] );
			}
			require_once WFACP_BUILDER_DIR . '/customizer/customizer-options/class-section-cart.php';
			$form_cart_panel = WFACP_SectionCart::get_instance( $this_data )->cart_settings();
			if ( is_array( $form_cart_panel ) && count( $form_cart_panel ) > 0 ) {
				$data[] = $form_cart_panel;
			}

			return $data;
		}

		public function add_cols_span_in_shipping( $colspan ) {

			return 'colspan=2';
		}

		public function change_col_span_for_order_summary( $colspan_attr1 ) {

			return '';
		}

		public function layout_9_change_default_setting( $field, $key ) {

			$selected_template_slug = $this->get_template_slug();

			$header_layout = 'top_bottom';


			$_header_icon_color  = '#737373';
			$_content_text_color = '#737373';
			if ( ! WFACP_Common::page_is_old_version( '1.9.3' ) ) {
				$header_layout       = 'outside_header';
				$_header_icon_color  = '#666666';
				$_content_text_color = '#666666';

			}


			if ( $key == 'wfacp_header' ) {
				$field['sections']['section']['fields']['logo']['default']                                         = $this->img_path . 'woo_checkout_logo.png';
				$field['sections']['section']['fields']['ct_header_layout']                                        = [
					'type'     => 'custom',
					'default'  => '<div class="options-title-divider">' . esc_html__( 'Layout', 'woofunnels-aero-checkout' ) . '</div>',
					'priority' => 19,
				];
				$field['sections']['section']['fields'][ $selected_template_slug . '_header_layout' ]              = [
					'type'     => 'radio-image-text',
					'label'    => '',
					'default'  => $header_layout,
					'choices'  => array(
						'outside_header' => array(
							'label' => '',
							'path'  => $this->img_path . 'headers/header-3.svg',
						),
						'top_bottom'     => array(
							'left_right' => '',
							'path'       => $this->img_path . 'headers/header-1.svg',
						),
						'left_right'     => array(
							'label' => '',
							'path'  => $this->img_path . 'headers/header-2.svg',
						),


					),
					'priority' => 19,
				];
				$field['sections']['section']['fields'][ $selected_template_slug . '_outside_section_bg_color' ]   = [
					'type'            => 'color',
					'label'           => esc_attr__( 'Background Color', 'woofunnels-aero-checkout' ),
					'default'         => 'transparent',
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
							'elem'     => '.wfacp_header',
						],
					],
					'active_callback' => [
						[
							'setting'  => 'wfacp_header_section_' . $selected_template_slug . '_header_layout',
							'operator' => '==',
							'value'    => 'outside_header',
						],
					],
				];
				$field['sections']['section']['fields'][ $selected_template_slug . '_outside_header_icon_color' ]  = [
					'type'            => 'color',
					'label'           => esc_attr__( 'Icon Color', 'woofunnels-aero-checkout' ),
					'default'         => $_header_icon_color,
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
							'elem'     => '.wfacp_header span.wfacp-hd-list-help:before',
						],
						[
							'internal' => true,
							'type'     => 'css',
							'prop'     => [ 'color' ],
							'elem'     => '.wfacp_header span.wfacp-hd-list-email:before',
						],
						[
							'internal' => true,
							'type'     => 'css',
							'prop'     => [ 'color' ],
							'elem'     => '.wfacp_header span.wfacp-hd-list-phn:before',
						],
					],
					'active_callback' => [
						[
							'setting'  => 'wfacp_header_section_' . $selected_template_slug . '_header_layout',
							'operator' => '==',
							'value'    => 'outside_header',
						],
					],
				];
				$field['sections']['section']['fields'][ $selected_template_slug . '_outside_content_text_color' ] = [
					'type'            => 'color',
					'label'           => esc_attr__( 'Content Color', 'woofunnels-aero-checkout' ),
					'default'         => $_content_text_color,
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
							'elem'     => '.outside_header_wrap .wfacp_header .wfacp-header-nav p',
						],
						[
							'internal' => true,
							'type'     => 'css',
							'prop'     => [ 'color' ],
							'elem'     => '.outside_header_wrap .wfacp_header .wfacp-header-nav ul li',
						],
						[
							'internal' => true,
							'type'     => 'css',
							'prop'     => [ 'color' ],
							'elem'     => '.outside_header_wrap .wfacp_header .wfacp-header-nav ul li a',
						],
					],
					'active_callback' => [
						[
							'setting'  => 'wfacp_header_section_' . $selected_template_slug . '_header_layout',
							'operator' => '==',
							'value'    => 'outside_header',
						],
					],
				];
				unset( $field['sections']['section']['fields']['header_text']['wfacp_partial'] );
				$field['sections']['section']['fields']['header_text']['wfacp_transport'] = [
					[
						'type'                => 'html',
						'container_inclusive' => false,
						'elem'                => '.wfacp_header .wfacp-hd-list-sup',
					],
					[
						'type' => 'add_remove_class',
						'elem' => '.wfacp_header .wfacp_header_list_sup ',
					],
				];
				$data_keys                                                                = $this->get_section_keys_data( $key );
				$data_keys['colors'][]                                                    = [
					$selected_template_slug . '_outside_section_bg_color'   => [
						[
							'type'   => 'background-color',
							'class'  => '.wfacp_header',
							'device' => 'desktop',
						],
					],
					$selected_template_slug . '_outside_header_icon_color'  => [
						[
							'type'   => 'color',
							'class'  => '.wfacp_header span.wfacp-hd-list-help:before',
							'device' => 'desktop',
						],
						[
							'type'   => 'color',
							'class'  => '.wfacp_header span.wfacp-hd-list-email:before',
							'device' => 'desktop',
						],
						[
							'type'   => 'color',
							'class'  => '.wfacp_header span.wfacp-hd-list-email:before',
							'device' => 'desktop',
						],
					],
					$selected_template_slug . '_outside_content_text_color' => [
						[
							'type'   => 'color',
							'class'  => '.wfacp_header .wfacp-header-nav p',
							'device' => 'desktop',
						],
						[
							'type'   => 'color',
							'class'  => '.wfacp_header .wfacp-header-nav ul li',
							'device' => 'desktop',
						],
						[
							'type'   => 'color',
							'class'  => '.wfacp_header .wfacp-header-nav ul li a',
							'device' => 'desktop',
						],
					],
				];

			} elseif ( $key == 'wfacp_form' ) {
				unset( $field['sections']['section']['fields'][ $selected_template_slug . '_field_style_position' ] );
				$field['sections']['section']['fields']['ct_bredcrumb']['default'] = sprintf( '<div class="options-title-divider">%s</div>', esc_html__( 'Progress Bar' ) );


				$field['sections']['section']['fields']['ct_mini_cart_on_mb'] = [
					'type'     => 'custom',
					'default'  => '<div class="options-title-divider">' . esc_html__( 'Mini Cart On Mobile View', 'woofunnels-aero-checkout' ) . '</div>',
					'priority' => 23,

				];


				$field['sections']['section']['fields'][ $selected_template_slug . '_enable_collapsible_order_summary' ] = [
					'type'     => 'checkbox',
					'label'    => __( 'Enable', 'woofunnels-aero-checkout' ),
					'default'  => true,
					'priority' => 23,
				];
				$field['sections']['section']['fields']['cart_collapse_title']                                           = [
					'type'            => 'text',
					'label'           => __( 'Collapse View Text', 'woofunnels-aero-checkout' ),
					'default'         => __( 'Show Order Summary', 'woofunnels-aero-checkout' ),
					'priority'        => 23,
					'transport'       => 'postMessage',
					'wfacp_transport' => [
						[
							'type'                => 'html',
							'container_inclusive' => false,
							'elem'                => 'body .wfacp_show_icon_wrap a span',
						],
					],
					'active_callback' => [
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_enable_collapsible_order_summary',
							'operator' => '==',
							'value'    => true,
						],
					]


				];
				$field['sections']['section']['fields']['cart_expanded_title']                                           = [
					'type'            => 'text',
					'label'           => __( 'Expanded View Text', 'woofunnels-aero-checkout' ),
					'default'         => __( 'Hide Order Summary', 'woofunnels-aero-checkout' ),
					'priority'        => 23,
					'transport'       => 'postMessage',
					'wfacp_transport' => [
						[
							'type'                => 'html',
							'container_inclusive' => false,
							'elem'                => 'body .wfacp_show_icon_wrap a span',
						],
					],
					'active_callback' => [
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_enable_collapsible_order_summary',
							'operator' => '==',
							'value'    => true,
						],
					]
				];
				$field['sections']['section']['fields'][ $selected_template_slug . '_enable_coupon' ]                    = [
					'type'            => 'checkbox',
					'label'           => __( 'Hide Coupon', 'woofunnels-aero-checkout' ),
					'description'     => __( 'Check if you want to hide the coupon', 'woofunnels-aero-checkout' ),
					'default'         => false,
					'priority'        => 23,
					'active_callback' => [
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_enable_collapsible_order_summary',
							'operator' => '==',
							'value'    => true,
						],
					]
				];
				$field['sections']['section']['fields'][ $selected_template_slug . '_enable_coupon_collapsible' ]        = [
					'type'            => 'checkbox',
					'label'           => __( 'Make Collapsible', 'woofunnels-aero-checkout' ),
					'description'     => __( 'Check if you want to keep coupon field collapsible', 'woofunnels-aero-checkout' ),
					'default'         => true,
					'priority'        => 23,
					'active_callback' => [
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_enable_coupon',
							'operator' => '==',
							'value'    => false,
						],
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_enable_collapsible_order_summary',
							'operator' => '==',
							'value'    => true,
						],
					]
				];


			} elseif ( $key == 'wfacp_product' ) {
				$field['sections']['section']['fields']['ct_layout']                                        = [
					'type'     => 'custom',
					'default'  => '<div class="options-title-divider">' . esc_html__( 'Layout', 'woofunnels-aero-checkout' ) . '</div>',
					'priority' => 19,
				];
				$field['sections']['section']['fields']['layouts']                                          = [
					'type'     => 'radio-image-text',
					'label'    => '',
					'default'  => 'top_bottom',
					'choices'  => array(
						'top_bottom' => array(
							'left_right' => __( 'Top bottom', 'woofunnels-aero-checkout' ),
							'path'       => $this->img_path . 'product/product-top-bottom.svg',
						),
						'left_right' => array(
							'label' => __( 'left right', 'woofunnels-aero-checkout' ),
							'path'  => $this->img_path . 'product/product-left-right.svg',
						),

					),
					'priority' => 19,
				];
				$field['sections']['section']['fields']['ct_heading']                                       = [
					'type'     => 'custom',
					'default'  => '<div class="options-title-divider">' . esc_html__( 'Section Heading', 'woofunnels-aero-checkout' ) . '</div>',
					'priority' => 19,
				];
				$field['sections']['section']['fields'][ $selected_template_slug . '_enable_heading' ]      = [
					'type'        => 'checkbox',
					'label'       => __( 'Enable Section Heading', 'woofunnels-aero-checkout' ),
					'description' => '',
					'default'     => true,
					'priority'    => 19,
				];
				$field['sections']['section']['fields']['heading']                                          = [
					'type'            => 'text',
					'label'           => __( 'Heading', 'woofunnels-aero-checkout' ),
					'description'     => '',
					'default'         => esc_attr__( 'YOUR AWESOME PRODUCT', 'woofunnels-aero-checkout' ),
					'transport'       => 'postMessage',
					'wfacp_partial'   => [
						'elem' => '.wfacp_product .wfacp_section_title',
					],
					'active_callback' => [
						[
							'setting'  => 'wfacp_product_section_' . $selected_template_slug . '_enable_heading',
							'operator' => '==',
							'value'    => true,
						],
					],
					'priority'        => 19,
				];
				$field['sections']['section']['fields'][ $selected_template_slug . '_heading_fs' ]          = [
					'type'            => 'wfacp-responsive-font',
					'label'           => __( 'Font Size', 'woofunnels-aero-checkout' ),
					'default'         => [
						'desktop' => 18,
						'tablet'  => 18,
						'mobile'  => 18,
					],
					'input_attrs'     => [
						'step' => 1,
						'min'  => 12,
						'max'  => 32,
					],
					'units'           => [
						'px' => 'px',
						'em' => 'em',
					],
					'transport'       => 'postMessage',
					'wfacp_transport' => [
						[
							'internal'   => true,
							'responsive' => true,
							'type'       => 'css',
							'prop'       => [ 'font-size' ],
							'elem'       => '.wfacp_product .wfacp_section_title',
						],
					],
					'active_callback' => [
						[
							'setting'  => 'wfacp_product_section_' . $selected_template_slug . '_enable_heading',
							'operator' => '==',
							'value'    => true,
						],
					],
					'priority'        => 19,
				];
				$field['sections']['section']['fields'][ $selected_template_slug . '_heading_talign' ]      = [
					'type'            => 'radio-buttonset',
					'label'           => __( 'Text Alignment', 'woofunnels-aero-checkout' ),
					'default'         => 'wfacp-text-left',
					'choices'         => [
						'wfacp-text-left'   => 'Left',
						'wfacp-text-center' => 'Center',
						'wfacp-text-right'  => 'Right',
					],
					'priority'        => 19,
					'transport'       => 'postMessage',
					'wfacp_transport' => [
						[
							'type'   => 'add_class',
							'direct' => 'true',
							'remove' => [ 'wfacp-text-left', 'wfacp-text-center', 'wfacp-text-right' ],
							'elem'   => '.wfacp_product .wfacp_section_title',
						],
					],
					'active_callback' => [
						[
							'setting'  => 'wfacp_product_section_' . $selected_template_slug . '_enable_heading',
							'operator' => '==',
							'value'    => true,
						],
					],
				];
				$field['sections']['section']['fields'][ $selected_template_slug . '_heading_font_weight' ] = [
					'type'    => 'radio-buttonset',
					'label'   => __( 'Font Weight', 'woofunnels-aero-checkout' ),
					'default' => 'wfacp-normal',
					'choices' => [
						'wfacp-bold'   => 'Bold',
						'wfacp-normal' => 'Normal',
					],

					'priority'        => 19,
					'transport'       => 'postMessage',
					'wfacp_transport' => [
						[
							'type'   => 'add_class',
							'direct' => 'true',
							'remove' => [ 'wfacp-bold', 'wfacp-normal' ],
							'elem'   => '.wfacp_product .wfacp_section_title',
						],
					],
					'active_callback' => [
						[
							'setting'  => 'wfacp_product_section_' . $selected_template_slug . '_enable_heading',
							'operator' => '==',
							'value'    => true,
						],
					],

				];
				$field['sections']['section']['fields'][ $selected_template_slug . '_section_bg_color' ]    = [
					'type'            => 'color',
					'label'           => esc_attr__( 'Background Color', 'woofunnels-aero-checkout' ),
					'default'         => 'transparent',
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
				];
				$field['sections']['section']['fields'][ $selected_template_slug . '_sec_heading_color' ]   = [
					'type'            => 'color',
					'label'           => esc_attr__( 'Section Heading Color', 'woofunnels-aero-checkout' ),
					'default'         => '#333333',
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
							'elem'     => '.wfacp_product .wfacp_section_title',
						],
					],
				];
				$field['sections']['section']['fields']['advanced_setting']                                 = [
					'type'     => 'custom',
					'default'  => '<div class="options-title-divider">' . esc_html__( 'Advanced', 'woofunnels-aero-checkout' ) . '</div>',
					'priority' => 225,
				];
				$field['sections']['section']['fields'][ $selected_template_slug . '_rbox_border_type' ]    = [
					'type'    => 'select',
					'label'   => esc_attr__( 'Border Type', 'woofunnels-aero-checkout' ),
					'default' => 'none',
					'choices' => array(
						'none'   => 'None',
						'solid'  => 'Solid',
						'double' => 'Double',
						'dotted' => 'Dotted',
						'dashed' => 'Dashed',
					),

					'priority'        => 226,
					'transport'       => 'postMessage',
					'wfacp_transport' => [
						[
							'internal' => true,
							'type'     => 'css',
							'prop'     => [ 'border-style' ],
							'elem'     => '.wfacp_product',
						],
						[
							'type'   => 'add_class',
							'direct' => 'true',
							'remove' => [ 'none', 'solid', 'double', 'dotted', 'dashed' ],
							'elem'   => '.wfacp_product',
						],
					],
				];
				$field['sections']['section']['fields'][ $selected_template_slug . '_rbox_border_width' ]   = [
					'type'            => 'slider',
					'label'           => esc_attr__( 'Border Width', 'woofunnels-aero-checkout' ),
					'default'         => 1,
					'choices'         => array(
						'min'  => '1',
						'max'  => '12',
						'step' => '1',
					),
					'priority'        => 226,
					'active_callback' => array(
						array(
							'setting'  => 'wfacp_product_section_' . $selected_template_slug . '_rbox_border_type',
							'operator' => '!=',
							'value'    => 'none',
						),
					),
					'transport'       => 'postMessage',
					'wfacp_transport' => [
						[
							'internal' => true,
							'type'     => 'css',
							'prop'     => [ 'border-width' ],
							'elem'     => '.wfacp_product',
						],
					],
				];
				$field['sections']['section']['fields'][ $selected_template_slug . '_rbox_border_color' ]   = [

					'type'            => 'color',
					'label'           => esc_attr__( 'Border Color', 'woofunnels-aero-checkout' ),
					'default'         => '#e2e2e2',
					'choices'         => array(
						'alpha' => true,
					),
					'priority'        => 226,
					'transport'       => 'postMessage',
					'wfacp_transport' => [
						[
							'internal' => true,
							'type'     => 'css',
							'prop'     => [ 'border-color' ],
							'elem'     => '.wfacp_product',
						],
					],
					'active_callback' => array(
						array(
							'setting'  => 'wfacp_product_section_' . $selected_template_slug . '_rbox_border_type',
							'operator' => '!=',
							'value'    => 'none',
						),
					),
				];
				$field['sections']['section']['fields'][ $selected_template_slug . '_rbox_padding' ]        = [
					'type'            => 'number',
					'label'           => __( 'Padding', 'woofunnels-aero-checkout' ),
					'default'         => 20,
					'priority'        => 226,
					'transport'       => 'postMessage',
					'wfacp_transport' => [
						[
							'internal' => true,
							'type'     => 'css',
							'suffix'   => 'px',
							'prop'     => [ 'padding' ],
							'elem'     => '.wfacp_product',
						],
					],
					'active_callback' => array(
						array(
							'setting'  => 'wfacp_product_section_' . $selected_template_slug . '_rbox_border_type',
							'operator' => '!=',
							'value'    => 'none',
						),
					),
				];
				unset( $field['sections']['section']['fields'][ $selected_template_slug . '_section_height' ] );
				unset( $field['sections']['section']['fields'][ $selected_template_slug . '_enable_product_section' ] );
				unset( $field['sections']['section']['fields']['ct_enable_product_section'] );
				unset( $field['sections']['section']['fields']['title'] );
				unset( $field['sections']['section']['fields'][ $selected_template_slug . '_title_fs' ] );
				unset( $field['sections']['section']['fields'][ $selected_template_slug . '_heading_text_color' ] );
			}

			return $field;

		}

		public function custom_add_form_steps() {
			if ( WFACP_Core()->pay->is_order_pay() ) {
				return '';
			}
			$selected_template_type = $this->get_template_type();

			$num_of_steps = $this->get_step_count();
			if ( $selected_template_type != 'embed_form' && $num_of_steps > 1 ) {
				if ( isset( $this->customizer_fields_data['wfacp_form']['form_data']['breadcrumb'] ) && is_array( $this->customizer_fields_data['wfacp_form']['form_data']['breadcrumb'] ) && count( $this->customizer_fields_data['wfacp_form']['form_data']['breadcrumb'] ) > 0 ) {

					$steps_arr = [ 'single_step', 'two_step', 'third_step' ];

					$breadcrumb = $this->customizer_fields_data['wfacp_form']['form_data']['breadcrumb'];
					if ( is_array( $breadcrumb ) && count( $breadcrumb ) > 0 ) {

						echo '<div class="wfacp_custom_breadcrumb">';
						echo '<div class=wfacp_steps_wrap>';
						echo '<div class=wfacp_steps_sec>';

						echo '<ul>';

						do_action( 'wfacp_before_breadcrumb', $breadcrumb );


						$breadcrumb_count = sizeof( $breadcrumb );
						$steps_count      = 100 / $breadcrumb_count;

						$width_calc = number_format( $steps_count, 0 ) . "%";

						foreach ( $breadcrumb as $key => $value ) {
							$active = '';

							if ( $key == 0 ) {
								$active = 'wfacp_bred_active wfacp_bred_visited';
							}

							$step = ( isset( $steps_arr[ $key ] ) ) ? $steps_arr[ $key ] : '';

							$active = apply_filters( 'wfacp_layout_9_active_progress_bar', $active, $step );

							echo "<li class='wfacp_step_$key wfacp_bred $active $step ' style='width:$width_calc;' step='$step' ><a href='javascript:void(0)' class='wfacp_step_text_have' data-text='" . sanitize_title( $value ) . "'>$value</a> </li>";
						}
						do_action( 'wfacp_after_breadcrumb' );
						echo '</ul></div></div></div>';
					}
				}
			}
		}

		public function change_header_text( $data ) {
			$partial_key_base = $data->id_data();

			if ( is_array( $partial_key_base ) && isset( $partial_key_base['keys'] ) ) {
				$partial_key = $partial_key_base['keys'][0];
				$header_text = WFACP_Common::get_option( $partial_key );

				$classBlank = 'wfacp_display_none';
				if ( $header_text != '' ) {
					$classBlank = '';
				}
				ob_start();
				?>
                <li class="wfacp_header_list_sup  <?php echo $classBlank; ?>">
                    <span class="wfacp-hd-list-sup"><?php echo $header_text; ?></span>
                </li>
				<?php
				$header_text_html = ob_get_clean();

				return $header_text_html;
			}
		}

		public function customizer_layout_order( $panel_details, $section_key ) {
			$selected_template_slug       = $this->template_slug;
			$fields_data                  = $panel_details['sections']['section']['fields'];
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
			$cartTitle = esc_attr__( 'Your Cart', 'woofunnels-aero-checkout' );


			$new_fields_layout_orders_choices = [
				'wfacp_product' => 'Product',
				'wfacp_cart'    => $cartTitle,
			];

			$new_fields_layout_orders = [ 'wfacp_product', 'wfacp_cart' ];

			$layout_9_order_choices = array_merge( $new_fields_layout_orders_choices, $default_sidebar_layout_order_choices );
			$layout_9_order         = array_merge( $new_fields_layout_orders, $default_sidebar_layout_order );


			$mobile_sections_page_choices = $panel_details['sections']['section']['fields'][ $selected_template_slug . '_mobile_sections_page_order' ]['choices'];
			$mobile_sections_page_default = $panel_details['sections']['section']['fields'][ $selected_template_slug . '_mobile_sections_page_order' ]['default'];

			$temp_arr         = [
				'wfacp_product' => 'Product',
			];
			$temp_arr_default = [ 'wfacp_product' ];

			unset( $mobile_sections_page_choices['wfacp_product'] );
			$identical_key = array_search( 'wfacp_product', $mobile_sections_page_default, true );
			unset( $mobile_sections_page_default[ $identical_key ] );
			$mobile_sections_page_default = array_values( $mobile_sections_page_default );

			$final_layout_mb         = array_merge( $temp_arr, $mobile_sections_page_choices );
			$final_layout_mb_default = array_merge( $temp_arr_default, $mobile_sections_page_default );

			if ( is_array( $layout_9_order ) && in_array( 'wfacp_product', $layout_9_order ) ) {

				$wfacp_product_key = array_search( 'wfacp_product', $layout_9_order, true );

				unset( $layout_9_order[ $wfacp_product_key ] );
				$layout_9_order = array_values( $layout_9_order );
			}

			$CurrentpageID  = WFACP_Common::get_id();
			$_wfacp_version = WFACP_Common::get_post_meta_data( $CurrentpageID, '_wfacp_version' );
			if ( isset( $_wfacp_version ) && ! empty( $_wfacp_version ) ) {
				if ( isset( $final_layout_mb['wfacp_product'] ) && $final_layout_mb['wfacp_product'] == 'Product' ) {
					$final_layout_mb_default_unset = array_search( 'wfacp_product', $final_layout_mb_default );
					unset( $final_layout_mb_default[ $final_layout_mb_default_unset ] );
					$final_layout_mb_default = array_values( $final_layout_mb_default );
				}
			}

			$panel_details['sections']['section']['fields'][ $selected_template_slug . '_sidebar_layout_order' ]['choices'] = $layout_9_order_choices;
			$panel_details['sections']['section']['fields'][ $selected_template_slug . '_sidebar_layout_order' ]['default'] = $layout_9_order;

			$panel_details['sections']['section']['fields'][ $selected_template_slug . '_mobile_sections_page_order' ]['choices'] = $final_layout_mb;
			$panel_details['sections']['section']['fields'][ $selected_template_slug . '_mobile_sections_page_order' ]['default'] = $final_layout_mb_default;

			return $panel_details;
		}

		public function wfacp_header_logo( $data ) {
			$partial_key_base = $data->id_data();

			if ( is_array( $partial_key_base ) && isset( $partial_key_base['keys'] ) ) {
				$partial_key = $partial_key_base['keys'][0];
				$logo        = WFACP_Common::get_option( $partial_key );

				$no_logo_img = $this->img_path . 'woo_checkout_logo_layout_9.png';

				ob_start();
				?>


                <img class="wfacp-logo" src="<?php echo $logo ? $logo : $no_logo_img; ?>" alt="<?php the_title() ?>">


				<?php
				$wfacp_header_logo = ob_get_clean();

				return $wfacp_header_logo;
			}
		}

		public function enqueue_style() {

			if ( is_rtl() ) {
				wp_enqueue_style( 'layout9-style', $this->url . 'css/style-rtl.css', array(), WFACP_VERSION, false );
				wp_enqueue_style( 'wfacp-common-rtl-style', plugin_dir_url( WFACP_PLUGIN_FILE ) . 'assets/css/wfacp-common-style-rtl.css', false, WFACP_VERSION_DEV );

			} else {
				wp_enqueue_style( 'layout9-style', $this->url . 'css/style.css', array(), WFACP_VERSION, false );
			}


		}


		public function add_order_summary_to_sidebar() {
			include __DIR__ . '/views/template-parts/order-summary.php';
		}

		public function add_order_summary_to_sidebar_section() {
			include __DIR__ . '/views/template-parts/mini-cart-order-summary.php';
		}


		public function add_fragment_order_summary( $fragments ) {
			ob_start();
			include __DIR__ . '/views/template-parts/main-order-summary.php';
			$fragments['.wfacp_order_summary'] = ob_get_clean();

			ob_start();
			include __DIR__ . '/views/template-parts/order-review.php';
			$fragments['.wfacp_template_9_cart_item_details'] = ob_get_clean();

			ob_start();
			include __DIR__ . '/views/template-parts/order-total.php';
			$fragments['.wfacp_template_9_cart_total_details'] = ob_get_clean();

			ob_start();
			wc_cart_totals_order_total_html();
			$fragments['.wfacp_cart_mb_fragment_price'] = ob_get_clean();
			$order_summary_cart_price                   = apply_filters( 'wfacp_collapsible_order_summary_cart_price', wc_price( WC()->cart->total ) );

			$fragments['.wfacp_show_price_wrap'] = '<div class="wfacp_show_price_wrap">' . do_action( "wfacp_before_mini_price" ) . '<strong>' . $order_summary_cart_price . '</strong>' . do_action( 'wfacp_after_mini_price' ) . '</div>';

			return $fragments;
		}


		public function woocommerce_order_summary_label_change( $field ) {

			if ( ! is_array( $field ) ) {
				$field = [];
			}

			$cart_text = WFACP_Common::get_option( 'wfacp_form_cart_section_heading' );

			if ( isset( $cart_text ) && ! empty( $cart_text ) ) {

				$field['label'] = $cart_text;
			} elseif ( isset( $field['label'] ) ) {

				$field['label'] = __( 'Your Cart', 'woofunnels-aero-checkout' );
				$pageID         = WFACP_Common::get_id();
				$_wfacp_version = WFACP_Common::get_post_meta_data( $pageID, '_wfacp_version' );

				if ( $_wfacp_version == WFACP_VERSION ) {
					$field['label'] = __( 'Order summary', 'woocommerce' );

				}
			}

			return $field;
		}

		public function check_setting_show_img_order_summary() {

			$selected_template_slug = $this->get_template_slug();
			$layout_key             = '';
			if ( isset( $selected_template_slug ) && $selected_template_slug != '' ) {
				$layout_key = $selected_template_slug . '_';
			}

			$order_hide_img = WFACP_Common::get_option( 'wfacp_form_cart_section_' . $layout_key . 'order_hide_img' );

			$order_hide_img = isset( $order_hide_img ) ? $order_hide_img : false;

			if ( ( true === $order_hide_img || 1 === $order_hide_img ) && ! empty( $order_hide_img ) ) {

				return true;
			}


			return false;

		}

		public function check_layout_9_sidebar_hide_coupon() {

			$selected_template_slug = $this->get_template_slug();
			$layout_key             = '';
			if ( isset( $selected_template_slug ) && $selected_template_slug != '' ) {
				$layout_key = $selected_template_slug . '_';
			}

			$order_hide_right_side_coupon = WFACP_Common::get_option( 'wfacp_form_cart_section_' . $layout_key . 'order_hide_right_side_coupon' );


			$order_hide_right_side_coupon = isset( $order_hide_right_side_coupon ) ? $order_hide_right_side_coupon : true;

			if ( wc_string_to_bool( $order_hide_right_side_coupon ) ) {

				return false;
			}


			return true;

		}

		public function hide_coupon_on_mobile_mini_cart() {

			$selected_template_slug = $this->get_template_slug();
			$layout_key             = '';
			if ( isset( $selected_template_slug ) && $selected_template_slug != '' ) {
				$layout_key = $selected_template_slug . '_';
			}

			$order_hide_right_side_coupon = WFACP_Common::get_option( 'wfacp_form_section_' . $layout_key . 'enable_coupon' );


			$order_hide_right_side_coupon = isset( $order_hide_right_side_coupon ) ? $order_hide_right_side_coupon : true;

			if ( wc_string_to_bool( $order_hide_right_side_coupon ) ) {

				return false;
			}


			return true;

		}


		public function hide_default_form_coupon() {
			return true;
		}

		public function add_blank_html_for_coupon_error() {
			echo '<div class="wfacp_layout_9_coupon_error_msg"></div>';
		}

		public function get_order_pay_summary( $order ) {
			include __DIR__ . '/views/template-parts/main-order-pay-summary.php';
		}

	}

	return WFACP_template_layout9::get_instance();
}