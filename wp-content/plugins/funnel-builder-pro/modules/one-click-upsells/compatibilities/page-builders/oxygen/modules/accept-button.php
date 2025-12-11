<?php
if ( ! class_exists( 'WFOCU_Oxy_Accept_Button' ) ) {
	class WFOCU_Oxy_Accept_Button extends WFOCU_Oxy_HTML_BLOCK {
		public $slug = 'wfocu_accept_button';
		protected $id = 'wfocu_accept_button';

		public function __construct() {
			$this->name = __( "WF Accept Button" );
			parent::__construct();
		}

		public function setup_data() {

			// icon setting missing
			$this->text_settings();
			$this->color_settings();
			$this->typgraphy_settings();
			$this->button_settings();
			$this->spacing_settings();
		}

		public function text_settings() {

			$tab_id = $this->add_tab( __( 'Text', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_select( $tab_id, 'selected_product', __( 'Product', 'woofunnels-upstroke-one-click-upsell' ), self::$product_options, key( self::$product_options ) );

			$this->add_text( $tab_id, 'title_text', __( 'Title', 'woofunnels-upstroke-one-click-upsell' ), __( 'Yes, Add This To My Order', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_text( $tab_id, 'subtitle', __( 'Subtitle', 'woofunnels-upstroke-one-click-upsell' ) );

			/* Margin bottom setting */
			$property_css = 'margin-bottom';
			$this->slider_measure_box( $tab_id, $this->slug . '_text_margin', '.wfocu-button-wrapper .wfocu-button-text', __( 'Spacing between Title and Subtitle', 'woofunnels-upstroke-one-click-upsell' ), "2", [], $property_css );


			$this->icon_settings();


		}


		public function typgraphy_settings() {
			$tab_id = $this->add_tab( __( 'Typography', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_heading( $tab_id, __( 'Title Typography' ) );

			$default = [
				'font_size' => '21',
			];

			$this->custom_typography( $tab_id, $this->slug . 'title_typography', '.wfocu-button-wrapper .wfocu-button-content-wrapper span:not(.wfocu-button-icon):not(.wfocu-button-subtitle)', '', $default );

			$default = [
				'font_size' => '15',
			];
			$this->add_heading( $tab_id, __( 'Subtitle Typography' ) );
			$this->custom_typography( $tab_id, $this->slug . 'subtitle_typography', '.wfocu-button-wrapper .wfocu-button-subtitle', '', $default );

		}

		public function icon_settings() {
			$tab_id = $this->add_tab( __( 'Icon' ) );
			$this->add_switcher( $tab_id, 'btn_show_icon', __( "Show Icon", 'woofunnels-upstroke-one-click-upsell' ), 'on' );
			$this->add_icon( $tab_id, 'icon' );
			$this->add_select( $tab_id, 'icon_align', __( 'Icon Position', 'woofunnels-upstroke-one-click-upsell' ), [
				'left'  => __( 'Before', 'woofunnels-upstroke-one-click-upsell' ),
				'right' => __( 'After', 'woofunnels-upstroke-one-click-upsell' ),
			], 'left' );
			$this->slider_measure_box( $tab_id, $this->slug . '_icon_font_size', '.wfocu-button-wrapper .wfocu-button-content-wrapper .wfocu-button-icon svg', "", "15", [], 'width|height' );
			$this->add_margin( $tab_id, $this->slug . '_icon_margin', '.wfocu-button-wrapper  .wfocu-button-icon', __( 'Icon Spacing', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_color( $tab_id, $this->slug . '_icon_color', '.wfocu-button-wrapper .wfocu-button-content-wrapper .wfocu-button-icon svg', __( 'Icon Color', 'woofunnels-upstroke-one-click-upsell' ), '' );

		}

		public function spacing_settings() {
			$spacing_setting = $this->add_tab( __( 'Spacing' ) );
			$this->add_margin( $spacing_setting, $this->slug . '_margin', '.wfocu-button-wrapper' );
			$this->add_padding( $spacing_setting, $this->slug . '_padding', '.wfocu-button-wrapper a.wfocu_upsell' );
		}


		public function button_settings() {
			$button_id = $this->add_tab( __( 'Button', 'woofunnels-upstroke-one-click-upsell' ) );

			$this->add_border( $button_id, $this->slug . '_border', '.wfocu-button-wrapper a.wfocu_upsell' );
			$this->add_box_shadow( $button_id, $this->slug . '_box_shadow', '.wfocu-button-wrapper a.wfocu_upsell' );

		}


		public function color_settings() {

			$tab_id = $this->add_tab( __( 'Colors', 'woofunnels-upstroke-one-click-upsell' ) );

			$this->add_sub_heading( $tab_id, __( "Normal", 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$this->add_color( $tab_id, $this->slug . '_btn_text_color_1', '.wfocu-button-wrapper a.wfocu_upsell', __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ), '#fff' );
			$this->add_background_color( $tab_id, $this->slug . '_btn__background_color_1', '.wfocu-button-wrapper a.wfocu-accept-button-link ', '#70dc1d', __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ) );

			$this->add_sub_heading( $tab_id, __( "Hover", 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$this->add_color( $tab_id, $this->slug . '_btn_text_hover_color', '.wfocu-button-wrapper a.wfocu_upsell:hover', __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ), '#fff' );
			$this->add_background_color( $tab_id, $this->slug . '_btn_background_hover_color', '.wfocu-button-wrapper a.wfocu_upsell:hover', '#89E047', __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ) );
		}

		public function get_icon_html( $icon ) {
			$html = '<span class="wfocu-button-icon">';
			$html .= '<div id="fancy_icon-50-' . esc_attr( time() ) . '" class="ct-fancy-icon"><svg id="svg-fancy_icon-50-' . esc_attr( time() ) . '"><use xlink:href="#' . esc_attr( $icon ) . '"></use></svg></div>';
			$html .= $this->output_svg_icon( $icon );
			$html .= '</span>';

			return $html;
		}

		function output_svg_icon( $icon ) {

			if ( defined( "SHOW_CT_BUILDER" ) ) {
				return;
			}
			$html = '';
			if ( function_exists( 'oxy_get_svg_sets' ) ) {
				$svg_sets = oxy_get_svg_sets();
			} else {
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

		public function html( $settings, $defaults, $content ) {//phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			$sel_product = isset( $settings['selected_product'] ) ? $settings['selected_product'] : '';
			$product     = WFOCU_Common::default_selected_product( $sel_product );
			$product_key = WFOCU_Common::default_selected_product_key( $sel_product );
			$product_key = ( $product_key !== false ) ? $product_key : '';

			$product_id = ( $product !== false ) ? $product->get_id() : '';
			$text       = isset( $settings['subtitle'] ) ? $settings['subtitle'] : '';
			$icon_show  = ( isset( $settings['btn_show_icon'] ) && 'on' === $settings['btn_show_icon'] ) ? true : false;
			do_action( 'wfocu_add_custom_html_above_accept_button', $product_id, $product_key );
			?>
            <div class="wfocu-button-wrapper">

                <a class='wfocu_upsell wfocu-accept-button-link' href="javascript:void(0);" data-key="<?php echo esc_attr( $product_key ) ?>" <?php WFOCU_Core()->template_loader->add_attributes_to_buy_button(); ?> style="display:block">
                <span class="wfocu-button-content-wrapper" style='display:block'>
                    <?php
                    if ( $icon_show && isset( $settings['icon_align'] ) && 'left' === $settings['icon_align'] && '' !== $settings['icon'] ) {
	                    echo $this->get_icon_html( $settings['icon'] );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    }
                    ?>
                    <span class="wfocu-button-text"><?php echo wp_kses_post( $settings['title_text'] ); ?></span>
                      <?php
                      if ( $icon_show && isset( $settings['icon_align'] ) && 'right' === $settings['icon_align'] && '' !== $settings['icon'] ) {
	                      echo $this->get_icon_html( $settings['icon'] );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	                      ?>
                          <span class='wfocu-button-icon et-pb-icon'></span>
	                      <?php
                      }
                      ?>

                     <span style='display:block' class='wfocu-button-subtitle'><?php echo wp_kses_post( $text ) ?></span>
                </span>

                </a>
            </div>
			<?php

		}

		public function defaultCSS() {

			$defaultCSS = "
			  body .wfocu-button-wrapper .wfocu-button-content-wrapper span:not(.wfocu-button-icon):not(.wfocu-button-subtitle) {
                font-size: 21px;
                font-weight: 700;
                line-height: 1.5
            }
            body .wfocu-button-wrapper .wfocu-button-subtitle {
                font-size: 15px;
                display: block;
                line-height: 1.5;
                font-weight: normal;
            }
            body .wfocu-button-wrapper a.wfocu-accept-button-link {
                padding: 15px 40px;
                color: #fff;
                border: 1px solid #dddddd;
                border-radius: 3px;
                box-shadow: 0px 5px 0px 0px #00b211;
                font-size: 21px;
                font-weight: 700;
                line-height: 1.5;
                background-color: #70dc1d;
                text-align:center;
            }

            .wfocu-button-content-wrapper span {
                display: inline-block;
            }

            body .wfocu-button-wrapper a.wfocu-accept-button-link:hover {
                background-color: #89E047;
            }
            .wfocu_subs_plan_selector_wrap{
            	margin-bottom:20px;
            }	
		";

			return $defaultCSS;


		}


	}

	return new WFOCU_Oxy_Accept_Button;
}