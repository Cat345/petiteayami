<?php
if ( ! class_exists( 'WFOCU_OXY_Reject_Button' ) ) {
	class WFOCU_OXY_Reject_Button extends WFOCU_Oxy_HTML_BLOCK {
		public $slug = 'wfocu_reject_button';
		protected $id = 'wfocu_reject_button';

		public function __construct() {
			$this->name = __( "WF Reject Button" );
			parent::__construct();
		}

		public function setup_data() {

			$this->text_settings();
			$this->color_settings();
			$this->typgraphy_settings();
			$this->style_field();
			$this->border_field();

		}

		public function text_settings() {
			$tab_id = $this->add_tab( __( 'Text', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_text( $tab_id, 'text', __( 'Title', 'woofunnels-upstroke-one-click-upsell' ), __( 'No thanks, I don’t want to take advantage of this one-time offer', 'woofunnels-upstroke-one-click-upsell' ) );


		}

		public function typgraphy_settings() {
			$tab_id = $this->add_tab( __( 'Typography', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_heading( $tab_id, __( 'Title Typography' ) );
			$default = [
				'font_size' => '25',
			];
			$this->add_text_alignments( $tab_id, $this->slug . '_alignment', '.wfocu-reject-button-wrap .wfocu-wfocu-reject', '', 'center' );
			$this->custom_typography( $tab_id, $this->slug . '_typography', '.wfocu-reject-button-wrap .wfocu-wfocu-reject', '', $default );

		}

		public function color_settings() {
			$tab_id = $this->add_tab( __( 'Colors', 'woofunnels-upstroke-one-click-upsell' ) );

			$this->add_sub_heading( $tab_id, __( "Normal", 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$this->add_color( $tab_id, $this->slug . '_btn_text_color_1', ' .wfocu-reject-button-wrap .wfocu-wfocu-reject', __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ), '#fff' );
			$this->add_background_color( $tab_id, $this->slug . '_btn__background_color', ' .wfocu-reject-button-wrap .wfocu-wfocu-reject', '#d9534f', __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ) );

			$this->add_sub_heading( $tab_id, __( "Hover", 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$this->add_color( $tab_id, $this->slug . '_btn_text_hover_color', '.wfocu-reject-button-wrap .wfocu-wfocu-reject:hover', __( 'Text Color', 'woofunnels-upstroke-one-click-upsell' ), '#fff' );
			$this->add_background_color( $tab_id, $this->slug . '_btn_background_hover_color', '.wfocu-reject-button-wrap .wfocu-wfocu-reject:hover', '#d9534f', __( 'Background Color', 'woofunnels-upstroke-one-click-upsell' ) );
		}


		private function style_field() {

			$tab_id = $this->add_tab( __( 'Spacing', 'woofunnels-upstroke-one-click-upsell' ) );

			$this->add_heading( $tab_id, __( 'Margin & Padding', 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$this->add_margin( $tab_id, $this->slug . '_margin', '.wfocu-reject-button-wrap .wfocu-wfocu-reject' );
			$this->add_padding( $tab_id, $this->slug . '_padding', '.wfocu-reject-button-wrap .wfocu-wfocu-reject' );


		}

		public function border_field() {
			$tab_id = $this->add_tab( __( 'Border', 'woofunnels-upstroke-one-click-upsell' ) );
			$this->add_heading( $tab_id, __( 'Button Border', 'woofunnels-upstroke-one-click-upsell' ), 2 );
			$this->add_border( $tab_id, $this->slug . '_border', '.wfocu-reject-button-wrap a.wfocu-wfocu-reject' );
			$this->add_box_shadow( $tab_id, $this->slug . '_box_shadow', '.wfocu-reject-button-wrap .wfocu-wfocu-reject' );
		}

		public function html( $settings, $defaults, $content ) {//phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedParameter
			$text = isset( $settings['text'] ) ? $settings['text'] : '';
			?>

            <div class="wfocu-button-wrapper wfocu-reject-button-wrap">
                <a class="wfocu-wfocu-reject wfocu_skip_offer" href="javascript:void(0);">
					<?php
					if ( ( isset( $settings['icon'] ) && '' === $settings['icon'] ) && ( isset( $settings['icon_align'] ) && 'left' === $settings['icon_align'] ) ) {
						?>
                        <span class='wfocu-button-icon et-pb-icon'></span>
						<?php
					} ?>

					<?php
					if ( ( isset( $settings['icon'] ) && '' === $settings['icon'] ) && ( isset( $settings['icon_align'] ) && 'right' === $settings['icon_align'] ) ) {
						?>
                        <span class='wfocu-button-icon et-pb-icon'></span>
						<?php
					} ?>
					<?php echo wp_kses_post( $text ) ?></a>
            </div>
			<?php
		}

		public function defaultCSS() {

			$defaultCSS = "
		     .wfocu-reject-button-wrap a.wfocu-wfocu-reject {
                font-size: 15px;
                line-height: 1.5;
                padding-top: 12px;
                padding-right: 24px;
                padding-bottom: 12px;
                padding-left: 24px;
                background-color: #d9534f;
                color: #fff;
                display: block;
                font-weight: normal;
                box-shadow: none;
                border: 1px solid #dddddd;
                border-radius: 3px;
            }
		";

			return $defaultCSS;
		}


	}

	return new WFOCU_OXY_Reject_Button;
}