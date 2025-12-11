<?php
if ( ! class_exists( 'WFOCU_Reject_Link' ) ) {
	class WFOCU_Reject_Link extends WFOCU_Divi_HTML_BLOCK {

		public function __construct() {
			parent::__construct();
		}

		public function setup_data() {
			$tab_id = $this->add_tab( __( 'Reject Offer', 'woofunnels-upstroke-one-click-upsell' ), 5 );
			$this->add_text( $tab_id, 'text', __( 'Reject Offer', 'woofunnels-upstroke-one-click-upsell' ), __( 'No thanks, I don’t want to take advantage of this one-time offer', 'woofunnels-upstroke-one-click-upsell' ) );


			$this->style_field();
		}

		public function style_field() {
			$tab_id = $this->add_tab( __( 'Typography', 'woofunnels-upstroke-one-click-upsell' ), 2 );

			$default = '||||on||||';
			$this->add_typography( $tab_id, 'typography', ' %%order_class%% .wfocu-reject', '', $default );
			$this->add_text_alignments( $tab_id, 'align', ' %%order_class%%', '', 'center' );

			$color_id         = $this->add_tab( __( 'Colors', 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$controls_tabs_id = $this->add_controls_tabs( $color_id, "Colors" );
			$colors_field     = [];
			$colors_field[]   = $this->add_color( $tab_id, 'wfocu_reject_text_color', ' %%order_class%% .wfocu-reject', __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ), '#777777' );
			$colors_field[]   = $this->add_background_color( $tab_id, 'wfocu_reject_background_color', ' %%order_class%% .wfocu-reject', '', __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_controls_tab( $controls_tabs_id, "Normal", $colors_field );
			$colors_field   = [];
			$colors_field[] = $this->add_color( $tab_id, 'hover_color', ' %%order_class%%:hover .wfocu-reject', __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ), '' );
			$colors_field[] = $this->add_background_color( $tab_id, 'wfocu_reject_background_hover_color', '%%order_class%% .wfocu-reject:hover', '', __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_controls_tab( $controls_tabs_id, "Hover", $colors_field );

			$border_id = $this->add_tab( __( 'Border', 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$default   = [
				'border_type' => 'none',
			];
			$this->add_border( $border_id, 'border', '%%order_class%% .wfocu-reject', [], $default );
			$this->add_box_shadow( $border_id, 'box_shadow', '%%order_class%% .wfocu-reject', [ 'enable' => 'off', 'vertical' => 0, 'color' => '#00B211' ] );

			$spacing_id = $this->add_tab( __( 'Spacing', 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$this->add_margin( $spacing_id, 'text_margin', '%%order_class%% .wfocu-reject' );
			$this->add_padding( $spacing_id, 'text_padding', '%%order_class%% .wfocu-reject' );

		}

		public function html( $attrs, $content = null, $render_slug = '' ) {

			ob_start();
			?>
            <div class="wfocu-button-wrapper">
                <a class="wfocu-reject wfocu_skip_offer" href="javascript:void(0);"><?php echo $this->props['text'] ?></a>
            </div>
			<?php
			return ob_get_clean();
		}


	}

	return new WFOCU_Reject_Link;
}