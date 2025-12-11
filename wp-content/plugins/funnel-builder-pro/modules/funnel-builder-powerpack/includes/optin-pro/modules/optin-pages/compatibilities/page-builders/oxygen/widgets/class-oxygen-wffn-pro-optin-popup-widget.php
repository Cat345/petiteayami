<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Oxygen_WFFN_Pro_Optin_Form_Popup_Widget
 */
if ( ! class_exists( 'Oxygen_WFFN_Pro_Optin_Form_Popup_Widget' ) ) {

	#[AllowDynamicProperties]

class Oxygen_WFFN_Pro_Optin_Form_Popup_Widget extends WFFN_Optin_HTML_Block_Oxy {
		public $slug = 'wffn_optin_oxy_form_popup';
		public $form_sub_headings = [];
		protected $id = 'wffn_optin_oxy_form_popup';
		static $css_build = false;

		/**
		 * Oxygen_WFFN_Pro_Optin_Form_Popup_Widget constructor.
		 */
		public function __construct() {
			$this->name = __( 'Optin Form Popup', 'funnel-builder-powerpack' );
			parent::__construct();

		}


		public function name() {
			return $this->name;
		}

		/**
		 * @param $template WFACP_Template_Common;
		 */
		public function setup_controls() {


			$this->progress_bar();
			$this->progress_bar_headings();
			$this->register_form_fields();
			$this->register_form_styles();
			$this->outer_button_settings();
			$this->popup_setting();
			$this->button_settings( __( 'Popup Inline Button', 'funnel-builder-powerpack' ) );
			$this->close_button_settings();
		}

		public function progress_bar() {
			$tab_id = $this->add_tab( __( 'Progress Bar', 'funnel-builder-powerpack' ) );

			$this->add_switcher( $tab_id, 'popup_bar_pp', '', 'on' );

			$this->add_max_width( $tab_id, '_popup_bar_width', '.bwf_pp_overlay .bwf_pp_bar_wrap .bwf_pp_bar', '', [
				'default' => 75,
				'unit'    => '%'
			] );

			$this->add_height( $tab_id, '_popup_bar_height', '.bwf_pp_overlay .bwf_pp_bar_wrap', '', [
				'default' => 30,
				'unit'    => 'px'
			] );

			$this->add_padding( $tab_id, '_popup_bar_padding', '.bwf_pp_overlay .bwf_pp_bar_wrap' );

			$this->add_switcher( $tab_id, 'popup_bar_text_position', __( 'Show progress text above the bar', 'funnel-builder-powerpack' ), '' );
			$this->add_switcher( $tab_id, 'popup_bar_animation', __( 'Animation', 'funnel-builder-powerpack' ), 'on' );
			$this->add_text( $tab_id, 'popup_bar_text', __( 'Text', 'funnel-builder-powerpack' ), __( '75% Complete', 'funnel-builder-powerpack' ) );


			$this->add_background_color( $tab_id, 'progress_color', '.bwf_pp_bar_wrap .bwf_pp_bar', '#338d48', __( 'Color', 'funnel-builder-powerpack' ) );
			$this->add_background_color( $tab_id, 'progress_background_color', '.bwf_pp_overlay .bwf_pp_bar_wrap', '#ffffff', __( 'Background', 'funnel-builder-powerpack' ) );
			$this->add_typography( $tab_id, 'popup_progress_typo', '.bwf_pp_overlay .pp-bar-text' );
		}

		public function progress_bar_headings() {
			$tab_id = $this->add_tab( __( 'Heading', 'funnel-builder-powerpack' ) );
			$this->add_text( $tab_id, 'popup_heading', __( 'Heading', 'funnel-builder-powerpack' ), __( "You're just one step away!", 'funnel-builder-powerpack' ) );
			$this->add_text( $tab_id, 'popup_sub_heading', __( 'Sub Heading', 'funnel-builder-powerpack' ), __( "Enter your details below and we'll get you signed up", 'funnel-builder-powerpack' ) );
			$this->add_typography( $tab_id, 'popup_heading_typo', '.bwf_pp_overlay .bwf_pp_opt_head', __( 'Heading Typography', 'funnel-builder-powerpack' ) );
			$this->add_typography( $tab_id, 'popup_subheading_typography', '.bwf_pp_overlay .bwf_pp_opt_sub_head', __( 'Sub Heading Typography', 'funnel-builder-powerpack' ) );


		}


		private function outer_button_settings() {
			$form_id = $this->add_tab( __( 'Button', 'funnel-builder-powerpack' ) );
			$this->add_text( $form_id, 'btn_text', __( 'Title', 'funnel-builder-powerpack' ), __( 'Signup Now', 'funnel-builder-powerpack' ) );
			$this->add_text( $form_id, 'btn_subheading_text', __( 'Subtitle', 'funnel-builder-powerpack' ) );


			$this->add_width( $form_id, 'outer_btn_color', '#bwf-custom-button-wrap a', '', [
				'default' => 30,
				'unit'    => '%'
			] );

			$this->add_heading( $form_id, __( 'Heading Typography ', 'funnel-builder-powerpack' ) );

			$this->add_text_alignments( $form_id, 'btn_alignment', '.wfop_popup_wrapper > .bwf-custom-button > #bwf-custom-button-wrap', __( 'Button Alignment', 'funnel-builder-powerpack' ) );

			$this->add_text_alignments( $form_id, 'btn_text_alignment', '#bwf-custom-button-wrap a', __( 'Text Alignment', 'funnel-builder-powerpack' ) );

			$this->custom_typography( $form_id, 'btn_text_typo', '#bwf-custom-button-wrap .bwf_heading, #bwf-custom-button-wrap .bwf_icon', '' );


			$this->add_heading( $form_id, __( "Button Icon", 'funnel-builder-powerpack' ) );
			$this->add_switcher( $form_id, 'btn_show_icon', __( "Show Icon", 'funnel-builder-powerpack' ), 'on' );
			$this->add_icon( $form_id, 'btn_icon' );

			$this->add_select( $form_id, 'btn_icon_position', __( 'Icon Position', 'funnel-builder-powerpack' ), [
				'left'  => __( 'Before', 'funnel-builder-powerpack' ),
				'right' => __( 'After', 'funnel-builder ' ),
			], 'left' );
			$this->slider_measure_box( $form_id, 'btn_icon_font_size', '.wfocu-button-wrapper .wfocu-button-content-wrapper .wfocu-button-icon svg', "", "15", [], 'height|width' );
			$this->add_margin( $form_id, 'btn_icon_margin', '.wfocu-button-wrapper  .wfocu-button-icon', __( 'Icon Spacing', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_color( $form_id, 'btn_icon_color', '.wfocu-button-wrapper .wfocu-button-content-wrapper .wfocu-button-icon svg', __( 'Icon Color', 'woofunnels-upstroke-one-click-upsell' ), '' );

			$style_id = $form_id;

			$this->add_heading( $form_id, __( 'Color', 'funnel-builder-powerpack' ) );

			$this->add_color( $style_id, 'btn_color', '#bwf-custom-button-wrap a', __( 'Text', 'funnel-builder-powerpack' ) );
			$this->add_background_color( $style_id, 'btn_bg_color', '#bwf-custom-button-wrap a', '#efefef' );

			$this->add_color( $style_id, 'btn_text_hover_color', '#bwf-custom-button-wrap a:hover', __( 'Text Hover', 'funnel-builder-powerpack' ) );
			$this->add_background_color( $style_id, 'btn_bg_hover_color_1', '#bwf-custom-button-wrap a:hover', '#efefef', __( 'Background Hover', 'funnel-builder-powerpack' ) );


			$this->add_heading( $style_id, "Advanced" );
			$this->add_padding( $style_id, 'btn_text_padding', '#bwf-custom-button-wrap a' );
			$this->add_margin( $style_id, 'btn_text_margin', '#bwf-custom-button-wrap a' );
			$this->add_border( $style_id, 'btn_text_alignment_border', '#bwf-custom-button-wrap a' );
			$this->add_box_shadow( $style_id, 'btn_text_alignment_box_shadow', '#bwf-custom-button-wrap a' );


			$this->add_typography( $style_id, 'btn_subheading_text_typo', '#bwf-custom-button-wrap .bwf_subheading', __( 'Sub Heading', 'funnel-builder-powerpack' ) );

		}

		private function popup_setting() {
			$form_id = $this->add_tab( __( 'Popup', 'funnel-builder-powerpack' ) );

			$this->add_select( $form_id, 'popup_open_animation', esc_html__( 'Effect' ), [
				'fade'       => __( 'Fade', 'funnel-builder-powerpack' ),
				'slide-up'   => __( 'Slide Up', 'funnel-builder-powerpack' ),
				'slide-down' => __( 'Slide Down', 'funnel-builder-powerpack' ),
			], 'fade' );

			$this->add_width( $form_id, __( 'popup_bar_width', 'funnel-builder-powerpack' ), '.bwf_pp_wrap', '', [
				'default' => 600,
				'unit'    => 'px'
			] );

			$this->add_padding( $form_id, 'popup_padding', '.bwf_pp_wrap .bwf_pp_cont' );
		}

		private function close_button_settings() {

			$field_id = $this->add_tab( __( 'Close Button', 'funnel-builder-powerpack' ) );
			$selector = '.bwf_pp_close';
			//Elementor Use Position but we use Margin

			$this->add_width( $field_id, 'close_icon_size_font_width', $selector );
			$this->add_height( $field_id, 'close_icon_size_font_height', $selector );

			$this->add_heading( $field_id, __( 'Typography', 'funnel-builder-powerpack' ) );
			$this->custom_typography( $field_id, 'close_btn_text_typo', $selector, '' );

			$this->add_heading( $field_id, __( 'Color', 'funnel-builder-powerpack' ) );
			$this->add_color( $field_id, 'close_button_text_color', $selector, '', '#ffffff' );
			$this->add_background_color( $field_id, 'close_button_background_color_', $selector, '#6E6E6E' );

			$this->add_color( $field_id, 'close_button_hover_color', '.bwf_pp_close:hover', 'Hover Color', '#ffffff' );
			$this->add_background_color( $field_id, 'close_button_hover_background_color', '.bwf_pp_close:hover', '6E6E6E', "Hover Background " );


			$this->add_heading( $field_id, __( 'Spacing', 'funnel-builder-powerpack' ) );
			$this->add_margin( $field_id, 'close_button_vertical', $selector );
			$this->add_padding( $field_id, 'close_btn_inner_gap', $selector );

			$this->add_border( $field_id, 'close_btn_border', $selector );
		}


		public function get_icon_html( $icon ) {
			$html =	'<span class="wffn-op-button-icon">';
			$html .= '<div id="fancy_icon-50-'. esc_attr( time() ).'" class="ct-fancy-icon"><svg id="svg-fancy_icon-50-'. esc_attr( time() ).'"><use xlink:href="#'.esc_attr( $icon ).'"></use></svg></div>';
			$html .= $this->output_svg_icon( $icon );
			$html .= '</span>';
			return $html;
		}

		function output_svg_icon( $icon ) {

			if ( defined( "SHOW_CT_BUILDER" ) ) {
				return;
			}
			$html = '';

			if( function_exists( 'oxy_get_svg_sets')){
				$svg_sets = oxy_get_svg_sets();
			}else{
				$svg_sets = get_option( "ct_svg_sets", array() );
			}

			if ( is_array( $svg_sets ) && count( $svg_sets ) > 0 ) {
				foreach ( $svg_sets as $set ) {

					$svg = new SimpleXMLElement( $set );
					if ( $svg->defs->symbol ) {
						foreach ( $svg->defs->symbol as $symbol ) {
							$icon_data  = (array) $symbol;
							$attributes = $icon_data["@attributes"];

							if ( $icon === $attributes['id'] ) {
								$view_box = explode( " ", $attributes['viewBox'] );
								if ( $view_box[2] !== $view_box[3] ) {
									echo "<style>";
									echo ".ct-" . esc_attr( $attributes['id'] ) . "{";
									echo "width:" . ( $view_box[2] / $view_box[3] ) . "em";//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									echo "}";
									echo "</style>\r\n";
								}
								$html .= '<symbol id="' . esc_attr( $attributes['id'] ) . '" viewBox="' . esc_attr( $attributes['viewBox'] ) . '"><title>' . esc_attr( $icon_data['title'] ) . '</title><path d="' . esc_attr( $icon_data['path']->attributes()->d ) . '"></path></symbol>';
								break;

							}
						}
					}
				}
			}

			if ( $html !== '' ) {
				return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true" style="position: absolute; width: 0; height: 0; overflow: hidden;" version="1.1"><defs>' . $html . '</defs></svg>';
			}

			return $html;
		}

		/**
		 * Render widget output on the frontend.
		 *
		 * Written in PHP and used to generate the final HTML.
		 *
		 * @access protected
		 */
		protected function html( $settings, $defaults, $content ) {//phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter


			$button_args = array(
				'title'         => $settings['btn_text'],
				'subtitle'      => $settings['btn_subheading_text'],
				'type'          => 'anchor',
				'link'          => '#',
				'wrapper_class' => 'wfop_popup_form',
				'show_icon'     => false,
			);


			if ( ! empty( $settings['btn_icon'] ) && ! empty( $settings['btn_show_icon'] ) && 'on' === $settings['btn_show_icon'] ) {
				$button_args['show_icon'] = true;
				$button_args['icon_html'] = $this->get_icon_html($settings['btn_icon']);
				$button_args['icon_class'] = $settings['btn_icon'];
			}

			if ( isset( $settings['btn_icon_position'] ) ) {
				$button_args['icon_position'] = $settings['btn_icon_position'] . ' bwf_icon';
			}


			$wrapper_class = 'oxy-form-fields-wrapper';
			$show_labels   = ( isset( $settings['show_labels'] ) && 'off' === $settings['show_labels'] ) ? false : true;
			$popup_open    = filter_input( INPUT_COOKIE, 'wfop_elementor_open_page', FILTER_UNSAFE_RAW );
			$popup_open    = 'on' === $popup_open ? 'show_popup_form' : '';
			if ( ! defined( "OXY_ELEMENTS_API_AJAX" ) ) {
				$popup_open = '';
			}
			$wrapper_class .= $show_labels ? '' : ' wfop_hide_label';

			$optinPageId    = WFOPP_Core()->optin_pages->get_optin_id();
			$optin_fields   = WFOPP_Core()->optin_pages->form_builder->get_optin_layout( $optinPageId );
			$optin_settings = WFOPP_Core()->optin_pages->get_optin_form_integration_option( $optinPageId );

			foreach ( $optin_fields as $step_slug => $optinFields ) {
				foreach ( $optinFields as $key => $optin_field ) {
					$optin_fields[ $step_slug ][ $key ]['width'] = $settings[ $optin_field['InputName'] ];
				}
			}

			$settings['popup_bar_pp'] = ( isset( $settings['popup_bar_pp'] ) && 'on' === $settings['popup_bar_pp'] ) ? 'enable' : 'disabled';

			$settings['popup_bar_text_wrap_classes'] = ( isset( $settings['popup_bar_text_position'] ) && 'on' === $settings['popup_bar_text_position'] ) ? 'on' : 'off';
			$settings['popup_bar_wrap_classes']      = ( isset( $settings['popup_bar_text_position'] ) && 'on' === $settings['popup_bar_text_position'] ) ? 'on' : 'off';


			$settings['popup_bar_animation'] = ( isset( $settings['popup_bar_animation'] ) && 'on' === $settings['popup_bar_animation'] ) ? 'yes' : '';
			$settings['popup_bar_width']     = ( isset( $settings['popup_bar_width'] ) && isset( $settings['popup_bar_width']['size'] ) ) ? $settings['popup_bar_width']['size'] : '75';
			$settings['button_border_size']  = 0;

			$custom_form = WFOPP_Core()->form_controllers->get_integration_object( 'form' );
			if ( $custom_form instanceof WFFN_Optin_Form_Controller_Custom_Form ) { ?>

                <div class="wfop_popup_wrapper wfop_pb_widget_wrap">
					<?php
					echo "<div class=bwf-custom-button>";
					$custom_form->wffn_get_button_html( $button_args );
					echo '</div>';
					$show_class = '';
					?>
                    <div class="bwf_pp_overlay <?php echo esc_attr( $show_class ); ?> bwf_pp_effect_<?php echo esc_attr( $settings['popup_open_animation'] ) ?> <?php echo esc_attr( $popup_open ); ?>">
                        <div class="bwf_pp_wrap" style="background: #ffffff!important;">
                            <a class="bwf_pp_close" href="javascript:void(0);">&times;</a>
                            <div class="bwf_pp_cont">
								<?php
								$custom_form->_output_form( $wrapper_class, $optin_fields, $optinPageId, $optin_settings, 'popover', $settings ); ?>
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


		}


		public function defaultCSS() {
			$defaultCSS = "
		
            .bwfac_form_sec.wffn-sm-50:nth-child(2n+1) {
                  clear: right;
            }
            .bwfac_form_sec.wffn-sm-50{
				clear: left;
			}
			.bwfac_form_sec.wffn-sm-50:nth-child(2n+1) {
				clear: right;
			}
			.oxy-optin-form-popup{
				width: 100%
			}
			.wffn-custom-optin-from .bwfac_form_sec.submit_button{
				clear:both;
			}             
            .wfop_section.single_step:after {
                 clear: both;
            }
            .wfop_section.single_step:after,
            .wfop_section.single_step:before {
                content: '';
                display: block;
            }
		    #bwf-custom-button-wrap {
                text-align: center;
            }
            .bwf_pp_overlay .bwf_pp_bar_wrap .bwf_pp_bar {
                width: 75%;
            }
            .bwf_pp_overlay .bwf_pp_bar_wrap {
                height: 40px;
                padding: 4px;
                background-color: #efefef;
            }
            .bwf_pp_overlay .pp-bar-text {
                color: #FFFFFF;
            }
            .bwf_pp_overlay .bwf_pp_opt_head {
                font-size: 17px;
                font-weight: 400;
                line-height: 1.5em;
                color: #000000;
            }
            .bwf_pp_overlay .bwf_pp_opt_sub_head {
                font-size: 24px;
                font-weight: 700;
                line-height: 1.5em;
            }
            .bwfac_form_sec .wffn-optin-input, .bwfac_form_sec .wffn-optin-input::placeholder {
                color: #3F3F3F;
            }
            body .bwfac_form_sec .wffn-optin-input {
				font-size: 16px;
				font-weight: 400;
				background-color: #ffffff;
				border:2px solid #d8d8d8;
				border-radius: 0px 0px 0px 0px;
				padding: 12px 15px;
			}
		  	.bwfac_forms_outer[data-field-size='small'] .bwfac_form_sec input:not(.wfop_submit_btn){
				padding: 12px 15px ;
		   	}
            .bwfac_form_sec {
                padding-right: calc(10px / 2);
                padding-left: calc(10px / 2);
                margin-bottom: 10px;
            }
            body .bwfac_form_sec .wfop_input_cont {
                margin-top: 0px;
            }
            .bwfac_form_sec #wffn_custom_optin_submit {
                background-color: #FBA506;
                padding: 15px 15px 15px 15px;
                margin: 15px 0px 25px 0px;
                border-style: solid;
                border-width: 2px 2px 2px 2px;
                border-color: #E69500;
                border-radius: 0px 0px 0px 0px;
            }
            .bwfac_form_sec #wffn_custom_optin_submit .bwf_heading,
            .bwfac_form_sec #wffn_custom_optin_submit .bwf_subheading {
                color: #ffffff ;
            }
            .bwfac_form_sec #wffn_custom_optin_submit:hover {
                background-color: #E69500;
            }
            .bwf_pp_wrap .bwf_pp_cont .bwf_pp_footer {
                font-size: 16px;
                font-weight: 700;
                line-height: 1em;
                color: #000000;
            }
            #bwf-custom-button-wrap a {
                min-width: 30%;
                background-color: #000;
                color: #ffffff;
                padding: 5px 5px 5px 5px;
                margin: 5px 5px 5px 5px;
                border-radius: 0px 0px 0px 0px;
            }
            #bwf-custom-button-wrap .bwf_subheading {
                color: #ffffff;
            }
            #bwf-custom-button-wrap a:hover {
                background-color: #000;
                color: #ffffff;
            }
            #bwf-custom-button-wrap a:hover .bwf_subheading {
                color: #ffffff;
            }
            .bwf_pp_wrap .bwf_pp_cont {
                padding: 40px 40px 40px 40px;
            }
            .bwf_pp_close {
                top: -8px;
                font-size: 25px;
                width: 25px;
                height: 25px;
                padding: 0px;
                border-radius: 15px;
                background-color: #6E6E6E;
                color: #ffffff;
            }
            body:not(.rtl) .bwf_pp_close {
                right: -14px;
            }
            body.rtl .bwf_pp_close {
                left: -14px;
            }
            .bwf_pp_close:hover {
                background-color: #D40F0F;
                color: #444444;
            }
            .pp-bar-text-wrapper.on {
                display: block;
            }
            .bwf_pp_bar_wrap.on span.pp-bar-text.inside {
                display: none;
            }
            .bwf_pp_footer {
                  text-align: center;
			}

			.bwf_pp_footer {
				    font-weight: 400;
			}
			.bwf_pp_cont .bwf_pp_opt_head,
			.bwf_pp_cont .bwf_pp_opt_sub_head {
			    text-align: center;
			    margin-bottom: 15px;
			}
			
			.pp-bar-text-wrapper.on > span {
                display: block;
            }
			@media (max-width: 767px) {
              .bwfac_form_sec #wffn_custom_optin_submit{
                  min-width: 100px;
              }
            }
            #oxygen-resize-box .rb.rb-overlay{
    			z-index: auto;
			}
			.bwfac_form_sec #wffn_custom_optin_submit{
			    width: 100%;
			}
			.wffn-custom-optin-from #bwf-custom-button-wrap span.bwf-text-wrapper {
                  display: block;
            }
		";


			return $defaultCSS;

		}


	}

	new Oxygen_WFFN_Pro_Optin_Form_Popup_Widget;
}