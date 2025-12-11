<?php
if ( ! class_exists( 'WFOCU_Reject_Button' ) ) {
	class WFOCU_Reject_Button extends WFOCU_Divi_HTML_BLOCK {

		public function __construct() {
			parent::__construct();
		}

		public function setup_data() {
			$offer_id = WFOCU_Core()->template_loader->get_offer_id();
			$key      = "wfocu_reject_button";
			$products = array();
			if ( ! empty( $offer_id ) ) {
				$products = WFOCU_Core()->template_loader->product_data->products;
			}
			$tab_id = $this->add_tab( __( 'Reject Offer', 'woofunnels-upstroke-one-click-upsell' ), 5 );

			do_action( 'wfocu_add_divi_controls', $this, $offer_id, $products );
			$this->add_text( $tab_id, 'text', __( 'Title', 'woofunnels-upstroke-one-click-upsell' ), __( 'No thanks, I don’t want to take advantage of this one-time offer', 'woofunnels-upstroke-one-click-upsell' ) );

			$this->add_text_alignments( $tab_id, 'align', ' %%order_class%%', '', 'center' );

			$this->add_icon( $tab_id, 'icon' );
			$this->add_select( $tab_id, 'icon_align', __( 'Icon Position', 'woofunnels-upstroke-one-click-upsell' ), [
				'left'  => __( 'Before', 'elementor' ),
				'right' => __( 'After', 'elementor' ),
			], 'left' );

			$this->add_margin( $tab_id, 'icon_margin', '%%order_class%% .wfocu-button-wrapper  .wfocu-button-icon', '', __( 'Icon Spacing', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->style_field();

		}

		private function style_field() {

			$key = "wfocu_reject_button";


			$tab_id = $this->add_tab( __( 'Typography', 'woofunnels-upstroke-one-click-upsell' ), 2 );

			$font_side_default = [ 'default' => '15px', 'unit' => 'px' ];
			$this->add_typography( $tab_id, $key . '_typography', '%%order_class%% .wfocu-reject-button-wrap .wfocu-wfocu-reject', '', '', [], $font_side_default );

			$color_id = $this->add_tab( __( 'Colors', 'woofunnels-upstroke-one-click-upsell' ), 2 );

			$controls_tabs_id = $this->add_controls_tabs( $color_id, "Colors" );
			$colors_field     = [];
			$colors_field[]   = $this->add_color( $color_id, $key . '_text_color', ' %%order_class%% .wfocu-reject-button-wrap .wfocu-wfocu-reject', __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ), '#ffffff' );
			$colors_field[]   = $this->add_background_color( $color_id, $key . '_background_color', ' %%order_class%% .wfocu-reject-button-wrap .wfocu-wfocu-reject', '#D9534F', __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_controls_tab( $controls_tabs_id, "Normal", $colors_field );
			$colors_field   = [];
			$colors_field[] = $this->add_color( $color_id, $key . '_text_hover_color', '%%order_class%% .wfocu-reject-button-wrap .wfocu-wfocu-reject:hover', __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ), '' );
			$colors_field[] = $this->add_background_color( $color_id, $key . '_background_hover_color', ' %%order_class%% .wfocu-reject-button-wrap .wfocu-wfocu-reject:hover', '', __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ) );
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

			$this->add_border( $border_id, $key . '_border', '%%order_class%% .wfocu-reject-button-wrap a.wfocu-wfocu-reject', [], $default_args );
			$this->add_box_shadow( $border_id, $key . '_box_shadow', '%%order_class%% .wfocu-reject-button-wrap .wfocu-wfocu-reject', [ 'enable' => 'off', 'vertical' => 0, 'color' => '#00B211' ] );

			$spacing_id = $this->add_tab( __( 'Spacing', 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$this->add_margin( $spacing_id, $key . '_margin', '%%order_class%% .wfocu-reject-button-wrap .wfocu-wfocu-reject' );

			$defaults_padding = '12px | 24px| 12px |  24px|  24px |false| 24px';
			$this->add_padding( $spacing_id, $key . '_padding', '%%order_class%% .wfocu-reject-button-wrap .wfocu-wfocu-reject', $defaults_padding );

		}

		public function html( $attrs, $content = null, $render_slug = '' ) {


			ob_start();


			?>
            <style>
                .wfocu-wfocu-reject {
                    background-color: #d9534f !important;
                    color: #fff !important;
                    display: block;
                    font-weight: bold;
                }

            </style>
            <div class="wfocu-button-wrapper wfocu-reject-button-wrap">
                <a class="wfocu-wfocu-reject wfocu_skip_offer" href="javascript:void(0);">
					<?php
					if ( 'left' == $this->props['icon_align'] && '' !== $this->props['icon'] ) {
						?>
                        <span class='wfocu-button-icon et-pb-icon'><?php echo html_entity_decode( et_pb_process_font_icon( $this->props['icon'] ) ); ?></span>
						<?php
					}
					echo $this->props['text'];

					if ( 'right' == $this->props['icon_align'] && '' !== $this->props['icon'] ) {
						?>
                        <span class='wfocu-button-icon et-pb-icon'><?php echo html_entity_decode( et_pb_process_font_icon( $this->props['icon'] ) ); ?></span>
						<?php
					} ?>


                </a>
            </div>
			<?php
			return ob_get_clean();
		}

	}

	return new WFOCU_Reject_Button;
}