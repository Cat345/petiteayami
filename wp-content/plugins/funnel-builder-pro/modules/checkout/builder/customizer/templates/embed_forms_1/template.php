<?php
if ( ! class_exists( 'WFACP_Template_Custom_Page' ) ) {
	#[AllowDynamicProperties]
	final class WFACP_Template_Custom_Page extends WFACP_Pre_Built {

		private static $ins = null;
		protected $template_type = 'embed_form';
		protected $form_steps_data = [];
		public $steps_inline_styles = [];
		private $shortcode_id = 'wfacp_form_summary_shortcode';

		/**
		 * Using protected method no one create new instance this class
		 * WFACP_template_layout1 constructor.
		 */
		protected function __construct() {
			parent::__construct();
			$this->template_dir  = __DIR__;
			$this->template_slug = 'embed_forms_2';
			$this->css_classes   = [];
			add_action( 'wfacp_before_process_checkout_template_loader', [ $this, 'get_ajax_exchange_keys' ] );

			define( 'WFACP_TEMPLATE_MODULE_DIR', __DIR__ . '/views/template-parts/sections' );
			$this->url = WFACP_PLUGIN_URL . '/builder/customizer/templates/embed_forms_1/views/';


			$is_customizer_preview = WFACP_Common::is_customizer();
			if ( true == $is_customizer_preview ) {
				add_action( 'wp_print_styles', [ $this, 'remove_theme_css_and_scripts' ], 100 );
			}

			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_style' ], 9999 );
			add_filter( 'wfacp_customizer_supports', '__return_empty_array' );
			add_action( 'wfacp_header_print_in_head', [ $this, 'add_step_form_style' ] );

			add_action( 'wfacpef_before_form', [ $this, 'get_step_forms_data' ] );

			$this->set_default_layout_setting();
			add_filter( 'wfacp_layout_default_setting', [ $this, 'change_default_setting1' ], 10, 2 );
			add_filter( 'wfacp_layout_default_setting', [ $this, 'change_default_setting_disabled_step_bar' ], 11, 2 );


			add_filter( 'wfacp_style_default_setting', [ $this, 'wfacp_multi_tab_default_setting' ], 11, 2 );
			add_filter( 'wfacp_load_template', [ $this, 'disable_template_loading' ] );

			add_filter( 'wfacp_order_summary_cols_span', '__return_empty_string' );

			/* Activate DIvi Customizer csss for embed form */
			add_action( 'wp', [ $this, 'run_divi_customizer_css' ] );

			add_filter( 'wfacp_customizer_fieldset', [ $this, 'add_panal_for_mini_cart' ], 10, 2 );

			add_action( 'wfacp_before_sidebar_content', array( $this, 'add_order_summary_to_sidebar' ), 11 );
			add_filter( 'wfacp_mini_cart_hide_coupon', [ $this, 'hide_coupon_on_mobile_mini_cart' ], 10 );
			add_action( 'wfacp_internal_css', [ $this, 'add_internal_css' ] );

		}

		public static function get_instance() {
			if ( is_null( self::$ins ) ) {
				self::$ins = new self();
			}

			return self::$ins;
		}


		public function enqueue_style() {
			if ( apply_filters( 'wfacp_not_allowed_cart_fragments_js_for_embed_form', true, $this ) ) {
				wp_dequeue_script( 'wc-cart-fragments' );
			}
			wp_enqueue_style( 'layout1-style', plugin_dir_url( WFACP_PLUGIN_FILE ) . 'assets/css/wfacp-form.min.css', '', WFACP_VERSION, false );
			if ( is_rtl() ) {
				wp_enqueue_style( 'layout1-style-rtl', plugin_dir_url( WFACP_PLUGIN_FILE ) . 'assets/css/wfacp-form-style-rtl.css', '', WFACP_VERSION, false );

			}
		}


		public function get_step_forms_data() {
			$selected_template_slug = $this->get_template_slug();
			$layout_key             = '';
			if ( isset( $selected_template_slug ) && $selected_template_slug != '' ) {
				$layout_key = $selected_template_slug . '_';
			}

			$section_key = 'wfacp_form';
			$data        = array();

			/* Layout Section */
			$data[ $section_key ]['layout']['step_form_max_width'] = WFACP_Common::get_option( $section_key . '_section_' . $layout_key . 'step_form_max_width' );
			$data[ $section_key ]['layout']['disable_steps_bar']   = WFACP_Common::get_option( $section_key . '_section_' . $layout_key . 'disable_steps_bar' );
			$data[ $section_key ]['layout']['select_type']         = WFACP_Common::get_option( $section_key . '_section_' . $layout_key . 'select_type' );

			if ( isset( $this->customizer_fields_data['wfacp_form']['form_data']['breadcrumb'] ) && isset( $data[ $section_key ]['layout']['disable_steps_bar'] ) ) {
				$this->customizer_fields_data['wfacp_form']['form_data']['bar']['disable_steps_bar'] = $data[ $section_key ]['layout']['disable_steps_bar'];
			}
			if ( isset( $data[ $section_key ]['layout']['select_type'] ) ) {
				$this->customizer_fields_data['wfacp_form']['form_data']['bar']['select_type'] = $data[ $section_key ]['layout']['select_type'];
			}

			/* Stepbar styling */
			$data[ $section_key ]['steps_styling']['step_heading_font_size']     = WFACP_Common::get_option( $section_key . '_section_' . $layout_key . 'step_heading_font_size' );
			$data[ $section_key ]['steps_styling']['step_sub_heading_font_size'] = WFACP_Common::get_option( $section_key . '_section_' . $layout_key . 'step_sub_heading_font_size' );
			$data[ $section_key ]['steps_styling']['step_alignment']             = WFACP_Common::get_option( $section_key . '_section_' . $layout_key . 'step_alignment' );


			/* Step Data Section */

			$no_of_fields = $this->get_step_count();
			$count_is     = 1;
			for ( $i = 0; $i < $no_of_fields; $i ++ ) {
				$stepData           = array();
				$field_key_name     = 'name_' . $i;
				$field_key_headling = 'headline_' . $i;

				$name_text    = WFACP_Common::get_option( $section_key . '_section_' . $layout_key . $field_key_name );
				$heading_text = WFACP_Common::get_option( $section_key . '_section_' . $layout_key . $field_key_headling );

				if ( ( isset( $heading_text ) && $heading_text != '' ) || ( isset( $name_text ) && $name_text != '' ) ) {
					$stepData[ $field_key_name ]         = $name_text;
					$stepData[ $field_key_headling ]     = $heading_text;
					$data[ $section_key ]['step_form'][] = $stepData;
				} else {
					$data[ $section_key ]['step_form'][] = [
						'name_' . $i     => 'Step ' . $count_is . ' Heading',
						'headline_' . $i => 'Step ' . $count_is . ' Sub heading',
					];
				}
				$count_is ++;

			}

			/* Layout Section */
			$data[ $section_key ]['colors']['active_step_bg_color']         = WFACP_Common::get_option( $section_key . '_section_' . $layout_key . 'active_step_bg_color' );
			$data[ $section_key ]['colors']['active_step_text_color']       = WFACP_Common::get_option( $section_key . '_section_' . $layout_key . 'active_step_text_color' );
			$data[ $section_key ]['colors']['active_step_count_bg_color']   = WFACP_Common::get_option( $section_key . '_section_' . $layout_key . 'active_step_count_bg_color' );
			$data[ $section_key ]['colors']['active_step_count_text_color'] = WFACP_Common::get_option( $section_key . '_section_' . $layout_key . 'active_step_count_text_color' );

			$data[ $section_key ]['border-color']['active_step_count_border_color'] = WFACP_Common::get_option( $section_key . '_section_' . $layout_key . 'active_step_count_border_color' );
			$data[ $section_key ]['border-color']['active_step_tab_border_color']   = WFACP_Common::get_option( $section_key . '_section_' . $layout_key . 'active_step_tab_border_color' );

			$data[ $section_key ]['colors']['inactive_step_bg_color']         = WFACP_Common::get_option( $section_key . '_section_' . $layout_key . 'inactive_step_bg_color' );
			$data[ $section_key ]['colors']['inactive_step_text_color']       = WFACP_Common::get_option( $section_key . '_section_' . $layout_key . 'inactive_step_text_color' );
			$data[ $section_key ]['colors']['inactive_step_count_bg_color']   = WFACP_Common::get_option( $section_key . '_section_' . $layout_key . 'inactive_step_count_bg_color' );
			$data[ $section_key ]['colors']['inactive_step_count_text_color'] = WFACP_Common::get_option( $section_key . '_section_' . $layout_key . 'inactive_step_count_text_color' );

			$data[ $section_key ]['border-color']['inactive_step_count_border_color'] = WFACP_Common::get_option( $section_key . '_section_' . $layout_key . 'inactive_step_count_border_color' );
			$data[ $section_key ]['border-color']['inactive_step_tab_border_color']   = WFACP_Common::get_option( $section_key . '_section_' . $layout_key . 'inactive_step_tab_border_color' );


			$data[ $section_key ]['colors']['form_content_color'] = WFACP_Common::get_option( $section_key . '_section_' . $layout_key . 'form_content_color' );


			$data[ $section_key ]['other']['field_border_width'] = WFACP_Common::get_option( $section_key . '_section_' . $layout_key . 'field_border_width' );

			/* FOrm Border */

			$data[ $section_key ]['other']['form_border']['border-style'] = WFACP_Common::get_option( $section_key . '_section_' . $layout_key . 'form_border_type' );
			$data[ $section_key ]['other']['form_border']['border-width'] = WFACP_Common::get_option( $section_key . '_section_' . $layout_key . 'form_border_width' );
			$data[ $section_key ]['other']['form_border']['border-color'] = WFACP_Common::get_option( $section_key . '_section_' . $layout_key . 'form_border_color' );
			$data[ $section_key ]['other']['padding']                     = WFACP_Common::get_option( $section_key . '_section_' . $layout_key . 'form_inner_padding' );


			if ( isset( $data[ $section_key ]['other']['form_border'] ) ) {
				$form_border = $data[ $section_key ]['other']['form_border'];
				if ( is_array( $form_border ) && count( $form_border ) > 0 ) {
					foreach ( $form_border as $fkey => $fborder ) {
						$px = '';
						if ( $fkey == 'border-width' ) {
							$px = 'px';
							if ( isset( $data[ $section_key ]['layout']['disable_steps_bar'] ) && $data[ $section_key ]['layout']['disable_steps_bar'] == false ) {
								$this->steps_inline_styles['desktop']['body.wfacpef_page #wfacp-e-form .wfacp_form .wfacp-inner-form-detail-wrap']['border-top-width']    = 0 . $px;
								$this->steps_inline_styles['desktop']['body.wfacpef_page #wfacp-e-form .wfacp_form .wfacp-inner-form-detail-wrap']['border-left-width']   = $fborder . $px;
								$this->steps_inline_styles['desktop']['body.wfacpef_page #wfacp-e-form .wfacp_form .wfacp-inner-form-detail-wrap']['border-right-width']  = $fborder . $px;
								$this->steps_inline_styles['desktop']['body.wfacpef_page #wfacp-e-form .wfacp_form .wfacp-inner-form-detail-wrap']['border-bottom-width'] = $fborder . $px;
								continue;
							}


						}
						$this->steps_inline_styles['desktop']['body.wfacpef_page #wfacp-e-form .wfacp_form .wfacp-inner-form-detail-wrap'][ $fkey ] = $fborder . $px;


					}

				}
			}


			if ( isset( $data[ $section_key ]['other']['padding'] ) ) {
				$this->steps_inline_styles['desktop']['body #wfacp-e-form .wfacp_form .wfacp-inner-form-detail-wrap']['padding'] = $data[ $section_key ]['other']['padding'] . "px";
			}


			$this->form_steps_data = $data;


			if ( isset( $data[ $section_key ]['colors']['form_content_color'] ) ) {
				$form_content_color                                                           = $data[ $section_key ]['colors']['form_content_color'];
				$this->steps_inline_styles['desktop']['body .wfacp_main_form label']['color'] = $form_content_color;

				$this->steps_inline_styles['desktop']['body .wfacp_main_form p']['color']                                    = $form_content_color;
				$this->steps_inline_styles['desktop']['body .wfacp_main_form .woocommerce-terms-and-conditions']['color']    = $form_content_color;
				$this->steps_inline_styles['desktop']['body .wfacp_main_form .woocommerce-terms-and-conditions h1']['color'] = $form_content_color;
				$this->steps_inline_styles['desktop']['body .wfacp_main_form .woocommerce-terms-and-conditions h2']['color'] = $form_content_color;
				$this->steps_inline_styles['desktop']['body .wfacp_main_form .woocommerce-terms-and-conditions h3']['color'] = $form_content_color;
				$this->steps_inline_styles['desktop']['body #et_builder_outer_content #wfacp-e-form p']['color']             = $form_content_color . ' !important';

				$this->steps_inline_styles['desktop']['body .wfacp_main_form span.woocommerce-input-wrapper label.checkbox']['color']                         = $form_content_color;
				$this->steps_inline_styles['desktop']['body .wfacp_main_form table.shop_table.woocommerce-checkout-review-order-table thead tr th']['color']  = $form_content_color;
				$this->steps_inline_styles['desktop']['body .wfacp_main_form table.shop_table.woocommerce-checkout-review-order-table tr td']['color']        = $form_content_color;
				$this->steps_inline_styles['desktop']['body .wfacp_main_form table.shop_table.woocommerce-checkout-review-order-table tr th']['color']        = $form_content_color;
				$this->steps_inline_styles['desktop']['body .wfacp_main_form .woocommerce-form-coupon-toggle.wfacp-woocom-coupon .woocommerce-info']['color'] = $form_content_color;

				$this->steps_inline_styles['desktop']['body .wfacp_main_form .woocommerce-info']['color']                 = $form_content_color . ' !important';
				$this->steps_inline_styles['desktop']['body #wfacp-e-form .woocommerce-info .message-container']['color'] = $form_content_color;

				$this->steps_inline_styles['desktop']['body .wfacp_main_form form.woocommerce-form.woocommerce-form-login.login p']['color']       = $form_content_color;
				$this->steps_inline_styles['desktop']['body .wfacp_main_form label.woocommerce-form__label span']['color']                         = $form_content_color;
				$this->steps_inline_styles['desktop']['body .wfacp_main_form form.checkout_coupon.woocommerce-form-coupon p']['color']             = $form_content_color;
				$this->steps_inline_styles['desktop']['body .wfacp_main_form .shop_table.wfacp-product-switch-panel .product-name label']['color'] = $form_content_color;

				$this->steps_inline_styles['desktop']['body .wfacp_main_form .shop_table.wfacp-product-switch-panel .product-price']['color']                         = $form_content_color;
				$this->steps_inline_styles['desktop']['body .wfacp_main_form .wfacp_row_wrap .product-name .wfacp_product_sec .wfacp_product_choosen_label']['color'] = $form_content_color;
				$this->steps_inline_styles['desktop']['body .wfacp_main_form .shop_table.wfacp-product-switch-panel .wfacp-product-switch-title']['color']            = $form_content_color;
				$this->steps_inline_styles['desktop']['body .wfacp_main_form .wfacp-product-switch-title .product-remove']['color']                                   = $form_content_color;
				$this->steps_inline_styles['desktop']['body .wfacp_main_form .wfacp-product-switch-title .product-quantity']['color']                                 = $form_content_color;
				$this->steps_inline_styles['desktop']['body .wfacp_main_form .wfacp_shipping_table tr.shipping td']['color']                                          = $form_content_color;

			}

			if ( isset( $data[ $section_key ]['layout']['step_form_max_width'] ) ) {
				$step_form_max_width = $data[ $section_key ]['layout']['step_form_max_width'];

				$popup_width = $step_form_max_width + 10;

				$this->steps_inline_styles['desktop'][ 'body .' . $section_key ]['max-width']                                                 = $step_form_max_width . 'px';
				$this->steps_inline_styles['desktop']['body .wfacp_modal_outerwrap .wfacp_modal_innerwrap #wfacp_modal_content']['max-width'] = $popup_width . 'px';

				$current_version = WFACP_Common::get_checkout_page_version();


				if ( version_compare( $current_version, '1.9.3', '<=' ) ) {
					$this->steps_inline_styles['desktop'][ 'body .' . $section_key ]['margin'] = 'auto';

				}
				$this->steps_inline_styles['desktop']['body .wfacp_form']['padding'] = '0';

				$this->steps_inline_styles['desktop']['body .wfacp_paypal_express']['padding'] = '0';

			}

			$data[ $section_key ]['colors']['section_bg_color'] = WFACP_Common::get_option( $section_key . '_section_' . $layout_key . 'section_bg_color' );
			if ( isset( $data[ $section_key ]['colors']['section_bg_color'] ) ) {
				$section_bg_color                                                                                                        = $data[ $section_key ]['colors']['section_bg_color'];
				$this->steps_inline_styles['desktop'][ 'body .' . $section_key . ' .wfacp-inner-form-detail-wrap' ]['background-color']  = $section_bg_color;
				$this->steps_inline_styles['desktop'][ 'body .' . $section_key . ' .woocommerce-checkout #payment' ]['background-color'] = $section_bg_color;
			}

			if ( isset( $data[ $section_key ]['colors']['active_step_bg_color'] ) ) {
				$active_step_bg_color                                                                                                          = $data[ $section_key ]['colors']['active_step_bg_color'];
				$this->steps_inline_styles['desktop'][ 'body .' . $section_key . ' .wfacp-payment-tab-list.wfacp-active' ]['background-color'] = $active_step_bg_color;
			}

			if ( isset( $data[ $section_key ]['colors']['active_step_text_color'] ) ) {
				$active_step_text_color                                                                                                           = $data[ $section_key ]['colors']['active_step_text_color'];
				$this->steps_inline_styles['desktop'][ 'body .' . $section_key . ' .wfacp-payment-tab-list.wfacp-active .wfacp_tcolor' ]['color'] = $active_step_text_color;
			}

			if ( isset( $data[ $section_key ]['colors']['inactive_step_bg_color'] ) ) {
				$inactive_step_bg_color                                                                                           = $data[ $section_key ]['colors']['inactive_step_bg_color'];
				$this->steps_inline_styles['desktop'][ 'body .' . $section_key . ' .wfacp-payment-tab-list' ]['background-color'] = $inactive_step_bg_color;
				//	$this->steps_inline_styles['desktop'][ 'body .' . $section_key . ' .wfacp-payment-tab-wrapper' ]['background-color'] = $inactive_step_bg_color;
			}
			if ( isset( $data[ $section_key ]['colors']['inactive_step_text_color'] ) ) {
				$inactive_step_text_color                                                                                            = $data[ $section_key ]['colors']['inactive_step_text_color'];
				$this->steps_inline_styles['desktop'][ 'body .' . $section_key . ' .wfacp-payment-tab-list .wfacp_tcolor' ]['color'] = $inactive_step_text_color;
			}
			if ( isset( $data[ $section_key ]['colors']['active_step_count_bg_color'] ) ) {
				$active_step_count_bg_color                                                                                                                            = $data[ $section_key ]['colors']['active_step_count_bg_color'];
				$this->steps_inline_styles['desktop'][ 'body .' . $section_key . ' .wfacp-payment-tab-list.wfacp-active .wfacp-order2StepNumber' ]['background-color'] = $active_step_count_bg_color;
			}

			if ( isset( $data[ $section_key ]['colors']['active_step_count_text_color'] ) ) {
				$active_step_count_text_color                                                                                                               = $data[ $section_key ]['colors']['active_step_count_text_color'];
				$this->steps_inline_styles['desktop'][ 'body .' . $section_key . ' .wfacp-payment-tab-list.wfacp-active .wfacp-order2StepNumber' ]['color'] = $active_step_count_text_color;
			}

			if ( isset( $data[ $section_key ]['colors']['inactive_step_count_bg_color'] ) ) {
				$inactive_step_count_bg_color                                                                                                             = $data[ $section_key ]['colors']['inactive_step_count_bg_color'];
				$this->steps_inline_styles['desktop'][ 'body .' . $section_key . ' .wfacp-payment-tab-list .wfacp-order2StepNumber' ]['background-color'] = $inactive_step_count_bg_color;
			}
			if ( isset( $data[ $section_key ]['colors']['inactive_step_count_text_color'] ) ) {
				$inactive_step_count_text_color                                                                                                = $data[ $section_key ]['colors']['inactive_step_count_text_color'];
				$this->steps_inline_styles['desktop'][ 'body .' . $section_key . ' .wfacp-payment-tab-list .wfacp-order2StepNumber' ]['color'] = $inactive_step_count_text_color;
			}
			if ( isset( $data[ $section_key ]['steps_styling']['step_alignment'] ) ) {
				$step_alignment = $data[ $section_key ]['steps_styling']['step_alignment'];

				$this->steps_inline_styles['desktop'][ 'body .' . $section_key . '  .wfacp-order2StepHeaderText' ]['text-align'] = $step_alignment;

			}


			/* Active border color */
			if ( isset( $data[ $section_key ]['border-color']['active_step_count_border_color'] ) ) {
				$active_step_count_border_color                                                                                                                     = $data[ $section_key ]['border-color']['active_step_count_border_color'];
				$this->steps_inline_styles['desktop'][ 'body .' . $section_key . '  .wfacp-payment-tab-list.wfacp-active .wfacp-order2StepNumber' ]['border-color'] = $active_step_count_border_color;
			}

			if ( isset( $data[ $section_key ]['border-color']['active_step_tab_border_color'] ) ) {
				$active_step_tab_border_color                                                                                               = $data[ $section_key ]['border-color']['active_step_tab_border_color'];
				$this->steps_inline_styles['desktop'][ 'body .' . $section_key . '  .wfacp-payment-tab-list.wfacp-active' ]['border-color'] = $active_step_tab_border_color;

			}

			/* inActive border color */
			if ( isset( $data[ $section_key ]['border-color']['inactive_step_count_border_color'] ) ) {
				$inactive_step_count_border_color                                                                                                                        = $data[ $section_key ]['border-color']['inactive_step_count_border_color'];
				$this->steps_inline_styles['desktop'][ 'body .' . $section_key . ' .wfacp-payment-tab-list:not(.wfacp-active) .wfacp-order2StepNumber' ]['border-color'] = $inactive_step_count_border_color;
			}
			if ( isset( $data[ $section_key ]['border-color']['inactive_step_tab_border_color'] ) ) {
				$inactive_step_tab_border_color                                                                                                   = $data[ $section_key ]['border-color']['inactive_step_tab_border_color'];
				$this->steps_inline_styles['desktop'][ 'body .' . $section_key . '  .wfacp-payment-tab-list:not(.wfacp-active)' ]['border-color'] = $inactive_step_tab_border_color;
			}

			$step_heading_fonts = [
				'section_key' => 'wfacp_form',
				'target_to'   => 'body .wfacp-order2StepTitle',
				'source_from' => 'step_heading_font_size',
			];

			$this->wfacp_font_size( $data[ $section_key ]['steps_styling'], $step_heading_fonts );

			$step_subheading_fonts = [
				'section_key' => 'wfacp_form',
				'target_to'   => 'body .wfacp-order2StepSubTitle',
				'source_from' => 'step_sub_heading_font_size',
			];
			$this->wfacp_font_size( $data[ $section_key ]['steps_styling'], $step_subheading_fonts );


		}

		public function steps_customizer( $panel_details ) {

			$selected_template_slug = $this->get_template_slug();
			$current_step           = $this->get_step_count();

			$tab_css        = [
				'active'   => [
					'active_step_bg_color'           => '#f98ac0',
					'active_step_text_color'         => '#ffffff',
					'active_step_count_bg_color'     => '#f3f3f3',
					'active_step_count_text_color'   => '#363b3f',
					'active_step_count_border_color' => 'transparent',
					'active_step_tab_border_color'   => 'transparent',
				],
				'inactive' => [
					'inactive_step_bg_color'           => '#363b3f',
					'inactive_step_text_color'         => '#ffffff',
					'inactive_step_count_bg_color'     => '#cdcdcd',
					'inactive_step_count_text_color'   => '#363b3f',
					'inactive_step_count_border_color' => 'transparent',
					'inactive_step_tab_border_color'   => 'transparent',
				],

			];
			$pageID         = WFACP_Common::get_id();
			$_wfacp_version = WFACP_Common::get_post_meta_data( $pageID, '_wfacp_version' );
			if ( version_compare( $_wfacp_version, '1.9.3', '>' ) ) {
				$tab_css = [
					'active'   => [
						'active_step_bg_color'           => 'transparent',
						'active_step_text_color'         => '#000000',
						'active_step_count_bg_color'     => '#ec9761',
						'active_step_count_text_color'   => '#ffffff',
						'active_step_count_border_color' => 'transparent',
						'active_step_tab_border_color'   => '#ec9761',
					],
					'inactive' => [
						'inactive_step_bg_color'           => 'transparent',
						'inactive_step_text_color'         => '#cecece',
						'inactive_step_count_bg_color'     => 'transparent',
						'inactive_step_count_text_color'   => '#a8a8a8',
						'inactive_step_count_border_color' => '#cecece',
						'inactive_step_tab_border_color'   => '#ededed',
					],

				];

			}


			/* Form Step  Section */

			$panel_details['sections']['section']['fields']['ct_stepbar_width'] = [
				'type'      => 'custom',
				'default'   => '<div class="options-title-divider">' . esc_html__( 'Form', 'woofunnels-aero-checkout' ) . '</div>',
				'priority'  => 9,
				'transport' => 'postMessage',
			];

			$panel_details['sections']['section']['fields']['ct_form_border_width'] = [
				'type'     => 'custom',
				'default'  => '<div class="wfacp-options-sub-heading">' . esc_html__( 'Width', 'woofunnels-aero-checkout' ) . '</div>',
				'priority' => 9,
			];

			$panel_details['sections']['section']['fields'][ $selected_template_slug . '_step_form_max_width' ] = [
				'type'            => 'slider',
				'label'           => '',
				'default'         => 664,
				'description'     => '<i>Choose form width according to page builder container width</i>',
				'choices'         => array(
					'min'  => '350',
					'max'  => '1400',
					'step' => '2',
				),
				'priority'        => 9,
				'transport'       => 'postMessage',
				'wfacp_transport' => [
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'max-width' ],
						'elem'     => 'body #wfacp-e-form .wfacp_form_steps_wrap .wfacp_form',
					],
				],

			];
			$panel_details['sections']['section']['fields']['ct_form_border']                                   = [
				'type'     => 'custom',
				'default'  => '<div class="wfacp-options-sub-heading">' . esc_html__( 'Border', 'woofunnels-aero-checkout' ) . '</div>',
				'priority' => 9,
			];
			$panel_details['sections']['section']['fields'][ $selected_template_slug . '_form_border_type' ]    = [
				'type'            => 'select',
				'label'           => esc_attr__( 'Border Type', 'woofunnels-aero-checkout' ),
				'default'         => 'solid',
				'choices'         => [
					'none'   => 'None',
					'solid'  => 'Solid',
					'double' => 'Double',
					'dotted' => 'Dotted',
					'dashed' => 'Dashed',
				],
				'priority'        => 9,
				'transport'       => 'postMessage',
				'wfacp_transport' => [
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'border-left-style' ],
						'elem'     => 'body #wfacp-e-form .wfacp_form_steps_wrap .wfacp_form .wfacp-inner-form-detail-wrap',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'border-right-style' ],
						'elem'     => 'body #wfacp-e-form .wfacp_form_steps_wrap .wfacp_form .wfacp-inner-form-detail-wrap',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'border-bottom-style' ],
						'elem'     => 'body #wfacp-e-form .wfacp_form_steps_wrap .wfacp_form .wfacp-inner-form-detail-wrap',
					],
				],
			];
			$panel_details['sections']['section']['fields'][ $selected_template_slug . '_form_border_width' ]   = [
				'type'            => 'slider',
				'label'           => esc_attr__( 'Width', 'woofunnels-aero-checkout' ),
				'default'         => 1,
				'choices'         => array(
					'min'  => '1',
					'max'  => '12',
					'step' => '1',
				),
				'priority'        => 9,
				'active_callback' => array(
					array(
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_form_border_type',
						'operator' => '!=',
						'value'    => 'none',
					),
				),
				'transport'       => 'postMessage',
				'wfacp_transport' => [
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'border-left-width' ],
						'elem'     => 'body #wfacp-e-form .wfacp_form_steps_wrap .wfacp_form .wfacp-inner-form-detail-wrap',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'border-right-width' ],
						'elem'     => 'body #wfacp-e-form .wfacp_form_steps_wrap .wfacp_form .wfacp-inner-form-detail-wrap',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'border-bottom-width' ],
						'elem'     => 'body #wfacp-e-form .wfacp_form_steps_wrap .wfacp_form .wfacp-inner-form-detail-wrap',
					],
				],
			];

			$panel_details['sections']['section']['fields'][ $selected_template_slug . '_form_border_color' ] = [
				'type'            => 'color',
				'label'           => esc_attr__( 'Color', 'woofunnels-aero-checkout' ),
				'default'         => '#bbbbbb',
				'choices'         => array(
					'alpha' => true,
				),
				'priority'        => 9,
				'active_callback' => array(
					array(
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_form_border_type',
						'operator' => '!=',
						'value'    => 'none',
					),
				),
				'transport'       => 'postMessage',
				'wfacp_transport' => [
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'border-left-color' ],
						'elem'     => 'body #wfacp-e-form .wfacp_form_steps_wrap .wfacp_form .wfacp-inner-form-detail-wrap',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'border-right-color' ],
						'elem'     => 'body #wfacp-e-form .wfacp_form_steps_wrap .wfacp_form .wfacp-inner-form-detail-wrap',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'border-bottom-color' ],
						'elem'     => 'body #wfacp-e-form .wfacp_form_steps_wrap .wfacp_form .wfacp-inner-form-detail-wrap',
					],
				],
			];
			$panel_details['sections']['section']['fields']['ct_form_border_padding_heading']                 = [
				'type'      => 'custom',
				'default'   => '<div class="wfacp-options-sub-heading">' . esc_html__( 'Padding', 'woofunnels-aero-checkout' ) . '</div>',
				'priority'  => 9,
				'transport' => 'postMessage',
			];

			$panel_details['sections']['section']['fields'][ $selected_template_slug . '_form_inner_padding' ] = [
				'type'            => 'number',
				'label'           => __( 'Form Padding', 'woofunnels-aero-checkout' ),
				'default'         => 15,
				'priority'        => 9,
				'transport'       => 'postMessage',
				'wfacp_transport' => [
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'padding' ],
						'elem'     => 'body #wfacp-e-form .wfacp_form .wfacp-inner-form-detail-wrap',
					],
				],
			];


			$panel_details['sections']['section']['fields']['ct_step_form_layout_stepbar'] = [
				'type'          => 'custom',
				'default'       => '<div class="options-title-divider">' . esc_html__( 'Top Bar', 'woofunnels-aero-checkout' ) . '</div>',
				'priority'      => 9,
				'transport'     => 'postMessage',
				'wfacp_partial' => [
					'elem' => '.wfacp_form .wfacp-payment-tab-wrapper',
				],

			];


			/* Advanced fields*/
			$panel_details['sections']['section']['fields']['ct_step_form_advance'] = [
				'type'          => 'custom',
				'default'       => '<div class="wfacp-options-sub-heading">' . esc_html__( 'Visibility', 'woofunnels-aero-checkout' ) . '</div>',
				'priority'      => 9,
				'transport'     => 'postMessage',
				'wfacp_partial' => [
					'elem' => '.wfacp_form .wfacp-payment-tab-wrapper',
				],
			];


			$panel_details['sections']['section']['fields'][ $selected_template_slug . '_disable_steps_bar' ] = [
				'type'     => 'checkbox',
				'label'    => __( 'Disable Top Bar', 'woofunnels-aero-checkout' ),
				'default'  => WFACP_Common::page_is_old_version() ? false : true,
				'priority' => 9,

			];

			$choices = [
				'none'       => 'None',
				'breadcrumb' => 'Breadcrumb',
				'tab'        => 'Steps Bar',
			];
			if ( $current_step <= 1 ) {

				unset( $choices['breadcrumb'] );
			}

			$panel_details['sections']['section']['fields'][ $selected_template_slug . '_select_type' ] = [
				'type'            => 'select',
				'label'           => __( 'Select Type', 'woofunnels-aero-checkout' ),
				'default'         => 'none',
				'choices'         => $choices,
				'priority'        => 9,
				'active_callback' => [
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_disable_steps_bar',
						'operator' => '!=',
						'value'    => true,
					],
				],

			];


			$panel_details['sections']['section']['fields']['ct_step_form_layout_content'] = [
				'type'            => 'custom',
				'default'         => '<div class="wfacp-options-sub-heading wfacp-options-top-sub-heading">' . esc_html__( 'Content', 'woofunnels-aero-checkout' ) . '</div>',
				'priority'        => 9,
				'active_callback' => [
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_disable_steps_bar',
						'operator' => '!=',
						'value'    => true,
					],
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_select_type',
						'operator' => '==',
						'value'    => "tab",
					],

				],
			];
			if ( $current_step > 1 ) {
				$panel_details['sections']['section']['fields']['ct_step_form_layout_content_breadcrumb'] = [
					'type'            => 'custom',
					'default'         => '<div class="wfacp-options-sub-heading wfacp-options-top-sub-heading">' . esc_html__( 'Content', 'woofunnels-aero-checkout' ) . '</div>',
					'priority'        => 9,
					'active_callback' => [
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_disable_steps_bar',
							'operator' => '!=',
							'value'    => true,
						],
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_select_type',
							'operator' => '==',
							'value'    => "breadcrumb",
						],

					],
				];
			}


			$no_of_fields = $this->get_step_count();


			$default_align = 'left';

			if ( $no_of_fields == 1 || $no_of_fields > 2 ) {
				$default_align = 'center';
			}


			$counter = 1;
			for ( $i = 0; $i < $no_of_fields; $i ++ ) {

				$section_ct_key = 'step_' . $counter;

				$step_sec_heading_name                                             = ucfirst( str_replace( '_', ' ', $section_ct_key ) );
				$panel_details['sections']['section']['fields'][ $section_ct_key ] = array(
					'type'            => 'custom',
					'default'         => sprintf( '<div class="wfacp-options-sub-title">%s </div>', esc_html__( ucfirst( $step_sec_heading_name ) ) ),
					'priority'        => 10,
					'active_callback' => [
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_disable_steps_bar',
							'operator' => '!=',
							'value'    => true,
						],
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_select_type',
							'operator' => '==',
							'value'    => "tab",
						],
					],
				);

				$tab_key             = 'wfacp-tab' . $counter;
				$field_key_name      = $selected_template_slug . '_name_' . $i;
				$field_key_headling  = $selected_template_slug . '_headline_' . $i;
				$name_temp_array     = [
					'type'            => 'text',
					'label'           => __( 'Heading', 'woofunnels-aero-checkout' ),
					'description'     => '',
					'priority'        => 10,
					'default'         => esc_attr__( $step_sec_heading_name . ' Heading' ),
					'transport'       => 'postMessage',
					'wfacp_transport' => array(
						array(
							'type' => 'html',
							'elem' => 'body #wfacp-e-form .wfacp-payment-tab-list.' . $tab_key . ' .wfacp-order2StepTitle',

						),
					),
					'active_callback' => [
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_disable_steps_bar',
							'operator' => '!=',
							'value'    => true,
						],
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_select_type',
							'operator' => '==',
							'value'    => "tab",
						],
					],

				];
				$headling_temp_array = [
					'type'            => 'text',
					'label'           => __( 'Sub Heading', 'woofunnels-aero-checkout' ),
					'description'     => '',
					'priority'        => 10,
					'default'         => esc_attr__( $step_sec_heading_name . ' Sub Heading' ),
					'transport'       => 'postMessage',
					'wfacp_transport' => array(
						array(
							'type' => 'html',
							'elem' => 'body #wfacp-e-form .wfacp-payment-tab-list.' . $tab_key . ' .wfacp-order2StepSubTitle',

						),
					),
					'active_callback' => [
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_disable_steps_bar',
							'operator' => '!=',
							'value'    => true,
						],
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_select_type',
							'operator' => '==',
							'value'    => "tab",
						],
					],
				];

				$panel_details['sections']['section']['fields'][ $field_key_name ]     = $name_temp_array;
				$panel_details['sections']['section']['fields'][ $field_key_headling ] = $headling_temp_array;

				$counter ++;
			}

			$panel_details['sections']['section']['fields']['ct_steps_fonts']                                          = [
				'type'            => 'custom',
				'default'         => '<div class="wfacp-options-sub-heading">' . esc_html__( 'Typography', 'woofunnels-aero-checkout' ) . '</div>',
				'priority'        => 10,
				'active_callback' => [
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_disable_steps_bar',
						'operator' => '!=',
						'value'    => true,
					],
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_select_type',
						'operator' => '==',
						'value'    => "tab",
					],
				],
			];
			$panel_details['sections']['section']['fields'][ $selected_template_slug . '_step_heading_font_size' ]     = [
				'type'            => 'wfacp-responsive-font',
				'label'           => __( 'Step Heading', 'woofunnels-aero-checkout' ),
				'default'         => [
					'desktop' => 15,
					'tablet'  => 14,
					'mobile'  => 14,
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
						'elem'       => 'body #wfacp-e-form .wfacp-order2StepTitle',
					],
				],
				'priority'        => 10,
				'active_callback' => [
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_disable_steps_bar',
						'operator' => '!=',
						'value'    => true,
					],
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_select_type',
						'operator' => '==',
						'value'    => "tab",
					],
				],
			];
			$panel_details['sections']['section']['fields'][ $selected_template_slug . '_step_sub_heading_font_size' ] = [
				'type'            => 'wfacp-responsive-font',
				'label'           => __( 'Step Sub-Heading', 'woofunnels-aero-checkout' ),
				'default'         => [
					'desktop' => 13,
					'tablet'  => 12,
					'mobile'  => 12,
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
						'elem'       => 'body #wfacp-e-form .wfacp-order2StepSubTitle',
					],
				],
				'priority'        => 10,
				'active_callback' => [
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_disable_steps_bar',
						'operator' => '!=',
						'value'    => true,
					],
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_select_type',
						'operator' => '==',
						'value'    => "tab",
					],
				],
			];
			$panel_details['sections']['section']['fields'][ $selected_template_slug . '_step_alignment' ]             = [
				'type'            => 'radio-buttonset',
				'label'           => __( 'Text Alignment', 'woofunnels-aero-checkout' ),
				'default'         => $default_align,
				'choices'         => [
					'left'   => 'Left',
					'center' => 'Center',
					'right'  => 'Right',
				],
				'priority'        => 10,
				'transport'       => 'postMessage',
				'wfacp_transport' => [
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'text-align' ],
						'elem'     => 'body #wfacp-e-form .wfacp_form .wfacp-order2StepHeaderText',
					],
				],
				'active_callback' => [
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_disable_steps_bar',
						'operator' => '!=',
						'value'    => true,
					],
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_select_type',
						'operator' => '==',
						'value'    => "tab",
					],
				],
			];

			/* Form Step Color Section */


			$panel_details['sections']['section']['fields'][ $selected_template_slug . '_section_bg_color' ] = [
				'type'            => 'color',
				'label'           => esc_attr__( 'Background', 'woofunnels-aero-checkout' ),
				'default'         => '#ffffff',
				'choices'         => [
					'alpha' => true,
				],
				'priority'        => 21,
				'transport'       => 'postMessage',
				'wfacp_transport' => [
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'background-color' ],
						'elem'     => 'body #wfacp-e-form .wfacp_form_steps_wrap .wfacp-inner-form-detail-wrap',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'background-color' ],
						'elem'     => 'body #wfacp-e-form .wfacp_form_steps_wrap .wfacp-inner-form-detail-wrap .wfacp_main_form',
					],
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'background-color' ],
						'elem'     => 'body #wfacp-e-form .wfacp_form_steps_wrap .woocommerce-checkout #payment ',
					],
				],

			];

			$panel_details['sections']['section']['fields']['ct_active_step_colors_heading'] = [
				'type'            => 'custom',
				'default'         => '<div class="wfacp-options-sub-heading ">' . esc_html__( 'Colors', 'woofunnels-aero-checkout' ) . '</div>',
				'priority'        => 10,
				'active_callback' => [
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_disable_steps_bar',
						'operator' => '!=',
						'value'    => true,
					],
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_select_type',
						'operator' => '==',
						'value'    => "tab",
					],
				],
			];

			$choices = [
				'active'   => 'Active Step',
				'inactive' => 'Inactive Step',
			];

			$panel_details['sections']['section']['fields']['ct_active_inactive_tab'] = [
				'type'            => 'radio-buttonset',
				'label'           => __( 'Step', 'woofunnels-aero-checkout' ),
				'default'         => 'active',
				'choices'         => $choices,
				'priority'        => 10,
				'transport'       => 'postMessage',
				'active_callback' => [
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_disable_steps_bar',
						'operator' => '!=',
						'value'    => true,
					],
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_select_type',
						'operator' => '==',
						'value'    => "tab",
					],

				],
			];


			$panel_details['sections']['section']['fields'][ $selected_template_slug . '_active_step_bg_color' ]   = [
				'type'            => 'color',
				'label'           => esc_attr__( 'Background', 'woofunnels-aero-checkout' ),
				'default'         => $tab_css['active']['active_step_bg_color'],
				'choices'         => [
					'alpha' => true,
				],
				'priority'        => 10,
				'transport'       => 'postMessage',
				'wfacp_transport' => [
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'background-color' ],
						'elem'     => 'body #wfacp-e-form .wfacp_form_steps_wrap .wfacp-payment-tab-list.wfacp-active',
					],
				],
				'active_callback' => [
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_disable_steps_bar',
						'operator' => '!=',
						'value'    => true,
					],
					[
						'setting'  => 'wfacp_form_section_ct_active_inactive_tab',
						'operator' => '=',
						'value'    => 'active',
					],
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_select_type',
						'operator' => '==',
						'value'    => "tab",
					],
				],

			];
			$panel_details['sections']['section']['fields'][ $selected_template_slug . '_active_step_text_color' ] = [
				'type'            => 'color',
				'label'           => esc_attr__( 'Text', 'woofunnels-aero-checkout' ),
				'default'         => $tab_css['active']['active_step_text_color'],
				'choices'         => [
					'alpha' => true,
				],
				'priority'        => 10,
				'transport'       => 'postMessage',
				'wfacp_transport' => [
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'color' ],
						'elem'     => 'body #wfacp-e-form .wfacp_form_steps_wrap .wfacp-payment-tab-list.wfacp-active .wfacp_tcolor',
					],
				],
				'active_callback' => [
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_disable_steps_bar',
						'operator' => '!=',
						'value'    => true,
					],
					[
						'setting'  => 'wfacp_form_section_ct_active_inactive_tab',
						'operator' => '=',
						'value'    => 'active',
					],
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_select_type',
						'operator' => '==',
						'value'    => "tab",
					],
				],

			];


			$panel_details['sections']['section']['fields'][ $selected_template_slug . '_active_step_tab_border_color' ] = [
				'type'            => 'color',
				'label'           => esc_attr__( 'Tab Border', 'woofunnels-aero-checkout' ),
				'default'         => $tab_css['active']['active_step_tab_border_color'],
				'choices'         => [
					'alpha' => true,
				],
				'priority'        => 10,
				'transport'       => 'postMessage',
				'wfacp_transport' => [
					[
						'internal' => true,
						'type'     => 'css',
						'prop'     => [ 'border-color' ],

						'elem' => 'body #wfacp-e-form .wfacp_form .wfacp-payment-tab-list.wfacp-active',

					],
				],
				'active_callback' => [
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_disable_steps_bar',
						'operator' => '!=',
						'value'    => true,
					],
					[
						'setting'  => 'wfacp_form_section_ct_active_inactive_tab',
						'operator' => '=',
						'value'    => 'active',
					],
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_select_type',
						'operator' => '==',
						'value'    => "tab",
					],
				],

			];

			if ( $no_of_fields > 1 ) {

				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_active_step_count_bg_color' ]     = [
					'type'            => 'color',
					'label'           => esc_attr__( 'Count Background', 'woofunnels-aero-checkout' ),
					'default'         => $tab_css['active']['active_step_count_bg_color'],
					'choices'         => [
						'alpha' => true,
					],
					'priority'        => 10,
					'transport'       => 'postMessage',
					'wfacp_transport' => [
						[
							'internal' => true,
							'type'     => 'css',
							'prop'     => [ 'background-color' ],
							'elem'     => 'body #wfacp-e-form .wfacp_form_steps_wrap .wfacp-payment-tab-list.wfacp-active .wfacp-order2StepNumber',
						],
					],
					'active_callback' => [
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_disable_steps_bar',
							'operator' => '!=',
							'value'    => true,
						],
						[
							'setting'  => 'wfacp_form_section_ct_active_inactive_tab',
							'operator' => '=',
							'value'    => 'active',
						],
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_select_type',
							'operator' => '==',
							'value'    => "tab",
						],
					],

				];
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_active_step_count_text_color' ]   = [
					'type'            => 'color',
					'label'           => esc_attr__( 'Count Text', 'woofunnels-aero-checkout' ),
					'default'         => $tab_css['active']['active_step_count_text_color'],
					'choices'         => [
						'alpha' => true,
					],
					'priority'        => 10,
					'transport'       => 'postMessage',
					'wfacp_transport' => [
						[
							'internal' => true,
							'type'     => 'css',
							'prop'     => [ 'color' ],
							'elem'     => 'body #wfacp-e-form .wfacp_form_steps_wrap .wfacp-payment-tab-list.wfacp-active .wfacp-order2StepNumber',

						],
					],
					'active_callback' => [
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_disable_steps_bar',
							'operator' => '!=',
							'value'    => true,
						],
						[
							'setting'  => 'wfacp_form_section_ct_active_inactive_tab',
							'operator' => '=',
							'value'    => 'active',
						],
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_select_type',
							'operator' => '==',
							'value'    => "tab",
						],
					],

				];
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_active_step_count_border_color' ] = [
					'type'            => 'color',
					'label'           => esc_attr__( 'Count Border', 'woofunnels-aero-checkout' ),
					'default'         => $tab_css['active']['active_step_count_border_color'],
					'choices'         => [
						'alpha' => true,
					],
					'priority'        => 10,
					'transport'       => 'postMessage',
					'wfacp_transport' => [
						[
							'internal' => true,
							'type'     => 'css',
							'prop'     => [ 'border-color' ],
							'elem'     => 'body #wfacp-e-form .wfacp_form .wfacp-payment-tab-list.wfacp-active .wfacp-order2StepNumber',

						],
					],
					'active_callback' => [
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_disable_steps_bar',
							'operator' => '!=',
							'value'    => true,
						],
						[
							'setting'  => 'wfacp_form_section_ct_active_inactive_tab',
							'operator' => '=',
							'value'    => 'active',
						],
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_select_type',
							'operator' => '==',
							'value'    => "tab",
						],
					],

				];

				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_inactive_step_bg_color' ]           = [
					'type'            => 'color',
					'label'           => esc_attr__( 'Background', 'woofunnels-aero-checkout' ),
					'default'         => $tab_css['inactive']['inactive_step_bg_color'],
					'choices'         => [
						'alpha' => true,
					],
					'priority'        => 10,
					'transport'       => 'postMessage',
					'wfacp_transport' => [
						[
							'internal' => true,
							'type'     => 'css',
							'prop'     => [ 'background-color' ],
							'elem'     => 'body #wfacp-e-form .wfacp_form_steps_wrap .wfacp-payment-tab-list',
						],
						[
							'internal' => true,
							'type'     => 'css',
							'prop'     => [ 'background-color' ],
							'elem'     => 'body #wfacp-e-form .wfacp_form_steps_wrap .wfacp-payment-tab-wrapper',
						],
					],
					'active_callback' => [
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_disable_steps_bar',
							'operator' => '!=',
							'value'    => true,
						],
						[
							'setting'  => 'wfacp_form_section_ct_active_inactive_tab',
							'operator' => '=',
							'value'    => 'inactive',
						],
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_select_type',
							'operator' => '==',
							'value'    => "tab",
						],
					],

				];
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_inactive_step_text_color' ]         = [
					'type'            => 'color',
					'label'           => esc_attr__( 'Text', 'woofunnels-aero-checkout' ),
					'default'         => $tab_css['inactive']['inactive_step_text_color'],
					'choices'         => [
						'alpha' => true,
					],
					'priority'        => 10,
					'transport'       => 'postMessage',
					'wfacp_transport' => [
						[
							'internal' => true,
							'type'     => 'css',
							'prop'     => [ 'color' ],
							'elem'     => 'body #wfacp-e-form .wfacp_form_steps_wrap .wfacp-payment-tab-list .wfacp_tcolor',
						],
					],
					'active_callback' => [
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_disable_steps_bar',
							'operator' => '!=',
							'value'    => true,
						],
						[
							'setting'  => 'wfacp_form_section_ct_active_inactive_tab',
							'operator' => '=',
							'value'    => 'inactive',
						],
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_select_type',
							'operator' => '==',
							'value'    => "tab",
						],
					],

				];
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_inactive_step_count_bg_color' ]     = [
					'type'            => 'color',
					'label'           => esc_attr__( 'Count Background', 'woofunnels-aero-checkout' ),
					'default'         => $tab_css['inactive']['inactive_step_count_bg_color'],
					'choices'         => [
						'alpha' => true,
					],
					'priority'        => 10,
					'transport'       => 'postMessage',
					'wfacp_transport' => [
						[
							'internal' => true,
							'type'     => 'css',
							'prop'     => [ 'background-color' ],

							'elem' => 'body #wfacp-e-form .wfacp_form_steps_wrap .wfacp-payment-tab-list .wfacp-order2StepNumber',
						],
					],
					'active_callback' => [
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_disable_steps_bar',
							'operator' => '!=',
							'value'    => true,
						],
						[
							'setting'  => 'wfacp_form_section_ct_active_inactive_tab',
							'operator' => '=',
							'value'    => 'inactive',
						],
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_select_type',
							'operator' => '==',
							'value'    => "tab",
						],
					],

				];
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_inactive_step_count_text_color' ]   = [
					'type'            => 'color',
					'label'           => esc_attr__( 'Count Text', 'woofunnels-aero-checkout' ),
					'default'         => $tab_css['inactive']['inactive_step_count_text_color'],
					'choices'         => [
						'alpha' => true,
					],
					'priority'        => 10,
					'transport'       => 'postMessage',
					'wfacp_transport' => [
						[
							'internal' => true,
							'type'     => 'css',
							'prop'     => [ 'color' ],

							'elem' => 'body #wfacp-e-form .wfacp_form_steps_wrap .wfacp-payment-tab-list .wfacp-order2StepNumber',
						],
					],
					'active_callback' => [
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_disable_steps_bar',
							'operator' => '!=',
							'value'    => true,
						],
						[
							'setting'  => 'wfacp_form_section_ct_active_inactive_tab',
							'operator' => '=',
							'value'    => 'inactive',
						],
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_select_type',
							'operator' => '==',
							'value'    => "tab",
						],
					],

				];
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_inactive_step_count_border_color' ] = [
					'type'            => 'color',
					'label'           => esc_attr__( 'Count Border', 'woofunnels-aero-checkout' ),
					'default'         => $tab_css['inactive']['inactive_step_count_border_color'],
					'choices'         => [
						'alpha' => true,
					],
					'priority'        => 10,
					'transport'       => 'postMessage',
					'wfacp_transport' => [
						[
							'internal' => true,
							'type'     => 'css',
							'prop'     => [ 'border-color' ],
							'elem'     => 'body #wfacp-e-form .wfacp_form .wfacp-payment-tab-list:not(.wfacp-active) .wfacp-order2StepNumber',

						],
					],
					'active_callback' => [
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_disable_steps_bar',
							'operator' => '!=',
							'value'    => true,
						],
						[
							'setting'  => 'wfacp_form_section_ct_active_inactive_tab',
							'operator' => '=',
							'value'    => 'inactive',
						],
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_select_type',
							'operator' => '==',
							'value'    => "tab",
						],
					],

				];
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_inactive_step_tab_border_color' ]   = [
					'type'            => 'color',
					'label'           => esc_attr__( 'Tab Border', 'woofunnels-aero-checkout' ),
					'default'         => $tab_css['inactive']['inactive_step_tab_border_color'],
					'choices'         => [
						'alpha' => true,
					],
					'priority'        => 10,
					'transport'       => 'postMessage',
					'wfacp_transport' => [
						[
							'internal' => true,
							'type'     => 'css',
							'prop'     => [ 'border-color' ],
							'elem'     => 'body #wfacp-e-form .wfacp_form .wfacp-payment-tab-list:not(.wfacp-active)',

						],
					],
					'active_callback' => [
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_disable_steps_bar',
							'operator' => '!=',
							'value'    => true,
						],
						[
							'setting'  => 'wfacp_form_section_ct_active_inactive_tab',
							'operator' => '=',
							'value'    => 'inactive',
						],
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_select_type',
							'operator' => '==',
							'value'    => "tab",
						],
					],

				];

			} else {
				$panel_details['sections']['section']['fields']['cta_handling_text_tab'] = [
					'type'            => 'custom',
					'priority'        => 10,
					'description'     => esc_html__( 'This setting is applicable for multistep forms only ', 'woofunnels-aero-checkout' ),
					'transport'       => 'postMessage',
					'active_callback' => [
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_disable_steps_bar',
							'operator' => '!=',
							'value'    => true,
						],
						[
							'setting'  => 'wfacp_form_section_ct_active_inactive_tab',
							'operator' => '=',
							'value'    => 'inactive',
						],
						[
							'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_select_type',
							'operator' => '==',
							'value'    => "tab",
						],
					],
				];
			}


			return $panel_details;

		}


		public function set_default_layout_setting() {
			$selected_template_slug = $this->template_slug;

			if ( ! isset( $selected_template_slug ) ) {
				return;
			}
			$this->layout_setting = [
				'wfacp_form' => [
					$selected_template_slug . '_heading_fs'                         => array(
						'desktop' => 18,
						'tablet'  => 18,
						'mobile'  => 18,
					),
					$selected_template_slug . '_sub_heading_fs'                     => array(
						'desktop' => 13,
						'tablet'  => 13,
						'mobile'  => 13,
					),
					$selected_template_slug . '_field_style_fs'                     => array(
						'desktop' => 13,
						'tablet'  => 13,
						'mobile'  => 13,
					),
					$selected_template_slug . '_sec_heading_color'                  => '#666666',
					$selected_template_slug . '_sec_sub_heading_color'              => '#666666',
					$selected_template_slug . '_btn_order-place_width'              => '100%',
					$selected_template_slug . '_btn_order-place_bg_color'           => '#26b462',
					$selected_template_slug . '_btn_order-place_text_color'         => '#ffffff',
					$selected_template_slug . '_btn_order-place_bg_hover_color'     => '#0f9046',
					$selected_template_slug . '_btn_order-place_text_hover_color'   => '#ffffff',
					$selected_template_slug . '_additional_bg_color'                => '#f8f8f8',
					$selected_template_slug . '_btn_order-place_top_bottom_padding' => '14',
					$selected_template_slug . '_btn_order-place_left_right_padding' => '22',
					$selected_template_slug . '_btn_order-place_border_radius'      => '3',
					$selected_template_slug . '_btn_order-place_fs'                 => [
						'desktop' => 25,
						'tablet'  => 22,
						'mobile'  => 18,
					],
					$selected_template_slug . '_field_border_color'                 => '#bfbfbf',
					$selected_template_slug . '_form_content_link_color'            => '#dd7575',
				],
			];
		}

		public function change_default_setting_disabled_step_bar( $panel_details, $panel_key ) {

			$no_of_fields = $this->get_step_count();

			$selected_template_slug = $this->template_slug;
			$selectedSlug           = $this->get_selected_register_template();


			$panel_details['sections']['section']['fields'][ $selected_template_slug . '_name_0' ]['default']     = __( 'Shipping', 'woocommerce' );
			$panel_details['sections']['section']['fields'][ $selected_template_slug . '_headline_0' ]['default'] = __( 'Where to ship it?', 'woofunnels-aero-checkout' );

			if ( $no_of_fields > 1 && isset( $panel_details['sections']['section']['fields'][ $selected_template_slug . '_disable_steps_bar' ] ) ) {
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_disable_steps_bar' ]['default'] = false;

			}
			if ( $no_of_fields == 2 ) {
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_name_1' ]['default']            = __( 'Payment', 'woocommerce' );
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_headline_1' ]['default']        = __( 'Confirm your order', 'woofunnels-aero-checkout' );
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_btn_next_btn_text' ]['default'] = __( 'PROCEED TO NEXT STEP  →', 'woofunnels-aero-checkout' );
			}

			if ( $no_of_fields > 2 ) {
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_headline_0' ]['default']        = __( '', 'woofunnels-aero-checkout' );
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_name_1' ]['default']            = __( 'PRODUCTS', 'woofunnels-aero-checkout' );
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_headline_1' ]['default']        = __( '', 'woofunnels-aero-checkout' );
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_name_2' ]['default']            = __( 'Payment', 'woocommerce' );
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_headline_2' ]['default']        = __( '', 'woofunnels-aero-checkout' );
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_btn_next_btn_text' ]['default'] = __( 'PROCEED TO NEXT STEP  →', 'woofunnels-aero-checkout' );
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_btn_back_btn_text' ]['default'] = __( 'PROCEED TO FINAL STEP →', 'woofunnels-aero-checkout' );
			}


			if ( ( $selectedSlug['slug'] == 'embed_forms_1' || $selectedSlug['slug'] == 'embed_forms_4' ) && $no_of_fields == 1 ) {
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_name_0' ]['default']     = __( 'GET YOUR FREE COPY OF AMAZING BOOK', 'woofunnels-aero-checkout' );
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_headline_0' ]['default'] = __( 'Shipped in less than 3 days!', 'woofunnels-aero-checkout' );
				$panel_details['sections']['section']['fields']['payment_methods_sub_heading']['default']             = __( '', 'woofunnels-aero-checkout' );


			} else if ( $selectedSlug['slug'] == 'embed_forms_3' && $no_of_fields > 2 ) {
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_name_0' ]['default'] = __( 'PRODUCTS', 'woofunnels-aero-checkout' );
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_name_1' ]['default'] = __( 'INFORMATION', 'woofunnels-aero-checkout' );

			}

			if ( $selectedSlug['slug'] == 'custom_funnel_form_3' && $no_of_fields > 2 ) {
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_name_0' ]['default'] = __( 'INFORMATION', 'woofunnels-aero-checkout' );
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_name_1' ]['default'] = __( 'REVIEW', 'woofunnels-aero-checkout' );
			}

			if ( $selectedSlug['slug'] == 'custom_funnel_form_2' && $no_of_fields == 2 ) {
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_name_0' ]['default']     = __( 'INFORMATION', 'woofunnels-aero-checkout' );
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_headline_0' ]['default'] = __( '', 'woofunnels-aero-checkout' );
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_headline_1' ]['default'] = __( '', 'woofunnels-aero-checkout' );

			}


			return $panel_details;
		}

		public function change_default_setting1( $panel_details, $panel_key ) {

			$fields_data            = $panel_details['sections']['section']['fields'];
			$selected_template_slug = $this->template_slug;

			if ( $panel_key == 'wfacp_form' ) {

				$label_position_key = $selected_template_slug . '_field_style_position';

				$no_of_fields = $this->get_step_count();

				$active_calls = [
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_disable_steps_bar',
						'operator' => '!=',
						'value'    => true,
					],
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_select_type',
						'operator' => '==',
						'value'    => "breadcrumb",
					],

				];


				if ( isset( $panel_details['sections']['section']['fields']['ct_bredcrumb'] ) ) {

					unset( $panel_details['sections']['section']['fields']['ct_bredcrumb'] );

				}
				if ( isset( $panel_details['sections']['section']['fields']['ct_bredcrumb_content'] ) ) {

					unset( $panel_details['sections']['section']['fields']['ct_bredcrumb_content'] );
				}

				for ( $bi = 0; $bi <= $no_of_fields; $bi ++ ) {

					$panel_details['sections']['section']['fields'][ 'breadcrumb_' . $bi . '_step_text' ]['active_callback'] = $active_calls;
					$panel_details['sections']['section']['fields'][ 'breadcrumb_' . $bi . '_step_text' ]['priority']        = 10;
				}

				if ( is_array( $panel_details['sections']['section']['fields'] ) && array_key_exists( $label_position_key, $panel_details['sections']['section']['fields'] ) ) {

					unset( $panel_details['sections']['section']['fields'][ $selected_template_slug . '_field_style_position' ] );
				}

				$panel_details['sections']['section']['fields']['ct_steps_colors']['active_callback']                                    = $active_calls;
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_breadcrumb_color_type' ]['active_callback'] = $active_calls;

				$active_calls_normal = [
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_disable_steps_bar',
						'operator' => '!=',
						'value'    => true,
					],
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_select_type',
						'operator' => '==',
						'value'    => "breadcrumb",
					],
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_breadcrumb_color_type',
						'operator' => '=',
						'value'    => 'normal',
					],

				];

				$active_calls_hover = [
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_disable_steps_bar',
						'operator' => '!=',
						'value'    => true,
					],
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_select_type',
						'operator' => '==',
						'value'    => "breadcrumb",
					],
					[
						'setting'  => 'wfacp_form_section_' . $selected_template_slug . '_breadcrumb_color_type',
						'operator' => '=',
						'value'    => 'hover',
					],

				];

				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_breadcrumb_text_color' ] ['active_callback']      = $active_calls_normal;
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_breadcrumb_text_hover_color' ]['active_callback'] = $active_calls_hover;

				$panel_details['sections']['section']['fields']['ct_steps_colors']['priority']                                    = 11;
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_breadcrumb_color_type' ]['priority'] = 11;


				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_breadcrumb_text_color' ]['priority']       = 11;
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_breadcrumb_text_hover_color' ]['priority'] = 11;


				$panel_details['sections']['section']['fields']['ct_bredcrumb']['default'] = sprintf( '<div class="options-title-divider">%s</div>', esc_html__( 'Progress Bar' ) );
				$panel_details['sections']['section']['fields']['ct_mini_cart_on_mb']      = [
					'type'     => 'custom',
					'default'  => '<div class="options-title-divider">' . esc_html__( 'Mini Cart On Mobile View', 'woofunnels-aero-checkout' ) . '</div>',
					'priority' => 23,
				];


				/* Collapsible Order Summary */

				$current_version = WFACP_Common::get_checkout_page_version();

				$opt_enable = false;
				if ( version_compare( $current_version, '2.1.3', '>' ) ) {
					$opt_enable = true;
				}

				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_enable_collapsible_order_summary' ] = [
					'type'     => 'checkbox',
					'label'    => __( 'Enable', 'woofunnels-aero-checkout' ),
					'default'  => $opt_enable,
					'priority' => 23,
				];
				$panel_details['sections']['section']['fields']['cart_collapse_title']                                           = [
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
				$panel_details['sections']['section']['fields']['cart_expanded_title']                                           = [
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
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_enable_coupon' ]                    = [
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
				$panel_details['sections']['section']['fields'][ $selected_template_slug . '_enable_coupon_collapsible' ]        = [
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


			}
			foreach ( $fields_data as $key => $value ) {

				if ( isset( $this->layout_setting[ $panel_key ][ $key ] ) ) {
					$panel_details['sections']['section']['fields'][ $key ]['default'] = $this->layout_setting[ $panel_key ][ $key ];
				}

				if ( isset( $value['wfacp_transport'] ) ) {
					$wfacp_transport = $value['wfacp_transport'];
					if ( is_array( $wfacp_transport ) && count( $wfacp_transport ) > 0 ) {

						foreach ( $wfacp_transport as $t_key => $t_val ) {
							$elment = $t_val['elem'];

							if ( preg_match( "~\bbody\b~", $elment ) ) {

								$elment = str_replace( 'body', '', $elment );
							}

							$panel_details['sections']['section']['fields'][ $key ]['wfacp_transport'][ $t_key ]['elem'] = 'body #wfacp-e-form ' . $elment;

						}
					}
				}
			}

			if ( $panel_key == 'wfacp_form' ) {
				$panel_details = $this->steps_customizer( $panel_details );
			}

			$register_template = $this->get_selected_register_template();
			$selectedSlug      = $register_template['slug'];

			$file = WFACP_PLUGIN_DIR . '/importer/checkout-settings/' . $selectedSlug . '.php';


			if ( file_exists( $file ) ) {


				$data = include $file;


				if ( is_array( $data ) && isset( $data['default_customizer_value'] ) ) {
					$panel_details_data = $this->import_default_customizer_data( $panel_details, $data['default_customizer_value'], $panel_key, $selected_template_slug );

					return $panel_details_data;
				}


			}


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
							$panel_details['sections']['section']['fields'][ $normal_key ]['default'] = $data[ $panel_key ][ $customizer_key ];
						} else if ( false !== strpos( $normal_key, '_form_fields_' ) ) {
							$main_key1 = str_replace( 'wfacp_form_form_fields_', '', $main_key );
							if ( isset( $panel_details['sections']['form_fields']['fields'][ $main_key1 ]['default'] ) ) {
								$panel_details['sections']['form_fields']['fields'][ $main_key1 ]['default'] = $data[ $panel_key ][ $customizer_key ];
							}

						}
					}
				}
			}


			return $panel_details;

		}


		public function add_step_form_style() {

			$finalStyle[]           = $this->customizer_css;
			$finalStyle[]           = $this->steps_inline_styles;
			$selected_template_slug = $this->template_slug;


			$deskotp_css_style = '';
			$tablet_css_style  = '';
			$mobile_css_style  = '';

			$this->customizer_css['desktop']['p .select2 span.selection']['display'] = 'block';

			if ( true == WFACP_Embed_Form_loader::$pop_up_trigger ) {
				$this->customizer_css['desktop']['p .select2 span.selection']['display']                                                 = 'none';
				$this->customizer_css['desktop']['body.wfacpef_page.et_divi_builder #et_builder_outer_content .et_pb_column']['z-index'] = '999';
			}


			foreach ( $this->steps_inline_styles as $key1 => $value ) {
				$this->customizer_css[ $key1 ] = array_merge( $this->customizer_css[ $key1 ], $value );
			}

			$form_id          = [
				'#wfacp_qr_model_wrap .wfacp_qr_wrap .button:hover',
				'#wfacp_qr_model_wrap .wfacp_qr_wrap .button',
				'body #wfacp_qr_model_wrap .wfacp_qr_wrap .button',
				'body  #wfacp_qr_model_wrap .wfacp_qr_wrap .button:hover',
				'.select2-search--dropdown .select2-search__field',
				'#wfacp_qr_model_wrap *',
				'.select2-results__options',
				'body #et_builder_outer_content #wfacp-e-form p',
				'body .wfacp_modal_outerwrap .wfacp_modal_innerwrap #wfacp_modal_content',
				'body.wfacpef_page #wfacp-e-form .wfacp_form .wfacp-inner-form-detail-wrap',
				'#et-boc .et-l span.select2-selection.select2-selection--multiple',
				'body.wfacpef_page .wfacp-payment-title.wfacp_embed_step_3',
				'body.wfacpef_page.et_divi_builder #et_builder_outer_content .et_pb_column',
				'body #wfacp-e-form .wfacp_form .wfacp-inner-form-detail-wrap',
				'body #et-boc #wfacp-e-form .wfacp-form a',
				'body #et-boc #wfacp-e-form .wfacp-form a:hover',
				'#et-boc .et-l span.select2-selection.select2-selection--multiple',
			];
			$body_not_removed = [
				'body.wfacpef_page #wfacp-e-form .wfacp_form',
				'body.wfacpef_page .wfacp-payment-title.wfacp_embed_step_3',
				'body #wfacp-e-form .wfacp_form .wfacp-inner-form-detail-wrap'
			];


			if ( isset( $this->customizer_css['desktop'] ) && is_array( $this->customizer_css['desktop'] ) && count( $this->customizer_css['desktop'] ) > 0 ) {
				foreach ( $this->customizer_css['desktop'] as $key => $value ) {
					$elment = $key;


					if ( preg_match( "~\bbody\b~", $key ) && ! in_array( $key, $body_not_removed ) ) {
						$elment = str_replace( 'body', '', $key );
					}


					if ( false !== strpos( $key, 'wfacp_form_cart' ) ) {
						$elment = 'body #wfacp_form_cart ' . $elment;

						$elment = str_replace( '.woocommerce-checkout-review-order-table_embed_forms_2', '', $key );

						$elment = str_replace( 'tfoot', '', $elment );
						$elment = str_replace( 'wfacp_section_title', 'wfacp-order-summary-label', $elment );


					} elseif ( ! in_array( $key, $form_id ) ) {
						$elment = 'body #wfacp-e-form ' . $elment;

					}


					$pixel_used_property = [ 'font-size', 'border-width' ];


					foreach ( $value as $css_property => $css_value ) {
						if ( '' == $css_value ) {
							continue;
						}
						$suffix = '';


						if ( in_array( $css_property, $pixel_used_property ) && false == strpos( $css_value, 'px' ) ) {
							$suffix = 'px';
						}
						if ( 'px' == $css_value ) {
							$css_value = '0px';
						}

						if ( $css_property == 'content' ) {
							if ( strpos( $css_value, "&#039;" ) !== false ) {
								$selector = $css_property . ':"' . str_replace( "&#039;", "'", $css_value ) . '"';
							} else {
								$selector = $css_property . ':"' . $css_value . '"';
							}

						} else {
							$selector = $css_property . ':' . $css_value . $suffix;
						}


						$style_inline      = $elment . '{' . $selector . ';}';
						$deskotp_css_style .= $style_inline;
					}
				}
			}


			if ( isset( $this->customizer_css['tablet'] ) && is_array( $this->customizer_css['tablet'] ) && count( $this->customizer_css['tablet'] ) > 0 ) {
				$tablet_css_style .= '@media (max-width: 991px) {';
				foreach ( $this->customizer_css['tablet'] as $key => $value ) {
					$elment = $key;
					if ( preg_match( "~\bbody\b~", $key ) ) {
						$elment = str_replace( 'body', '', $key );

					}
					$elment = 'body #wfacp-e-form ' . $elment;

					if ( false !== strpos( $key, 'wfacp_form_cart' ) ) {
						$elment = 'body #wfacp_form_cart ' . $elment;

						$elment = str_replace( '.woocommerce-checkout-review-order-table_embed_forms_2', '', $key );

						$elment = str_replace( 'tfoot', '', $elment );
						$elment = str_replace( 'wfacp_section_title', 'wfacp-order-summary-label', $elment );

					}

					foreach ( $value as $css_property => $css_value ) {
						$selector         = $css_property . ':' . $css_value;
						$style_inline     = $elment . '{' . $selector . ';}';
						$tablet_css_style .= $style_inline;
					}
				}
				$tablet_css_style .= '}';
			}

			if ( isset( $this->customizer_css['mobile'] ) && is_array( $this->customizer_css['mobile'] ) && count( $this->customizer_css['mobile'] ) > 0 ) {
				$mobile_css_style .= '@media (max-width: 767px) {';
				foreach ( $this->customizer_css['mobile'] as $key => $value ) {
					$elment = $key;
					if ( preg_match( "~\bbody\b~", $key ) ) {
						$elment = str_replace( 'body', '', $key );

					}
					$elment = 'body #wfacp-e-form ' . $elment;

					if ( false !== strpos( $key, 'wfacp_form_cart' ) ) {
						$elment = 'body #wfacp_form_cart ' . $elment;

						$elment = str_replace( '.woocommerce-checkout-review-order-table_embed_forms_2', '', $key );

						$elment = str_replace( 'tfoot', '', $elment );
						$elment = str_replace( 'wfacp_section_title', 'wfacp-order-summary-label', $elment );

					}

					foreach ( $value as $css_property => $css_value ) {
						$selector         = $css_property . ':' . $css_value;
						$style_inline     = $elment . '{' . $selector . ';}';
						$mobile_css_style .= $style_inline;
					}
				}
				$mobile_css_style .= '}';
			}

			echo '<style>';
			echo $deskotp_css_style;
			echo $tablet_css_style;
			echo $mobile_css_style;
			echo '</style>';
		}

		public function get_form_step_data() {
			return $this->form_steps_data;
		}


		public function wfacp_get_header() {
			return $this->template_dir . '/views/template-parts/customizer-header.php';
		}

		public function wfacp_get_footer() {
			return $this->template_dir . '/views/template-parts/customizer-footer.php';
		}

		public function change_cancel_url( $url ) {
			if ( ! WFACP_Core()->public->is_checkout_override() ) {
				if ( isset( $_REQUEST['wfacp_embed_form_page_id'] ) ) {
					$url = get_the_permalink( $_REQUEST['wfacp_embed_form_page_id'] );
				}

			}

			return $url;
		}


		public function wfacp_multi_tab_default_setting( $panel_details, $panel_key ) {

			$selected_template_slug = $this->get_template_slug();
			if ( array_key_exists( 'wfacp_style', $panel_key ) ) {
				$panel_details['panel'] = 'no';
				unset( $panel_details['sections']['colors'] );
				unset( $panel_details['sections']['typography']['fields']['ct_font_size'] );
				unset( $panel_details['sections']['typography']['fields'][ $selected_template_slug . '_content_fs' ] );
				$panel_details['sections']['typography']['data']['priority'] = 35;

			}

			return $panel_details;

		}

		public function disable_template_loading( $status ) {
			if ( ! WFACP_Common::is_customizer() ) {
				$status = false;
			}

			return $status;
		}

		public function run_divi_customizer_css() {
			if ( function_exists( 'et_divi_add_customizer_css' ) ) {
				et_divi_add_customizer_css();
			}
		}

		public function no_follow_no_index() {
			global $post;
			if ( ! is_null( $post ) && $post->post_type == WFACP_Common::get_post_type_slug() ) {
				parent::no_follow_no_index();

			}
		}


		public function get_customizer_data() {
			parent::get_customizer_data();
			WFACP_Common::set_customizer_fields_default_vals( $this->customizer_data );
		}

		public function add_panal_for_mini_cart( $data, $this_data ) {

			require_once WFACP_BUILDER_DIR . '/customizer/customizer-options/class-section-cart.php';
			$form_cart_panel = WFACP_SectionCart::get_instance( $this_data )->cart_settings();
			if ( is_array( $form_cart_panel ) && count( $form_cart_panel ) > 0 ) {
				$data[] = $form_cart_panel;
			}

			return $data;
		}


		/*********************************** Mini Cart Order Summary Widget ********************************/
		public function get_localize_data() {
			$data                                = parent::get_localize_data();
			$data['exchange_keys']['embed_form'] = $this->get_locals();

			return $data;
		}

		protected function get_locals() {
			return [ 'wfacp_form_summary' => $this->shortcode_id ];
		}

		public function add_fragment_product_switching( $fragments ) {
			$fragments = parent::add_fragment_product_switching( $fragments );
			$fragments = $this->get_mini_cart_fragments( $fragments, $this->shortcode_id );

			return $fragments;
		}

		public function get_ajax_exchange_keys() {
			$keys = WFACP_Common::$exchange_keys;

			if ( ! empty( is_array( $keys ) ) && isset( $keys['embed_form'] ) ) {
				$mini_cart_form_id    = $keys['embed_form']['wfacp_form_summary'];
				$this->mini_cart_data = WFACP_Common::get_session( $mini_cart_form_id );
			}
		}


		public function shortcode_mini_cart( $attr ) {

			if ( is_null( WC()->cart ) || true == apply_filters( 'wfacp_do_not_allow_shortcode_printing', false ) ) {
				return '';
			}

			$selected_template_slug = $this->get_template_slug();
			$settings               = shortcode_atts( array(
				'mini_cart_heading'         => __( 'Order Summary', 'woofunnels-aero-checkout' ),
				'enable_product_image'      => 'on',
				'enable_quantity_box'       => 'off',
				'enable_delete_item'        => 'off',
				'enable_coupon'             => 'off',
				'enable_coupon_collapsible' => 'off',
			), $attr, 'wfacp_mini_cart' );


			if ( empty( $attr ) ) {
				$settings['mini_cart_heading']    = WFACP_Common::get_option( 'wfacp_form_cart_section_heading' );
				$settings['enable_product_image'] = wc_string_to_bool( WFACP_Common::get_option( 'wfacp_form_cart_section_' . $selected_template_slug . '_order_hide_img' ) );
				$settings['enable_quantity_box']  = wc_string_to_bool( WFACP_Common::get_option( 'wfacp_form_cart_section_' . $selected_template_slug . '_order_quantity_switcher' ) );
				$settings['enable_delete_item']   = wc_string_to_bool( WFACP_Common::get_option( 'wfacp_form_cart_section_' . $selected_template_slug . '_order_delete_item' ) );

				$enable_coupon_collapsible = wc_string_to_bool( WFACP_Common::get_option( 'wfacp_form_cart_section_' . $selected_template_slug . '_order_hide_right_side_coupon' ) );
				if ( $enable_coupon_collapsible === true ) {
					$enable_coupon_collapsible = false;
				} else {
					$enable_coupon_collapsible = true;
				}
				$settings['enable_coupon'] = $enable_coupon_collapsible;

				$settings['enable_coupon_collapsible'] = wc_string_to_bool( WFACP_Common::get_option( 'wfacp_form_cart_section_' . $selected_template_slug . '_enable_coupon_right_side_coupon' ) );
			} else {
				$settings['enable_product_image']      = 'on' == $settings['enable_product_image'];
				$settings['enable_quantity_box']       = 'on' == $settings['enable_quantity_box'];
				$settings['enable_delete_item']        = 'on' == $settings['enable_delete_item'];
				$settings['enable_coupon']             = 'on' == $settings['enable_coupon'];
				$settings['enable_coupon_collapsible'] = 'on' == $settings['enable_coupon_collapsible'];
			}
			$this->set_mini_cart_data( $settings );

			WFACP_Common::set_session( $this->shortcode_id, $settings );
			ob_start();

			echo "<div id='wfacp_form_cart'>";
			echo "<div class='wfacp_form_cart'>";
			$this->get_mini_cart_widget( $this->shortcode_id );
			echo "</div>";
			echo "</div>";

			return ob_get_clean();
		}

		//Mini Cart Settings
		public function mini_cart_heading() {
			return isset( $this->mini_cart_data['mini_cart_heading'] ) ? $this->mini_cart_data['mini_cart_heading'] : parent::mini_cart_heading();
		}

		public function mini_cart_allow_product_image() {

			if ( isset( $this->mini_cart_data['enable_product_image'] ) ) {
				return $this->mini_cart_data['enable_product_image'];
			}

			return '';
		}

		public function mini_cart_allow_quantity_box() {
			return isset( $this->mini_cart_data['enable_quantity_box'] ) ? $this->mini_cart_data['enable_quantity_box'] : false;


			if ( isset( $this->mini_cart_data['enable_quantity_box'] ) ) {
				return $this->mini_cart_data['enable_quantity_box'];
			}

			return '';

		}

		public function mini_cart_allow_deletion() {
			if ( isset( $this->mini_cart_data['enable_delete_item'] ) ) {
				return $this->mini_cart_data['enable_delete_item'];
			}

		}

		public function mini_cart_allow_coupon() {
			return isset( $this->mini_cart_data['enable_coupon'] ) ? $this->mini_cart_data['enable_coupon'] : false;

		}

		public function mini_cart_collapse_enable_coupon_collapsible() {
			return isset( $this->mini_cart_data['enable_coupon_collapsible'] ) ? $this->mini_cart_data['enable_coupon_collapsible'] : false;
		}

		/* Collapsible Order Summary */
		public function add_order_summary_to_sidebar() {
			include WFACP_BUILDER_DIR . '/customizer/templates/layout_9/views/template-parts/order-summary.php';
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

		public function add_fragment_order_summary( $fragments ) {

			ob_start();


			include WFACP_BUILDER_DIR . '/customizer/templates/layout_9/views/template-parts/main-order-summary.php';

			$path = WFACP_BUILDER_DIR . '/customizer/templates/layout_9';

			$order_summary                     = ob_get_clean();
			$fragments['.wfacp_order_summary'] = $order_summary;

			ob_start();
			include $path . '/views/template-parts/order-review.php';
			$fragments['.wfacp_mb_mini_cart_sec_accordion_content .wfacp_template_9_cart_item_details'] = ob_get_clean();

			ob_start();
			include $path . '/views/template-parts/order-total.php';
			$fragments['.wfacp_mb_mini_cart_sec_accordion_content .wfacp_template_9_cart_total_details'] = ob_get_clean();


			ob_start();
			include $path . '/views/template-parts/order-total.php';
			$fragments['.wfacp_mb_mini_cart_sec_accordion_content .wfacp_mini_cart_reviews'] = ob_get_clean();

			ob_start();
			wc_cart_totals_order_total_html();
			$fragments['.wfacp_cart_mb_fragment_price'] = ob_get_clean();
			$order_summary_cart_price                   = apply_filters( 'wfacp_collapsible_order_summary_cart_price', wc_price( WC()->cart->total ) );

			$fragments['.wfacp_show_price_wrap'] = '<div class="wfacp_show_price_wrap">' . do_action( "wfacp_before_mini_price" ) . '<strong>' . $order_summary_cart_price . '</strong>' . do_action( 'wfacp_after_mini_price' ) . '</div>';


			return $fragments;
		}

		public function get_selected_register_template() {
			$data                  = parent::get_selected_register_template();
			$data['template_type'] = 'embed_forms';

			return $data;
		}

		public function add_internal_css() {

			echo "<style>";
			echo ".customize-partial-edit-shortcuts-shown #wfacp-e-form .wfacp_form {margin: auto;}";
			echo ".customize-partial-edit-shortcuts-shown .wfacpef_page #wfacp-e-form .wfacp_form .wfacp-inner-form-detail-wrap {border-top-width: 0px;}";
			echo ".customize-partial-edit-shortcuts-shown a.wfacp_qv-button.var_product {pointer-events: none !important;}";
			echo 'body.wfacpef_page #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-section.wfacp_order_coupon_box{ margin-bottom: 0;}';
			echo 'body.wfacpef_page #wfacp-e-form .wfacp_main_form.woocommerce .wfacp-section.wfacp_order_summary_box{ margin-bottom: 0;}';
			echo 'body.wfacpef_page #wfacp-e-form .wfacp_main_form.woocommerce .woocommerce-info{ padding-bottom: 0 !important;}';
			echo 'body.wfacpef_page #wfacp-e-form .woocommerce-notices-wrapper + .wfacp-coupon-section.clearfix {margin-bottom: 15px;}';
			echo 'body.wfacpef_page .wfacp_mb_mini_cart_sec_accordion_content .wfacp_form_cart {padding: 0;}';
			echo 'body.wfacpef_page #wfacp-e-form .wfacp-order2StepTitle {font-weight: 700;}';
			echo "</style>";

		}

	}

	return WFACP_Template_Custom_Page::get_instance();
}