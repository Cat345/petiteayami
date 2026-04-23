<?php
if ( ! class_exists( 'WFOP_Optin_Form_Popup' ) ) {
	#[AllowDynamicProperties]
	class WFOP_Optin_Form_Popup extends WFOP_Divi_HTML_BLOCK {
		public $slug = 'wfop_optin_form_popup';

		public function __construct() {
			$this->ajax = true;
			parent::__construct();

			add_action( 'wp_footer', array( $this, 'script' ) );
		}


		public function setup_data() {

			$this->button_settings();
			$this->pop_up_form_settings();
			$this->close_button_settings();
			$this->style_field();
		}

		public function pop_up_form_settings() {
			$pop_up_form = $this->add_tab( __( 'Popup Form', 'funnel-builder-powerpack' ), 5 );
			$this->popup_setting( $pop_up_form );
			$this->progress_bar( $pop_up_form );
			$this->progress_heading( $pop_up_form );
			$this->form_settings( $pop_up_form );
		}

		private function popup_setting( $form_id ) {
			$key = 'wfop_optin_form_popup';

			$this->add_heading( $form_id, __( 'Popup', 'funnel-builder-powerpack' ) );

			$this->add_switcher( $form_id, 'enable_popup_bar_on_builder', __( 'View Popup', 'funnel-builder-powerpack' ), 'off' );

			$this->add_select(
				$form_id,
				'popup_open_animation',
				esc_html__( 'Effect' ),
				array(
					'fade'       => __( 'Fade', 'funnel-builder-powerpack' ),
					'slide-up'   => __( 'Slide Up', 'funnel-builder-powerpack' ),
					'slide-down' => __( 'Slide Down', 'funnel-builder-powerpack' ),
				),
				'fade'
			);

			$style_id = $this->add_tab( __( 'Popup', 'funnel-builder-powerpack' ), 2 );

			$default = array(
				'default' => '30%',
				'unit'    => '%',
			);
			$this->add_max_width( $style_id, $key . '_popup_bar_width', '%%order_class%% .bwf_pp_wrap', __( 'Width', 'funnel-builder-powerpack' ), $default, array(), true );

			$defaults_padding = '40px | 40px| 40px | 40px';
			$this->add_padding( $style_id, $key . '_popup_padding', '%%order_class%% .bwf_pp_wrap .bwf_pp_cont', $defaults_padding );
		}

		private function progress_bar( $form_id ) {
			$key = 'wfop_optin_form_popup';

			$this->add_heading( $form_id, __( 'Progress Bar', 'funnel-builder-powerpack' ) );

			$this->add_switcher( $form_id, 'popup_bar_pp', __( 'Enable', 'funnel-builder-powerpack' ), 'on' );
			$condition = array( 'popup_bar_pp' => 'on' );
			$this->add_switcher( $form_id, 'popup_bar_text_position', __( 'Show progress text above the bar', 'funnel-builder-powerpack' ), 'on', $condition );
			$this->add_switcher( $form_id, 'popup_bar_animation', __( 'Animation', 'funnel-builder-powerpack' ), 'on', $condition );
			$this->add_text( $form_id, 'popup_bar_text', __( 'Text', 'funnel-builder-powerpack' ), __( 'Almost Complete...', 'funnel-builder-powerpack' ), $condition );

			$style_id  = $this->add_tab( __( 'Progress Bar', 'funnel-builder-powerpack' ), 2 );
			$condition = array(
				'popup_bar_pp' => 'on',
			);
			$this->add_subheading( $style_id, __( 'Size', 'funnel-builder-powerpack' ), '', $condition );

			$default = array(
				'default' => '75%',
				'unit'    => '%',
			);
			$this->add_width( $style_id, $key . '_popup_progress_bar_width', '%%order_class%% .bwf_pp_bar_wrap .bwf_pp_bar', __( 'Popup Bar Width', 'funnel-builder-powerpack' ), $default, $condition );

			$default = array(
				'default' => '40px',
				'unit'    => 'px',
			);
			$this->add_height( $style_id, $key . '_popup_progress_bar_height', '%%order_class%% .bwf_pp_bar_wrap', __( 'Popup Bar Height', 'funnel-builder-powerpack' ), $default, $condition );

			$defaults_padding = '4px|4px|4px|4px|false|false';
			$this->add_padding( $style_id, $key . '_popup_progress_bar_padding', '%%order_class%% .bwf_pp_bar_wrap', $defaults_padding, __( 'Popup Bar Padding', 'funnel-builder-powerpack' ), $condition );

			$this->add_subheading( $style_id, __( 'Styling', 'funnel-builder-powerpack' ), '', $condition );

			$this->add_typography( $style_id, $key . '_progress_bar_typography', '%%order_class%% .bwf_pp_overlay .pp-bar-text', '', '', $condition );
			$this->add_color( $style_id, $key . '_progress_text_color', '%%order_class%% .bwf_pp_overlay .pp-bar-text', __( 'Text', 'funnel-builder-powerpack' ), '#ffffff', $condition );
			$this->add_background_color( $style_id, $key . '_progress_color', '%%order_class%% .bwf_pp_bar_wrap .bwf_pp_bar', '#338d48', __( 'Color', 'funnel-builder-powerpack' ), $condition );
			$this->add_background_color( $style_id, $key . '_progress_background_color', '%%order_class%% .bwf_pp_overlay .bwf_pp_bar_wrap', '#ffffff', __( 'Background', 'funnel-builder-powerpack' ), $condition );
		}

		private function progress_heading( $form_id ) {
			$key = 'wfop_optin_form_popup';
			$this->add_heading( $form_id, __( 'Heading', 'funnel-builder-powerpack' ) );

			$this->add_subheading( $form_id, __( 'Text', 'funnel-builder-powerpack' ) );
			$this->add_text( $form_id, 'popup_heading', __( 'Heading', 'funnel-builder-powerpack' ), __( "You're just one step away!", 'funnel-builder-powerpack' ) );
			$this->add_text( $form_id, 'popup_sub_heading', __( 'Sub Heading', 'funnel-builder-powerpack' ), __( "Enter your details below and we'll get you signed up", 'funnel-builder-powerpack' ) );

			// Heading
			$style_id = $this->add_tab( __( 'Heading', 'funnel-builder-powerpack' ), 2 );
			$this->add_subheading( $style_id, __( 'Heading', 'funnel-builder-powerpack' ) );

			$default           = '|700|||||||';
			$font_side_default = array(
				'default' => '20px',
				'unit'    => 'px',
			);
			$this->add_typography( $style_id, $key . '_popup_heading', '%%order_class%% .bwf_pp_cont .bwf_pp_opt_head', '', $default, array(), $font_side_default );

			$this->add_color( $style_id, $key . '_popup_heading_color', '%%order_class%% .bwf_pp_cont .bwf_pp_opt_head', __( 'Text', 'funnel-builder-powerpack' ), '#093969' );

			$default = '||on||||||';
			// Sub Heading
			$this->add_subheading( $style_id, __( 'Sub-Heading', 'funnel-builder-powerpack' ) );
			$this->add_typography( $style_id, $key . '_popup_subheading_typography', '%%order_class%% .bwf_pp_cont .bwf_pp_opt_sub_head', '', $default, array(), $font_side_default );
			$this->add_color( $style_id, $key . '_popup_subheading_color', '%%order_class%% .bwf_pp_cont  .bwf_pp_opt_sub_head', __( 'Text', 'funnel-builder-powerpack' ) );
		}

		private function form_settings( $form_id ) {

			$this->add_heading( $form_id, __( 'Form', 'funnel-builder-powerpack' ) );
			$optinPageId = $this->get_divi_page_id();

			$get_fields = array();
			if ( $optinPageId > 0 ) {
				$get_fields = WFOPP_Core()->optin_pages->form_builder->get_form_fields( $optinPageId );
			}

			$options = array(
				'wffn-sm-100' => __( 'Full', 'funnel-builder-powerpack' ),
				'wffn-sm-50'  => __( 'One Half', 'funnel-builder-powerpack' ),
				'wffn-sm-33'  => __( 'One Third', 'funnel-builder-powerpack' ),
				'wffn-sm-67'  => __( 'Two Third', 'funnel-builder-powerpack' ),
			);

			if ( is_array( $get_fields ) && count( $get_fields ) > 0 ) {
				foreach ( $get_fields as $field ) {
					$default    = isset( $field['width'] ) ? $field['width'] : 'wffn-sm-100';
					$input_name = isset( $field['InputName'] ) ? $field['InputName'] : '';
					$label      = isset( $field['label'] ) ? $field['label'] : '';
					if ( ! empty( $input_name ) && ! empty( $label ) ) {
						$this->add_select( $form_id, $input_name, __( $label, 'funnel-builder-powerpack' ), $options, $default );
					}
				}
			}

			$this->add_switcher( $form_id, 'show_labels', __( 'Label', 'funnel-builder-powerpack' ), 'on' );
			$this->add_heading( $form_id, __( 'Submit Button', 'funnel-builder-powerpack' ) );
			$this->add_text( $form_id, 'button_text', __( 'Title', 'funnel-builder-powerpack' ), __( 'Send Me My Free Guide', 'funnel-builder-powerpack' ) );
			$this->add_text( $form_id, 'subtitle', __( 'Sub Title', 'funnel-builder-powerpack' ), '', array(), '', __( 'Enter subtitle', 'funnel-builder-powerpack' ) );
			$this->add_text( $form_id, 'button_submitting_text', __( 'Submitting Text', 'funnel-builder-powerpack' ), __( 'Submitting...', 'funnel-builder-powerpack' ) );
			$this->add_text( $form_id, 'popup_footer_text', __( 'Text After Button', 'funnel-builder-powerpack' ), __( 'Your Information is 100% Secure', 'funnel-builder-powerpack' ) );
		}

		private function button_settings() {
			$key = 'wfop_optin_form_popup';

			$form_id = $this->add_tab( __( 'Call To Action Button', 'funnel-builder-powerpack' ), 5 );
			$this->add_text( $form_id, 'btn_text', __( 'Title', 'funnel-builder-powerpack' ), __( 'Signup Now', 'funnel-builder-powerpack' ) );
			$this->add_text( $form_id, 'btn_subheading_text', __( 'Subtitle', 'funnel-builder-powerpack' ) );
			$this->add_text_alignments( $form_id, 'btn_alignment', '%%order_class%% .bwf-custom-button', __( 'Button Alignment', 'funnel-builder-powerpack' ), 'center' );
			$this->add_text_alignments( $form_id, 'btn_text_alignment', '%%order_class%%  .bwf-custom-button a', __( 'Text Alignment', 'funnel-builder-powerpack' ), 'center' );

			$this->add_subheading( $form_id, __( 'Button Icon', 'funnel-builder-powerpack' ) );
			$this->add_icon( $form_id, 'btn_icon', '' );

			$this->add_select(
				$form_id,
				'btn_icon_position',
				__( 'Icon Position', 'funnel-builder-powerpack' ),
				array(
					'left'  => __( 'Before', 'funnel-builder-powerpack' ),
					'right' => __( 'After', 'funnel-builder-powerpack' ),
				),
				'left'
			);

			// Button Styling
			$style_id = $this->add_tab( __( 'Call To Action Button', 'funnel-builder-powerpack' ), 2 );
			$default  = array(
				'default' => '30%',
				'unit'    => '%',
			);
			$this->add_width( $style_id, $key . '_popup_button_width', '%%order_class%%  .bwf-custom-button .wfop_popup_form', '', $default, array(), true );

			$controls_tabs_id = $this->add_controls_tabs( $style_id, '', '' );
			$field_keys       = array();
			$field_keys[]     = $this->add_background_color( $style_id, $key . '_btn_bg_color', '%%order_class%% .bwf-custom-button a', '#000', __( 'Background', 'funnel-builder-powerpack' ) );
			$field_keys[]     = $this->add_color( $style_id, $key . '_btn_color', '%%order_class%% .bwf-custom-button a span', __( 'Text', 'funnel-builder-powerpack' ), '#fff' );

			$this->add_controls_tab( $controls_tabs_id, __( 'Normal', 'funnel-builder-powerpack' ), $field_keys );
			$field_keys   = array();
			$field_keys[] = $this->add_background_color( $style_id, $key . '_btn_hover_bg_color', '%%order_class%% .bwf-custom-button a:hover', '#000', __( 'Background', 'funnel-builder-powerpack' ) );
			$field_keys[] = $this->add_color( $style_id, $key . '_btn_hover_color', '%%order_class%% .bwf-custom-button a:hover span', __( 'Text', 'funnel-builder-powerpack' ), '#fff' );
			$this->add_controls_tab( $controls_tabs_id, __( 'Hover', 'funnel-builder-powerpack' ), $field_keys );

			$this->add_subheading( $style_id, __( 'Title Typography', 'funnel-builder-powerpack' ) );
			$default           = '|400|||||||';
			$font_side_default = array(
				'default' => '16px',
				'unit'    => 'px',
			);
			$this->add_typography( $style_id, $key . '_btn_text_typo', '%%order_class%% .bwf-custom-button a span:not(.bwf_subheading)', __( 'Heading', 'funnel-builder-powerpack' ), $default, array(), $font_side_default );

			$this->add_subheading( $style_id, __( 'SubTitle Typography', 'funnel-builder-powerpack' ) );
			$this->add_typography( $style_id, $key . '_btn_subheading_text_typo', '%%order_class%% .bwf-custom-button .bwf_subheading', __( 'Sub Heading', 'funnel-builder-powerpack' ) );

			$this->add_subheading( $style_id, 'Advanced' );
			$defaults = '5px | 5px| 5px | 5px';
			$this->add_padding( $style_id, $key . '_btn_text_padding', '%%order_class%% .bwf-custom-button a', $defaults );
			$this->add_margin( $style_id, $key . '_btn_text_margin', '%%order_class%% .bwf-custom-button a', $defaults );

			$default_args = array(
				'border_type'          => 'none',
				'border_width_top'     => '1',
				'border_width_bottom'  => '1',
				'border_width_left'    => '1',
				'border_width_right'   => '1',
				'border_radius_top'    => '3',
				'border_radius_bottom' => '3',
				'border_radius_left'   => '3',
				'border_radius_right'  => '3',
				'border_color'         => '#dddddd',
			);
			$this->add_border( $style_id, $key . '_btn_text_alignment_border', '%%order_class%% .bwf-custom-button a', array(), $default_args );
			$this->add_box_shadow( $style_id, $key . '_btn_text_alignment_box_shadow', '%%order_class%% .bwf-custom-button a' );
		}


		private function close_button_settings() {

			$key = 'wfop_optin_form_popup';

			$field_id = $this->add_tab( __( 'Close Button', 'funnel-builder-powerpack' ), 2 );
			$this->add_subheading( $field_id, __( 'Position', 'funnel-builder-powerpack' ) );

			$selector = '%%order_class%% .bwf_pp_close';
			// Divi Use Position but we use Margin

			$defaults_margin = '-12px | -14px| 0 | 0px';
			$this->add_margin( $field_id, $key . '_close_icon_position_margin', $selector, $defaults_margin );

			$this->add_subheading( $field_id, __( 'Size', 'funnel-builder-powerpack' ) );

			$default           = '|700|||||||';
			$font_side_default = array(
				'default' => '25px',
				'unit'    => 'px',
			);

			$this->add_font_size( $field_id, $key . '_icon_size_font_size', $selector, '', $default, array(), $font_side_default );
			$this->add_padding( $field_id, $key . '_close_btn_inner_gap_padding', $selector );
			$this->add_border_radius( $field_id, $key . '_close_btn_border', $selector );

			$controls_tabs_id = $this->add_controls_tabs( $field_id, __( 'Color', 'funnel-builder-powerpack' ) );
			$colors_field     = array();
			$colors_field[]   = $this->add_background_color( $field_id, 'close_button_background_color', $selector, '#6E6E6E', __( 'Background', 'funnel-builder-powerpack' ) );
			$colors_field[]   = $this->add_color( $field_id, 'close_button_color', $selector, __( 'Color', 'funnel-builder-powerpack' ), '#26b453' );

			$this->add_controls_tab( $controls_tabs_id, __( 'Normal', 'funnel-builder-powerpack' ), $colors_field );

			$colors_field   = array();
			$colors_field[] = $this->add_background_color( $field_id, 'close_button_hover_background_color', $selector . ':hover', '#D40F0F', __( 'Background', 'funnel-builder-powerpack' ) );
			$colors_field[] = $this->add_color( $field_id, 'close_button_hover_color', $selector . ':hover', __( 'Color', 'funnel-builder-powerpack' ), '#444444' );

			$this->add_controls_tab( $controls_tabs_id, __( 'Hover', 'funnel-builder-powerpack' ), $colors_field );
		}


		private function style_field() {

			$key       = 'wfop_optin_form_popup';
			$condition = array( 'show_labels' => 'on' );

			$form_id = $this->add_tab( __( 'Form', 'funnel-builder-powerpack' ), 2 );

			$this->add_subheading( $form_id, __( 'Label', 'funnel-builder-powerpack' ), '', $condition );
			$this->add_typography( $form_id, 'label_typography', '%%order_class%% .bwfac_form_sec > label, %%order_class%% .bwfac_form_sec .wfop_input_cont > label', '', '', $condition );
			$this->add_color( $form_id, 'label_color', '%%order_class%% .bwfac_form_sec > label, %%order_class%%  .bwfac_form_sec .wfop_input_cont > label', __( 'Text', 'funnel-builder-powerpack' ), '', $condition );
			$this->add_color( $form_id, 'mark_required_color', '%%order_class%% .bwfac_form_sec > label > span, %%order_class%% .bwfac_form_sec .wfop_input_cont > label > span', __( 'Asterisk', 'funnel-builder-powerpack' ), 'red', $condition );

			$this->add_subheading( $form_id, __( 'Input', 'funnel-builder-powerpack' ) );
			$this->add_typography( $form_id, 'field_typography', '%%order_class%% .bwfac_form_sec .wffn-optin-input' );
			$this->add_color( $form_id, 'field_text_color', '%%order_class%% .bwfac_form_sec .wffn-optin-input, %%order_class%% .bwfac_form_sec .wffn-optin-input::placeholder', __( 'Text', 'funnel-builder-powerpack' ), '#3F3F3F' );
			$this->add_background_color( $form_id, 'field_background_color', '%%order_class%% .bwfac_form_sec .wffn-optin-input', '#ffffff', __( 'Background', 'funnel-builder-powerpack' ) );
			$this->add_select( $form_id, 'input_size', __( 'Field Size', 'funnel-builder-powerpack' ), self::get_input_fields_sizes(), '12px' );

			$this->add_subheading( $form_id, __( 'Advanced', 'funnel-builder-powerpack' ) );

			$border_args = array(
				'border_type'          => 'solid',
				'border_width_top'     => '2',
				'border_width_bottom'  => '2',
				'border_width_left'    => '2',
				'border_width_right'   => '2',
				'border_radius_top'    => '0',
				'border_radius_bottom' => '0',
				'border_radius_left'   => '0',
				'border_radius_right'  => '0',
				'border_color'         => '#d8d8d8',
			);
			$this->add_border( $form_id, 'field_border', '%%order_class%% .bwfac_form_sec .wffn-optin-input', array(), $border_args );

			$this->add_subheading( $form_id, __( 'Spacing', 'funnel-builder-powerpack' ) );

			$defaults_padding = '0px|10px|0px|12px';

			$defaults_margin = '0px|0px|10px|0px';

			$this->add_padding( $form_id, $key . '_pop_column_gap_padding', '%%order_class%% .bwfac_form_sec', $defaults_padding, __( 'Columns', 'funnel-builder-powerpack' ) );

			$this->add_margin( $form_id, $key . '_pop_row_gap_margin', '%%order_class%% .bwfac_form_sec', $defaults_margin, __( 'Columns Row', 'funnel-builder-powerpack' ) );

			$this->add_subheading( $form_id, __( 'Text After Button', 'funnel-builder-powerpack' ) );

			$default = '|400|||||||';
			$this->add_typography( $form_id, $key . '_text_after_submit_typography', '%%order_class%% .bwf_pp_wrap .bwf_pp_cont .bwf_pp_footer', '', $default );
			$this->add_color( $form_id, $key . '_text_after_submit_color', '%%order_class%% .bwf_pp_wrap .bwf_pp_cont .bwf_pp_footer', __( 'Label', 'funnel-builder-powerpack' ), '#000000' );
			$this->add_letter_spacing( $form_id, $key . '_text_after_submit_letter_spacing', '%%order_class%% .bwf_pp_wrap .bwf_pp_cont .bwf_pp_footer' );

			$btn_id = $this->add_tab( __( 'Submit Button', 'funnel-builder-powerpack' ), 2 );

			$default_width = array(
				'default' => '100%',
				'unit'    => '%',
			);
			$this->add_max_width( $btn_id, $key . '_button_width', '%%order_class%% .bwfac_form_sec #wffn_custom_optin_submit', __( 'Button width (in %)', 'funnel-builder-powerpack' ), $default_width, array(), true );

			$this->add_text_alignments( $btn_id, $key . '_button_alignment', '%%order_class%% .wffn-custom-optin-from #bwf-custom-button-wrap', __( 'Alignment', 'funnel-builder-powerpack' ), 'center' );
			$this->add_text_alignments( $btn_id, $key . '_button_text_alignment', '%%order_class%% .wffn-custom-optin-from #bwf-custom-button-wrap span', __( 'Text Alignment', 'funnel-builder-powerpack' ), 'center' );

			$controls_tabs_id = $this->add_controls_tabs( $btn_id, 'Button Color' );
			$colors_field     = array();
			$colors_field[]   = $this->add_color( $btn_id, 'button_color', '%%order_class%% .bwfac_form_sec #wffn_custom_optin_submit .bwf_heading, %%order_class%% .bwfac_form_sec #wffn_custom_optin_submit .bwf_subheading', __( 'Label', 'funnel-builder-powerpack' ), '#ffffff' );
			$colors_field[]   = $this->add_background_color( $btn_id, 'button_bg_color', '%%order_class%% .bwfac_form_sec #wffn_custom_optin_submit', '#26b453', __( 'Background', 'funnel-builder-powerpack' ) );

			$this->add_controls_tab( $controls_tabs_id, __( 'Normal', 'funnel-builder-powerpack' ), $colors_field );

			$colors_field   = array();
			$colors_field[] = $this->add_color( $btn_id, 'button_hover_color', '%%order_class%% .bwfac_form_sec #wffn_custom_optin_submit:hover .bwf_heading, %%order_class%% .bwfac_form_sec #wffn_custom_optin_submit:hover .bwf_subheading', __( 'Label', 'funnel-builder-powerpack' ), '#ffffff' );
			$colors_field[] = $this->add_background_color( $btn_id, 'button_hover_bg_color', '%%order_class%% .bwfac_form_sec #wffn_custom_optin_submit:hover', '#0a6c2f', __( 'Background', 'funnel-builder-powerpack' ) );

			$this->add_controls_tab( $controls_tabs_id, __( 'Hover', 'funnel-builder-powerpack' ), $colors_field );

			$this->add_subheading( $btn_id, __( 'Heading Typography', 'funnel-builder-powerpack' ) );
			$this->add_typography( $btn_id, 'button_text_typo', '%%order_class%% .bwfac_form_sec #wffn_custom_optin_submit .bwf_heading' );

			$this->add_subheading( $btn_id, __( 'Sub Heading Typography', 'funnel-builder-powerpack' ) );
			$this->add_typography( $btn_id, 'button_subheading_text_typo', '%%order_class%% .bwfac_form_sec #wffn_custom_optin_submit .bwf_subheading' );

			$this->add_subheading( $btn_id, __( 'Advanced', 'funnel-builder-powerpack' ) );
			$defaults_padding = '15px | 15px| 15px | 15px';
			$this->add_padding( $btn_id, 'button_text_padding', '%%order_class%% .bwfac_form_sec #wffn_custom_optin_submit', $defaults_padding );

			$defaults_margin = '15px | 0px| 25px | 0px';
			$this->add_margin( $btn_id, 'button_text_margin', '%%order_class%% .bwfac_form_sec #wffn_custom_optin_submit', $defaults_margin );

			$btn_border_args = array(
				'border_type'          => 'solid',
				'border_width_top'     => '2',
				'border_width_bottom'  => '2',
				'border_width_left'    => '2',
				'border_width_right'   => '2',
				'border_radius_top'    => '0',
				'border_radius_bottom' => '0',
				'border_radius_left'   => '0',
				'border_radius_right'  => '0',
				'border_color'         => '#FBA506',
			);

			$this->add_border( $btn_id, 'bwf_button_border', '%%order_class%% .bwfac_form_sec #wffn_custom_optin_submit', array(), $btn_border_args );

			$this->add_box_shadow( $btn_id, 'button_text_alignment_box_shadow', '%%order_class%% .bwfac_form_sec #wffn_custom_optin_submit' );
		}

		public static function get_input_fields_sizes() {
			return array(
				'6px'  => __( 'Small', 'funnel-builder-powerpack' ),
				'9px'  => __( 'Medium', 'funnel-builder-powerpack' ),
				'12px' => __( 'Large', 'funnel-builder-powerpack' ),
				'15px' => __( 'Extra Large', 'funnel-builder-powerpack' ),
			);
		}

		public function get_divi_page_id() {
			$post_id = 0;
			if ( wp_doing_ajax() ) {

				if ( isset( $_REQUEST['action'] ) && 'heartbeat' === $_REQUEST['action'] && isset( $_REQUEST['data'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
					if ( isset( $_REQUEST['data']['et'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
						$post_id = $_REQUEST['data']['et']['post_id']; //phpcs:ignore
					}
				}

				if ( isset( $_REQUEST['post_id'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$post_id = absint( $_REQUEST['post_id'] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				}
				if ( isset( $_REQUEST['et_post_id'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$post_id = absint( $_REQUEST['et_post_id'] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				}
				if ( $post_id > 0 ) {
					$post      = get_post( $post_id );
					$post_type = WFOPP_Core()->optin_pages->get_post_type_slug();
					if ( ! is_null( $post ) && $post->post_type === $post_type ) {
						return $post->ID;
					}
				}
			}

			return $this->get_the_ID();
		}


		/**
		 * Render widget output on the frontend.
		 *
		 * Written in PHP and used to generate the final HTML.
		 *
		 * @access protected
		 */
		protected function html( $attrs, $content = null, $render_slug = '' ) {//phpcs:ignore
			$settings = $this->props;

			// Match reject button pattern: direct access to props like reject button does
			// Support both Divi 4 (btn_icon) and Divi 5 (btn_icon_field) attribute names
			$btn_icon          = '';
			$btn_icon_position = '';

			// Check all possible locations for icon value
			// Divi 5 may store in $attrs or merge into $this->props
			$icon_raw     = '';
			$position_raw = '';

			// Check $this->props first (like reject button does) - Divi may merge attributes here
			// Use isset() check first, then validate content (empty arrays/objects might be valid)
			if ( isset( $settings['btn_icon_field'] ) ) {
				$icon_raw = $settings['btn_icon_field'];
			} elseif ( isset( $settings['btn_icon'] ) ) {
				$icon_raw = $settings['btn_icon'];
			}

			if ( isset( $settings['btn_icon_field_position'] ) ) {
				$position_raw = $settings['btn_icon_field_position'];
			} elseif ( isset( $settings['btn_icon_position'] ) ) {
				$position_raw = $settings['btn_icon_position'];
			}

			// Also check $attrs parameter (Divi 5 may pass separately)
			if ( ( empty( $icon_raw ) || ( is_array( $icon_raw ) && empty( array_filter( $icon_raw ) ) ) ) && is_array( $attrs ) ) {
				if ( isset( $attrs['btn_icon_field'] ) ) {
					$icon_raw = $attrs['btn_icon_field'];
				} elseif ( isset( $attrs['btn_icon'] ) ) {
					$icon_raw = $attrs['btn_icon'];
				}
			}

			if ( ( empty( $position_raw ) || ( is_array( $position_raw ) && empty( array_filter( $position_raw ) ) ) ) && is_array( $attrs ) ) {
				if ( isset( $attrs['btn_icon_field_position'] ) ) {
					$position_raw = $attrs['btn_icon_field_position'];
				} elseif ( isset( $attrs['btn_icon_position'] ) ) {
					$position_raw = $attrs['btn_icon_position'];
				}
			}

			// Extract icon value from nested structures - handle all possible formats
			if ( ! empty( $icon_raw ) ) {
				// Handle Divi 5 nested format (desktop.value)
				if ( is_array( $icon_raw ) ) {
					// Check for desktop.value structure first
					if ( isset( $icon_raw['desktop']['value'] ) ) {
						$btn_icon = $icon_raw['desktop']['value'];
					} elseif ( isset( $icon_raw['desktop'] ) ) {
						// desktop might be the value directly or another array
						if ( is_array( $icon_raw['desktop'] ) ) {
							// Check for nested value or unicode
							if ( isset( $icon_raw['desktop']['value'] ) ) {
								$btn_icon = $icon_raw['desktop']['value'];
							} elseif ( isset( $icon_raw['desktop']['unicode'] ) ) {
								$btn_icon = $icon_raw['desktop']['unicode'];
							} else {
								// Try to get first string value
								$btn_icon = reset( $icon_raw['desktop'] );
							}
						} else {
							$btn_icon = $icon_raw['desktop'];
						}
					} elseif ( isset( $icon_raw['unicode'] ) ) {
						$btn_icon = $icon_raw['unicode'];
					} elseif ( isset( $icon_raw['value'] ) ) {
						$btn_icon = $icon_raw['value'];
					} else {
						// Try to get first string value from array
						$btn_icon = reset( $icon_raw );
					}
				} else {
					$btn_icon = $icon_raw;
				}
			}

			// Extract position value from nested structures
			if ( ! empty( $position_raw ) ) {
				if ( is_array( $position_raw ) ) {
					if ( isset( $position_raw['desktop']['value'] ) ) {
						$btn_icon_position = $position_raw['desktop']['value'];
					} elseif ( isset( $position_raw['desktop'] ) ) {
						$btn_icon_position = is_array( $position_raw['desktop'] ) && isset( $position_raw['desktop']['value'] ) ? $position_raw['desktop']['value'] : $position_raw['desktop'];
					} else {
						$btn_icon_position = reset( $position_raw );
					}
				} else {
					$btn_icon_position = $position_raw;
				}
			}

			// Ensure icon is a string (not array) and position has a default
			if ( is_array( $btn_icon ) || is_object( $btn_icon ) ) {
				// Try to extract string value from array/object
				if ( is_array( $btn_icon ) ) {
					$btn_icon = reset( $btn_icon );
				} else {
					$btn_icon = '';
				}
			}

			// Ensure position is a string
			if ( is_array( $btn_icon_position ) || is_object( $btn_icon_position ) ) {
				$pos_array          = (array) $btn_icon_position;
				$btn_icon_position  = reset( $pos_array );
			}

			if ( empty( $btn_icon_position ) ) {
				$btn_icon_position = 'left';
			}

			// Convert icon to string if it's not already
			$btn_icon = (string) $btn_icon;

			$button_args = array(
				'title'              => isset( $settings['btn_text'] ) ? $settings['btn_text'] : '',
				'subtitle'           => isset( $settings['btn_subheading_text'] ) ? $settings['btn_subheading_text'] : '',
				'icon_class'         => $btn_icon,
				'type'               => 'anchor',
				'link'               => '#',
				'icon_position'      => $btn_icon_position,
				'divi_icon_position' => $btn_icon_position,
				'wrapper_class'      => 'wfop_popup_form',
				'icon_html'          => '',
				'show_icon'          => ! empty( $btn_icon ),
			);

			if ( ! empty( $btn_icon ) ) {
				$icon_html = html_entity_decode( et_pb_process_font_icon( $btn_icon ) );

				$button_args['icon_html'] = "<span class='wfocu-button-icon et-pb-icon $btn_icon_position'>" . $icon_html . '</span>';
			}

			$wrapper_class = 'elementor-form-fields-wrapper';
			$show_labels   = ( isset( $settings['show_labels'] ) && 'off' === $settings['show_labels'] ) ? false : true;

			$popup_open = ( isset( $settings['popup_open'] ) && 'yes' === $settings['popup_open'] ) ? 'show_popup_form' : '';

			$wrapper_class .= $show_labels ? '' : ' wfop_hide_label';

			$optinPageId    = WFOPP_Core()->optin_pages->get_optin_id();
			$optin_fields   = WFOPP_Core()->optin_pages->form_builder->get_optin_layout( $optinPageId );
			$optin_settings = WFOPP_Core()->optin_pages->get_optin_form_integration_option( $optinPageId );

			foreach ( $optin_fields as $step_slug => $optinFields ) {
				foreach ( $optinFields as $key => $optin_field ) {
					$optin_fields[ $step_slug ][ $key ]['width'] = $settings[ $optin_field['InputName'] ];
				}
			}

			$settings['popup_bar_pp']                = ( isset( $settings['popup_bar_pp'] ) && 'on' === $settings['popup_bar_pp'] ) ? 'enable' : 'disabled';
			$settings['popup_bar_text_wrap_classes'] = ( isset( $settings['popup_bar_text_position'] ) && 'on' === $settings['popup_bar_text_position'] ) ? 'on' : 'off';
			$settings['popup_bar_wrap_classes']      = ( isset( $settings['popup_bar_text_position'] ) && 'on' === $settings['popup_bar_text_position'] ) ? 'on' : 'off';

			$settings['popup_bar_animation'] = ( isset( $settings['popup_bar_animation'] ) && 'on' === $settings['popup_bar_animation'] ) ? 'yes' : 'no';
			$settings['popup_bar_width']     = ( isset( $settings['popup_bar_width'] ) && isset( $settings['popup_bar_width']['size'] ) ) ? $settings['popup_bar_width']['size'] : '75';
			$settings['field_size']          = isset( $settings['input_size'] ) ? $settings['input_size'] : 'small';

			$settings['button_border_size'] = 0;

			$input_size = $settings['input_size'];

			$custom_form = WFOPP_Core()->form_controllers->get_integration_object( 'form' );

			ob_start();
			if ( $custom_form instanceof WFFN_Optin_Form_Controller_Custom_Form ) { ?>

				<div class="wfop_popup_wrapper wfop_pb_widget_wrap">
					<?php
					echo '<div class=bwf-custom-button>';

					// Generate button HTML to match frontend structure exactly
					$btn_text            = isset( $settings['btn_text'] ) ? $settings['btn_text'] : '';
					$btn_subheading      = isset( $settings['btn_subheading_text'] ) ? $settings['btn_subheading_text'] : '';
					$icon_position_class = ! empty( $btn_icon_position ) ? $btn_icon_position : 'left';

					// Process icon if it exists - ensure it's a valid string
					$icon_html = '';
					if ( ! empty( $btn_icon ) ) {
						// Convert to string if needed
						$icon_value = is_string( $btn_icon ) ? $btn_icon : (string) $btn_icon;
						if ( ! empty( $icon_value ) ) {
							$icon_html = html_entity_decode( et_pb_process_font_icon( $icon_value ) );
						}
					}
					?>
					<div class="wfop_popup_form" id="bwf-custom-button-wrap">
						<a href="#">
							<span class="bwf-text-wrapper">
								<?php if ( 'left' === $icon_position_class && ! empty( $icon_html ) ) { ?>
									<span class="wfocu-button-icon et-pb-icon <?php echo esc_attr( $icon_position_class ); ?>"><?php echo $icon_html; ?></span>
								<?php } ?>
								<?php if ( ! empty( $btn_text ) ) { ?>
									<span class="bwf_heading"><?php echo esc_html( $btn_text ); ?></span>
								<?php } ?>
								<?php if ( 'right' === $icon_position_class && ! empty( $icon_html ) ) { ?>
									<span class="wfocu-button-icon et-pb-icon <?php echo esc_attr( $icon_position_class ); ?>"><?php echo $icon_html; ?></span>
								<?php } ?>
							</span>
							<?php if ( ! empty( $btn_subheading ) ) { ?>
								<span class="bwf_subheading"><?php echo esc_html( $btn_subheading ); ?></span>
							<?php } ?>
						</a>
					</div>
					<?php
					echo '</div>';
					$show_class = '';

					if ( ( isset( $settings['enable_popup_bar_on_builder'] ) && 'on' === $settings['enable_popup_bar_on_builder'] ) && wp_doing_ajax() ) {

						$show_class = 'show_popup_form';

					}

					?>
					<div class="bwf_pp_overlay <?php echo esc_attr( $show_class ); ?> bwf_pp_effect_<?php echo esc_attr( $settings['popup_open_animation'] ); ?> <?php echo esc_attr( $popup_open ); ?>">
						<div class="bwf_pp_wrap" style="background: #ffffff!important;">
							<a class="bwf_pp_close" href="javascript:void(0);">&times;</a>
							<div class="bwf_pp_cont">
								<?php
								$custom_form->_output_form( $wrapper_class, $optin_fields, $optinPageId, $optin_settings, 'popover', $settings );
								?>
							</div>
						</div>
					</div>


				</div>
				<script>
					jQuery(document).trigger('wffn_reload_popups');
					jQuery(document).trigger('wffn_reload_phone_field');
				</script>
				<?php
			}

			?>

			<style>

				<?php
				if ( wp_doing_ajax() ) {
					?>
				.wfop_popup_form a {
					pointer-events: none !important;
				}

					<?php

				}
				?>


				.et-db #et-boc .et-l .et_pb_module .wffn-custom-optin-from .wffn-optin-input {
                    padding: <?php echo $input_size; //phpcs:ignore ?> 15px;
				}

				body.et-db #et-boc .et-l #et_wfop_optin_form_popup .bwf-custom-button .et-pb-icon {
					font-family: ETmodules !important;
					text-decoration: none !important;
				}


			</style>
			<?php

			return ob_get_clean();
		}

		public function script() {

			if ( ! isset( $_REQUEST['et_fb'] ) ) {//phpcs:ignore
				return;

			}

			?>
			<script>
				window.addEventListener('load', function () {

					(function ($) {
						$(document.body).on('click', '.et-fb-button--success,.et-fb-button--danger', function () {
							$('.show_popup_form').removeClass('show_popup_form');
							let iframe = $('#et-fb-app-frame');
							if (iframe.length > 0) {
								var innerDoc = (iframe[0].contentDocument) ? iframe[0].contentDocument : iframe[0].contentWindow.document;
								$(innerDoc.body).trigger('optin_popup_close');

								$(innerDoc).find('.show_popup_form').removeClass('show_popup_form');
							} else {
								$('.show_popup_form').removeClass('show_popup_form');
							}
						});
					})(jQuery);
				})
			</script>
			<?php
		}
	}

	return new WFOP_Optin_Form_Popup();
}
