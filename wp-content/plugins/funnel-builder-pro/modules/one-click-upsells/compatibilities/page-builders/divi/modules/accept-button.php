<?php
if ( ! class_exists( 'WFOCU_Accept_Button' ) ) {
	class WFOCU_Accept_Button extends WFOCU_Divi_HTML_BLOCK {

		public function __construct() {
			parent::__construct();
		}

		public function setup_data() {

			$offer_id = WFOCU_Core()->template_loader->get_offer_id();


			$tab_id = $this->add_tab( __( 'Accept Offer', 'woofunnels-upstroke-one-click-upsell' ), 5 );

			$this->add_select( $tab_id, 'selected_product', __( 'Product', 'woofunnels-upstroke-one-click-upsell' ), self::$product_options, key( self::$product_options ) );
			do_action( 'wfocu_add_divi_controls', $this, $offer_id, self::$product_options );
			$this->add_text( $tab_id, 'text', __( 'Title', 'woofunnels-upstroke-one-click-upsell' ), __( 'Yes, Add This To My Order', 'woofunnels-upstroke-one-click-upsell' ) );


			$this->add_text( $tab_id, 'subtitle', __( 'Subtitle', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_margin( $tab_id, 'text_margin', '%%order_class%% .wfocu-button-wrapper .wfocu-button-text', '', __( 'Spacing between Title and Subtitle', 'woofunnels-upstroke-one-click-upsell' ) );
			$alignments = [
				'%%order_class%% .wfocu-button-wrapper ',

			];
			$this->add_text_alignments( $tab_id, 'align', implode( ', ', $alignments ), '', 'center' );

			// icon setting missing


			$this->add_icon( $tab_id, 'icon' );

			$icon_conditions = [ 'icon' => '23' ];

			$this->add_select( $tab_id, 'icon_align', __( 'Icon Position', 'woofunnels-upstroke-one-click-upsell' ), [
				'left'  => __( 'Before', 'elementor' ),
				'right' => __( 'After', 'elementor' ),
			], 'left' );

			//$icon_indent = $this->add_margin( $tab_id, 'icon_margin', '%%order_class%% .wfocu-button-wrapper  .wfocu-button-icon', '', __( 'Icon Spacing', 'woofunnels-upstroke-one-click-upsell' ) );

			$this->style_field();
		}

		public function style_field() {

			$key = "wfocu_accept_button";


			$tab_id = $this->add_tab( __( 'Typography', 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$this->add_subheading( $tab_id, 'Title' );

			$default           = '|700|||||||';
			$font_side_default = [ 'default' => '21px', 'unit' => 'px' ];
			$this->add_typography( $tab_id, $key . 'title_typography', '%%order_class%% .wfocu-button-wrapper .wfocu-button-content-wrapper span:not(.wfocu-button-icon)', '', $default, [], $font_side_default );

			$font_side_default = [ 'default' => '15px', 'unit' => 'px' ];
			$this->add_subheading( $tab_id, 'Subtitle' );
			$this->add_typography( $tab_id, $key . 'subtitle_typography', '%%order_class%% .wfocu-button-wrapper .wfocu-button-subtitle', __( 'Subtitle Typography', 'woofunnels-upstroke-one-click-upsell' ), '', [], $font_side_default );


			$color_id         = $this->add_tab( __( 'Colors', 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$controls_tabs_id = $this->add_controls_tabs( $color_id, "Colors" );
			$colors_field     = [];

			$colors_field[] = $this->add_color( $color_id, $key . '_button_text_color', '%%order_class%% .wfocu-button-wrapper .wfocu-button-content-wrapper', __( 'Title Text Color', 'woofunnels-upstroke-one-click-upsell' ), '' );
			$colors_field[] = $this->add_color( $color_id, $key . '_button_subtitle_color', '%%order_class%% .wfocu-button-wrapper .wfocu-button-subtitle', __( 'Subtitle Text Color', 'woofunnels-upstroke-one-click-upsell' ), '' );
			$colors_field[] = $this->add_color( $color_id, $key . '_button_icon_color', '%%order_class%% .wfocu-button-wrapper .wfocu-button-content-wrapper .wfocu-button-icon', __( 'Icon Color', 'woofunnels-upstroke-one-click-upsell' ), '' );
			$colors_field[] = $this->add_background_color( $color_id, $key . '_background_color', '%%order_class%% .wfocu-button-wrapper a.wfocu_upsell', '', __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ) );

			$this->add_controls_tab( $controls_tabs_id, "Normal", $colors_field );

			$colors_field   = [];
			$colors_field[] = $this->add_color( $color_id, $key . '_accept_button_hover_color', '%%order_class%%:hover .wfocu-button-content-wrapper', __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ), '' );
			$colors_field[] = $this->add_color( $color_id, $key . '_hover_subtitle_color', '%%order_class%%:hover .wfocu-button-subtitle', __( 'Subtitle Text Color', 'woofunnels-upstroke-one-click-upsell' ), '' );
			$colors_field[] = $this->add_color( $color_id, $key . '_button_hover_icon_color', '%%order_class%%:hover .wfocu-button-content-wrapper .wfocu-button-icon.et-pb-icon', __( 'Icon Color', 'woofunnels-upstroke-one-click-upsell' ), '' );
			$colors_field[] = $this->add_background_color( $color_id, $key . '_button_background_hover_color', '%%order_class%% .wfocu-button-wrapper a.wfocu_upsell:hover', '#89E047', __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ) );

			$this->add_controls_tab( $controls_tabs_id, "Hover", $colors_field );

			$border_id = $this->add_tab( __( 'Border', 'woofunnels-upstroke-one-click-upsell' ), 2 );

			$default_args = [
				'border_type'          => 'solid',
				'border_width_top'     => '0',
				'border_width_bottom'  => '0',
				'border_width_left'    => '0',
				'border_width_right'   => '0',
				'border_radius_top'    => '3',
				'border_radius_bottom' => '3',
				'border_radius_left'   => '3',
				'border_radius_right'  => '3',
				'border_color'         => '#dddddd',
			];


			$this->add_border( $border_id, $key . '_border', '%%order_class%% .wfocu-button-wrapper a.wfocu_upsell', [], $default_args );

			$default_args = [
				'enable'     => 'on',
				'type'       => '',
				'horizontal' => '0',
				'vertical'   => '5',
				'blur'       => '0',
				'spread'     => '0',
				'color'      => '#00b211',
			];
			$this->add_box_shadow( $border_id, $key . '_box_shadow', '%%order_class%% .wfocu-button-wrapper a.wfocu_upsell', $default_args );

			$spacing_id = $this->add_tab( __( 'Spacing', 'woofunnels-upstroke-one-click-upsell' ), 2 );

			$this->add_margin( $spacing_id, $key . '_margin', '%%order_class%%' );


			$defaults_padding = '15px | 40px| 15px |  40px|  5px |false| 5px';
			$this->add_padding( $spacing_id, $key . '_padding', '%%order_class%% .wfocu-button-wrapper a.wfocu_upsell', $defaults_padding );


		}

		public function html( $attrs, $content = null, $render_slug = '' ) {

			$sel_product  = isset( $this->props['selected_product'] ) ? $this->props['selected_product'] : '';
			$product_key  = WFOCU_Core()->template_loader->default_product_key( $sel_product );
			$product_data = WFOCU_Core()->template_loader->product_data->products;
			$product_id   = '';
			if ( isset( $product_data->{$product_key} ) ) {
				$product = $product_data->{$product_key}->data;
				if ( $product instanceof WC_Product ) {
					$product_id = $product->get_id();
				}
			}
			ob_start();
			do_action( 'wfocu_add_custom_html_above_accept_button', $product_id, $product_key );
			?>
            <style>
                #wfocu_accept_button .et-pb-icon {
                    font-family: ETmodules !important;
                }

                .wfocu-button-content-wrapper span {
                    display: inline !important;;
                }


            </style>
            <div class="wfocu-button-wrapper">
                <a id="wfocu-accept-button-link" class='wfocu_upsell wfocu-accept-button-link wfocu_paypal_in_context_btn' href="javascript:void(0);" data-key="<?php echo $product_key ?>" <?php WFOCU_Core()->template_loader->add_attributes_to_buy_button(); ?>>
                <span class="wfocu-button-content-wrapper" style='display:block'>
                    <?php

                    if ( 'left' == $this->props['icon_align'] && '' !== $this->props['icon'] ) {
	                    ?>
                        <span class='wfocu-button-icon et-pb-icon'><?php echo html_entity_decode( et_pb_process_font_icon( $this->props['icon'] ) ); ?></span>
	                    <?php
                    }
                    ?>
                    <span class="wfocu-button-text"><?php echo do_shortcode( html_entity_decode( $this->props['text'] ) ); ?></span>

                      <?php
                      if ( 'right' == $this->props['icon_align'] && '' !== $this->props['icon'] ) {
	                      ?>
                          <span class='wfocu-button-icon et-pb-icon'><?php echo html_entity_decode( et_pb_process_font_icon( $this->props['icon'] ) ); ?></span>
	                      <?php
                      }
                      ?>
                </span>


                    <span style='display:block' class='wfocu-button-subtitle'><?php echo do_shortcode( html_entity_decode( $this->props['subtitle'] ) ); ?></span>
                </a>
            </div>
			<?php

			return ob_get_clean();
		}


	}

	return new WFOCU_Accept_Button;
}